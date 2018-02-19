<?php namespace WP_CLI_Build;

use Symfony\Component\Yaml\Yaml;
use WP_CLI;
use WP_CLI_Build\Helper\Build_File;
use WP_CLI_Build\Helper\Gitignore;
use WP_CLI_Build\Helper\Utils as HelperUtils;
use WP_CLI_Build\Processor\Generate;

class Build_Generate_Command extends \WP_CLI_Command {

	/**
	 * Generates build file with core and activated plugins/themes. Also generates .gitignore with custom plugins/themes.
	 *
	 * ## OPTIONS
	 *
	 * [--output=<file>]
	 * : Where to output build generation result (yml file)
	 *
	 * [--skip-git]
	 * : .gitignore will not be generated
	 *
	 * [--all]
	 * : Includes all plugins/themes wether they're activated or not
	 *
	 * [--yes]
	 * : Skip overwriting confirmation if the destination build file already exists
	 *
	 * ## EXAMPLES
	 *
	 *     wp build-generate
	 *     wp build-generate --output=production.yml
	 *
	 */
	public function __invoke( $args = NULL, $assoc_args = NULL ) {
		$this->generate( $args, $assoc_args );
	}

	// Generate
	private function generate( $args = NULL, $assoc_args = NULL ) {

		// Build file.
		$build_filename = empty( $assoc_args['file'] ) ? 'build.yml' : $assoc_args['file'];

		// If file exists, prompt if the user want to replace it.
		$build_file = NULL;
		if ( file_exists( HelperUtils::wp_path( $build_filename ) ) ) {
			if ( empty( $assoc_args['yes'] ) ) {
				WP_CLI::confirm( WP_CLI::colorize( "%WFile %Y$build_filename%n%W exists, do you want to overwrite it?%n" ) );
			}
			$build_file = new Build_File( $build_filename );
		}

		// Process status.
		WP_CLI::line( WP_CLI::colorize( "%WGenerating build file (%n%Y$build_filename%n%W), please wait..." ) );

		// Get structure for build file.
		$generator = new Generate( $assoc_args, $build_file );
		$build     = $generator->get();

		// YAML content.
		$yaml = [];
		if ( ! empty( $build['core'] ) ) {
			$yaml['core'] = $build['core'];
		}
		if ( ! empty( $build['plugins']['build'] ) ) {
			$yaml['plugins'] = $build['plugins']['build'];
		}
		if ( ! empty( $build['themes']['build'] ) ) {
			$yaml['themes'] = $build['themes']['build'];
		}

		if ( ! empty( $yaml ) ) {
			@file_put_contents( $build_filename, Yaml::dump( $yaml, 10 ) );
			if ( file_exists( HelperUtils::wp_path( $build_filename ) ) ) {
				// Gitignore generation, unless '--no-gitignore' is specified.
				if ( empty( $assoc_args['no-gitignore'] ) ) {
					// Custom plugins/themes to exclude from gitignore.
					$custom_items = [];
					if ( ! empty( $build['plugins']['custom'] ) ) {
						$custom_items = array_merge( $custom_items, $build['plugins']['custom'] );
					}
					if ( ! empty( $build['themes']['custom'] ) ) {
						$custom_items = array_merge( $custom_items, $build['themes']['custom'] );
					}
					Gitignore::build_block( $custom_items );
				}
				WP_CLI::line( WP_CLI::colorize( "%GSuccess:%n YAML file generated." ) );

				return TRUE;
			}
		}

		WP_CLI::line( WP_CLI::colorize( "%RError:%n Failed to generated YAML build file, no content." ) );

		return TRUE;
	}


}