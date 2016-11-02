<?php
namespace WP_CLI_Build;

use WP_CLI;

class Build_Command extends \WP_CLI_Command {

	private $build_file = NULL;

	/**
	 * Installs wordpress, plugins and themes.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Specify custom build file (default: build.yml)
	 *
	 * ## EXAMPLES
	 *
	 *     wp build all
	 *     wp build all --file=production.yml
	 *
	 * @when before_wp_load
	 */
	public function all( $args = NULL, $assoc_args = NULL ) {
		// Install WordPress.
		self::core( $args, $assoc_args );
		// Install plugins.
		self::plugins( $args, $assoc_args );
		// Install themes.
		self::themes( $args, $assoc_args );
	}

	/**
	 * Installs core if not installed already.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Specify custom build file (default: build.yml)
	 *
	 * ## EXAMPLES
	 *
	 *     wp build core
	 *     wp build core --file=production.yml
	 *
	 * @when before_wp_load
	 */
	public function core( $args = NULL, $assoc_args = NULL ) {
		// Build file.
		$build_file = $this->__get_build_file( $assoc_args );
		if ( ! empty( $build_file ) ) {
			// Install core.
			WP_CLI::line('Processing core...');
			Build_Task::process_core( $build_file );
		}
	}

	/**
	 * Installs plugins specified in build YAML file.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Specify custom build file (default: build.yml)
	 *
	 * ## EXAMPLES
	 *
	 *     wp build plugins
	 *     wp build plugins --file=production.yml
	 */
	public function plugins( $args = NULL, $assoc_args = NULL ) {
		// Build file.
		$build_file = $this->__get_build_file( $assoc_args );
		if ( ! empty( $build_file ) ) {
			// Install plugins.
			WP_CLI::line('Processing plugins...');
			Build_Task::install_plugins( $build_file );
		}
	}

	/**
	 * Installs themes specified in build YAML file.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Specify custom build file (default: build.yml)
	 *
	 * ## EXAMPLES
	 *
	 *     wp build themes
	 *     wp build themes --file=production.yml
	 */
	public function themes( $args = NULL, $assoc_args = NULL ) {
		// Build file.
		$build_file = $this->__get_build_file( $assoc_args );
		if ( ! empty( $build_file ) ) {
			// Install themes.
			WP_CLI::line('Processing themes...');
			Build_Task::install_themes( $build_file );
		}
	}

	/**
	 * Generates YAML build file based on current installation.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Specify custom build file (default: build.yml)
	 *
	 * ## EXAMPLES
	 *
	 *     wp build generate
	 *     wp build generate --file=production.yml
	 */
	public function generate( $args = NULL, $assoc_args = NULL ) {
		Build_File::generate( $args, $assoc_args );
	}

	// Get build file into an array.
	public function __get_build_file( $assoc_args = NULL ) {
		$build_filename = empty( $assoc_args['file'] ) ? 'build.yml' : $assoc_args['file'];
		if ( empty( $this->build_file ) ) {
			$this->build_file = new Build_File( $build_filename );
		}

		return $this->build_file;
	}

}