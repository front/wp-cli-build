<?php namespace WP_CLI_Build\Processor;

use WP_CLI;
use WP_CLI_Build\Build_Parser;
use WP_CLI_Build\Helper\Utils;
use WP_CLI_Build\Helper\WP_API;

class Core {

	private $build;
	private $assoc_args;

	public function __construct( $assoc_args = NULL ) {
		// Build file.
		$this->build = new Build_Parser( Utils::get_build_filename( $assoc_args ), $assoc_args );
		// Set command arguments.
		$this->assoc_args = $assoc_args;
	}

	public function process() {
		// WP installation status.
		$installed = Utils::wp_installed();
		// Check if we have core info in build.yml.
		if ( ! empty( $this->build->get( 'core' ) ) ) {
			// If WordPress is not installed...
			if ( ! $installed ) {
				// Download WordPress.
				$download = $this->download_wordpress();
				// Configure WordPress.
				$config = $this->config_wordpress();
				// Install WordPress.
				$install = $this->install_wordpress();
			} else {
				// Update WordPress.
				$update = $this->update_wordpress();
			}
		}

		// Return false if nothing was done.
		if ( empty( $update ) && empty( $download ) && empty( $config ) && empty( $install ) ) {
			return FALSE;
		}

		return TRUE;
	}

	// Update WordPress if build.yml version is higher than currently installed.
	private function update_wordpress() {
		// Config
		$config             = $this->build->get( 'core', 'download' );
		$installed_version  = Utils::wp_version();
		$config_version     = empty( $config['version'] ) ? NULL : $config['version'];
		$version_to_install = WP_API::core_version_check( $config_version );
		// Compare installed version with the one in build.yml.
		if ( version_compare( $installed_version, $version_to_install ) === - 1 ) {
			// Change config version.
			$config['version'] = $version_to_install;
			// Status.
			Utils::line( "- Updating WordPress (%W{$installed_version}%n => %Y{$version_to_install}%n)" );
			// Update WordPress.
			$result = Utils::launch_self( 'core', [ 'update' ], $config, FALSE, TRUE, [], FALSE, FALSE );

			// Print result.
			return Utils::result( $result );
		}

		return NULL;
	}

	// Download WordPress if not downloaded or if force setting is defined.
	private function download_wordpress() {
		// Version check.
		$version_check = Utils::wp_version();
		// Config
		$config = $this->build->get( 'core', 'download' );
		// If version is false or force is true, download WordPress.
		if ( ( ( $version_check === FALSE ) || ( ( ! empty( $config['force'] ) ) && ( $config['force'] === TRUE ) ) ) ) {
			if ( ! empty( $config['version'] ) ) {
				// WP Version.
				$download_args['version'] = WP_API::core_version_check( $config['version'] );
				// Locale.
				if ( ! empty( $config['locale'] ) ) {
					$download_args['locale'] = $config['locale'];
				}
				// Download WP without the default themes and plugins
				$download_args['skip-content'] = isset( $config['skip-content'] ) ? $config['skip-content'] : TRUE;
				// Whether to exit on error or not
				$exit_on_error = isset( $config['exit-on-error'] ) ? $config['exit-on-error'] : FALSE;
				// Force download.
				if ( ( ! empty( $config['force'] ) ) && ( $config['force'] === TRUE ) ) {
					$download_args['force'] = TRUE;
				}
				// Download WordPress.
				$extra = empty( $config['locale'] ) ? "%G{$config['version']}%n (%Yen_US%n)" : "%G{$download_args['version']}%n (%Y{$config['locale']}%n)";
				Utils::line( "- Downloading WordPress $extra" );
				$result = Utils::launch_self( 'core', [ 'download' ], $download_args, FALSE, TRUE, [], FALSE, FALSE );

				// Print result.
				return Utils::result( $result );

			}
		}

		return NULL;
	}

	// Configure WordPress if 'wp-config.php' is not found.
	private function config_wordpress() {
		// Check if wp-config.php exists.
		if ( ( ! Utils::wp_config_exists() ) || ( ! empty( $this->assoc_args['force'] ) ) ) {
			// Version check.
			$version_check = Utils::wp_version();
			// Config
			$config = $this->build->get( 'core', 'config' );
			// Set config from assoc args (override).
			if ( ! empty( $this->assoc_args ) ) {
				// Set database details.
				if ( ! empty( $this->assoc_args['dbname'] ) ) {
					$config['dbname'] = $this->assoc_args['dbname'];
				}
				if ( ! empty( $this->assoc_args['dbuser'] ) ) {
					$config['dbuser'] = $this->assoc_args['dbuser'];
				}
				if ( ! empty( $this->assoc_args['dbpass'] ) ) {
					$config['dbpass'] = $this->assoc_args['dbpass'];
				}
			}
			// Only proceed with configuration if we have the database name, user and password.
			if ( ( $version_check !== FALSE ) && ( ! empty( $config['dbname'] ) ) && ( ! empty( $config['dbuser'] ) ) && ( ! empty( $config['dbpass'] ) ) ) {
				// Status.
				Utils::line( "- Generating '%Gwp-config.php%n'" );
				// Override more config parameters from command line.
				if ( ! empty( $this->assoc_args['dbhost'] ) ) {
					$config['dbhost'] = $this->assoc_args['dbhost'];
				}
				if ( ! empty( $this->assoc_args['dbprefix'] ) ) {
					$config['dbprefix'] = $this->assoc_args['dbprefix'];
				}
				if ( ! empty( $this->assoc_args['dbcharset'] ) ) {
					$config['dbcharset'] = $this->assoc_args['dbcharset'];
				}
				if ( ! empty( $this->assoc_args['dbcollate'] ) ) {
					$config['dbcollate'] = $this->assoc_args['dbcollate'];
				}
				if ( ! empty( $this->assoc_args['locale'] ) ) {
					$config['locale'] = $this->assoc_args['locale'];
				}
				if ( ! empty( $this->assoc_args['skip-salts'] ) ) {
					$config['skip-salts'] = $this->assoc_args['skip-salts'];
				}
				if ( ! empty( $this->assoc_args['skip-check'] ) ) {
					$config['skip-check'] = $this->assoc_args['skip-check'];
				}
				if ( ! empty( $this->assoc_args['force'] ) ) {
					$config['force'] = $this->assoc_args['force'];
				}
				// Set global parameter: path.
				if ( ! empty( WP_CLI::get_runner()->config['path'] ) ) {
					$config['path'] = WP_CLI::get_runner()->config['path'];
				}
				// Config WordPress.
				$result = Utils::launch_self( 'config', [ 'create' ], $config, FALSE, TRUE, [], FALSE, FALSE );

				// Print result.
				return Utils::result( $result );
			}
		}

		return NULL;
	}

	private function install_wordpress() {
		// Check if wp-config.php exists.
		if ( Utils::wp_config_exists() ) {
			// Config
			$config = $this->build->get( 'core', 'install' );
			// If version exists, blog is not installed and we have install section defined, try to install.
			if ( ( ! Utils::wp_installed() ) && ( ! empty( $config ) ) ) {

				// Status.
				Utils::line( "- Installing WordPress..." );

				$install_args = [];
				if ( ! empty( $config['url'] ) ) {
					$install_args['url'] = $config['url'];
				}
				if ( ! empty( $config['title'] ) ) {
					$install_args['title'] = $config['title'];
				}
				if ( ! empty( $config['admin-user'] ) ) {
					$install_args['admin_user'] = $config['admin-user'];
				}
				if ( ! empty( $config['admin-email'] ) ) {
					$install_args['admin_email'] = $config['admin-email'];
				}
				if ( ! empty( $config['skip-email'] ) ) {
					$install_args['skip-email'] = TRUE;
				}

				// Check if admin password is set, if not, ask for it.
				if ( empty( $config['admin-pass'] ) ) {
					$config['admin-pass'] = NULL;
					do {
						$config['admin-pass'] = Utils::prompt( WP_CLI::colorize( "    Enter the admin %Rpassword%n for the user %G{$config['admin-user']}%n: " ) );
					} while ( $config['admin-pass'] == NULL );
				}
				$install_args['admin_password'] = $config['admin-pass'];

				// Install WordPress.
				$result = Utils::launch_self( 'core', [ 'install' ], $install_args, FALSE, TRUE, [], TRUE );

				// Print result.
				return Utils::result( $result );

			}
		}

		return NULL;
	}

}
