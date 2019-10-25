<?php namespace WP_CLI_Build;

use WP_CLI;
use WP_CLI_Build\Helper\Gitignore;
use WP_CLI_Build\Helper\Utils;
use WP_CLI_Build\Processor\Generate;

class Build_Generate_Command extends \WP_CLI_Command {

	/**
	 * Generates build file with core and activated plugins/themes. Also generates .gitignore with custom plugins/themes.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Where to output build generation result (json file)
	 *
	 * [--format]
	 * : Build file format: json or yml
	 *
	 * [--skip-git]
	 * : .gitignore will not be generated
	 *
	 * [--all]
	 * : Includes all plugins/themes whether they're activated or not
	 *
	 * [--yes]
	 * : Skip overwriting confirmation if the destination build file already exists
	 *
	 * ## EXAMPLES
	 *
	 *     wp build-generate
	 *     wp build-generate --file=production.yml
	 *
	 */
	public function __invoke( $args = NULL, $assoc_args = NULL ) {

		// Build file.
		$build_filename = Utils::get_build_filename( $assoc_args );

		// If file exists, prompt if the user want to replace it.
		if ( file_exists( Utils::wp_path( $build_filename ) ) ) {
			if ( empty( $assoc_args['yes'] ) ) {
				WP_CLI::confirm( WP_CLI::colorize( "%WFile %Y$build_filename%n%W exists, do you want to overwrite it?%n" ) );
			}
		}

		// New generator class.
		$generator = new Generate( $assoc_args, $build_filename );

		// Attempt to create build file.
		$generator->create_build_file();

		// Attempt to gitignore.
		if ( empty( $assoc_args['skip-git'] ) ) {
			$generator->create_gitignore();
		}

	}


}
