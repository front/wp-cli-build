<?php namespace WP_CLI_Build;

use Symfony\Component\Yaml\Yaml;
use WP_CLI;
use WP_CLI_Build\Helper\Utils;

class Build_Parser {

	private mixed $filename = 'build.json';
	private string $format = 'json';
	private array $build = [];

	public function __construct( $filename, $assoc_args = null ) {
		// Set Build file.
		$this->filename = empty( $filename ) ? 'build.json' : $filename;
		// Set format.
		$this->format = (str_contains($this->filename, 'yml')) ? 'yml' : 'json';
		// Parse the Build file and Build sure it's valid.
		$this->parse();
	}

	private function parse(): void
    {
		// Full Build file path.
		$file_path = ( Utils::is_absolute_path( $this->filename ) ) ? $this->filename : realpath( '.' ) . '/' . $this->filename;
		// Set specified path with --path argument.
		if ( ! empty( WP_CLI::get_runner()->config['path'] ) ) {
			$file_path = WP_CLI::get_runner()->config['path'] . '/' . $this->filename;
		}
		// Check if the file exists.
		if ( ! file_exists( $file_path ) ) {
            return;
		}
		// Check if the Build file is a valid yaml file.
		if ( $this->format == 'yml' ) {
			try {
				$this->build = Yaml::parse( file_get_contents( $file_path ) );
			} catch ( \Exception $e ) {
				WP_CLI::error( 'Error parsing YAML from Build file (' . $this->filename . ').' );

                return;
			}

            return;
		}

		// Build.json
		try {
			$this->build = json_decode( file_get_contents( $file_path ), TRUE );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'Error parsing JSON from Build file (' . $this->filename . ').' );

            return;
		}

    }

	public function get( $key = null, $sub_key = null ) {

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

		return null;
	}

	public function get_plugin_version( $slug ) {
		if ( ! empty( $this->build['plugins'][ $slug ]['version'] ) ) {
			return $this->build['plugins'][ $slug ]['version'];
		}

		return null;
	}

	public function get_theme_version( $slug ) {
		if ( ! empty( $this->build['themes'][ $slug ]['version'] ) ) {
			return $this->build['themes'][ $slug ]['version'];
		}

		return null;
	}
}