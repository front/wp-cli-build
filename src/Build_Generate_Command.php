<?php namespace WP_CLI_Build;

use Symfony\Component\Yaml\Yaml;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Build\Helper\Gitignore;
use WP_CLI_Build\Helper\Utils as HelperUtils;

class Build_Generate_Command extends \WP_CLI_Command {

	/**
	 * Generates YAML build file with core and activated plugins/themes.
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
		if ( ( file_exists( HelperUtils::wp_path( $build_filename ) ) ) && ( empty( $assoc_args['yes'] ) ) ) {
			WP_CLI::confirm( WP_CLI::colorize( "File %Y$build_filename%n exists, do you want to overwrite it?" ) );
		}

		// Process status.
		WP_CLI::line( WP_CLI::colorize( "Generating build to %Y$build_filename%n..." ) );

		// Get information about core.
		$core = self::generate_core( $assoc_args );

		// Get current installed plugins.
		$plugins = self::generate_plugins( $assoc_args );

		// Get current installed themes.
		$themes = self::generate_themes( $assoc_args );

		// YAML content.
		$yaml = NULL;
		if ( ! empty( $core ) ) {
			$yaml['core'] = $core;
		}
		if ( ! empty( $plugins['yaml'] ) ) {
			$yaml['plugins'] = $plugins['yaml'];
		}
		if ( ! empty( $themes['yaml'] ) ) {
			$yaml['themes'] = $themes['yaml'];
		}

		if ( ! empty( $yaml ) ) {
			@file_put_contents( $build_filename, Yaml::dump( $yaml, 10 ) );
			if ( file_exists( HelperUtils::wp_path( $build_filename ) ) ) {
				// Gitignore generation, unless '--no-gitignore' is specified.
				if ( empty( $assoc_args['no-gitignore'] ) ) {
					// Custom plugins/themes to exclude from gitignore.
					$exclude_items = [ ];
					if ( ! empty( $plugins['exclude'] ) ) {
						$exclude_items = array_merge( $exclude_items, $plugins['exclude'] );
					}
					if ( ! empty( $themes['exclude'] ) ) {
						$exclude_items = array_merge( $exclude_items, $themes['exclude'] );
					}
					Gitignore::build_block( $exclude_items );
				}
				WP_CLI::line( WP_CLI::colorize( "%GSuccess:%n YAML file generated." ) );

				return TRUE;
			}
		}

		WP_CLI::line( WP_CLI::colorize( "%RError:%n Failed to generated YAML build file, no content." ) );

		return TRUE;
	}

	private function generate_core( $assoc_args = NULL ) {
		$core    = [ ];
		$version = HelperUtils::wp_version();
		if ( ! empty( $version ) ) {
			$core['download']['version'] = $version;
			$core['download']['locale']  = get_locale();
		}

		return $core;
	}

	private function generate_plugins( $assoc_args = NULL ) {
		// Plugins.
		$installed_plugins = get_plugins();
		$return            = NULL;
		if ( ! empty( $installed_plugins ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			foreach ( $installed_plugins as $file => $details ) {
				// Check if plugin is active.
				if ( ( is_plugin_active( $file ) ) || ( ! empty( $assoc_args['all'] ) ) ) {
					// Plugin slug.
					$slug = strtolower( Utils\get_plugin_name( $file ) );
					// Check plugin information on wp official repository.
					$api = plugins_api( 'plugin_information', [ 'slug' => $slug ] );
					// If the plugin is not found we assume the plugin is custom.
					if ( is_wp_error( $api ) ) {
						// Exclude our custom plugin from being ignored by git.
						if ( empty( $assoc_args['no-gitignore'] ) ) {
							$return['exclude'][] = [ 'slug' => $slug, 'type' => 'plugin' ];
						}
						continue;
					}
					// Plugin version.
					if ( ! empty( $details['Version'] ) ) {
						$return['yaml'][ $slug ]['version'] = $details['Version'];
					}
					// Plugin network activation.
					if ( ! empty( $details['Network'] ) ) {
						$return['yaml'][ $slug ]['activate-network'] = 'yes';
					}
				}
			}
		}

		return $return;
	}


	private function generate_themes( $assoc_args = NULL ) {
		// Themes.
		$installed_themes = wp_get_themes();
		$return           = NULL;
		if ( ! empty( $installed_themes ) ) {
			$current_theme = get_stylesheet();
			foreach ( $installed_themes as $slug => $theme ) {
				if ( $slug == $current_theme ) {
					// Check theme information on wp official repository.
					$api = themes_api( 'theme_information', [ 'slug' => $slug ] );
					// If the theme is not found we assume the plugin is custom.
					if ( is_wp_error( $api ) ) {
						// Exclude our custom theme from being ignored by git.
						if ( empty( $assoc_args['no-gitignore'] ) ) {
							$return['exclude'][] = [ 'slug' => $slug, 'type' => 'theme' ];
						}
						continue;
					}
					// Theme version.
					if ( ! empty( $theme->display( 'Version' ) ) ) {
						$return['yaml'][ $slug ]['version'] = $theme->display( 'Version' );
					}
				}
			}
		}

		return $return;
	}


}