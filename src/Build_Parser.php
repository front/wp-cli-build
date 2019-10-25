<?php namespace WP_CLI_Build;

use Symfony\Component\Yaml\Yaml;
use WP_CLI;
use WP_CLI_Build\Helper\Utils;

class Build_Parser {

	private $filename = 'build.json';
	private $format = 'json';
	private $build = [];
	private $assoc_args = NULL;

	public function __construct( $filename, $assoc_args = NULL ) {
		// Set Build file.
		$this->filename = empty( $filename ) ? 'build.json' : $filename;
		// Set format.
		$this->format = ( strpos( $this->filename, 'yml' ) !== FALSE ) ? 'yml' : 'json';
		// Set arguments.
		$this->assoc_args = $assoc_args;

		// Parse the Build file and Build sure it's valid.
		$this->parse();
	}

	private function parse() {
		// Full Build file path.
		$file_path = ( Utils::is_absolute_path( $this->filename ) ) ? $this->filename : realpath( '.' ) . '/' . $this->filename;
		// Set specified path with --path argument if no --file argument is set
		if ( ! empty( WP_CLI::get_runner()->config['path'] ) && empty( $this->assoc_args['file'] ) ) {
			$file_path = WP_CLI::get_runner()->config['path'] . '/' . $this->filename;
		}
		// Check if the file exists.
		if ( ! file_exists( $file_path ) ) {
			return NULL;
		}
		// Check if the Build file is a valid yaml file.
		if ( $this->format == 'yml' ) {
			try {
				$this->build = Yaml::parse( file_get_contents( $file_path ) );
			} catch ( \Exception $e ) {
				WP_CLI::error( 'Error parsing YAML from Build file (' . $this->filename . ').' );

				return FALSE;
			}

			return TRUE;
		}

		// Build.json
		try {
			$this->build = json_decode( file_get_contents( $file_path ), TRUE );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'Error parsing JSON from Build file (' . $this->filename . ').' );

			return FALSE;
		}

		return TRUE;
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

		return [];
	}

	public function get_core_version() {
		if ( ! empty( $this->build['core']['download']['version'] ) ) {
			return $this->build['core']['download']['version'];
		}

		return NULL;
	}

	public function get_plugin_version( $slug ) {
		if ( ! empty( $this->build['plugins'][ $slug ]['version'] ) ) {
			return $this->build['plugins'][ $slug ]['version'];
		}

		return NULL;
	}

	public function get_theme_version( $slug ) {
		if ( ! empty( $this->build['themes'][ $slug ]['version'] ) ) {
			return $this->build['themes'][ $slug ]['version'];
		}

		return NULL;
	}
}
