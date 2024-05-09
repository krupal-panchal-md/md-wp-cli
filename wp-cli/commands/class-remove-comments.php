<?php
/**
 * Class for remove comments.
 *
 * Command `comments`
 *
 * @author Krupal Panchal <krupal.panchal@multidots.com>
 *
 * @package md-wp-cli
 */

/**
 * Class Remove_Revision
 */
class Remove_Comments extends WP_CLI_Base {

	/**
	 * Command Name.
	 *
	 * @var string
	 */
	public const COMMAND_NAME = 'comments';

	/**
	 * Command for Remove comments older than 6 months and unapproved.
	 *
	 * [--month-old=<month-old>]
	 * : Month from today, we want to remove.
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
	 * # Command to remove comments.
	 * $ wp comments remove --month-old=6 --dry-run=true/false
	 *
	 * # DB Query to get the count of comments.
	 * SELECT COUNT(*) FROM wp_comments WHERE comment_approved = '0' AND comment_date_gmt < DATE_SUB(NOW(), INTERVAL 6 MONTH);
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

		$month = 6;
		if ( ! empty( $assoc_args['month-old'] ) ) {
			$month = (int) $assoc_args['month-old'];
		}

		$before_month_ago = gmdate( 'Y-m-d', strtotime( '-' . $month . ' months' ) );

		$page = 1;
		$args = array(
			'status'        => 'hold',
			'number'        => $this->batch_size,
			'date_query'    => array(
				array(
					'column' => 'comment_date_gmt',
					'before' => $before_month_ago,
				),
			),
			'no_found_rows' => false,
			'fields'        => 'ids',
		);

		$count = 1;

		do {
			$args['paged'] = $page;
			$comment_query = new WP_Comment_Query();

			$comments = $comment_query->query( $args );

			$comments_count = count( $comments );

			// Loop through the comments and delete them.
			foreach ( $comments as $comment_id ) {
				if ( $this->is_dry_run() ) {
					WP_CLI::log(
						sprintf(
							'%d) Comment will be removed of ID: %s',
							$count,
							$comment_id,
						)
					);
				} else {
					wp_delete_comment( $comment_id, true );
					WP_CLI::log(
						sprintf(
							'%d) Comment removed of ID: %s',
							$count,
							$comment_id,
						)
					);
				}

				++$count;
				$this->update_iteration();
			}

			/**
			 * If dry run is enabled the count will work as normal. But when dry run is disabled, the comments will actually be deleted.
			 * So, we need to increment the page number only when dry run is enabled.
			 */
			if ( $this->is_dry_run() ) {
				++$page;
			}
		} while ( $comments_count === $this->batch_size );

		WP_CLI::log( '' );
		if ( $this->is_dry_run() ) {
			WP_CLI::success(
				sprintf(
					'Total %d Comments will be removed.',
					$count - 1
				)
			);
		} else {
			WP_CLI::success(
				sprintf(
					'Total %d Comments removed successfully!!!',
					$count - 1
				)
			);
		}

		$this->notify_on_done();
	}
}
