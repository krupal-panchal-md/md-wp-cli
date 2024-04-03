<?php
/**
 * Register of all WP Commands
 *
 * @author Krupal Panchal <krupal.panchal@multidots.com>
 *
 * @package md-wp-cli
 */

/**
 * Class WP_CLI_Commands
 */
class WP_CLI_Commands {

	/**
	 * Class of all commands,
	 *
	 * @var array
	 */
	protected array $commands = array(
		Test_Complete::class,
		Yoast_Posts_Import::class,
		Woo_Products_Migrate::class,
	);

	/**
	 * Class Constructor
	 */
	public function __construct() {

		// Autoload.
		spl_autoload_register( array( $this, 'wp_cli_command_autoload' ) );

		// Register all Commands.
		$this->regiser_commands();
	}

	/**
	 * Method to autoload all command class.
	 *
	 * @param string $class_name Class name.
	 *
	 * @return void
	 */
	public function wp_cli_command_autoload( string $class_name ): void {

		$class_name = str_replace( '_', '-', $class_name );
		$class_name = strtolower( $class_name );

		$get_file = plugin_dir_path( __DIR__ ) . "wp-cli/commands/class-$class_name.php";

		file_exists( $get_file ) ? require_once $get_file : '';
	}

	/**
	 * Method to register custom commands with WP-CLI
	 *
	 * @return void
	 */
	protected function regiser_commands(): void {

		// Check if WP-CLI is defined.
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		// Hook into the plugins_loaded action to register commands after all plugins have been loaded.
		add_action(
			'plugins_loaded',
			function () {
				if ( ! empty( $this->commands ) ) {
					foreach ( $this->commands as $command ) {
						WP_CLI::add_command( $command::COMMAND_NAME, $command );
					}
				}
			}
		);

		// if ( ! empty( $this->commands ) ) {
		// 	foreach ( $this->commands as $command ) {
		// 		WP_CLI::add_command( $command::COMMAND_NAME, $command );
		// 	}
		// }
	}
} // end class.

new WP_CLI_Commands();

// EOF.
