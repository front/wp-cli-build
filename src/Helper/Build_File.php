<?php namespace WP_CLI_Build\Helper;

use Symfony\Component\Yaml\Yaml;
use WP_CLI;

class Build_File {

	private $filename = 'build.yml';
	private $build = [];

	public function __construct( $file ) {
		// Set Build file.
		$this->filename = empty( $file ) ? 'build.yml' : $file;
		// Parse the Build file and Build sure it's valid.
		$this->parse();
	}

	private function parse() {
		// Full Build file path.
		$file_path = ( Utils::is_absolute_path( $this->filename ) ) ? $this->filename : realpath( '.' ) . '/' . $this->filename;
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