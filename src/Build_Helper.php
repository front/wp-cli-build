<?php namespace WP_CLI_Build;

use WP_CLI;

class Build_Helper {

	public static function wp_config_exists() {
		if ( file_exists( realpath( '.' ) . '/wp-config.php' ) ) {
			return TRUE;
		}

		return FALSE;
	}

	public static function check_wp_version() {
		$result = self::launch_self( 'core', [ 'version' ], [ ], FALSE, TRUE, [ ], FALSE, FALSE );
		if ( ! empty( $result->stdout ) ) {
			return trim( $result->stdout );
		}

		return FALSE;
	}

	public static function is_installed() {
		$result = self::launch_self( 'core', [ 'is-installed' ], [ ], FALSE, TRUE, [ ], FALSE, FALSE );
		if ( ! empty( $result->return_code ) ) {
			return FALSE;
		}

		return TRUE;
	}

	public static function line( $text, $pseudo_tab = TRUE ) {
		$spaces = ( $pseudo_tab ) ? '  ' : NULL;
		WP_CLI::line( $spaces . WP_CLI::colorize( $text ) );
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
		$parts = [ ];
		if ( ! preg_match( $regExp, $path, $parts ) ) {
			$mess = sprintf( 'Path is NOT valid, was given %s', $path );
			throw new \DomainException( $mess );
		}
		if ( '' !== $parts['root'] ) {
			return TRUE;
		}

		return FALSE;
	}

	public static function launch_self( $command, $args = [ ], $assoc_args = [ ], $exit_on_error = TRUE, $return_detailed = FALSE, $runtime_args = [ ], $exit_on_error_print = FALSE, $print = TRUE ) {

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

}