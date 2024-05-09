<?php
/**
 * Class for WooCommerce products migration in a multisite.
 *
 * Command `woo-products`
 *
 * @author Krupal Panchal <krupal.panchal@multidots.com>
 *
 * @package md-wp-cli
 */

/**
 * Class Woo_Products_Migrate
 */
class Woo_Products_Migrate extends WP_CLI_Base {

	/**
	 * Command Name.
	 *
	 * @var string
	 */
	public const COMMAND_NAME = 'woo-products';

	/**
	 * From site ID.
	 *
	 * @var int
	 */
	public $from_site = 0;

	/**
	 * To site ID.
	 *
	 * @var int
	 */
	public $to_site = 0;

	/**
	 * Post type
	 *
	 * @var string
	 */
	public const POST_TYPE = 'product';

	/**
	 * Command for Product migration.
	 *
	 * [--from-site=<from-site>]
	 * : Site ID from where we need to migrate.
	 *
	 * [--to-site=<to-site>]
	 * : Site ID to where we need to migrate.
	 *
	 * [--categories=<categories>]
	 * : Product categories. Comma separated. Use category slug.
	 * Example: --categories=cat1,cat2,cat3
	 *
	 * ## OPTIONS
	 *
	 * [--number-products=<number-products>]
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
	 * # Migrate the Products.
	 * $ wp woo-products migrate --from-site=1 --to-site=2 --number-products=10 --categories=cat1,cat2,cat3 --url=shop.multisitedemo.local --dry-run=true
	 * Success: Products Migrated!!!
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

		$this->notify_on_start();

		// Show error if the given param is not there.
		if ( empty( $assoc_args['from-site'] ) ) {
			WP_CLI::error( 'Missing argument: <from-site> is required.' );
		}

		if ( empty( $assoc_args['to-site'] ) ) {
			WP_CLI::error( 'Missing argument: <to-site> is required.' );
		}

		// Get the number of products.
		$number_products = $assoc_args['number-products'];
		if ( ! empty( $assoc_args['number-products'] ) ) {
			$number_products = (int) $assoc_args['number-products'];
		} else {
			$number_products = -1;  // Default all products.
		}

		// Get the categories.
		$categories = $assoc_args['categories'];
		if ( ! empty( $categories ) ) {
			$categories = explode( ',', $categories );
		} else {
			$categories = array();
		}

		// Get the param values.
		$this->from_site = (int) $assoc_args['from-site'];
		$this->to_site   = (int) $assoc_args['to-site'];

		$sr_count = 1;

		// Switch to the 'from_site' site.
		switch_to_blog( $this->from_site );

		// Get all products.
		$products = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => -1,
				'category' => $categories,
			)
		);

		// Switch back to the original site.
		restore_current_blog();

		// Switch to the 'to' site.
		switch_to_blog( $this->to_site );

		$sr_count = 1;

		// Loop through the products and insert them into the 'to' site.
		foreach ( $products as $product ) {

			$product_data = $product->get_data();
			$slug         = $product_data['slug'];

			$product_id = $this->get_product_if_exist( $slug );

			// If product found then update it else insert.
			if ( $product_id > 0 ) {

				if ( ! $this->is_dry_run() ) {

					// Update Product.
					$this->add_or_update_product( $product_id, $product );

					WP_CLI::log(
						sprintf(
							'%d) Product ID %d Updated.',
							$sr_count,
							$product_id,
						)
					);
				} else {
					WP_CLI::log(
						sprintf(
							'%d) Product ID %d will be Updated.',
							$sr_count,
							$product_id,
						)
					);
				}
			} else { // phpcs:ignore

				if ( ! $this->is_dry_run() ) {

					// Add Product.
					$new_product_id = $this->add_or_update_product( 0, $product );

					WP_CLI::log(
						sprintf(
							'%d) Product ID %d Inserted. New ID: %d',
							$sr_count,
							$product->get_id(),
							$new_product_id
						)
					);
				} else {
					WP_CLI::log(
						sprintf(
							'%d) Product ID %d will be Inserted.',
							$sr_count,
							$product->get_id(),
						)
					);
				}
			}

			// Sleep for 2 seconds after every 10 products.
			if ( 0 === $sr_count % 10 ) {
				WP_CLI::line( '' );
				WP_CLI::line( 'Sleep for 2 seconds...' );
				WP_CLI::line( '' );
				sleep( 2 );
			}

			// Break the loop if the number of products is reached.
			if ( $number_products > 0 && $sr_count >= $number_products ) {
				break;
			}

			++$sr_count;
		}

		// Switch back to the original site.
		restore_current_blog();

		WP_CLI::success( 'Products migrated.' );
	}

	/**
	 * Method to get products count.
	 *
	 * @param int $site_id  Site ID.
	 *
	 * @return int
	 */
	public function get_products_count( int $site_id ): int {

		switch_to_blog( $site_id );

		$args = array(
			'post_type'              => static::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$query = new WP_Query( $args );
		$count = $query->found_posts;

		wp_reset_postdata();

		restore_current_blog();

		return $count;
	}

	/**
	 * Method to check if product is exist in the sub site.
	 *
	 * @param string $slug Product Slug.
	 * @param string $post_type Post Type.
	 *
	 * @return int|false Returns product ID if product exist else FALSE.
	 */
	public function get_product_if_exist( string $slug, string $post_type = '' ): int|false {

		$post_type = ! empty( $post_type ) ? $post_type : static::POST_TYPE;

		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
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
	 * Method to add product.
	 *
	 * @param int    $product_id Product ID.
	 * @param Object $product    Product Object.
	 *
	 * @return int
	 */
	public function add_or_update_product( int $product_id, object $product ): int {

		// Get product type. Acccording to product type, call the respective method.
		$product_type = $product->get_type();

		switch ( $product_type ) {
			case 'simple':
				return $this->add_or_update_simple_product( $product_id, $product );
				break; // phpcs:ignore

			case 'variable':
				return $this->add_or_update_variable_product( $product_id, $product );
				break; // phpcs:ignore

			case 'external':
				return $this->add_or_update_external_product( $product_id, $product );
				break; // phpcs:ignore

			case 'grouped':
				return $this->add_or_update_grouped_product( $product_id, $product );
				break; // phpcs:ignore

			default:
				break;
		}
	}

	/**
	 * Method to add simple product.
	 *
	 * @param int    $product_id  Product ID.
	 * @param Object $product_obj Product Object.
	 *
	 * @return int
	 */
	public function add_or_update_simple_product( int $product_id, object $product_obj ): int {

		if ( 0 === $product_id ) {
			$product = new WC_Product_Simple();
		} else {
			$product = new WC_Product_Simple( $product_id );
		}

		// Add default product data.
		$this->add_default_product_data( $product_obj, $product );

		// Set product images and terms.
		$this->maybe_add_product_images( $product, $product_obj );
		$this->maybe_add_terms( $product, $product_obj );

		return $product->save();
	}

	/**
	 * Method to add/update variable product.
	 *
	 * @param int    $product_id  Product ID.
	 * @param Object $product_obj Product Object.
	 *
	 * @return int
	 */
	public function add_or_update_variable_product( int $product_id, object $product_obj ): int {

		if ( 0 === $product_id ) {
			$product = new WC_Product_Variable();
		} else {
			$product = new WC_Product_Variable( $product_id );
		}

		// Add default product data.
		$this->add_default_product_data( $product_obj, $product );

		// Set product images and terms.
		$this->maybe_add_product_images( $product, $product_obj );
		$this->maybe_add_terms( $product, $product_obj );
		$this->manage_variation( $product, $product_obj );

		return $product->save();
	}

	/**
	 * Method to manage variations. That Includes the attributes as well.
	 *
	 * @param Object $product     New product Object.
	 * @param Object $product_obj Old product Object.
	 *
	 * @return void
	 */
	public function manage_variation( object $product, object $product_obj ): void {

		$variations_arr = array();

		// Switch to the 'from_site' site.
		switch_to_blog( $this->from_site );

		// Get the product's attributes.
		$attributes = $product_obj->get_attributes();

		// Get the product's variations.
		$variations = $product_obj->get_children();

		foreach ( $variations as $variation ) {
			$variations_arr[] = wc_get_product( $variation );
		}

		// Switch back to the original site.
		restore_current_blog();

		$this->maybe_add_attributes( $product, $attributes );
		$this->maybe_add_variations( $product, $variations_arr );

		$product->save();
	}

	/**
	 * Method to add attributes.
	 *
	 * @param Object $product        New product Object.
	 * @param array  $attributes_arr Attributes Array.
	 *
	 * @return void
	 */
	public function maybe_add_attributes( object $product, array $attributes_arr ): void {

		$temp_array = array();

		foreach ( $attributes_arr as $attr_data ) {

			$attribute = $attr_data->get_data();

			$taxonomy    = wc_attribute_taxonomy_name( $attribute['name'] );
			$taxonomy_id = wc_attribute_taxonomy_id_by_name( $taxonomy );

			if ( 0 === $taxonomy_id ) {
				wc_create_attribute(
					array(
						'name'         => $attribute['name'],
						'type'         => 'select',
						'order_by'     => 'menu_order',
						'has_archives' => false,
					)
				);
			} else {
				wc_update_attribute(
					$taxonomy_id,
					array(
						'name'         => $attribute['name'],
						'type'         => 'select',
						'order_by'     => 'menu_order',
						'has_archives' => false,
					)
				);
			}

			$new_attribute = new WC_Product_Attribute();
			$new_attribute->set_id( $attribute['id'] );
			$new_attribute->set_name( $attribute['name'] );
			$new_attribute->set_options( $attribute['options'] );
			$new_attribute->set_position( $attribute['position'] );
			$new_attribute->set_visible( $attribute['is_visible'] );
			$new_attribute->set_variation( $attribute['is_variation'] );
			$temp_array[] = $new_attribute;
		}

		$product->set_attributes( $temp_array );

		$product->save();
	}

	/**
	 * Method to add variations.
	 *
	 * @param Object $product       New product Object.
	 * @param array  $variations_arr Variations Array.
	 *
	 * @return void
	 */
	public function maybe_add_variations( object $product, array $variations_arr ): void {

		// Loop through each variation.
		foreach ( $variations_arr as $variation ) {

			// Get the variation data.
			$variation_data = $variation->get_data();

			// Check if the variation exists.
			$variation_id = $this->get_product_if_exist( $variation_data['slug'], 'product_variation' );

			if ( is_int( $variation_id ) && $variation_id > 0 ) {
				$new_variation = new WC_Product_Variation( $variation_id );
			} else {
				$new_variation = new WC_Product_Variation();
			}

			// Set the parent product.
			$new_variation->set_parent_id( $product->get_id() );

			// Set the variation data.
			$new_variation->set_id( $variation_id );
			$new_variation->set_regular_price( $variation_data['regular_price'] );
			$new_variation->set_slug( $variation_data['slug'] );
			$new_variation->set_sale_price( $variation_data['sale_price'] );
			$new_variation->set_stock_status( $variation_data['stock_status'] );
			$new_variation->set_manage_stock( $variation_data['manage_stock'] );
			$new_variation->set_stock_quantity( $variation_data['stock_quantity'] );
			$new_variation->set_weight( $variation_data['weight'] );
			$new_variation->set_length( $variation_data['length'] );
			$new_variation->set_width( $variation_data['width'] );
			$new_variation->set_height( $variation_data['height'] );
			$new_variation->set_tax_status( $variation_data['tax_status'] );
			$new_variation->set_sku( $variation_data['sku'] );
			$new_variation->set_status( $variation_data['status'] );
			$new_variation->set_downloadable( $variation_data['downloadable'] );

			// // Set the variation's attributes.
			$new_variation->set_attributes( $variation_data['attributes'] );

			// Save the variation.
			$new_variation->save();

		}

		$product->save();
	}

	/**
	 * Method to add/update external product.
	 *
	 * @param int    $product_id  Product ID.
	 * @param Object $product_obj Product Object.
	 *
	 * @return int
	 */
	public function add_or_update_external_product( int $product_id, object $product_obj ): int {

		if ( 0 === $product_id ) {
			$product = new WC_Product_External();
		} else {
			$product = new WC_Product_External( $product_id );
		}

		// Add default product data.
		$this->add_default_product_data( $product_obj, $product );

		// Set external product data.
		$product->set_button_text( $product_obj->get_button_text() );
		$product->set_product_url( $product_obj->get_product_url() );

		// Set product images and terms.
		$this->maybe_add_product_images( $product, $product_obj );
		$this->maybe_add_terms( $product, $product_obj );

		return $product->save();
	}

	/**
	 * Method to add/update grouped product.
	 *
	 * @param int    $product_id  Product ID.
	 * @param Object $product_obj Product Object.
	 *
	 * @return int
	 */
	public function add_or_update_grouped_product( int $product_id, object $product_obj ): int {

		if ( 0 === $product_id ) {
			$product = new WC_Product_Grouped();
		} else {
			$product = new WC_Product_Grouped( $product_id );
		}

		// Add default product data.
		$this->add_default_product_data( $product_obj, $product );

		// Set grouped product data.
		$child_products = $product_obj->get_children();

		$new_product_child_ids = $this->get_child_products( $child_products );

		$product->set_children( $new_product_child_ids );

		// Set product images and terms.
		$this->maybe_add_product_images( $product, $product_obj );
		$this->maybe_add_terms( $product, $product_obj );

		return $product->save();
	}

	/**
	 * Method to get product child. This method can be used to retrieve the child products for group.
	 * Also we can use this for upsell, cross-sell, related products.
	 *
	 * @param array $child_products Child Products.
	 *
	 * @return array
	 */
	public function get_child_products( array $child_products ): array {

		$child_product_ids = array();
		$product_slugs     = array();

		switch_to_blog( $this->from_site );

		foreach ( $child_products as $child_product ) {
			$product_slugs[] = get_post_field( 'post_name', $child_product );
		}

		restore_current_blog();

		foreach ( $product_slugs as $product_slug ) {
			$id = $this->get_product_if_exist( $product_slug );
			if ( is_int( $id ) && $id > 0 ) {
				$child_product_ids[] = $id;
			}
		}

		return $child_product_ids;
	}

	/**
	 * Method to add default product data.
	 *
	 * @param object $product_obj Product Object.
	 * @param object $product     Product Object.
	 *
	 * @return object
	 */
	public function add_default_product_data( object $product_obj, object $product ): object {

		$product->set_name( $product_obj->get_name() );
		$product->set_slug( $product_obj->get_slug() );
		$product->set_status( $product_obj->get_status() );
		$product->set_description( $product_obj->get_description() );
		$product->set_short_description( $product_obj->get_short_description() );
		$product->set_sku( $product_obj->get_sku() );
		$product->set_price( $product_obj->get_price() );
		$product->set_regular_price( $product_obj->get_regular_price() );
		$product->set_sale_price( $product_obj->get_sale_price() );
		$product->set_date_on_sale_from( $product_obj->get_date_on_sale_from() );
		$product->set_date_on_sale_to( $product_obj->get_date_on_sale_to() );
		$product->set_stock_status( $product_obj->get_stock_status() );
		$product->set_manage_stock( $product_obj->get_manage_stock() );
		$product->set_stock_quantity( $product_obj->get_stock_quantity() );
		$product->set_backorders( $product_obj->get_backorders() );
		$product->set_sold_individually( $product_obj->get_sold_individually() );
		$product->set_weight( $product_obj->get_weight() );
		$product->set_length( $product_obj->get_length() );
		$product->set_width( $product_obj->get_width() );
		$product->set_height( $product_obj->get_height() );
		$product->set_tax_status( $product_obj->get_tax_status() );
		$product->set_tax_class( $product_obj->get_tax_class() );
		$product->set_reviews_allowed( $product_obj->get_reviews_allowed() );
		$product->set_purchase_note( $product_obj->get_purchase_note() );
		$product->set_menu_order( $product_obj->get_menu_order() );

		// Set upsell products.
		$new_upsell_product_ids = $this->get_child_products( $product_obj->get_upsell_ids() );
		$product->set_upsell_ids( $new_upsell_product_ids );

		// Set cross-sell products.
		$new_cross_sell_product_ids = $this->get_child_products( $product_obj->get_cross_sell_ids() );
		$product->set_cross_sell_ids( $new_cross_sell_product_ids );

		$product->save();

		return $product;
	}

	/**
	 * Method to add product images.
	 *
	 * @param Object $product     Product Object.
	 * @param Object $product_obj Product Object.
	 *
	 * @return void
	 */
	public function maybe_add_product_images( object $product, object $product_obj ): void {

		// Switch to the 'from_site' site.
		switch_to_blog( $this->from_site );

		// Get the featured image URL.
		$featured_image_id  = $product_obj->get_image_id();
		$featured_image_url = wp_get_attachment_url( $featured_image_id );

		// Get the product gallery image URLs.
		$gallery_image_ids  = $product_obj->get_gallery_image_ids();
		$gallery_image_urls = array_map( 'wp_get_attachment_url', $gallery_image_ids );

		// Switch back to the original site.
		restore_current_blog();

		// Process the featured image upload.
		$attachment_id = $this->upload_image_to_media_library( $featured_image_url );

		// Process the gallery images upload.
		$gallery_attachment_id = array();

		foreach ( $gallery_image_urls as $gallery_image_url ) {
			$gallery_attachment_id[] = $this->upload_image_to_media_library( $gallery_image_url );
		}

		$product->set_image_id( $attachment_id );
		$product->set_gallery_image_ids( $gallery_attachment_id );
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
						'md-wp-cli'
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
	 * Method to add terms if they do not exist already.
	 *
	 * @param Object $product     Product Object.
	 * @param Object $product_obj Product Object.
	 *
	 * @return void
	 */
	public function maybe_add_terms( object $product, object $product_obj ): void {

		// Switch to the 'from_site' site.
		switch_to_blog( $this->from_site );

		// Get the product's category IDs.
		$category_ids = $product_obj->get_category_ids();

		// Get the category names.
		$categories = array();
		foreach ( $category_ids as $category_id ) {
			$term = get_term( $category_id, 'product_cat' );
			if ( ! is_wp_error( $term ) ) {
				$categories[ $term->slug ] = $term->name;
			}
		}

		// Get the product's tag IDs.
		$tag_ids = $product_obj->get_tag_ids();

		// Get the tag names.
		$tags = array();
		foreach ( $tag_ids as $tag_id ) {
			$term = get_term( $tag_id, 'product_tag' );
			if ( ! is_wp_error( $term ) ) {
				$tags[ $term->slug ] = $term->name;
			}
		}

		// Switch back to the original site.
		restore_current_blog();

		// Process the categories.
		$product->set_category_ids( $this->insert_or_update_terms( $categories, 'product_cat' ) );

		// Process the tags.
		$product->set_tag_ids( $this->insert_or_update_terms( $tags, 'product_tag' ) );
	}

	/**
	 * Method to insert or update terms.
	 *
	 * @param array  $terms    Terms Array.
	 * @param string $taxonomy Taxonomy.
	 *
	 * @return array
	 */
	public function insert_or_update_terms( array $terms, string $taxonomy ): array {

		$term_ids = array();

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
					$term_ids[] = $term_added['term_id'];
				}
			} elseif ( is_array( $term_data ) && isset( $term_data['term_id'] ) ) {
				$term_ids[] = $term_data['term_id'];
			}
		}

		return $term_ids;
	}
} // End class.
