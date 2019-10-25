<?php namespace WP_CLI_Build;

use WP_CLI;
use WP_CLI_Build\Processor\Core;
use WP_CLI_Build\Processor\Item;
use WP_CLI_Build\Helper\Utils;

class Build_Command extends \WP_CLI_Command {

	/**
	 * Installs wordpress, plugins and themes.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Specify custom build file (default: build.json)
	 *
	 * [--clean]
	 * : Deletes and re-download all plugins and themes listed in build file
	 *
	 * [--ignore-core]
	 * : Don't process core
	 *
	 * [--ignore-plugins]
	 * : Don't process plugins
	 *
	 * [--ignore-themes]
	 * : Don't process themes
	 *
	 * [--yes]
	 * : Skip confirmation of some questions
	 *
	 * [--dbname]
	 * : Database name for wp-config.php (if WP is not installed)
	 *
	 * [--dbuser]
	 * : Database user for wp-config.php (if WP is not installed)
	 *
	 * [--dbpass]
	 * : Database pass for wp-config.php (if WP is not installed)
	 *
	 * [--dbhost]
	 * : Database host for wp-config.php (if WP is not installed)
	 *
	 * [--dbprefix]
	 * : Database prefix for wp-config.php (if WP is not installed)
	 *
	 * [--dbcharset]
	 * : Database charset for wp-config.php (if WP is not installed)
	 *
	 * [--dbcollate]
	 * : Database collate for wp-config.php (if WP is not installed)
	 *
	 * [--locale]
	 * : Locale for wp-config.php (if WP is not installed)
	 *
	 * [--skip-salts]
	 * : If set, keys and salts won't be generated for wp-config.php (if WP is not installed)
	 *
	 * [--skip-check]
	 * : If set, the database connection is not checked.
	 *
	 * [--force]
	 * : Overwrites existing wp-config.php
	 *
	 * ## EXAMPLES
	 *
	 *     wp build
	 *     wp build --file=production.json --ignore-plugins
	 *
	 * @when  before_wp_load
	 */
	public function __invoke( $args = NULL, $assoc_args = NULL ) {

		$build_filename = Utils::get_build_filename( $assoc_args );
		WP_CLI::line( WP_CLI::colorize( "%GParsing %W$build_filename%n%G, please wait...%n" ) );

		// Clean mode check
		if ( ( ! empty( $assoc_args['clean'] ) ) && ( empty( $assoc_args['yes'] ) ) ) {
			WP_CLI::confirm( WP_CLI::colorize( "\n%RItems will be deleted! => This will delete and re-download all plugins and themes listed in build file.\n%n%YAre you sure you want to continue?%n" ) );
		}

		// Process core.
		if ( empty( $assoc_args['ignore-core'] ) ) {
			$core = new Core( $assoc_args );
			$core = $core->process();
		}

		// Item processor.
		$item = new Item( $assoc_args );

		// Process plugins.
		if ( empty( $assoc_args['ignore-plugins'] ) ) {
			$plugins = $item->run( 'plugin' );
		}

		// Process themes.
		if ( empty( $assoc_args['ignore-themes'] ) ) {
			$themes = $item->run( 'theme' );
		}

		// Nothing to do!
		if ( empty( $core ) && empty( $plugins ) && empty( $themes ) ) {
			WP_CLI::line( WP_CLI::colorize( "%WNothing to do.%n" ) );
		} else {
			WP_CLI::line( WP_CLI::colorize( "%WFinished.%n" ) );
		}

	}

}
