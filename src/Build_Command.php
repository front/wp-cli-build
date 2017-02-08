<?php namespace WP_CLI_Build;

use WP_CLI;
use WP_CLI_Build\Processor\Core;
use WP_CLI_Build\Processor\Item;

class Build_Command extends \WP_CLI_Command {

	/**
	 * Installs wordpress, plugins and themes.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Specify custom build file (default: build.yml)
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
	 * ## EXAMPLES
	 *
	 *     wp build
	 *     wp build --file=production.yml --no-plugins
	 *
	 * @when  before_wp_load
	 */
	public function __invoke( $args = NULL, $assoc_args = NULL ) {

		WP_CLI::line( WP_CLI::colorize( '%gLoading build.yml%n' ) );

		// Process core.
		if ( empty( $assoc_args['no-core'] ) ) {
			$core = new Core( $assoc_args );
			$core = $core->process();
		}

		// Process plugins.
		$item = new Item( $assoc_args );
		if ( empty( $assoc_args['no-plugins'] ) ) {
			$plugins = $item->run( 'plugin' );
		}

		// Process themes.
		$item = new Item( $assoc_args );
		if ( empty( $assoc_args['no-themes'] ) ) {
			$themes = $item->run( 'theme' );
		}

		// Nothing to do!
		if ( empty( $core ) && empty( $plugins ) && empty( $themes ) ) {
			WP_CLI::line( "Nothing to do." );
		} else {
			WP_CLI::line( "Finished." );
		}

	}

}