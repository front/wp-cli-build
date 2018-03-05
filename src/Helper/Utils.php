<?php namespace WP_CLI_Build\Helper;

use WP_CLI;
use Requests;
use Alchemy\Zippy\Exception\InvalidArgumentException;
use Alchemy\Zippy\Exception\RuntimeException;
use Alchemy\Zippy\Zippy;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use PharIo\Version\Version;
use PharIo\Version\VersionConstraintParser;

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
		if ( ! empty( $path ) ) {
			$wp_path = ( ( ! self::is_absolute_path( ABSPATH ) ) || ( $wp_path == '/' ) ) ? getcwd() . '/' . $path : $wp_path . '/' . $path;
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
		$parts  = [];
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
		// Work around to add 'cache dir' as env var, to avoid permission errors.
		$full_command = self::get_launch_self_workaround_command( $command, $args, $assoc_args, $runtime_args );
		// Run command.
		$result = WP_CLI::launch( $full_command, $exit_on_error, $return_detailed );
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

	private static function get_launch_self_workaround_command( $command = NULL, $args = [], $assoc_args = [], $runtime_args = [] ) {
		$reused_runtime_args = array(
			'path',
			'url',
			'user',
			'allow-root',
		);
		foreach ( $reused_runtime_args as $key ) {
			if ( isset( $runtime_args[ $key ] ) ) {
				$assoc_args[ $key ] = $runtime_args[ $key ];
			} elseif ( $value = WP_CLI::get_runner()->config[ $key ] ) {
				$assoc_args[ $key ] = $value;
			}
		}
		$php_bin     = escapeshellarg( \WP_CLI\Utils\get_php_binary() );
		$script_path = $GLOBALS['argv'][0];
		if ( getenv( 'WP_CLI_CONFIG_PATH' ) ) {
			$config_path = getenv( 'WP_CLI_CONFIG_PATH' );
		} else {
			$config_path = \WP_CLI\Utils\get_home_dir() . '/.wp-cli/config.yml';
		}
		$config_path = escapeshellarg( $config_path );
		$args        = implode( ' ', array_map( 'escapeshellarg', $args ) );
		$assoc_args  = \WP_CLI\Utils\assoc_args_to_str( $assoc_args );
		$cache_dir   = getenv( 'WP_CLI_CACHE_DIR' ) ? getenv( 'WP_CLI_CACHE_DIR' ) : escapeshellarg( \WP_CLI\Utils\get_home_dir() . '/.wp-cli/cache' );

		return "WP_CLI_CACHE_DIR={$cache_dir} WP_CLI_CONFIG_PATH={$config_path} {$php_bin} {$script_path} {$command} {$args} {$assoc_args}";
	}

	public static function item_download( $type = NULL, $slug = NULL, $version = NULL ) {
		if ( ( ! empty( $slug ) ) && ( $type == 'plugin' || $type == 'theme' ) && ( ! empty( $version ) ) ) {
			$info_fn = $type . '_info';
			$info    = WP_API::$info_fn( $slug, $version );
			if ( ! empty( $info->download_link ) ) {
				$filename = basename( $info->download_link );
				if ( ! empty( $filename ) ) {
					$download = Utils::download_url( $info->download_link );
					if ( $download === TRUE ) {
						$file_path = Utils::wp_path( 'wp-content/' . $filename );
						if ( file_exists( $file_path ) ) {
							return self::unzip( $file_path, Utils::wp_path( 'wp-content/' . $type . 's/' ) );
						}
					}

					return $download;
				}
			} else {
				return 'not available in wordpress.org';
			}

		}

		return NULL;
	}

	public static function download_url( $url = NULL ) {

		// If we have an URL proceed.
		if ( ! empty( $url ) ) {
			$filename = basename( $url );
			if ( ! empty( $filename ) ) {
				$save_dir   = self::wp_path( 'wp-content/' );
				$create_dir = self::mkdir( $save_dir );
				if ( $create_dir === TRUE ) {
					$save_path = $save_dir . $filename;
					$download  = Requests::get( $url, [], [ 'filename' => $save_path, 'verify' => FALSE, 'timeout' => 20 ] );
					if ( $download->status_code == 200 ) {
						return TRUE;
					}
				}

				return $create_dir;
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
				} catch ( IOException $e ) {
					return $e->getMessage();
				} catch ( IOExceptionInterface $e ) {
					return $e->getMessage();
				}

				return file_exists( $dir );
			} else {
				return TRUE;
			}
		}

		return FALSE;
	}

	// Print success or error message.
	public static function result( $result = NULL ) {
		if ( ! empty( $result ) ) {
			// Success.
			if ( ! empty( $result->stdout ) && ( empty( $result->stderr ) ) ) {
				self::line( ": done\n" );

				return TRUE;
			}

			// Output error.
			if ( ! empty( $result->stderr ) ) {
				$stderr = str_replace( [ 'Error:', 'Warning:' ], [ '', '' ], $result->stderr );
				self::line( ": %R" . trim( $stderr ) . "%n\n" );
			}
		}

		return FALSE;
	}

	public static function convert_to_numeric( $version = NULL ) {
		if ( ( ! empty( $version ) ) && ( is_numeric( $version ) ) ) {
			return strpos( $version, '.' ) === FALSE ? (int) $version : (float) $version;
		}

		return $version;
	}

	public static function get_build_filename( $assoc_args = NULL ) {
		// Legacy YAML support.
		if ( file_exists( self::wp_path( 'build.yml' ) ) ) {
			return 'build.yml';
		}

		// Format argument.
		if ( ! empty( $assoc_args['format'] ) ) {
			if ( $assoc_args['format'] == 'yml' ) {
				return empty( $assoc_args['file'] ) ? 'build.yml' : $assoc_args['file'];
			}
		}

		return empty( $assoc_args['file'] ) ? 'build.json' : $assoc_args['file'];
	}

	public static function strposa( $haystack, $needles = array(), $offset = 0 ) {
		$chr = array();
		foreach ( $needles as $needle ) {
			$res = strpos( $haystack, $needle, $offset );
			if ( $res !== FALSE ) {
				$chr[ $needle ] = $res;
			}
		}
		if ( empty( $chr ) ) {
			return FALSE;
		}

		return min( $chr );
	}

	public static function version_comply( $version, $latest_version ) {
		// Determine the item version if we have '^', '~' or '*'.
		if ( Utils::strposa( $version, [ '~', '^', '*' ] ) !== FALSE ) {
			// Return latest version if '*'.
			if ( strpos( $version, '*' ) ) {
				return $latest_version;
			}
			// Figure out version if '^' or '~' operators are used.
			$parser           = new VersionConstraintParser();
			$caret_constraint = $parser->parse( $version );
			try {
				$complies = $caret_constraint->complies( new Version( $latest_version ) );
				if ( $complies ) {
					return $latest_version;
				}
			} catch ( \Exception $e ) {
			}
		}

		return $version;
	}

	public static function determine_version( $item_version, $wporg_latest, $wporg_versions ) {
		// Return latest version if '*'.
		if ( $item_version == '*' ) {
			return $wporg_latest;
		}
		// Determine the item version if we have '^', '~' or '*'.
		if ( Utils::strposa( $item_version, [ '~', '^', '.*' ] ) !== FALSE ) {
			// Figure out the version if '^', '~' and '.*' are used.
			if ( ! empty( $wporg_versions ) ) {
				$parser                          = new VersionConstraintParser();
				$caret_constraint                = $parser->parse( $item_version );
				$wporg_versions->{$wporg_latest} = 'latest';
				foreach ( $wporg_versions as $version => $url ) {
					$complies = FALSE;
					try {
						if ( version_compare( $item_version, $version, '<' ) ) {
							$complies = $caret_constraint->complies( new Version( $version ) );
						}
					} catch ( \Exception $e ) {
					}
					if ( $complies ) {
						$item_version = $version;
					}
				}
				if ( Utils::strposa( $item_version, [ '~', '^', '.*' ] ) !== FALSE ) {
					return $wporg_latest;
				}

				return $item_version;
			}
		}

		return $item_version;
	}

}