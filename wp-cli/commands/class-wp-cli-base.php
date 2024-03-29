<?php
/**
 * Base class for WP-CLI
 *
 * @author Krupal Panchal
 *
 * @package wp-cli
 */

/**
 * Class WP_CLI_Base
 */
class WP_CLI_Base {

	/**
	 * Dry run
	 *
	 * @var bool
	 */
	protected bool $dry_run = true;  // default to true to prevent accidental command runs.

	/**
	 * Batch size of command run.
	 *
	 * @var int
	 */
	protected int $batch_size = 60;

	/**
	 * Current iteration.
	 *
	 * @var int
	 */
	protected int $current_iteration = 0;

	/**
	 * Max iteration.
	 *
	 * @var int
	 */
	protected int $max_iterations = 20;

	/**
	 * Sleep after max iteration.
	 *
	 * @var int
	 */
	protected int $sleep = 2;

	/**
	 * Start time.
	 *
	 * @var int
	 */
	protected int $start_time = 0;

	/**
	 * Class constructor
	 */
	public function __construct() {
	}

	/**
	 * Method to check if current run is dry run or not
	 *
	 * @return bool
	 */
	public function is_dry_run(): bool {
		return (bool) ( true === $this->dry_run );
	}

	/**
	 * Method to parse global arguments on any CLI command run
	 *
	 * @param array $assoc_args Associate arguments.
	 *
	 * @return void
	 */
	protected function parse_global_arguments( array $assoc_args = array() ): void {

		if ( empty( $assoc_args ) ) {
			return;
		}

		$command_truthy_values = array( '', 'yes', 'true', '1' );

		if ( ! empty( $assoc_args['batch-size'] ) ) {
			$this->batch_size = (int) $assoc_args['batch-size'];
		}

		if ( isset( $assoc_args['dry-run'] ) ) {
			$this->dry_run = in_array(
				strtolower( $assoc_args['dry-run'] ),
				$command_truthy_values,
				true
			);
		}
	}

	/**
	 * Method to log and notify start of command run
	 *
	 * @return void
	 */
	protected function notify_on_start(): void {

		$this->start_time = time();

		$message = sprintf(
			'WP_CLI command has started running on %s',
			wp_parse_url( home_url(), PHP_URL_HOST )
		);

		if ( $this->is_dry_run() ) {
			$message = sprintf( '%s - Dry Run Started.', $message );
		}

		WP_CLI::log( '' );
		WP_CLI::log( $message );
		WP_CLI::log( '' );
	}

	/**
	 * Method to log and notify end of command run
	 *
	 * @param string $msg Message to show at the end of command run.
	 *
	 * @return void
	 */
	protected function notify_on_done( string $msg = '' ): void {

		if ( empty( $msg ) ) {
			$msg = 'WP-CLI command run completed!';
		}

		if ( $this->is_dry_run() ) {
			$msg_completed = sprintf(
				'%s - Dry Run Completed.',
				$msg
			);
		} else {
			$msg_completed = sprintf(
				'%s - Time taken: %s seconds',
				$msg,
				( time() - $this->start_time )
			);
		}

		WP_CLI::log( '' );
		WP_CLI::success( $msg_completed );
	}

	/**
	 * Method to update iteration and give a pause after N iterations
	 * to prevent DB from being hammered.
	 *
	 * @return void
	 */
	protected function update_iteration(): void {

		++$this->current_iteration;

		if (
			1 > $this->sleep ||
			1 > $this->max_iterations ||
			$this->current_iteration < $this->max_iterations
		) {
			return;
		}

		$this->current_iteration = 0; // reset current iteration.
		WP_CLI::log( sprintf( 'Sleep for %d seconds...', $this->sleep ) );
		WP_CLI::log( '' );

		sleep( $this->sleep );
	}
} // end class.

// EOF.
