<?php namespace WP_CLI_Build\Processor;

use WP_CLI;
use WP_CLI_Build\Helper\Build_File;
use WP_CLI_Build\Helper\Utils;

class Core {

	private $build;

	public function __construct( $assoc_args = NULL ) {
		// Build file.
		$build_filename = empty( $assoc_args['file'] ) ? 'build.yml' : $assoc_args['file'];
		$this->build    = new Build_File( $build_filename );
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
		$config            = $this->build->get( 'core', 'download' );
		$installed_version = Utils::wp_version();
		$config_version    = empty( $config['version'] ) ? NULL : $config['version'];

		// Compare installed version with the one in build.yml.
		if ( version_compare( $installed_version, $config_version ) === - 1 ) {

			// Status.
			Utils::line( "- Updating WordPress (%W{$installed_version}%n => %Y{$config_version}%n)" );

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
				$download_args['version'] = $config['version'];
				// Locale.
				if ( ! empty( $config['locale'] ) ) {
					$download_args['locale'] = $config['locale'];
				}
				// Force download.
				if ( ( ! empty( $config['force'] ) ) && ( $config['force'] === TRUE ) ) {
					$download_args['force'] = TRUE;
				}
				// Download WordPress.
				$extra = empty( $config['locale'] ) ? "%G{$config['version']}%n (%Yen_US%n)" : "%G{$config['version']}%n (%Y{$config['locale']}%n)";
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
		if ( ! Utils::wp_config_exists() ) {
			// Version check.
			$version_check = Utils::wp_version();
			// Config
			$config = $this->build->get( 'core', 'config' );
			if ( ( $version_check !== FALSE ) && ( ! empty( $config ) ) ) {

				// Only proceed with configuration if we have at least db-name and db-user.
				if ( ! empty( $config['dbname'] ) ) {

					// Status.
					Utils::line( '- Configuring WordPress...' );

					// Database name.
					$config_args['dbname'] = $config['dbname'];

					// Check if database username is set, if not, ask for it.
					if ( empty( $config['dbuser'] ) ) {
						$config['dbuser'] = NULL;
						do {
							$config['dbuser'] = Utils::prompt( WP_CLI::colorize( "    Enter the %Gusername%n for the database %Y{$config['dbname']}%n: " ) );
						} while ( $config['dbuser'] == NULL );
					}
					$config_args['dbuser'] = $config['dbuser'];

					// Check if database password is set, if not, ask for it.
					if ( empty( $config['dbpass'] ) ) {
						$config['dbpass'] = NULL;
						do {
							$config['dbpass'] = Utils::prompt( WP_CLI::colorize( "    Enter the database %Rpassword%n for the username %G{$config['dbuser']}%n: " ) );
						} while ( $config['dbpass'] == NULL );
					}
					$config_args['dbpass'] = $config['dbpass'];

					// Database host.
					if ( ! empty( $config['dbhost'] ) ) {
						$config_args['dbhost'] = $config['dbhost'];
					}

					// Database prefix.
					if ( ! empty( $config['dbprefix'] ) ) {
						$config_args['dbprefix'] = $config['dbprefix'];
					}

					// Database charset.
					if ( ! empty( $config['dbcharset'] ) ) {
						$config_args['dbcharset'] = $config['dbcharset'];
					}

					// Database collate.
					if ( ! empty( $config['dbcollate'] ) ) {
						$config_args['dbcollate'] = $config['dbcollate'];
					}

					// Database locale.
					if ( ! empty( $config['locale'] ) ) {
						$config_args['locale'] = $config['locale'];
					}

					// Config WordPress.
					$result = Utils::launch_self( 'core', [ 'config' ], $config_args, FALSE, TRUE, [], TRUE );

					// Print result.
					return Utils::result( $result );

				}
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