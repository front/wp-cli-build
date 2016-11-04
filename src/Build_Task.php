<?php
namespace WP_CLI_Build;

use WP_CLI;

class Build_Task {

	public static function process_core( $build = NULL ) {

		// Download WordPress.
		$download = Build_Task::download_wordpress( $build );

		// Configure WordPress.
		$config = Build_Task::config_wordpress( $build );

		// Install WordPress.
		$install = Build_Task::install_wordpress( $build );

		if ( empty( $download ) && empty( $config ) && empty( $install ) ) {
			WP_CLI::line( 'Nothing to do.' );
			WP_CLI::line();
		}

	}

	public static function install_plugins( $build = NULL ) {
		if ( ( ! empty( $build ) ) && ( Build_Helper::is_installed() ) ) {
			$plugins = $build->get( 'plugins' );
			if ( empty( $plugins ) ) {
				WP_CLI::line( 'Nothing to do.' );
				WP_CLI::line();

				return FALSE;
			}

			$defaults = $build->get( 'defaults', 'plugins' );

			self::install( 'plugin', $plugins, $defaults );
			WP_CLI::line();

		}
	}

	public static function install_themes( $build = NULL ) {
		if ( ( ! empty( $build ) ) && ( Build_Helper::is_installed() ) ) {
			$themes = $build->get( 'themes' );
			if ( empty( $themes ) ) {
				WP_CLI::line( 'Nothing to do.' );
				WP_CLI::line();

				return FALSE;
			}

			$defaults = $build->get( 'defaults', 'themes' );

			self::install( 'theme', $themes, $defaults );
			WP_CLI::line();
		}
	}

	// Install plugins or themes.
	private static function install( $type = NULL, $items = [ ], $defaults = [ ] ) {
		if ( ( $type == 'theme' || $type == 'plugin' ) && ( ! empty( $items ) ) ) {
			foreach ( $items as $item => $item_info ) {

				// Processing text.
				$process = "- Installing %G$item%n";

				// Item install point.
				$install_point = empty( $item['url'] ) ? $item : $item['url'];

				// Item install arguments.
				$install_args = [ ];

				// Defaults merge.
				$defaults_code = [ 'version' => 'latest', 'force' => FALSE, 'activate' => FALSE, 'activate-network' => FALSE, 'gitignore' => FALSE ];
				$defaults      = array_merge( $defaults_code, $defaults );

				// Merge item info with the defaults (item info will override defaults).
				$item_info = array_merge( $defaults, $item_info );

				// Item version.
				$process .= " (%Y{$item_info['version']}%n)";
				if ( ( ! empty( $item_info['version'] ) ) && ( $item_info['version'] != 'latest' ) ) {
					$install_args['version'] = $item_info['version'];
				}

				// Wether to force installation if the item is already installed.
				if ( ( ! empty( $item_info['force'] ) ) && ( $item_info['force'] ) ) {
					$install_args['force'] = TRUE;
				}

				// Activate it after installing.
				if ( ( ! empty( $item_info['activate'] ) ) && ( $item_info['activate'] ) ) {
					$install_args['activate'] = TRUE;
				}

				// Active it on network after installing.
				if ( ( ! empty( $item_info['activate-network'] ) ) && ( $item_info['activate-network'] ) ) {
					$install_args['activate-network'] = TRUE;
				}

				// Processing message.
				WP_CLI::line();
				Build_Helper::line( $process );

				// Install item.
				$result = Build_Helper::launch_self( $type, [ 'install', $install_point ], $install_args, FALSE, TRUE, [ ], FALSE, FALSE );

				// Success.
				if ( ! empty( $result->stdout ) && ( empty( $result->stderr ) ) ) {
					Build_Helper::line( '  ' . ucfirst( $type ) . ' installed successfully.' );
				}

				// Output error.
				if ( ! empty( $result->stderr ) ) {
					$stderr = str_replace( [ 'Error:', 'Warning:' ], [ '%RError:%n', '%YWarning:%n' ], $result->stderr );
					Build_Helper::line( '  ' . trim( $stderr ) );
				}
			}
		}

		return NULL;
	}

	// Download WordPress if not downloaded or if force setting is defined.
	private static function download_wordpress( $build = NULL ) {
		// Version check.
		$version_check = Build_Helper::check_wp_version();
		// Config
		$config = $build->get( 'core', 'download' );
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
				WP_CLI::line();
				Build_Helper::line( "- Downloading WordPress $extra..." );
				$result = Build_Helper::launch_self( 'core', [ 'download' ], $download_args, TRUE, TRUE, [ ], FALSE, FALSE );

				// Success message.
				if ( ( ! empty( $result->stdout ) ) && ( strpos( $result->stdout, 'Success' ) !== FALSE ) ) {
					Build_Helper::line( '  %GSuccess:%n WordPress downloaded.' );

					return TRUE;
				}
			}
		}

		return NULL;
	}

	// Configure WordPress if 'wp-config.php' is not found.
	private static function config_wordpress( $build = NULL ) {
		// Check if wp-config.php exists.
		if ( ! Build_Helper::wp_config_exists() ) {
			// Version check.
			$version_check = Build_Helper::check_wp_version();
			// Config
			$config = $build->get( 'core', 'config' );
			if ( ( $version_check !== FALSE ) && ( ! empty( $config ) ) ) {

				// Only proceed with configuration if we have at least db-name and db-user.
				if ( ! empty( $config['dbname'] ) ) {

					// Status.
					WP_CLI::line();
					Build_Helper::line( '- Configuring WordPress...' );

					// Database name.
					$config_args['dbname'] = $config['dbname'];

					// Check if database username is set, if not, ask for it.
					if ( empty( $config['dbuser'] ) ) {
						$config['dbuser'] = NULL;
						do {
							$config['dbuser'] = Build_Helper::prompt( WP_CLI::colorize( "    Enter the %Gusername%n for the database %Y{$config['dbname']}%n: " ) );
						} while ( $config['dbuser'] == NULL );
					}
					$config_args['dbuser'] = $config['dbuser'];

					// Check if database password is set, if not, ask for it.
					if ( empty( $config['dbpass'] ) ) {
						$config['dbpass'] = NULL;
						do {
							$config['dbpass'] = Build_Helper::prompt( WP_CLI::colorize( "    Enter the database %Rpassword%n for the username %G{$config['dbuser']}%n: " ) );
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
					$result = Build_Helper::launch_self( 'core', [ 'config' ], $config_args, FALSE, TRUE, [ ], TRUE );
					if ( empty( $result->stderr ) ) {
						return TRUE;
					}

				}
			}
		}

		return NULL;
	}

	private static function install_wordpress( $build = NULL ) {
		// Check if wp-config.php exists.
		if ( Build_Helper::wp_config_exists() ) {
			// Config
			$config = $build->get( 'core', 'install' );
			// If version exists, blog is not installed and we have install section defined, try to install.
			if ( ( ! Build_Helper::is_installed() ) && ( ! empty( $config ) ) ) {

				// Status.
				Build_Helper::line( "- Installing WordPress..." );

				$install_args = [ ];
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
						$config['admin-pass'] = Build_Helper::prompt( WP_CLI::colorize( "    Enter the admin %Rpassword%n for the user %G{$config['admin-user']}%n: " ) );
					} while ( $config['admin-pass'] == NULL );
				}
				$install_args['admin_password'] = $config['admin-pass'];

				// Install WordPress.
				return Build_Helper::launch_self( 'core', [ 'install' ], $install_args, FALSE, TRUE, [ ], TRUE );
			}
		}

		return NULL;
	}

}