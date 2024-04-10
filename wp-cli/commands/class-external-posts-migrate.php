<?php
/**
 * Class for External post migration.
 *
 * Command `migrate`
 *
 * @author Krupal Panchal <krupal.panchal@multidots.com>
 *
 * @package md-wp-cli
 */

/**
 * Class External_Posts_Migrate
 */
class External_Posts_Migrate extends WP_CLI_Base {

	public const COMMAND_NAME = 'migrate';

	/**
	 * Default post type.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'post';

	/**
	 * Command for external site's post migration.
	 *
	 * This command is created for site https://www.pointcentral.com/blog/.
	 * And used to find and get content based on the HTML div classes.
	 * If you want to use this command for other site then you need to change the HTML classes.
	 *
	 * [--site_url=<site_url>]
	 * : External Site URL from where we need to migrate.
	 *
	 *  [--page-from=<page-from>]
	 * : Page number from where we need to start migration.
	 *
	 * [--page-to=<page-to>]
	 * : Page number to where we need to end migration.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Whether to do a dry run or not.
	 * ---
	 * default: true
	 * options:
	 *  - false
	 *  - true
	 *
	 * ## EXAMPLES
	 *
	 * # Migrate the external posts.
	 * $ wp migrate external-posts --site_url=https://example.com --page-from=1 --page-to=10 --dry-run=true
	 * Success: Posts Migrated successfully!!!
	 *
	 * @subcommand external-posts
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args Associate arguments.
	 *
	 * @return void
	 */
	public function external_posts( array $args, array $assoc_args ): void {

		// Parse the global arguments.
		$this->parse_global_arguments( $assoc_args );

		// Check if page the data is provided.
		$page_from = 1;
		$page_to   = (int) $assoc_args['page-to'];
		if ( ! empty( $assoc_args['page-from'] ) ) {
			$page_from = (int) $assoc_args['page-from'];
		}

		if ( empty( $assoc_args['page-to'] ) ) {
			WP_CLI::error( 'You need to provide a --page-to value.' );
		}

		// Check if site URL is provided.
		$site_url = untrailingslashit( $assoc_args['site_url'] );
		if (
			empty( $site_url )
			|| ! filter_var( $site_url, FILTER_VALIDATE_URL ) ) {
			WP_CLI::error( 'You need to provide a valid site URL.' );
		}

		$this->notify_on_start();

		$sr_num = 1;

		for ( $page = $page_from; $page <= $page_to; $page++ ) {

			$new_site_url = $site_url;

			// Change the site URL if page is greater than 1.
			if ( $page > 1 ) {
				$new_site_url = $site_url . '/page/' . $page;
			}

			$post_urls = $this->get_post_urls( $new_site_url );

			foreach ( $post_urls as $post_url => $post_title ) {

				if ( ! $this->is_dry_run() ) {
					$this->migrate_individual_post( $post_url );
					WP_CLI::log(
						sprintf(
							'%d) %s - Post migrated successfully.',
							$sr_num,
							$post_title
						)
					);
				} else {
					WP_CLI::log(
						sprintf(
							'%d) %s - Post will be migrated.',
							$sr_num,
							$post_title
						)
					);
				}
				++$sr_num;
			}

			// Add log and sleep for 2 seconds.
			WP_CLI::log( '' );
			WP_CLI::log(
				sprintf(
					'Exported posts from page %s.',
					$page,
				)
			);
			WP_CLI::log( 'Sleep for 2 seconds...' );
			WP_CLI::log( '' );
		}

		// Final success message.
		if ( ! $this->is_dry_run() ) {
			$this->notify_on_done(
				sprintf(
					'Total %d posts migrated.',
					$sr_num - 1
				)
			);
		} else {
			$this->notify_on_done(
				sprintf(
					'Dry run ended - Total %d posts will be migrated.',
					$sr_num - 1
				)
			);
		}
	}

	/**
	 * Method to get post URLs.
	 *
	 * @param string $site_url Site URL.
	 *
	 * @return array
	 */
	public function get_post_urls( string $site_url ): array {

		$response = wp_remote_get( $site_url );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( 'Failed to fetch the blog posts.' );
		} else {
			$body = wp_remote_retrieve_body( $response );

			// Create a new DOMDocument instance.
			$dom = new DOMDocument();

			// Suppress errors due to malformed HTML.
			libxml_use_internal_errors( true );

			// Load the HTML into the DOMDocument.
			$dom->loadHTML( $body );

			// Create a new DOMXPath instance.
			$xpath = new DOMXPath( $dom );

			// Query the DOM for the anchor within the div with class "archive-article-title".
			$anchors = $xpath->query( '//div[contains(@class, "archive-article-title")]/a' );

			$post_urls = array();
			foreach ( $anchors as $anchor ) {
				$post_urls[ $anchor->getAttribute( 'href' ) ] = $anchor->nodeValue; // phpcs:ignore
			}

			return $post_urls;
		}
	}

	/**
	 * Migrate individual post.
	 *
	 * @param string $post_url Post URL.
	 *
	 * @return void
	 */
	public function migrate_individual_post( string $post_url ): void {

		// Get the post content.
		$response = wp_remote_get( $post_url );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( 'Failed to fetch the blog posts.' );
		} else {
			$body = wp_remote_retrieve_body( $response );

			// Create a new DOMDocument instance.
			$dom = new DOMDocument();

			// Suppress errors due to malformed HTML.
			libxml_use_internal_errors( true );

			// Load the HTML into the DOMDocument.
			$dom->loadHTML( $body );

			// Create a new DOMXPath instance.
			$xpath = new DOMXPath( $dom );

			// Get the featured image.
			$featured_image_div  = $xpath->query( '//div[contains(@class, "page-header-bg-image-wrap")]//div[contains(@class, "page-header-bg-image")]' );
			$featured_image_html = $dom->saveHTML( $featured_image_div->item( 0 ) );

			// Match the URL from the HTML.
			preg_match( '/url\((.*?)\)/', $featured_image_html, $matches );

			$featured_image_url = $matches[1] ?? '';

			// Get Post Title.
			$post_title_div = $xpath->query( '//div[contains(@class, "featured-media-under-header__content")]//h1[contains(@class, "entry-title")]' );
			$post_title     = '';

			if ( $post_title_div->length > 0 ) {
				$post_title = $post_title_div->item( 0 )->nodeValue;
			}

			// Get the date.
			$published_date_span = $xpath->query( '//span[contains(@class, "meta-date date published")]' );
			$updated_date_span   = $xpath->query( '//span[contains(@class, "meta-date date updated")]' );
			$post_date           = '';

			if (
				$published_date_span->length > 0
				|| $updated_date_span->length > 0
			) {
				$published_date = ! empty( $published_date_span->item( 0 ) ) ? $published_date_span->item( 0 )->nodeValue : '';

				if ( ! empty( $published_date ) ) {
					$post_date = $published_date;
				} else {
					$post_date = $updated_date_span->item( 0 )->nodeValue;
				}
			}

			// Get the categories.
			$category_anchors = $xpath->query( '//span[contains(@class, "meta-category")]/a' );
			$categories       = array();

			if ( $category_anchors->length > 0 ) {
				foreach ( $category_anchors as $anchor ) {
					$categories[] = $anchor->nodeValue; // phpcs:ignore
				}
			}

			// Get the content.
			$content_div  = $xpath->query( '//div[contains(@class, "wpb_text_column wpb_content_element")]//div[contains(@class, "wpb_wrapper")]' );
			$post_content = '';

			if ( $content_div->length > 0 ) {
				$div = $content_div->item( 0 );
				foreach ( $div->childNodes as $child ) { // phpcs:ignore
					$post_content .= $dom->saveHTML( $child );
				}
			}

			// Convert the date to the correct format.
			$date_object = date_create( $post_date );
			$post_date   = date_format( $date_object, 'Y-m-d H:i:s' );

			$post_data = array(
				'title'        => $post_title,
				'content'      => wp_kses_post( $post_content ),
				'date'         => $post_date,
				'categories'   => $categories,
				'featured_img' => $featured_image_url,
			);

			// Insert or update the post.
			$this->insert_or_update_post( $post_data );
		}
	}

	/**
	 * Method to insert or update post.
	 *
	 * @param array $post_arr Post Data.
	 *
	 * @return void
	 */
	public function insert_or_update_post( array $post_arr ): void {

		$title        = sanitize_text_field( $post_arr['title'] );
		$content      = $this->convert_content_to_blocks( $post_arr['content'] );
		$featured_img = $post_arr['featured_img'];
		$categories   = $post_arr['categories'];

		// Prepare post array to insert/update.
		$post_data = array(
			'post_type'    => static::POST_TYPE,
			'post_title'   => $title,
			'post_content' => $content,
			'post_date'    => $post_arr['date'],
			'post_status'  => 'publish',
		);

		// Get image URLs from the content.
		$image_urls = $this->get_image_urls_from_content( $content );

		// Check if atleat one img URL found.
		if ( count( $image_urls ) > 0 ) {

			foreach ( $image_urls as $image_url ) {

				// Upload the image to the subsite's media library.
				$attachment_id = $this->upload_image_to_media_library( $image_url );

				// Update the post content to use the new image URL.
				if ( $attachment_id > 0 ) {
					$new_image_url = wp_get_attachment_url( $attachment_id );
					$post_content  = str_replace( $image_url, $new_image_url, $post_data['post_content'] );
				}
			}

			$post_data['post_content'] = $post_content;
		}

		// Check if the current post exist in the sub site.
		$post_id = $this->get_post_if_exist( sanitize_title( $title ) );

		// If post found then update else insert.
		if ( $post_id > 0 ) {

			$post_data['ID'] = $post_id;

			$new_post_id = wp_update_post( $post_data, true );

		} else {
			$new_post_id = wp_insert_post( $post_data, true );
		}

		// If new post inserted/updated then do the following.
		if ( $new_post_id > 0 ) {

			if ( ! empty( $featured_img ) ) {

				$attachment_id = $this->upload_image_to_media_library( $featured_img );

				// Set the featured image.
				if ( $attachment_id > 0 ) {
					set_post_thumbnail( $new_post_id, $attachment_id );
				}
			}

			if ( ! empty( $categories ) ) {
				$terms = array();
				if ( count( $categories ) > 0 ) {
					foreach ( $categories as $category ) {
						$term_slug           = sanitize_title( $category );
						$terms[ $term_slug ] = $category;
					}
				}

				// Assign categories.
				$this->maybe_add_terms( $new_post_id, $terms, 'category' );
			}
		}
	}

	/**
	 * Method to get the image URLs from the post content.
	 *
	 * @param string $post_content Post Content.
	 *
	 * @return array
	 */
	public function get_image_urls_from_content( string $post_content ): array {

		$image_urls = array();

		// Match all img tags in the post content.
		preg_match_all( '/<img [^>]*src=["\']([^"\']+)["\'][^>]*>/i', $post_content, $matches );

		if ( ! empty( $matches[1] ) ) {
			$image_urls = $matches[1];
		}

		return $image_urls;
	}

	/**
	 * Method to upload images to site media.
	 *
	 * @param string $image_url Image URL.
	 *
	 * @return int|false
	 *
	 * @throws ErrorException Error message.
	 */
	public function upload_image_to_media_library( string $image_url ): int|false {

		// Check if attachment is alraeady exist in the site.
		$existing_attachment_id = $this->get_attachment_id_by_name( basename( $image_url ) );

		// If existing attachment found then return the ID.
		if ( $existing_attachment_id > 0 ) {
			return $existing_attachment_id;
		}

		$image_content = file_get_contents( $image_url ); // phpcs:ignore

		if ( false === $image_content ) {

			throw new ErrorException(
				sprintf(
					/* translators: %s: Image URL */
					esc_html__(
						'Unable to download the image: %s',
						'twentytwentyone'
					),
					esc_url( $image_url )
				)
			);

		}

		$file_name = wp_unique_filename( wp_upload_dir()['path'], basename( $image_url ) );

		$upload = wp_upload_bits( $file_name, null, $image_content );

		if ( $upload['error'] ) {

			throw new ErrorException(
				sprintf(
					/* translators: %s: Image URL */
					esc_html__(
						'Unable to upload the image: %s',
						'twentytwentyone'
					),
					esc_url( $image_url )
				)
			);
		}

		$attachment = array(
			'post_title'     => sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) ),
			'post_mime_type' => wp_check_filetype( $upload['file'] )['type'],
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert the attachment into the subsite's media library.
		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {

			throw new ErrorException(
				sprintf(
					/* translators: %s: Image URL */
					esc_html__(
						'Something went wrong on inserting the image: %s',
						'creative'
					),
					esc_url( $image_url )
				)
			);

		}

		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		return $attachment_id;
	}

	/**
	 * Method to convert the content to Gutenberg blocks.
	 *
	 * @param string $content Post Content.
	 *
	 * @return string
	 */
	public function convert_content_to_blocks( string $content ): string {

		// Load the content into a DOMDocument.
		$dom = new DOMDocument();
		$dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );

		// Initialize the Gutenberg content.
		$gutenberg_content = '';

		// Loop through each child node of the body.
		foreach ( $dom->getElementsByTagName( 'body' )->item( 0 )->childNodes as $node ) {

			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			// Check the type of the node and add the corresponding Gutenberg block.
			if ( 'p' === $node->nodeName ) {
				$gutenberg_content .= "<!-- wp:paragraph -->\n<p>" . $node->nodeValue . "</p>\n<!-- /wp:paragraph -->";
			} elseif (
				'h1' === $node->nodeName ||
				'h2' === $node->nodeName ||
				'h3' === $node->nodeName ||
				'h4' === $node->nodeName ||
				'h5' === $node->nodeName ||
				'h6' === $node->nodeName
			) {
				$level              = substr( $node->nodeName, 1 ); // Get the heading level from the node name.
				$gutenberg_content .= '<!-- wp:heading {"level":' . $level . "} -->\n<" . $node->nodeName . '>' . $node->nodeValue . '</' . $node->nodeName . ">\n<!-- /wp:heading -->";
			} elseif (
				'ul' === $node->nodeName ||
				'ol' === $node->nodeName
			) {
				$gutenberg_content .= "<!-- wp:list -->\n<" . $node->nodeName . ">\n";
				foreach ( $node->childNodes as $list_item ) {
					if ( 'li' === $list_item->nodeName ) {
						$gutenberg_content .= '<li>' . $list_item->nodeValue . "</li>\n";
					}
				}
				$gutenberg_content .= '</' . $node->nodeName . ">\n<!-- /wp:list -->";
			} elseif ( 'img' === $node->nodeName ) {
				$src                = $node->getAttribute( 'src' );
				$alt                = $node->getAttribute( 'alt' );
				$gutenberg_content .= "<!-- wp:image -->\n<figure><img src=\"" . $src . '" alt="' . $alt . "\"></figure>\n<!-- /wp:image -->";
			}

			// phpcs:enable
		}

		return $gutenberg_content;
	}

	/**
	 * Method to get the attachment ID by image name.
	 *
	 * @param string $file_name Attachment file name.
	 *
	 * @return int|false
	 */
	public function get_attachment_id_by_name( string $file_name ): int|false {

		$args = array(
			'post_type'        => 'attachment',
			'posts_per_page'   => 1,
			'meta_query'       => array( // phpcs:ignore
				array(
					'key'     => '_wp_attached_file',
					'value'   => $file_name,
					'compare' => '=',
				),
			),
			'suppress_filters' => false,
		);

		$attachments = get_posts( $args );

		return ! empty( $attachments ) ? $attachments[0]->ID : false;
	}

	/**
	 * Method to check if post is exist in the sub site.
	 *
	 * @param string $slug    Post Slug.
	 *
	 * @return int|false Returns Post ID if post exist else FALSE.
	 */
	public function get_post_if_exist( string $slug ): int|false {

		$query = new WP_Query(
			array(
				'post_type'              => static::POST_TYPE,
				'name'                   => $slug,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return $query->have_posts() ? $query->posts[0] : false;
	}

	/**
	 * Method to add terms if they do not exist already.
	 *
	 * @param int    $post_id  Post ID to assign terms.
	 * @param array  $terms    Array of term.
	 * @param string $taxonomy Taxonomy to assign terms.
	 *
	 * @return void
	 */
	public function maybe_add_terms( int $post_id, array $terms, string $taxonomy ): void {

		$new_term = array();

		foreach ( $terms as $term_slug => $term_name ) {

			$term_data = term_exists( (string) $term_slug, $taxonomy, 0 );

			// If the term does not exist then insert it.
			if ( empty( $term_data ) ) {

				// The term doesn't exist, lets create it.
				$term_added = wp_insert_term(
					$term_name,
					$taxonomy
				);

				if ( is_array( $term_added ) && isset( $term_added['term_id'] ) ) {
					$new_term[] = $term_added['term_id'];
				}
			} elseif ( is_array( $term_data ) && isset( $term_data['term_id'] ) ) {
				$new_term[] = $term_data['term_id'];
			}
		}

		// Set terms in a post.
		wp_set_post_terms(
			$post_id,
			$new_term,
			$taxonomy,
		);
	}
} // End Class.
