<?php
namespace WP_CLI_Build;

use Symfony\Component\Yaml\Yaml;
use WP_CLI;
use WP_CLI\Utils;

class Build_File {

	private $filename = 'build.yml';
	private $build = [ ];

	public function __construct( $file ) {
		// Set Build file.
		$this->filename = empty( $file ) ? 'build.yml' : $file;
		// Parse the Build file and Build sure it's valid.
		$this->parse();
	}

	private function parse() {
		// Full Build file path.
		$file_path = ( Build_Helper::is_absolute_path( $this->filename ) ) ? $this->filename : realpath( '.' ) . '/' . $this->filename;
		// Check if the file exists.
		if ( ! file_exists( $file_path ) ) {
			WP_CLI::error( 'Build file (' . $this->filename . ') not found.' );
		}
		// Check if the Build file is a valid yaml file.
		try {
			$this->build = Yaml::parse( file_get_contents( $file_path ) );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'Error parsing YAML from Build file (' . $this->filename . ').' );
		}
	}

	public function get( $key = NULL, $sub_key = NULL ) {

		// With subkey.
		if ( ! empty( $this->build[ $key ][ $sub_key ] ) ) {
			return $this->build[ $key ][ $sub_key ];
		}

		// With key.
		if ( ( ! empty( $this->build[ $key ] ) ) && ( empty( $sub_key ) ) ) {
			return $this->build[ $key ];
		}

		return [ ];
	}

	// Generate
	public static function generate( $args = NULL, $assoc_args = NULL ) {

		// If file exists, prompt if the user want to replace it.
		$build_file = empty( $assoc_args['file'] ) ? 'build.yml' : $assoc_args['file'];
		if ( ( file_exists( ABSPATH . $build_file ) ) && ( empty( $assoc_args['yes'] ) ) ) {
			WP_CLI::confirm( WP_CLI::colorize( "File %Y$build_file%n exists, do you want to overwrite it?" ) );
		}

		// Process status.
		WP_CLI::line( WP_CLI::colorize( "Generating YAML to %Y$build_file%n..." ) );

		// Ignore everything unless '--no-gitignore' is specified.
		if ( empty( $assoc_args['no-gitignore'] ) ) {
			Build_Gitignore::add_line( "# Ignore everything in the root.\n" );
			Build_Gitignore::add_line( "/*\n" );
			Build_Gitignore::add_line( "!.gitignore\n" );
			Build_Gitignore::add_line( "!build.yml\n" );
			Build_Gitignore::add_line( "!wp-content\n" );
			Build_Gitignore::add_line( "wp-content/*\n" );
			Build_Gitignore::add_line( "!wp-content/plugins\n" );
			Build_Gitignore::add_line( "wp-content/plugins/*\n" );
			Build_Gitignore::add_line( "!wp-content/themes\n" );
			Build_Gitignore::add_line( "wp-content/themes/*\n" );
			Build_Gitignore::add_line( "\n" );
			Build_Gitignore::add_line( "# Add exceptions for our custom plugins/themes.\n" );
		}

		// Get information about core.
		$yaml['core'] = self::generate_core( $assoc_args );

		// Get current installed plugins.
		$plugins = self::generate_plugins( $assoc_args );

		// Get current installed themes.
		$themes = self::generate_themes( $assoc_args );

		// YAML content.
		$yaml = NULL;
		if ( ! empty( $plugins ) ) {
			$yaml['plugins'] = $plugins;
		}
		if ( ! empty( $themes ) ) {
			$yaml['themes'] = $themes;
		}

		if ( ! empty( $yaml ) ) {
			@file_put_contents( $build_file, Yaml::dump( $yaml, 10 ) );
			if ( file_exists( ABSPATH . $build_file ) ) {
				WP_CLI::line( WP_CLI::colorize( "%GSuccess:%n YAML file generated." ) );

				return TRUE;
			}
		}

		WP_CLI::line( WP_CLI::colorize( "%RError:%n Failed to generated YAML build file, no content." ) );

		return TRUE;
	}

	private static function generate_core( $assoc_args = NULL ) {
		$core    = [ ];
		$version = Build_Helper::check_wp_version();
		if ( ! empty( $version ) ) {
			$core['download']['version'] = $version;
		}

		return $core;
	}

	private static function generate_plugins( $assoc_args = NULL ) {
		// Plugins.
		$installed_plugins = get_plugins();
		$yaml_plugins      = NULL;
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
							Build_Gitignore::exclude_item( $slug, 'plugin' );
						}
						continue;
					}
					// Plugin version.
					if ( ! empty( $details['Version'] ) ) {
						$yaml_plugins[ $slug ]['version'] = $details['Version'];
					}
					// Plugin network activation.
					if ( ! empty( $details['Network'] ) ) {
						$yaml_plugins[ $slug ]['activate-network'] = 'yes';
					}
				}
			}
		}

		return $yaml_plugins;
	}


	private static function generate_themes( $assoc_args = NULL ) {
		// Themes.
		$installed_themes = wp_get_themes();
		$yaml_themes      = NULL;
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
							Build_Gitignore::exclude_item( $slug, 'theme' );
						}
						continue;
					}
					// Theme version.
					if ( ! empty( $theme->display( 'Version' ) ) ) {
						$yaml_themes[ $slug ]['version'] = $theme->display( 'Version' );
					}
				}
			}
		}

		return $yaml_themes;
	}

}