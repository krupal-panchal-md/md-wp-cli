<?php
/**
 * Class for Yoas Data Migration.
 *
 * Command `yoast-posts`
 *
 * @author Krupal Panchal <krupal.panchal@multidots.com>
 *
 * @package wp-cli
 */

/**
 * Class Yoast_Posts_Import
 */
class Yoast_Posts_Import extends WP_CLI_Base {

	public const COMMAND_NAME = 'yoast-posts';

	/**
	 * Default post type.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'page';

	/**
	 * Log type.
	 *
	 * @var array
	 */
	public const LOG_TYPE = array( 'text', 'table' );

	/**
	 * Table item.
	 *
	 * @var array
	 */
	public $table_item = array();

	/**
	 * Command for import Yoast data.
	 *
	 * [--post-type=<post-type>]
	 * : Post Type to import data.
	 *
	 * [--file=<file>]
	 * : CSV file path.
	 *
	 *  [--format-type=<format-type>]
	 * : CSV log format type.
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
	 * # Complete the test.
	 * $ wp yoast-posts import --post-type=page --file=wp-content/uploads/yoast-data.csv --dry-run=true --format-type=text
	 * Success: Posts Imported successfully!!!
	 *
	 * @subcommand import
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args Associate arguments.
	 *
	 * @return void
	 */
	public function import( array $args, array $assoc_args ): void {

		// Parse the global arguments.
		$this->parse_global_arguments( $assoc_args );

		$this->notify_on_start();

		// Get Post Type.
		$post_type = static::POST_TYPE;
		if ( ! empty( $assoc_args['post-type'] ) ) {
			$post_type = $assoc_args['post-type'];
		}

		// Get Log type.
		$log_type = 'text';
		if (
			! empty( $assoc_args['format-type'] )
			&& in_array( $assoc_args['format-type'], static::LOG_TYPE, true )
		) {
			$log_type = $assoc_args['format-type'];
		} else {
			WP_CLI::error( 'Invalid/Blank log format type.' );
		}

		// Check if file path is provided.
		if ( ! isset( $assoc_args['file'] ) ) {
			WP_CLI::error( 'You need to provide a CSV file path.' );
			return;
		}

		// Check if file exists.
		$file = $assoc_args['file'];

		if ( ! file_exists( $file ) ) {
			WP_CLI::error( 'The file does not exist.' );
			return;
		}

		WP_CLI::line( WP_CLI::colorize( "%CWe found the file %Y{$file}%n. %CNow importing Yoast data...%n" ) );
		WP_CLI::line( '' );

		// Open the CSV file.
		$handle = fopen( $file, 'r' );

		if ( false !== $handle ) {
			$row           = 1;
			$row_count     = 1;
			$inserted_post = 0;
			$updated_post  = 0;

			// Get total row count.
			$total_rows = count( file( $file ) ) - 1; // Subtract 1 to exclude header row if it exists.

			while ( ( $data = fgetcsv( $handle, 1000, ';' ) ) !== false ) {

				// Skip the first row.
				if ( 1 === $row ) {
					++$row;
					continue;
				}

				// Prepare post array to insert/update.
				$post_data = array(
					'post_type'   => $post_type,
					'post_title'  => esc_html( $data[0] ),
					'post_name'   => esc_html( $data[2] ), // Post Slug.
					'post_status' => esc_html( $data[6] ),
				);

				$yoast_meta = array(
					'_yoast_wpseo_title'    => esc_html( $data[4] ),
					'_yoast_wpseo_metadesc' => esc_html( $data[3] ),
					'_yoast_wpseo_focuskw'  => esc_html( $data[5] ),
				);

				// get slug from CSV row.
				$slug = esc_html( $data[2] );

				// Check if the post exist in the site.
				$post_id = $this->get_post_if_exist( $slug, $post_type );

				// If post found then update else insert.
				if ( $post_id > 0 ) { // Post Found.
					if ( ! $this->is_dry_run() ) {
						$post_data['ID'] = $post_id;
						$new_post_id     = wp_update_post( $post_data, true );

						// Update Yoast Meta.
						$this->update_yoast_meta( $new_post_id, $yoast_meta );

						if ( 'text' === $log_type ) {
							WP_CLI::line(
								sprintf(
									'%d) Post with slug "%s" found. Updated post with ID: %d',
									$row_count,
									$slug,
									$post_id
								)
							);
						}
					} else { // phpcs:ignore
						if ( 'text' === $log_type ) {
							WP_CLI::line(
								sprintf(
									'%d) Post with slug "%s" found. Post will be updated with ID: %d',
									$row_count,
									$slug,
									$post_id
								)
							);
						}
					}

					if ( 'table' === $log_type ) {
						$this->add_table_row(
							$row_count,
							$post_id,
							'',
							$slug,
						);
					}

					++$updated_post;
				} else { // phpcs:ignore
					if ( ! $this->is_dry_run() ) {
						$new_post_id = wp_insert_post( $post_data, true );

						// Update Yoast Meta.
						$this->update_yoast_meta( $new_post_id, $yoast_meta );

						if ( 'text' === $log_type ) {
							WP_CLI::line(
								sprintf(
									'%d) Post with slug "%s" not found. Inserted post with ID: %d',
									$row_count,
									$slug,
									$new_post_id
								)
							);
						}
					} else { // phpcs:ignore
						if ( 'text' === $log_type ) {
							WP_CLI::line(
								sprintf(
									'%d) Post with slug "%s" not found. New Post will insert.',
									$row_count,
									$slug,
								)
							);
						}
					}

					if ( 'table' === $log_type ) {
						$this->add_table_row(
							$row_count,
							$new_post_id,
							$slug,
							'',
						);
					}

					++$inserted_post;
				}

				// If 50 rows have been processed, pause for 2 second.
				if ( 0 === $row_count % 50 ) {

					if ( 'text' === $log_type ) {
						WP_CLI::line( '' );
						WP_CLI::line( 'Sleep for 2 seconds...' );
						WP_CLI::line( '' );
					}
					sleep( 2 );
				}

				++$row;
				++$row_count;

			} // end while.
		}
		// Close the CSV file.
		fclose( $handle );

		// Show table format.
		if ( 'table' === $log_type ) {
			// Set the table headers.
			$fields = array( 'No.', 'Post ID', 'Posts Inserted', 'Posts Updated' );

			// Output the table.
			\WP_CLI\Utils\format_items( 'table', $this->table_item, $fields );
		}

		// Final success message.
		if ( ! $this->is_dry_run() ) {
			$this->notify_on_done(
				sprintf(
					'%d post inserted and %d posts updated out of %d posts',
					$inserted_post,
					$updated_post,
					$total_rows,
				)
			);
		} else {
			$this->notify_on_done(
				sprintf(
					'%d post will be inserted and %d posts will be updated out of %d posts',
					$inserted_post,
					$updated_post,
					$total_rows,
				)
			);
		}
	}

	/**
	 * Method to check if post is exist in the site.
	 *
	 * @param string $slug Post Slug.
	 * @param string $post_type Post Type.
	 *
	 * @return int|false Returns Post ID if post exist else FALSE.
	 */
	public function get_post_if_exist( string $slug, string $post_type ): int|false {

		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'name'                   => $slug,
				'post_status'            => array( 'publish', 'draft' ),
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
	 * Method to update Yoast Meta.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $yoast_meta Yoast Meta.
	 *
	 * @return void
	 */
	public function update_yoast_meta( int $post_id, array $yoast_meta ): void {
		foreach ( $yoast_meta as $meta_key => $meta_value ) {

			// Do not update if meta value is empty.
			if ( ! empty( $meta_value ) ) {
				update_post_meta( $post_id, $meta_key, $meta_value );
			}
		}
	}

	/**
	 * Method to add table row.
	 *
	 * @param int      $row Row number.
	 * @param int|null $post_id Post ID.
	 * @param string   $inserted_slug Inserted Slug.
	 * @param string   $updated_slug Updated Slug.
	 *
	 * @return void
	 */
	public function add_table_row( int $row, int|null $post_id, string $inserted_slug, string $updated_slug ): void {
		$this->table_item[] = array(
			'No.'            => $row,
			'Post ID'        => $post_id,
			'Posts Inserted' => $inserted_slug,
			'Posts Updated'  => $updated_slug,
		);
	}
} // end class.

// EOF.
