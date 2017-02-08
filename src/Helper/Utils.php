<?php namespace WP_CLI_Build\Helper;

use Alchemy\Zippy\Exception\InvalidArgumentException;
use Alchemy\Zippy\Exception\RuntimeException;
use Alchemy\Zippy\Zippy;
use Requests;
use Symfony\Component\Filesystem\Filesystem;
use WP_CLI;

class Utils {

	public static function wp_config_exists() {
		if ( ( file_exists( realpath( '.' ) . '/wp-config.php' ) ) || ( file_exists( ABSPATH . '/wp-config.php' ) ) ) {
			return TRUE;
		}

		return FALSE;
	}

	// Return WP version.
	public static function wp_version() {
		$result = self::launch_self( 'core', [ 'version' ], [], FALSE, TRUE, [], FALSE, FALSE );
		if ( ! empty( $result->stdout ) ) {
			return trim( $result->stdout );
		}

		return FALSE;
	}

	// Check if WP is installed.
	public static function wp_installed() {
		$result = self::launch_self( 'core', [ 'is-installed' ], [], FALSE, TRUE, [], FALSE, FALSE );
		if ( ! empty( $result->return_code ) ) {
			return FALSE;
		}

		return TRUE;
	}

	public static function wp_path( $path = NULL ) {
		$wp_path = ABSPATH;
		if ( ( ! self::is_absolute_path( ABSPATH ) ) || ( $wp_path == '/' ) ) {
			$wp_path = getcwd() . '/' . $path;
		}

		return $wp_path;
	}

	public static function line( $text, $pseudo_tab = FALSE ) {
		$spaces = ( $pseudo_tab ) ? '  ' : NULL;
		echo $spaces . WP_CLI::colorize( $text );
	}

	public static function prompt( $question ) {
		if ( function_exists( 'readline' ) ) {
			return readline( $question );
		} else {
			echo $question;

			return stream_get_line( STDIN, 1024, PHP_EOL );
		}
	}

	public static function is_absolute_path( $path ) {
		if ( ! is_string( $path ) ) {
			$mess = sprintf( 'String expected but was given %s', gettype( $path ) );
			throw new \InvalidArgumentException( $mess );
		}
		if ( ! ctype_print( $path ) ) {
			$mess = 'Path can NOT have non-printable characters or be empty';
			throw new \DomainException( $mess );
		}
		// Optional wrapper(s).
		$regExp = '%^(?<wrappers>(?:[[:print:]]{2,}://)*)';
		// Optional root prefix.
		$regExp .= '(?<root>(?:[[:alpha:]]:/|/)?)';
		// Actual path.
		$regExp .= '(?<path>(?:[[:print:]]*))$%';
		$parts = [];
		if ( ! preg_match( $regExp, $path, $parts ) ) {
			$mess = sprintf( 'Path is NOT valid, was given %s', $path );
			throw new \DomainException( $mess );
		}
		if ( '' !== $parts['root'] ) {
			return TRUE;
		}

		return FALSE;
	}

	public static function launch_self( $command, $args = [], $assoc_args = [], $exit_on_error = TRUE, $return_detailed = FALSE, $runtime_args = [], $exit_on_error_print = FALSE, $print = TRUE ) {

		// Run command.
		$result = WP_CLI::launch_self( $command, $args, $assoc_args, $exit_on_error, $return_detailed, $runtime_args );

		// Standard output.
		if ( ! empty( $result->stdout ) && ( empty( $result->stderr ) ) && ( $print ) ) {
			$stdout = str_replace( [ 'Success:', 'Error:', 'Warning:' ], [ '%GSuccess:%n', '%RError:%n', '%YWarning:%n' ], $result->stdout );
			WP_CLI::line( '    ' . WP_CLI::colorize( $stdout ) );
		}

		// Output error.
		if ( ( ! empty( $result->stderr ) ) && ( $print ) ) {
			$stderr = str_replace( [ 'Error:', 'Warning:' ], [ '%RError:%n', '%YWarning:%n' ], $result->stderr );
			WP_CLI::line( '    ' . WP_CLI::colorize( $stderr ) );
			if ( $exit_on_error_print ) {
				exit( 1 );
			}
		}

		return $result;
	}

	public static function item_download( $type = NULL, $slug = NULL, $version = NULL ) {
		if ( ( ! empty( $slug ) ) && ( $type == 'plugin' || $type == 'theme' ) && ( ! empty( $version ) ) ) {
			$info_fn = $type . '_info';
			$info    = WP_API::$info_fn( $slug, $version );
			if ( ! empty( $info->download_link ) ) {
				$filename = basename( $info->download_link );
				if ( ! empty( $filename ) ) {
					$download = Utils::download_url( $info->download_link );
					if ( ! empty( $download ) ) {
						$file_path = Utils::wp_path() . 'wp-content/' . $filename;
						if ( file_exists( $file_path ) ) {
							return self::unzip( $file_path, Utils::wp_path( 'wp-content/' . $type . 's/' ) );
						}
					}
				}
			}

		}

		return NULL;
	}

	public static function download_url( $url = NULL ) {

		// If we have an URL proceed.
		if ( ! empty( $url ) ) {
			$filename = basename( $url );
			if ( ! empty( $filename ) ) {
				$save_dir   = self::wp_path() . 'wp-content/';
				$create_dir = self::mkdir( $save_dir );
				if ( $create_dir ) {
					$save_path = $save_dir . $filename;
					$download  = Requests::get( $url, [], [ 'filename' => $save_path, 'verify' => FALSE, 'timeout' => 20 ] );
					if ( $download->status_code == 200 ) {
						return TRUE;
					}
				}
			}
		}

		// Delete the file if we don't get a 200 code.
		if ( ( ! empty( $save_path ) ) && ( file_exists( $save_path ) ) ) {
			@unlink( $save_path );
		}

		return FALSE;
	}

	public static function unzip( $file, $to, $delete = TRUE ) {
		if ( ( ! empty( $file ) ) && ( ! empty( $to ) ) ) {

			// Create the directory to extract to.
			$create_dir = self::mkdir( $to );
			if ( $create_dir ) {
				$zippy   = Zippy::load();
				$archive = $zippy->open( $file );
				try {
					$archive->extract( $to );
				} catch ( InvalidArgumentException $e ) {
					return FALSE;
				} catch ( RuntimeException $e ) {
					return FALSE;
				}

				if ( $delete ) {
					@unlink( $file );
				}

				return TRUE;
			}

		}

		return FALSE;
	}

	public static function mkdir( $dir = NULL ) {
		if ( ! empty( $dir ) ) {
			if ( ! file_exists( $dir ) ) {
				$fs = new Filesystem();
				try {
					$fs->mkdir( $dir, 0755 );
				} catch ( IOExceptionInterface $e ) {
					return FALSE;
				}

				return file_exists( $dir );
			} else {
				return TRUE;
			}
		}

		return FALSE;
	}

}