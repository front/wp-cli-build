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
		if ( file_exists( ABSPATH . $build_file ) ) {
			WP_CLI::confirm( WP_CLI::colorize( "File %Y$build_file%n exists, do you want to overwrite it?" ) );
		}

		// Generate YAML file.
		$yaml = NULL;
		WP_CLI::line( WP_CLI::colorize( "Generating YAML to %Y$build_file%n..." ) );

		// Get information about core.
		$yaml['core'] = self::generate_core( $assoc_args );

		// Get current installed plugins.
		$yaml['plugins'] = self::generate_plugins( $assoc_args );

		// Get current installed themes.
		$yaml['themes'] = self::generate_themes( $assoc_args );

		if ( ! empty( $yaml ) ) {
			@file_put_contents( $build_file, Yaml::dump( $yaml, 10 ) );
			if ( file_exists( ABSPATH . $build_file ) ) {
				WP_CLI::line( WP_CLI::colorize( "%GSuccess:%n YAML file generated." ) );

				return TRUE;
			}
		}

		WP_CLI::line( WP_CLI::colorize( "%RError:%n YAML file generated." ) );

		return TRUE;
	}

	private static function generate_core( $assoc_args = NULL ) {
		$core    = [ ];
		$version = Build_Helper::check_wp_version();
		if ( ! empty( $version ) ) {
			$core['download']['version'] = $version;
		}

		if ( ! empty( $assoc_args['gitignore'] ) ) {
			Build_Gitignore::add_line( "# Ignore everything in the root except the \"wp-content\" directory.\n" );
			Build_Gitignore::add_line( "/*\n" );
			Build_Gitignore::add_line( "!.gitignore\n" );
			Build_Gitignore::add_line( "!wp-content/\n" );
			Build_Gitignore::add_line( "# Ignore everything in the \"wp-content\" directory, except the \"plugins\" and \"themes\" directories.\n" );
			Build_Gitignore::add_line( "wp-content/*\n" );
			Build_Gitignore::add_line( "!wp-content/plugins/\n" );
			Build_Gitignore::add_line( "!wp-content/themes/\n" );
		}

		return $core;
	}

	private static function generate_plugins( $assoc_args = NULL ) {
		// Plugins.
		$installed_plugins = get_plugins();
		$yaml_plugins      = [ ];
		if ( ! empty( $installed_plugins ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			foreach ( $installed_plugins as $file => $details ) {
				// Plugin slug.
				$slug = strtolower( Utils\get_plugin_name( $file ) );
				// Check for WP.org information, if the plugin info is not found, don't add it.
				$api = plugins_api( 'plugin_information', [ 'slug' => $slug ] );
				if ( is_wp_error( $api ) ) {
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
				// Gitignore.
				if ( ! empty( $assoc_args['gitignore'] ) ) {
					Build_Gitignore::add_item( $slug, 'plugin' );
					$yaml_plugins[ $slug ]['gitignore'] = TRUE;
				}
			}
		}

		return $yaml_plugins;
	}

	private static function generate_themes( $assoc_args = NULL ) {
		// Themes.
		$installed_themes = get_themes();
		$yaml_themes      = [ ];
		if ( ! empty( $installed_themes ) ) {
			foreach ( $installed_themes as $file => $details ) {
				// Slug.
				$slug = strtolower( Utils\get_theme_name( $file ) );
				// Check for WP.org information, if the theme info is not found, don't add it.
				$api = themes_api( 'theme_information', [ 'slug' => $slug ] );
				if ( is_wp_error( $api ) ) {
					continue;
				}
				// Theme version.
				if ( ! empty( $details['Version'] ) ) {
					$yaml_themes[ $slug ]['version'] = $details['Version'];
				}
				// Theme network activation.
				if ( ! empty( $details['Network'] ) ) {
					$yaml_themes[ $slug ]['activate-network'] = 'yes';
				}
				// Gitignore.
				if ( ! empty( $assoc_args['gitignore'] ) ) {
					Build_Gitignore::add_item( $slug, 'theme' );
					$yaml_themes[ $slug ]['gitignore'] = TRUE;
				}
			}
		}

		return $yaml_themes;
	}

}