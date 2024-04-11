<?php
/**
 * This file add command to migrate multisite posts.
 *
 * @package WordPress
 */

/**
 * Class for Post migration from one multisite to another multisite.
 *
 * Command `migrate`
 *
 * @author Krupal Panchal
 */
class Post_Migration extends WP_CLI_Base {

	public const COMMAND_NAME = 'migrate';

	/**
	 * Post type
	 *
	 * @var string
	 */
	public const POST_TYPE = 'post';

	/**
	 * Command for Post migration
	 *
	 * [--from-site=<from-site>]
	 * : Site URL from where we need to migrate
	 *
	 * [--to-site=<to-site>]
	 * : Site ID to where we need to migrate
	 *
	 * ## OPTIONS
	 *
	 * [--number-posts=<number-posts>]
	 * : Number of post to migrate.
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
	 * # Migrate the posts.
	 * $ wp migrate posts --from-site=multisitedemo.local --to-site=2 --number-posts=10 --dry-run=true
	 * Success: Post Migrated!!!
	 *
	 * @subcommand posts
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args Associate arguments.
	 *
	 * @return void
	 */
	public function posts(array $args, array $assoc_args ): void {

		// Parse the global arguments.
		$this->_parse_global_arguments( $assoc_args );

		$this->_notify_on_start();

		// Show error if the given param is not there.
		if ( empty( $assoc_args['from-site'] ) ) {
			WP_CLI::error( 'Missing argument: <from-site> is required.' );
		}

		if ( empty( $assoc_args['to-site'] ) ) {
			WP_CLI::error( 'Missing argument: <to-site> is required.' );
		}

		$number_posts = -1;
		if ( ! empty( $assoc_args['number-posts'] ) ) {
			$number_posts = (int) $assoc_args['number-posts'];
		}

		$from_site   = $assoc_args['from-site'];
		$to_site     = (int) $assoc_args['to-site'];
		$posts_count = 0;

		// Get the post count from the source site.
		$total_posts = 0;
		$api_url     = sprintf(
			'http://%s/wp-json/wp/v2/posts/',
			$from_site
		);

		$response = wp_remote_get( $api_url );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( 'Error: ' . esc_html( $response->get_error_message() ) );
		} else {
			$headers     = wp_remote_retrieve_headers( $response );
			$total_posts = (int) $headers['x-wp-total'];
		}

		$sr_count   = 1;
		$page_count = 1;

		do {
			$post_api_url = sprintf(
				'http://%s/wp-json/wp/v2/posts/?page=%d',
				$from_site,
				$page_count
			);

			$response = wp_remote_get( $post_api_url );

			if ( is_wp_error( $response ) ) {
				echo 'Error: ' . esc_html( $response->get_error_message() );
			} else {
				$body = wp_remote_retrieve_body( $response );
				$data = json_decode( $body, true );

				if ( isset( $data['code'] ) && 404 === $data['code'] ) {
					WP_CLI::error( 'Site ID not found on the source site.' );
				}

				$fetched_posts = count( $data );

				foreach ( $data as $post ) {

					if ( ! $this->is_dry_run() ) {

						// Migrate the post.
						$new_post_id = $this->migrate_post( $post, $to_site );

						if ( $new_post_id > 0 ) {
							WP_CLI::log(
								sprintf(
									'%d) Post Migrated: %s => New ID: %d',
									$sr_count,
									$post['title']['rendered'],
									$new_post_id
								)
							);
						}
					} else {
						WP_CLI::log(
							sprintf(
								'%d) Post Title: %s ',
								$sr_count,
								$post['title']['rendered']
							)
						);
					}

					if ( $sr_count === $number_posts ) {
						break;
					}

					$sr_count++;
					$this->_update_iteration();
				}
				$page_count++;
			}
		} while ( $fetched_posts === $this->batch_size );

		// Log if 0 post found to migrate.
		if ( 0 === $total_posts ) {
			WP_CLI::Log( 'There is no post to migrate.' );
		}

		if ( $number_posts > $total_posts ) {
			$number_posts = $total_posts;
		}

		// Final success message.
		if ( ! $this->is_dry_run() ) {
			$this->notify_on_done(
				sprintf(
					'Total %d out of %d posts migrated.',
					$number_posts,
					$total_posts
				)
			);
		} else {
			$this->notify_on_done(
				sprintf(
					'Dry run ended - Total %d out of %d posts will be migrated.',
					$number_posts,
					$total_posts
				)
			);
		}
	}

	/**
	 * Migrate the post from one site to another site.
	 *
	 * @param array $post    Post object.
	 * @param int   $site_id Site ID to where we need to migrate.
	 *
	 * @return int
	 */
	protected function migrate_post( array $post, int $site_id ): int {

		switch_to_blog( $site_id );

		// Prepare post array to insert/update.
		$post_data = array(
			'post_type'    => static::POST_TYPE,
			'post_title'   => $post['title']['rendered'],
			'post_content' => $post['content']['rendered'],
			'post_status'  => $post['status'],
			'post_date'    => $post['date'],
		);

		// Get image URLs from the content.
		$image_urls = $this->get_image_urls_from_content( $post_data['post_content'] );

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
		$post_id = $this->get_post_if_exist( $site_id, $post['slug'] );

		// If post found then update else insert.
		if ( $post_id > 0 ) {

			$post_data['ID'] = $post_id;

			$new_post_id = wp_update_post( $post_data, true );

		} else {
			$new_post_id = wp_insert_post( $post_data, true );
		}

		// If new post inserted/updated then do the following.
		if ( $new_post_id > 0 ) {

			// Category migration.
			if (
				isset( $post['md_extended']['categories'] )
				&& count( $post['md_extended']['categories'] ) > 0
			) {
				// Assign categories.
				$this->maybe_add_terms( $new_post_id, $post['md_extended']['categories'], 'category' );
			}

			// Tag migration.
			if (
				isset( $post['md_extended']['tags'] )
				&& count( $post['md_extended']['tags'] ) > 0
			) {
				// Assign tags.
				$this->maybe_add_terms( $new_post_id, $post['md_extended']['tags'], 'post_tag' );
			}

			// Post Meta Migration.
			$all_post_meta = $post['md_extended']['post_meta'];
			if (
				$new_post_id > 0
				&& count( $all_post_meta ) > 0
			) {

				// Update the post meta.
				foreach ( $all_post_meta as $meta_key => $meta_value ) {
					update_post_meta( $new_post_id, $meta_key, $meta_value );
				}
			}

			// Featured Image Migration.
			$featured_image = $post['md_extended']['featured_image'];

			if (
				$new_post_id > 0
				&& isset( $featured_image )
			) {
				// Upload the image to the site's media library.
				$attachment_id = $this->upload_image_to_media_library( $featured_image );

				// Set the featured image.
				if ( $attachment_id > 0 ) {
					set_post_thumbnail( $new_post_id, $attachment_id );
				}
			}
		}

		restore_current_blog();

		return $new_post_id;
	}

	/**
	 * Method to check if post is exist in the sub site.
	 *
	 * @param int    $site_id Sub site ID.
	 * @param string $slug    Post Slug.
	 *
	 * @return int|false Returns Post ID if post exist else FALSE.
	 */
	public function get_post_if_exist( int $site_id, string $slug ): int|false {

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

		// Things working differently for category and post_tag.
		if ( 'category' === $taxonomy ) {
			$terms = $new_term;
		} elseif ( 'post_tag' === $taxonomy ) {
			$terms = $this->get_term_slug( $new_term, $taxonomy );
		}

		// Set terms in a post.
		wp_set_post_terms(
			$post_id,
			$terms,
			$taxonomy,
		);
	}

	/**
	 * Method to get term slug from term ID.
	 *
	 * @param int|array $term_id Term ID.
	 * @param string    $taxonomy Taxonomy.
	 *
	 * @return string|array
	 */
	public function get_term_slug( int|array $term_id, string $taxonomy ): string|array {

		// If $term_id is integer.
		if ( is_int( $term_id ) ) {
			$term = get_term_by(
				'term_id',
				$term_id,
				$taxonomy
			);

			return $term ? $term->slug : '';
		}

		// If $term_id is array.
		if ( is_array( $term_id ) ) {
			$slugs = array();
			foreach ( $term_id as $id ) {
				$term = get_term_by(
					'term_id',
					$id,
					$taxonomy
				);
				if ( $term ) {
					$slugs[] = $term->slug;
				}
			}
			return $slugs;
		}

		// Return an empty string if $term_id is neither an interger nor an array.
		return '';
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

		$image_content = file_get_contents( $image_url );

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
			'meta_query'       => array(
				array(
					'key'     => '_wp_attached_file',
					'value'   => $file_name,
					'compare' => 'LIKE',
				),
			),
			'suppress_filters' => false,
		);

		$attachments = get_posts( $args );

		return ! empty( $attachments ) ? $attachments[0]->ID : false;
	}
} // end class.

// EOF.
