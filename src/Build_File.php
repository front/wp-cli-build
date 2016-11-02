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
		$file_path = ( Build_Helper::is_absolute_path( $this->filename ) ) ? $this->filename : ABSPATH . $this->filename;
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

		// Get current installed plugins.
		$yaml['plugins'] = self::generate_plugins();

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

	private static function generate_plugins() {
		// Plugins.
		$installed_plugins = get_plugins();
		$yaml_plugins      = [ ];
		if ( ! empty( $installed_plugins ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			foreach ( $installed_plugins as $file => $details ) {
				// Plugin slug.
				$slug = Utils\get_plugin_name( $file );
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
			}
		}

		return $yaml_plugins;
	}

}