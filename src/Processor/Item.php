<?php namespace WP_CLI_Build\Processor;

use Symfony\Component\Filesystem\Filesystem;
use WP_CLI_Build\Build_Parser;
use WP_CLI_Build\Helper\Utils;
use WP_CLI_Build\Helper\WP_API;

class Item {

	private Build_Parser $build;

	public function __construct( $assoc_args = null ) {
		// Build file.
		$this->build      = new Build_Parser( Utils::get_build_filename( $assoc_args ) );
		$this->filesystem = new Filesystem();
		$this->clean      = !empty($assoc_args['clean']);
	}

	// Starts processing items.
	public function run( $item_type = null ): bool
    {
		$result = FALSE;
		if ( ( $item_type == 'plugin' ) || ( $item_type == 'theme' ) ) {
			if ( ! empty( $this->build ) ) {
				$items = $this->build->get( $item_type . 's' );
				if ( ! empty( $items ) ) {
					$defaults = $this->build->get( 'defaults', $item_type . 's' );
					$result   = $this->process( $item_type, $items, $defaults );
				}
			}
		}

		return $result;
	}

	// Process item (plugin or theme).
	private function process( $type = null, $items = [], $defaults = [] ): bool
    {
		$result = FALSE;
		if ( ( $type == 'theme' || $type == 'plugin' ) && ( ! empty( $items ) ) ) {
			// Check if WP is installed.
			$wp_installed = Utils::wp_installed();
			$status       = FALSE;
			foreach ( $items as $item => $item_info ) {
				// Sets item version.
				$item_info['version'] = $this->set_item_version( $type, $item, $item_info['version'] );
				// Download, install or activate the item depending on WordPress installation status.
				if ( ( $wp_installed ) && ( ! $this->clean ) ) {
					// Install if the plugin doesn't exist.
					$item_status = $this->status( $type, $item );
					if ( $item_status === FALSE ) {
						$status = $this->install( $type, $item, $item_info, $defaults );
					} // If the plugin is inactive, activate it.
					elseif ( $item_status === 'inactive' ) {
						$status = $this->activate( $type, $item, $item_info );
					} // Update if the version differs.
					elseif ( $item_status === 'active' ) {
						// Get item info.
						if ( ! empty( $item_info['version'] ) ) {
							// Check if we need an update
							if ( $item_info['version'] != $this->version( $type, $item ) ) {
								$status = $this->update( $type, $item, $item_info );
							}
						}
					}
				} else {
					// If we're in clean mode, delete item folder.
					if ( $this->clean ) {
						$this->delete_item_folder( $type, $item );
					}
					$status = $this->download( $type, $item, $item_info );
				}

				// Change result to TRUE, if something was downloaded, updated, installed or activated.
				if ( $status ) {
					$result = TRUE;
				}

			}
		}

		return $result;
	}

	// Download an item.
	private function download( $type = null, $item = null, $item_info = null ): bool
    {
		if ( ( $type == 'theme' || $type == 'plugin' ) && ( ! empty( $item ) ) && ( ! empty( $item_info ) ) ) {
			// Check if the item folder already exists or not.
			// If the folder exists and the version is the same as the build file, skip it.
			$folder = Utils::wp_path( 'wp-content/' . $type . 's/' . $item );
			$exists = $this->filesystem->exists( $folder );
			// If the folder doesn't exist, download the plugin.
			if ( ! $exists ) {
				Utils::line( "- Downloading %G$item%n (%Y{$item_info['version']}%n)" );
				$download_status = Utils::item_download( $type, $item, (string) $item_info['version'] );
				if ( $download_status === TRUE ) {
					Utils::line( ": done%n\n" );
				} else {
					Utils::line( ": %R{$download_status}%n\n" );
				}

				return TRUE;
			}
		}

		return FALSE;
	}

	// Install and activate an item.
	private function install( $type = null, $item = null, $item_info = null, $defaults = [] ): bool
    {
		// Processing text.
		$process = "- Installing %G$item%n";

		// Item install point.
		$install_point = empty( $item['url'] ) ? $item : $item['url'];

		// Item install arguments.
		$install_args = [];

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

		// Whether to force installation if the item is already installed.
		if ( ( ! empty( $item_info['force'] ) ) && ( $item_info['force'] ) ) {
			$install_args['force'] = TRUE;
		}

		// Active it on network after installing.
		if ( ( ! empty( $item_info['activate-network'] ) ) && ( $item_info['activate-network'] ) ) {
			$install_args['activate-network'] = TRUE;
		}

		// Processing message.
		Utils::line( $process );

		// Install item.
		$result = Utils::launch_self( $type, [ 'install', $install_point ], $install_args, FALSE, TRUE, [], FALSE, FALSE );

		// Silently activate item.
		if ( ! $this->is_active( $type, $item ) ) {
			Utils::launch_self( $type, [ 'activate', $item ], [], FALSE, TRUE, [], FALSE, FALSE );
		}

		// Print result.
		return Utils::result( $result );
	}

	// Activate an item.
	private function activate( $type = null, $item = null, $item_info = null ): bool
    {
		// Processing text.
		$process = "- Activating %G$item%n (%Y{$item_info['version']}%n)";

		// Processing message.
		Utils::line( $process );

		// Install item.
		$result = Utils::launch_self( $type, [ 'activate', $item ], [], FALSE, TRUE, [], FALSE, FALSE );

		// Print result.
		return Utils::result( $result );
	}

	// Activate an item.
	private function update( $type = null, $item = null, $item_info = null ): bool
    {

		// Current version.
		$old_version = $this->version( $type, $item );

		// Update/Downgrade.
		$action_label = ( version_compare( $old_version, $item_info['version'] ) === - 1 ) ? 'Updating' : 'Downgrading';

		// Processing text.
		$process = "- {$action_label} %G$item%n (%W{$old_version}%n => %Y{$item_info['version']}%n)";

		// Processing message.
		Utils::line( $process );

		// Install item.
		$result = Utils::launch_self( $type, [ 'update', $item ], [ 'version' => $item_info['version'] ], FALSE, TRUE, [], FALSE, FALSE );

		// Print result.
		return Utils::result( $result );
	}

	private function version( $type = null, $name = null ): bool|string
    {
		if ( ( $type == 'theme' || $type == 'plugin' ) && ( ! empty( $name ) ) ) {
			$result = Utils::launch_self( $type, [ 'get', $name ], [ 'field' => 'version' ], FALSE, TRUE, [], FALSE, FALSE );
			if ( ! empty( $result->stdout ) ) {
				return trim( $result->stdout );
			}
		}

		return FALSE;
	}

	private function status( $type = null, $name = null ): bool|string
    {
		if ( ( $type == 'theme' || $type == 'plugin' ) && ( ! empty( $name ) ) ) {
			$result = Utils::launch_self( $type, [ 'status', $name ], [], FALSE, TRUE, [], FALSE, FALSE );
			$result = trim( strtolower( $result->stdout ) );
			if ( strpos( $result, 'status: active' ) ) {
				return 'active';
			}
			if ( strpos( $result, 'status: inactive' ) ) {
				return 'inactive';
			}
		}

		return FALSE;
	}

	private function is_active( $type = null, $name = null ): bool
    {
		if ( ( $type == 'theme' || $type == 'plugin' ) && ( ! empty( $name ) ) ) {
			$result = Utils::launch_self( $type, [ 'is-active', $name ], [], FALSE, TRUE, [], FALSE, FALSE );
			if ( empty( $result->return_code ) ) {
				return TRUE;
			}
		}

		return FALSE;
	}

	private function get_item_info( $type = null, $slug = null, $version = '*', $field = null ) {
		if ( ( $type == 'theme' || $type == 'plugin' ) && ( ! empty( $slug ) ) ) {
			$info_fn = $type . '_info';
			$info    = WP_API::$info_fn( $slug, $version, FALSE );
			if ( ! empty( $info->{$field} ) ) {
				return $info->{$field};
			}

			return $info;
		}

		return null;
	}

	private function set_item_version( $type = null, $slug = null, $item_version = '*' ) {
		if ( ( $type == 'theme' || $type == 'plugin' ) && ( ! empty( $slug ) ) ) {
			$item_info = $this->get_item_info( $type, $slug, $item_version );
			if ( ! empty( $item_info->version ) ) {
				return $item_info->version;
			}
		}

		return ( $item_version === '*' ) ? 'latest' : $item_version;
	}

	private function delete_item_folder( $type = null, $item = null ): void
    {
		if ( ( ! empty( $type ) ) && ( ! empty( $item ) ) ) {
			$folder = Utils::wp_path( 'wp-content/' . $type . 's/' . $item );

            $this->filesystem->remove($folder);
            return;
		}

    }

}