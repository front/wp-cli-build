<?php namespace WP_CLI_Build\Processor;

use WP_CLI\Utils as WP_CLI_Utils;
use WP_CLI_Build\Build_Parser;
use WP_CLI_Build\Helper\Utils;

class Generate {

	private $core;
	private $plugins;
	private $themes;
	private $assoc_args;
	private $build_file;
	private $build_filename;

	public function __construct( $assoc_args = NULL, $build_filename = NULL ) {
		// Cmd line arguments.
		$this->assoc_args = $assoc_args;
		// Existing build file (if any).
		$this->build_filename = $build_filename;
		$this->build_file     = new Build_Parser( $build_filename );
		// WP core, plugins and themes information.
		// Verbose output
		Utils::line( "%WCompiling information from the existing installation, please wait...\n\n" );
		$this->core    = $this->get_core();
		$this->plugins = $this->get_plugins();
		$this->themes  = $this->get_themes();
	}

	public function create_build_file() {

		// Build structure.
		$build = [];
		if ( ! empty( $this->core ) ) {
			$build['core'] = $this->core;
		}
		if ( ! empty( $this->plugins['wp.org'] ) ) {
			$build['plugins'] = $this->plugins['wp.org'];
		}
		if ( ! empty( $this->themes['wp.org'] ) ) {
			$build['themes'] = $this->themes['wp.org'];
		}

		// No content, skip build file creation.
		if ( empty( $build ) ) {
			Utils::line( "Not enough content to generate the build file." );

			return FALSE;
		}

		Utils::line( "%WGenerating %n%Y$this->build_filename%n%W with the items from %Ywp.org%n%W, please wait...%n" );

		// YAML.
		if ( ! empty( $this->assoc_args['format'] ) ) {
			if ( $this->assoc_args['format'] == 'yaml' ) {
				$content = Yaml::dump( $build, 10 );
			}
		}

		// JSON.
		if ( empty( $content ) ) {
			$content = json_encode( $build, JSON_PRETTY_PRINT );
		}

		// Write to file.
		if ( ! empty( $content ) ) {
			@file_put_contents( $this->build_filename, $content );
			Utils::line( "%G done%n\n" );

			return TRUE;
		}
		Utils::line( "%Rfail :(%n\n" );

		return FALSE;

	}

	public function create_gitignore() {
		$custom_items = [];
		if ( ! empty( $this->plugins['custom'] ) ) {
			$custom_items = array_merge( $custom_items, $this->plugins['custom'] );
		}
		if ( ! empty( $this->themes['custom'] ) ) {
			$custom_items = array_merge( $custom_items, $this->themes['custom'] );
		}

		// Skip .gitignore creation.
		if ( empty( $custom_items ) ) {
			Utils::line( "%WNo %Rcustom%n%W items found, skipping %Y.gitignore%n%W creation.%n\n" );
		}

		// Create .gitignore.
		Utils::line( "%WGenerating %n%Y.gitignore%n%W, please wait..." );
		if ( $this->save_gitignore( $custom_items ) ) {
			Utils::line( "%Gdone%n\n" );

			return TRUE;
		}
		Utils::line( "%Rfail :(%n\n" );

		return FALSE;
	}

	private function get_core() {
		// Verbose output
		Utils::line( "%W- Checking %n%Ccore%n%W...\n" );
		// Get locale
		$locale = get_locale();
		// If the core version from existing file is the latest, set it.
		if ( ( $this->build_file->get_core_version() == '*' ) || ( $this->build_file->get_core_version() == 'latest' ) ) {
			$version = '*';
		} else {
			// Current WordPress version.
			$version = Utils::wp_version();
			$version = empty( $version ) ? '*' : $version;
		}
		// Verbose output
		Utils::line( "  %Gversion%n: $version\n  %Glocale%n: $locale\n\n" );

		return array(
			'download' => array(
				'version' => $version,
				'locale'  => $locale
			)
		);
	}

	private function get_plugins() {
		// Verbose output
		Utils::line( "%W- Checking %n%Cplugins%n%W...\n" );
		$installed_plugins = get_plugins();
		$plugins           = NULL;
		if ( ! empty( $installed_plugins ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			foreach ( $installed_plugins as $file => $details ) {
				// Plugin slug.
				$slug = strtolower( WP_CLI_Utils\get_plugin_name( $file ) );
				// Plugin version.
				$build_version = $this->build_file->get_plugin_version( $slug );
				$version       = ( ( $build_version == '*' ) || ( $build_version == 'latest' ) ) ? '*' : $details['Version'];
				// Check if plugin is active.
				if ( ( is_plugin_active( $file ) ) || ( ! empty( $this->assoc_args['all'] ) ) ) {
					// Check plugin information on wp official repository.
					$api = plugins_api( 'plugin_information', [ 'slug' => $slug ] );
					// Origin.
					$plugin_origin = is_wp_error( $api ) ? 'custom' : 'wp.org';
					// Verbose output.
					$plugin_origin_colorize = ( $plugin_origin == 'wp.org' ) ? "%Y$plugin_origin%n" : "%R$plugin_origin%n";
					Utils::line( "%W  %n%G$slug%n%W (%n$plugin_origin_colorize%W):%n {$version}\n" );
					// Add plugin to the list.
					$plugins[ $plugin_origin ][ $slug ]['version'] = $version;
					// Plugin network activation.
					if ( ! empty( $details['Network'] ) ) {
						$plugins[ $plugin_origin ][ $slug ]['activate-network'] = 'yes';
					}
				}

			}
		}
		Utils::line( "\n" );

		return $plugins;
	}

	private function get_themes() {
		// Verbose output
		Utils::line( "%W- Checking %n%Cthemes%n%W...\n" );
		$installed_themes = wp_get_themes();
		$themes           = NULL;
		if ( ! empty( $installed_themes ) ) {
			$current_theme = get_stylesheet();
			foreach ( $installed_themes as $slug => $theme ) {
				// Version.
				$build_version = $this->build_file->get_theme_version( $slug );
				$version       = ( ( $build_version == '*' ) || ( $build_version == 'latest' ) ) ? '*' : $theme->display( 'Version' );
				if ( ( $slug == $current_theme ) || ( ! empty( $this->assoc_args['all'] ) ) ) {
					// Check theme information on wp.org.
					$api = themes_api( 'theme_information', [ 'slug' => $slug ] );
					// Origin.
					$origin = is_wp_error( $api ) ? 'custom' : 'wp.org';
					// Verbose output.
					$origin_colorize = ( $origin == 'wp.org' ) ? "%Y$origin%n" : "%R$origin%n";
					Utils::line( "%W  %n%G$slug%n%W (%n$origin_colorize%W):%n {$version}\n" );
					// Add theme to the list.
					$plugins[ $origin ][ $slug ]['version'] = $version;
				}
			}
		}
		Utils::line( "\n" );

		return $themes;
	}

	private function save_gitignore( $custom_items = [] ) {
		// .gitignore path.
		$gitignore_path = ABSPATH . '.gitignore';
		if ( $gitignore_path == '/.gitignore' ) {
			$gitignore_path = realpath( '.' ) . '/.gitignore';
		}

		// Check if the file exists and load.
		$gitignore = [];
		if ( file_exists( $gitignore_path ) ) {
			$gitignore = @file( $gitignore_path );
			// Check if the path is already in ignore file.
			if ( ! empty( $gitignore ) ) {
				// Remove existing block from gitignore.
				$start = array_search( "# START WP-CLI BUILD BLOCK\n", $gitignore );
				$end   = array_search( "# END WP-CLI BUILD BLOCK\n", $gitignore );
				if ( ( is_int( $start ) ) && ( is_int( $end ) ) ) {
					for ( $i = $start; $i <= $end; $i ++ ) {
						unset( $gitignore[ $i ] );
					}
				}
			}
		}

		// Start block and add common stuff.
		$gitignore[] = "# START WP-CLI BUILD BLOCK\n";
		$gitignore[] = "# ------------------------------------------------------------\n";
		$gitignore[] = "# This block is auto generated every time you run 'wp build-generate'\n";
		$gitignore[] = "# Rules: Exclude everything from Git except for your custom plugins and themes (that is: those that are not on wordpress.org)\n";
		$gitignore[] = "# ------------------------------------------------------------\n";
		$gitignore[] = "/*\n";
		$gitignore[] = "!.gitignore\n";
		$gitignore[] = "!build.yml\n";
		$gitignore[] = "!wp-content\n";
		$gitignore[] = "wp-content/*\n";
		$gitignore[] = "!wp-content/plugins\n";
		$gitignore[] = "wp-content/plugins/*\n";
		$gitignore[] = "!wp-content/themes\n";
		$gitignore[] = "wp-content/themes/*\n";

		// Add custom items.
		if ( ! empty( $custom_items ) ) {
			$gitignore[] = "# ------------------------------------------------------------\n";
			$gitignore[] = "# Your custom themes/plugins\n";
			$gitignore[] = "# Added automagically by WP-CLI Build (wp build-generate)\n";
			$gitignore[] = "# ------------------------------------------------------------\n";
			foreach ( $custom_items as $item ) {
				if ( ( ! empty( $item['slug'] ) ) && ( ! empty( $item['type'] ) ) ) {
					$gitignore[] = "!wp-content/{$item['type']}s/{$item['slug']}/\n";
				}
			}
		}

		// Close gitignore block.
		$gitignore[] = "# ------------------------------------------------------------\n";
		$gitignore[] = "# END WP-CLI BUILD BLOCK\n";

		// Put content in .gitignore.
		if ( ! empty( $gitignore ) ) {
			@file_put_contents( $gitignore_path, $gitignore );

			return TRUE;
		}

		return FALSE;
	}

}