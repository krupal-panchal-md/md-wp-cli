<?php
/**
 * Class for Anitian resources migration.
 *
 * Command `migrate`
 *
 * @author Krupal Panchal <krupal.panchal@multidots.com>
 *
 * @package md-wp-cli
 */

/**
 * Class Anitian_Resources_Migrate
 */
class Anitian_Resources_Migrate extends WP_CLI_Base {

	public const COMMAND_NAME = 'anitian-resources';

	/**
	 * Resource Post type.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'resources';

	/**
	 * Resource Taxonomy.
	 *
	 * @var string
	 */
	public const TAXONOMY = 'resource_categories';

	/**
	 * Default post type.
	 *
	 * @var string
	 */
	public $post_type = '';

	/**
	 * Command for Anitian Resources migration.
	 *
	 * This command is created for page https://www.anitian.com/resources/.
	 * And used to find and get content based on the HTML div classes.
	 * If you want to use this command for other site then you need to change the HTML classes.
	 *
	 * [--site-url=<site-url>]
	 * : External Site URL from where we need to migrate.
	 *
	 * [--post-type=<post-type>]
	 * : Post type in which we need to migrate the resources.
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
	 * # Migrate the resources.
	 * $ wp anitian-resources migrate --site_url=https://www.anitian.com/resources/ --post-type=resources --dry-run=true
	 * Success: Resources Migrated successfully!!!
	 *
	 * @subcommand migrate
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args Associate arguments.
	 *
	 * @return void
	 */
	public function migrate( array $args, array $assoc_args ): void {

		// Parse the global arguments.
		$this->parse_global_arguments( $assoc_args );

		// Check if site URL is provided.
		$site_url = untrailingslashit( $assoc_args['site-url'] );
		if (
			empty( $site_url )
			|| ! filter_var( $site_url, FILTER_VALIDATE_URL ) ) {
			WP_CLI::error( 'You need to provide a valid site URL.' );
		}
		// Check if post type is provided.
		$this->post_type = $assoc_args['post-type'] ?? static::POST_TYPE;

		// Check if post type exists.
		if ( ! post_type_exists( $this->post_type ) ) {
			WP_CLI::error( 'The provided post type does not exist.' );
		}

		$this->notify_on_start();

		$sr_num        = 1;
		$cat_page_urls = $this->get_category_page_urls( $site_url );
		$page_count    = count( $cat_page_urls );

		foreach ( $cat_page_urls as $cat_name => $cat_url ) {

			WP_CLI::log( '---------------------------------' );
			WP_CLI::log(
				sprintf(
					'Migrating %s Posts...',
					$cat_name
				)
			);
			WP_CLI::log( '---------------------------------' );

			$same_layout_item = array(
				'documents'         => 'Documents',
				'case-study'        => 'Case Studies',
				'on-demand-webinar' => 'On-Demand Webinars',
			);

			// Documents, Case Studies and On-Demand Webinars Posts.
			if ( in_array( $cat_name, array_values( $same_layout_item ), true ) ) {

				$item_name = array_flip( $same_layout_item );
				$item_name = $item_name[ $cat_name ];

				$doc_case_webinar_posts = $this->get_doc_case_webinar_posts( $item_name );

				foreach ( $doc_case_webinar_posts as $post_data ) {

					$post_data['categories'] = array( $cat_name );

					if ( ! $this->is_dry_run() ) {
						$this->insert_or_update_post( $post_data );

						WP_CLI::log(
							sprintf(
								'%d) %s',
								$sr_num,
								$post_data['title']
							)
						);
					} else {
						WP_CLI::log(
							sprintf(
								'%d) %s',
								$sr_num,
								$post_data['title']
							)
						);
					}

					++$sr_num;
				}
			}

			// Press & News Posts.
			if ( 'Press & News' === $cat_name ) {
				$press_news_posts = $this->get_all_press_news_posts( 'press-release' );

				foreach ( $press_news_posts as $post_data ) {

					$post_data['categories'] = array( $cat_name );

					if ( ! $this->is_dry_run() ) {
						$this->insert_or_update_post( $post_data );

						WP_CLI::log(
							sprintf(
								'%d) %s',
								$sr_num,
								$post_data['title']
							)
						);
					} else {
						WP_CLI::log(
							sprintf(
								'%d) %s',
								$sr_num,
								$post_data['title']
							)
						);
					}

					++$sr_num;
				}
			}

			// Awards.
			if ( 'Awards' === $cat_name ) {
				$awards_posts = $this->get_all_awards_posts( $cat_url );

				foreach ( $awards_posts as $post_data ) {

					$post_data['categories'] = array( $cat_name );

					if ( ! $this->is_dry_run() ) {
						$this->insert_or_update_post( $post_data );

						WP_CLI::log(
							sprintf(
								'%d) %s',
								$sr_num,
								$post_data['title']
							)
						);
					} else {
						WP_CLI::log(
							sprintf(
								'%d) %s',
								$sr_num,
								$post_data['title']
							)
						);
					}

					++$sr_num;
				}
			}
		}
	}

	/**
	 * Method to Get the category page URLs.
	 *
	 * @param string $site_url Site URL.
	 *
	 * @return array
	 */
	public function get_category_page_urls( string $site_url ): array {

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

			// Query the DOM for the ul element with the id "menu-resources-menu".
			$elements = $xpath->query( '//ul[@id="menu-resources-menu"]' );

			// phpcs:disable

			$cat_pagepage_urls = array();
			if ( ! is_null( $elements ) ) {
				foreach ( $elements as $element ) {
					foreach ( $element->childNodes as $childNode ) {
						if ( $childNode->nodeName === 'li' ) {
							foreach ( $childNode->childNodes as $grandChildNode ) {
								if ( $grandChildNode->nodeName === 'a' ) {
									$href                   = $grandChildNode->getAttribute( 'href' );
									$text                   = $grandChildNode->nodeValue;
									$cat_page_urls[ $text ] = $href;
								}
							}
						}
					}
				}
			}

			// phpcs:enable

			$exclude_item = array(
				'Featured'       => '',
				'More Resources' => '',
			);

			return array_diff_key( $cat_page_urls, $exclude_item );
		}
	}

	/**
	 * Method to get the Documents, Case Studies and On-Demand Webinars Posts.
	 * This method will not work for other types of posts.
	 *
	 * @param string $category Category.
	 *
	 * @return array
	 */
	public function get_doc_case_webinar_posts( string $category ): array {

		$page      = 1;
		$post_data = array();
		do {
			$posts = $this->if_doc_case_webinar_news_posts( $page, $category, 'resources_listing_filter', 'res-list-filter__item' );
			if ( is_array( $posts ) && count( $posts ) > 0 ) {
				$post_data = array_merge( $post_data, $posts );
			} else {
				break;
			}
			++$page;
		} while ( false !== $posts );

		return $post_data;
	}

	/**
	 * Method to get the Documents, Case Studies and On-Demand Webinars Posts.
	 *
	 * @param string $category Category.
	 *
	 * @return array
	 */
	public function get_all_press_news_posts( string $category ): array {

		$page      = 1;
		$post_data = array();
		do {
			$posts = $this->if_doc_case_webinar_news_posts( $page, $category, 'posts_listing_filter_v2', 'post-list-filter__item' );
			if ( is_array( $posts ) && count( $posts ) > 0 ) {
				$post_data = array_merge( $post_data, $posts );
			} else {
				break;
			}
			++$page;
		} while ( false !== $posts );

		return $post_data;
	}

	/**
	 * Method to get Awards Posts.
	 *
	 * @param string $post_url Post URL.
	 *
	 * @return array
	 */
	public function get_all_awards_posts( string $post_url ): array {

		$response = wp_remote_get( $post_url );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$html = wp_remote_retrieve_body( $response );

		$awards_arr = array();

		$awards      = $this->get_awards_by_cat( $html );
		$more_awards = $this->get_awards_more_posts( 1, 'ant_award_listing_filter_callback' );

		return array_merge( $awards, $more_awards );
	}

	/**
	 * Method to get Awards Posts.
	 *
	 * @param string $html HTML.
	 *
	 * @return array
	 */
	public function get_awards_by_cat( string $html ): array {

		$dom = new DOMDocument();
		$dom->loadHTML( $html );

		$xpath = new DOMXPath( $dom );

		$elements = $xpath->query( "//*[contains(@class, 'awards-listing__awards-list-item')]" );

		$awards = array();
		foreach ( $elements as $element ) {
			$award_dom = new DOMDocument();
			$award_dom->loadHTML( $dom->saveHTML( $element ) );

			$award_xpath = new DOMXPath( $award_dom );

			$year       = $award_xpath->query( "//*[contains(@class, 'awards-listing__year')]" )->item( 0 )->nodeValue;
			$awards_el  = $award_xpath->query( "//*[contains(@class, 'awards-listing__post')]" );
			$awards_div = $award_dom->saveHTML( $awards_el->item( 0 ) );

			$award_posts = $this->prepare_post_array_for_awards( $awards_div, 'awards-listing__post', array( $year ) );
		}

		return $award_posts;
	}

	/**
	 * Method to prepare post array from HTML.
	 *
	 * @param string $html       HTML.
	 * @param string $class_name Div class Name.
	 * @param array  $terms      Year.
	 *
	 * @return array
	 */
	public function prepare_post_array_for_awards( string $html, string $class_name, array $terms ): array {

		// Create a new DOMDocument instance.
		$dom = new DOMDocument();

		// Suppress errors due to malformed HTML.
		libxml_use_internal_errors( true );

		// Load the HTML into the DOMDocument.
		$dom->loadHTML( $html );

		// Create a new DOMXPath instance.
		$xpath = new DOMXPath( $dom );

		// Query the DOM for the div elements with the class name.
		$div      = '//div[@class="' . $class_name . '"]';
		$elements = $xpath->query( $div );

		$items = array();
		if ( ! is_null( $elements ) ) {
			foreach ( $elements as $element ) {
				$title = $xpath->query( './/div[contains(@class, "awards-listing__post-title")]', $element )->item( 0 );
				$img   = $xpath->query( './/img', $element )->item( 0 );

				$items[] = array(
					'title'   => trim( $title->nodeValue ), // phpcs:ignore
					'img'     => $img->getAttribute( 'src' ),
					'content' => '',
					'date'    => '',
					'terms'   => $terms,
				);
			}
		}

		return $items;
	}

	/**
	 * Method to get if the Documents, Case Studie, Press & News and On-Demand Webinars Posts found.
	 *
	 * @param int    $page       Page Number.
	 * @param string $action     AJAX Action.
	 *
	 * @return bool|array
	 */
	public function get_awards_more_posts( int $page, string $action ): bool|array {

		$url  = 'https://www.anitian.com/wp-admin/admin-ajax.php';
		$args = array(
			'method' => 'GET',
			'body'   => array(
				'action' => $action,
				'page'   => $page,
			),
		);

		$response = wp_remote_request( $url, $args );

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $response_code ) {
			return false;
		}

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			WP_CLI::error( "Something went wrong: $error_message" );
		} else {
			$html = wp_remote_retrieve_body( $response );
		}

		return $this->get_awards_by_cat( $html );
	}

	/**
	 * Method to get if the Documents, Case Studie, Press & News and On-Demand Webinars Posts found.
	 *
	 * @param int    $page       Page Number.
	 * @param string $category   Category.
	 * @param string $action     AJAX Action.
	 * @param string $class_name Div class Name.
	 *
	 * @return bool|array
	 */
	public function if_doc_case_webinar_news_posts( int $page, string $category, string $action, string $class_name ): bool|array {

		$url  = 'https://www.anitian.com/wp-admin/admin-ajax.php';
		$args = array(
			'method' => 'GET',
			'body'   => array(
				'action'              => $action,
				'pageNumber'          => $page,
				'selected_categories' => $category,
				'show_title'          => 'true',
				'show_pub_date'       => 'false',
				'show_desc'           => 'true',
				'show_tag'            => 'true',
				'post_per_page'       => 10,
				'data_append'         => 'false',
			),
		);

		$response = wp_remote_request( $url, $args );

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $response_code ) {
			return false;
		}

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			WP_CLI::error( "Something went wrong: $error_message" );
		} else {
			$body = wp_remote_retrieve_body( $response );

			$json_response = json_decode( $body, true );
			$html          = $json_response['ajax_response'];
		}

		return $this->prepare_post_array_from_html( $html, $class_name, $category );
	}

	/**
	 * Method to prepare post array from HTML.
	 *
	 * @param string $html       HTML.
	 * @param string $class_name Div class Name.
	 * @param string $category   Category.
	 *
	 * @return array
	 */
	public function prepare_post_array_from_html( string $html, string $class_name, string $category ) {

		// Create a new DOMDocument instance.
		$dom = new DOMDocument();

		// Suppress errors due to malformed HTML.
		libxml_use_internal_errors( true );

		// Load the HTML into the DOMDocument.
		$dom->loadHTML( $html );

		// Create a new DOMXPath instance.
		$xpath = new DOMXPath( $dom );

		// Query the DOM for the div elements with the class name.
		$div      = '//div[@class="' . $class_name . '"]';
		$elements = $xpath->query( $div );

		$items = array();
		if ( ! is_null( $elements ) ) {
			foreach ( $elements as $element ) {
				$a   = $xpath->query( './/a', $element )->item( 0 );
				$h3  = $xpath->query( './/h3', $element )->item( 0 );
				$img = $xpath->query( './/img', $element )->item( 0 );

				if ( $a && $h3 ) {

					if ( 'press-release' === $category ) {
						$news_data = $this->get_press_news_post_data( $a->getAttribute( 'href' ) );
						$items[]   = $news_data;
					} else {
						$items[] = array(
							'url'     => $a->getAttribute( 'href' ),
							'title'   => trim( $h3->nodeValue ), // phpcs:ignore
							'img'     => $img->getAttribute( 'src' ),
							'content' => '',
							'date'    => '',
						);
					}
				}
			}
		}

		return $items;
	}

	/**
	 * Method to get Press & News Posts.
	 *
	 * @param string $url URL.
	 *
	 * @return array
	 */
	public function get_press_news_posts( string $url ): array {

		$response = wp_remote_get( $url );

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

			$featured_image_div  = $xpath->query( '//div[contains(@class, "anitian-post-article")]//div[contains(@class, "post-thumbnail")]' );
			$featured_image_html = $dom->saveHTML( $featured_image_div->item( 0 ) );

			$items[] = array(
				'url'     => $a->getAttribute( 'href' ),
				'title'   => trim( $title->nodeValue ), // phpcs:ignore
				'img'     => $featured_image_html,
				'content' => '',
				'date'    => trim( $date->nodeValue ), // phpcs:ignore
			);

			return $items;
		}
	}

	/**
	 * Method to migrate single post.
	 *
	 * @param string $post_url URL.
	 *
	 * @return array
	 */
	public function get_press_news_post_data( string $post_url ): array {

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
			$featured_image_div = $xpath->query( '//article[contains(@class, "anitian-post-article")]//img[contains(@class, "attachment-post-thumbnail")]' );
			$featured_image_url = '';
			if ( $featured_image_div->length > 0 ) {
				$img                = $featured_image_div->item( 0 );
				$featured_image_url = $img->getAttribute( 'src' );
			}

			// Get Post Title.
			$post_title_div = $xpath->query( '//header[contains(@class, "entry-header")]//h1[contains(@class, "entry-title")]' );
			$post_title     = '';

			if ( $post_title_div->length > 0 ) {
				$post_title = $post_title_div->item( 0 )->nodeValue;
			}

			// Get the date.
			$published_date_span = $xpath->query( '//time[contains(@class, "entry-date")]' );
			$post_date           = '';

			if ( $published_date_span->length > 0 ) {
				$post_date = $published_date_span->item( 0 )->nodeValue;
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
			$content_div  = $xpath->query( '//div[contains(@class, "post-entry-content")]' );
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
				'title'   => $post_title,
				'content' => wp_kses_post( $post_content ),
				'date'    => $post_date,
				'img'     => $featured_image_url,
			);

			return $post_data;
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
		$featured_img = $post_arr['img'];
		$categories   = $post_arr['categories'];

		// Prepare post array to insert/update.
		$post_data = array(
			'post_type'    => $this->post_type,
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_date'    => $post_arr['date'],
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
				$this->maybe_add_terms( $new_post_id, $terms, static::TAXONOMY );
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

		$parsed_url = wp_parse_url( $image_url );

		$image_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path'];

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
					'compare' => 'LIKE',
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
				'post_type'              => $this->post_type,
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

		$terms = $this->get_term_slug( $new_term, $taxonomy );

		// Set terms in a post.
		wp_set_post_terms(
			$post_id,
			$terms,
			$taxonomy,
		);
	}

	/**
	 * Method to convert the content to Gutenberg blocks.
	 *
	 * @param string $content Post Content.
	 *
	 * @return string
	 */
	public function convert_content_to_blocks( string $content ): string {

		// Return if no content found.
		if ( empty( $content ) ) {
			return '';
		}

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
} // End Class.
