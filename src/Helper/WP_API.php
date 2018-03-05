<?php namespace WP_CLI_Build\Helper;

use Requests;

class WP_API {

	public static function core_version_check( $config_version = NULL ) {
		$response = Requests::get( 'http://api.wordpress.org/core/version-check/1.7/' );
		if ( ! empty( $response->body ) ) {
			$core = json_decode( $response->body );
			if ( ( ! empty( $config_version ) ) && ( ! empty( $core->offers[0]->current ) ) ) {
				$config_version = Utils::version_comply( $config_version, $core->offers[0]->current );
			}
		}

		return str_replace( [ '~', '^', '*' ], '', $config_version );
	}

	public static function plugin_info( $slug = NULL, $config_version = NULL ) {
		if ( ! empty( $slug ) ) {
			$response = Requests::post(
				'http://api.wordpress.org/plugins/info/1.0/' . $slug . '.json',
				[],
				[ 'action' => 'plugin_information' ]
			);
			if ( ! empty( $response->body ) ) {
				$plugin = json_decode( $response->body );
				// Determine the version to be used.
				if ( ! empty( $plugin->version ) ) {
					$resolved_version = $plugin->version;
					if ( ! empty( $plugin->versions ) ) {
						$resolved_version = Utils::determine_version( $config_version, $plugin->version, $plugin->versions );
					}
					$plugin = self::_get_item_download_link( $plugin, $resolved_version );

					return $plugin;
				}
			}

		}

		return NULL;
	}

	public static function theme_info( $slug = NULL, $config_version = NULL ) {
		if ( ! empty( $slug ) ) {
			$response = Requests::post(
				'http://api.wordpress.org/themes/info/1.1/',
				[],
				[ 'action' => 'theme_information', 'request' => [ 'slug' => $slug, 'fields' => [ 'versions' => TRUE ] ] ]
			);
			if ( ! empty( $response->body ) ) {
				$theme = json_decode( $response->body );
				// Determine the version to be used.
				if ( ! empty( $theme->version ) ) {
					$resolved_version = $theme->version;
					if ( ! empty( $theme->versions ) ) {
						$resolved_version = Utils::determine_version( $config_version, $theme->version, $theme->versions );
					}
					$theme = self::_get_item_download_link( $theme, $resolved_version );

					return $theme;
				}
			}

		}

		return NULL;
	}

	// Changes item download link with the specified version.
	private static function _get_item_download_link( $item, $version ) {
		if ( ! empty( $item->download_link ) ) {
			// WordPress.org forces https, but still sometimes returns http
			// See https://twitter.com/nacin/status/512362694205140992
			$item->download_link = str_replace( 'http://', 'https://', $item->download_link );

			list( $link ) = explode( $item->slug, $item->download_link );

			if ( $version == 'dev' ) {
				$item->download_link = $link . $item->slug . '.zip';
				$item->version       = 'Development Version';
			} else {
				// Build the download link
				$item->download_link = $link . $item->slug . '.' . $version . '.zip';
				$item->version       = $version;
			}
		}

		return $item;

	}

}