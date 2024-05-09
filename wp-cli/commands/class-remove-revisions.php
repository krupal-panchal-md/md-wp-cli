<?php
/**
 * Class for Remove revisions.
 *
 * Command `revisions`
 *
 * @author Krupal Panchal <krupal.panchal@multidots.com>
 *
 * @package md-wp-cli
 */

/**
 * Class Remove_Revision
 */
class Remove_Revisions extends WP_CLI_Base {

	/**
	 * Command Name.
	 *
	 * @var string
	 */
	public const COMMAND_NAME = 'revisions';

	/**
	 * Command for Remove revisions older than 1 year.
	 *
	 * [--year-old=<year-old>]
	 * : Year from today, we want to remove.
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
	 * # Command to remove revisions.
	 * $ wp revisions remove --year-old=1 --dry-run=true/false
	 *
	 * # DB Query to get the count of revisions.
	 * SELECT COUNT(*) FROM wp_posts WHERE post_type = 'revision' AND post_modified_gmt < DATE_SUB(NOW(), INTERVAL 1 YEAR);
	 *
	 * @subcommand remove
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args Associate arguments.
	 *
	 * @return void
	 */
	public function remove( array $args, array $assoc_args ): void {

		// Parse the global arguments.
		$this->parse_global_arguments( $assoc_args );

		$this->notify_on_start();

		$year = 1;
		if ( ! empty( $assoc_args['year-old'] ) ) {
			$year = $assoc_args['year-old'];
		}

		// Get the date a specific year ago from today.
		$date_year_ago = gmdate( 'Y-m-d', strtotime( "-{$year} year" ) );

		$page = 1;
		$args = array(
			'post_type'        => 'revision',
			'numberposts'      => $this->batch_size,
			'post_status'      => 'any',
			'suppress_filters' => false,
			'fields'           => 'ids',
			'no_found_rows'    => false,
			'date_query'       => array(
				array(
					'column' => 'post_modified_gmt',
					'before' => $date_year_ago,
				),
			),
		);

		$count = 1;

		do {

			$args['paged'] = $page;

			$revisions   = get_posts( $args );
			$posts_count = count( $revisions );

			foreach ( $revisions as $revision_id ) {
				if ( $this->is_dry_run() ) {
					WP_CLI::log(
						sprintf(
							'%d) Revision will be removed: %s',
							$count,
							$revision_id,
						)
					);
				} else {
					wp_delete_post( $revision_id, true );
					WP_CLI::log(
						sprintf(
							'%d) Revision removed: %s',
							$count,
							$revision_id,
						)
					);
				}

				++$count;
				$this->update_iteration();
			}

			/**
			 * If dry run is enabled the count will work as normal. But when dry run is disabled, the revisions will actually be deleted.
			 * So, we do need to increment the page number when dry run is disabled.
			 */
			if ( $this->is_dry_run() ) {
				++$page;
			}
		} while ( $posts_count === $this->batch_size );

		WP_CLI::log( '' );
		if ( $this->is_dry_run() ) {
			WP_CLI::success(
				sprintf(
					'Total %d Revisions will be removed.',
					$count - 1
				)
			);
		} else {
			WP_CLI::success(
				sprintf(
					'Total %d Revisions removed successfully!!!',
					$count - 1
				)
			);
		}

		$this->notify_on_done();
	}
}
