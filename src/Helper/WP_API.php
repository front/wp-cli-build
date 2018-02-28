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

	public static function plugin_info( $slug = NULL, $version = NULL, $download_link = TRUE ) {
		if ( ! empty( $slug ) ) {
			$response = Requests::post(
				'http://api.wordpress.org/plugins/info/1.0/' . $slug . '.json',
				[],
				[ 'action' => 'plugin_information' ]
			);
			if ( ! empty( $response->body ) ) {
				$plugin = json_decode( $response->body );
				// Determine the version to be used.
				if ( ! empty( $plugin->versions ) ) {
					$version = Utils::determine_version( $version, $plugin->version, $plugin->versions );
				}
				if ( ( ( ! empty( $version ) ) && ( ! empty( $plugin->version ) ) && ( $plugin->version != $version ) )
				     || ( $download_link ) ) {
					if ( ! empty( $plugin->download_link ) ) {
						$plugin = self::_get_item_download_link( $plugin, $version );
					}
				}

				return $plugin;
			}

		}

		return NULL;
	}

	public static function theme_info( $slug = NULL, $version = NULL, $download_link = TRUE ) {
		if ( ! empty( $slug ) ) {
			$response = Requests::post(
				'http://api.wordpress.org/themes/info/1.1/',
				[],
				[ 'action' => 'theme_information', 'request' => [ 'slug' => $slug, 'fields' => [ 'versions' => TRUE ] ] ]
			);
			if ( ! empty( $response->body ) ) {
				$theme = json_decode( $response->body );
				// Determine the version to be used.
				if ( ! empty( $theme->versions ) ) {
					$version = Utils::determine_version( $version, $theme->version, $theme->versions );
				}
				if ( ( ! empty( $version ) ) && ( ! empty( $theme->version ) ) && ( $theme->version != $version ) ) {
					if ( ! empty( $theme->download_link ) ) {
						$theme = self::_get_item_download_link( $theme, $version );
					}
				}

				return $theme;
			}

		}

		return NULL;
	}

	// Changes item download link with the specified version.
	private static function _get_item_download_link( $response, $version ) {

		// WordPress.org forces https, but still sometimes returns http
		// See https://twitter.com/nacin/status/512362694205140992
		$response->download_link = str_replace( 'http://', 'https://', $response->download_link );

		list( $link ) = explode( $response->slug, $response->download_link );

		if ( $version == 'dev' ) {
			$response->download_link = $link . $response->slug . '.zip';
			$response->version       = 'Development Version';
		} else {
			// Sets the latest version if '*' is specified
			if ( $version == '*' || $version == 'latest' ) {
				$version = $response->version;
			}
			// Build the download link
			$response->download_link = $link . $response->slug . '.' . $version . '.zip';
			$response->version       = $version;
		}

		return $response;

	}

}