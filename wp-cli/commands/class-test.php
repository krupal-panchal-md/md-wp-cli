<?php
/**
 * Class for testing purpose
 *
 * Command `test`
 *
 * @author Krupal Panchal
 *
 * @package md-wp-cli
 */

/**
 * Class Test
 */
class Test extends WP_CLI_Base {

	public const COMMAND_NAME = 'test';

	/**
	 * Command for testing purpose
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 * # Complete the test.
	 * $ wp test update-test
	 * Success: Test Completed!!!
	 *
	 * @subcommand update-test
	 *
	 * @return void
	 */
	public function update_test(): void {

		$msg = 'Test Started!';

		/**
		 * Log the message
		 */
		WP_CLI::log( $msg );

		/**
		 * Show success message after completion
		 */
		WP_CLI::success( 'Test Completed!!!' );
	}

	/**
	 * Command for check if post exist by slug.
	 *
	 * [--slug=<slug>]
	 * : Slug of the post.
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
	 * $ wp test post-exist
	 * Success: Test Completed!!!
	 *
	 * @subcommand post-exist
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args Associate arguments.
	 *
	 * @return void
	 */
	public function post_exist( array $args, array $assoc_args ): void {

		// Parse the global arguments.
		$this->parse_global_arguments( $assoc_args );

		$this->notify_on_start();

		$slug = $assoc_args['slug'];

		$query = new WP_Query(
			array(
				'post_type'              => 'post',
				'name'                   => $slug,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$status = $query->have_posts() ? $query->posts[0] : false;

		if ( is_int( $status ) && $status > 0 ) {
			WP_CLI::success(
				sprintf(
					'Post with slug %s exists!',
					$slug
				)
			);
		} else {
			WP_CLI::error( 'Post does not exist!' );
		}

		$this->notify_on_done();
	}
} // end class.

// EOF.
