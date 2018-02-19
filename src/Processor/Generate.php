<?php namespace WP_CLI_Build\Processor;

use WP_CLI\Utils;
use WP_CLI_Build\Helper\Build_File;
use WP_CLI_Build\Helper\Utils as HelperUtils;

class Generate {

	private $assoc_args;
	private $build_file;

	public function __construct( $assoc_args = NULL, $build_filename = NULL ) {
		$this->assoc_args = $assoc_args;
		$this->build_file = new Build_File( $build_filename );
	}

	public function get() {
		$build['core']    = $this->get_core();
		$build['plugins'] = $this->get_plugins();
		$build['themes']  = $this->get_themes();

		return $build;
	}

	private function get_core() {
		// Init.
		$locale = get_locale();
		// If the core version from existing file is the latest, set it.
		if ( ( $this->build_file->get_core_version() == '*' ) || ( $this->build_file->get_core_version() == 'latest' ) ) {
			$version = '*';
		} else {
			// Current WordPress version.
			$version = HelperUtils::wp_version();
			$version = empty( $version ) ? '*' : $version;
		}

		return array(
			'download' => array(
				'version' => $version,
				'locale'  => $locale
			)
		);
	}

	private function get_plugins( $assoc_args = NULL ) {
		// Plugins.
		$installed_plugins = get_plugins();
		$plugins           = NULL;
		if ( ! empty( $installed_plugins ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			foreach ( $installed_plugins as $file => $details ) {
				// Check if plugin is active.
				if ( ( is_plugin_active( $file ) ) || ( ! empty( $assoc_args['all'] ) ) ) {
					// Plugin slug.
					$slug = strtolower( Utils\get_plugin_name( $file ) );
					// Check plugin information on wp official repository.
					$api = plugins_api( 'plugin_information', [ 'slug' => $slug ] );
					// If the plugin is not found we assume the plugin is custom.
					if ( is_wp_error( $api ) ) {
						// Add plugin to the custom list.
						$plugins['custom'][] = [ 'slug' => $slug, 'type' => 'plugin' ];
						continue;
					}
					// Plugin version.
					$build_version = $this->build_file->get_plugin_version( $slug );
					if ( ( $build_version == '*' ) || ( $build_version == 'latest' ) ) {
						$plugins['build'][ $slug ]['version'] = '*';
					} elseif ( ! empty( $details['Version'] ) ) {
						$plugins['build'][ $slug ]['version'] = $this->convert_to_numeric( $details['Version'] );
					}
					// Plugin network activation.
					if ( ! empty( $details['Network'] ) ) {
						$plugins['build'][ $slug ]['activate-network'] = 'yes';
					}
				}
			}
		}

		return $plugins;
	}

	private function get_themes( $assoc_args = NULL ) {
		// Themes.
		$installed_themes = wp_get_themes();
		$themes           = NULL;
		if ( ! empty( $installed_themes ) ) {
			$current_theme = get_stylesheet();
			foreach ( $installed_themes as $slug => $theme ) {
				if ( $slug == $current_theme ) {
					// Check theme information on wp official repository.
					$api = themes_api( 'theme_information', [ 'slug' => $slug ] );
					// If the theme is not found we assume the plugin is custom.
					if ( is_wp_error( $api ) ) {
						// Exclude our custom theme from being ignored by git.
						if ( empty( $assoc_args['no-gitignore'] ) ) {
							$themes['custom'][] = [ 'slug' => $slug, 'type' => 'theme' ];
						}
						continue;
					}
					// Theme version.
					$build_version = $this->build_file->get_theme_version( $slug );
					if ( ( $build_version == '*' ) || ( $build_version == 'latest' ) ) {
						$themes['build'][ $slug ]['version'] = '*';
					} elseif ( ! empty( $theme->display( 'Version' ) ) ) {
						$themes['build'][ $slug ]['version'] = $this->convert_to_numeric( $theme->display( 'Version' ) );
					}
				}
			}
		}

		return $themes;
	}

	private function convert_to_numeric( $version = NULL ) {
		if ( ( ! empty( $version ) ) && ( is_numeric( $version ) ) ) {
			return strpos($version, '.') === FALSE ? (int) $version : (float) $version;
		}

		return $version;
	}

}