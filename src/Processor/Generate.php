<?php namespace WP_CLI_Build\Processor;

use WP_CLI\Utils as WP_CLI_Utils;
use WP_CLI_Build\Build_Parser;
use WP_CLI_Build\Helper\Utils;
use Symfony\Component\Yaml\Yaml;

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
		$this->build_file     = new Build_Parser( Utils::get_build_filename( $assoc_args ), $assoc_args );
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
		if ( ( ( ! empty( $this->assoc_args['format'] ) ) && ( $this->assoc_args['format'] == 'yml' ) ) || ( strpos( $this->build_filename, 'yml' ) !== FALSE ) ) {
			$content = Yaml::dump( $build, 10 );
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
			$custom_items['plugins'] = $this->plugins['custom'];
		}
		if ( ! empty( $this->themes['custom'] ) ) {
			$custom_items['themes'] = $this->themes['custom'];
		}

		// Skip .gitignore creation.
		if ( empty( $custom_items ) ) {
			Utils::line( "%WNo %Rcustom%n%W items found, skipping %Y.gitignore%n%W creation.%n\n" );

			return FALSE;
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
		$version = ( Utils::strposa( $this->build_file->get_core_version(), [ '~', '^', '*', 'latest' ] ) !== FALSE ) ? $this->build_file->get_core_version() : Utils::wp_version();
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
				$version       = ( Utils::strposa( $build_version, [ '~', '^', '*', 'latest' ] ) !== FALSE ) ? str_replace( 'latest', '*', $build_version ) : $details['Version'];
				// Check if plugin is active.
				if ( ( is_plugin_active( $file ) ) || ( ! empty( $this->assoc_args['all'] ) ) ) {
					// Check plugin information on wp official repository.
					$api = plugins_api( 'plugin_information', [ 'slug' => $slug ] );
					// Origin.
					$plugin_origin = ( is_wp_error( $api ) || ( empty( $api->download_link ) ) || ( ! empty( $api->external ) ) ) ? 'custom' : 'wp.org';
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
				$version       = ( Utils::strposa( $build_version, [ '~', '^', '*', 'latest' ] ) !== FALSE ) ? str_replace( 'latest', '*', $build_version ) : $theme->display( 'Version' );
				if ( ( $slug == $current_theme ) || ( ! empty( $this->assoc_args['all'] ) ) ) {
					// Check theme information on wp.org.
					$api = themes_api( 'theme_information', [ 'slug' => $slug ] );
					// Origin.
					$origin = is_wp_error( $api ) ? 'custom' : 'wp.org';
					// Origin color.
					$origin_colorize = ( $origin == 'wp.org' ) ? "%Y$origin%n" : "%R$origin%n";
					// Message.
					$message = empty( $version ) ? "%W  %n%G$slug%n%W (%n$origin_colorize%W)\n" : "%W  %n%G$slug%n%W (%n$origin_colorize%W):%n {$version}\n";
					Utils::line( $message );
					// Add theme to the list.
					$themes[ $origin ][ $slug ]['version'] = $version;
					// Add main theme in case the theme we've added have '-child' in the name.
					$is_child_theme = strpos( $slug, '-child' );
					if ( $is_child_theme !== FALSE ) {
						// Generate main theme name.
						$main_theme = substr( $slug, 0, $is_child_theme );
						// Check theme information on wp.org.
						$api = themes_api( 'theme_information', [ 'slug' => $main_theme ] );
						// Origin.
						$origin = is_wp_error( $api ) ? 'custom' : 'wp.org';
						// Origin color.
						$origin_colorize = ( $origin == 'wp.org' ) ? "%Y$origin%n" : "%R$origin%n";
						// Message.
						Utils::line( "%W  %n%G$main_theme%n%W (%n$origin_colorize%W)\n" );
						// Add theme to the themes list.
						$themes[ $origin ][ $main_theme ] = NULL;
					}
				}
			}
		}
		Utils::line( "\n" );

		return $themes;
	}

	private function create_gitignore_wp_build_block( $custom_items = [] ) {
	  $gitignore = [];

	  // Start block and add common stuff.
	  $gitignore[] = "# START WP-CLI BUILD BLOCK\n";
	  $gitignore[] = "# ------------------------------------------------------------\n";
	  $gitignore[] = "# This block is auto generated every time you run 'wp build-generate'\n";
	  $gitignore[] = "# Rules: Exclude everything from Git except for your custom plugins and themes (that is: those that are not on wordpress.org)\n";
	  $gitignore[] = "# ------------------------------------------------------------\n";
	  $gitignore[] = "/*\n";
	  $gitignore[] = "!.gitignore\n";
	  $gitignore[] = "!{$this->build_filename}\n";
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
	    foreach ( $custom_items as $type => $items ) {
	      foreach ( $items as $slug => $version ) {
	        if ( ! empty( $slug ) ) {
	          $gitignore[] = "!wp-content/$type/$slug/\n";
	        }
	      }
	    }
	  }

	  // Close gitignore block.
	  $gitignore[] = "# ------------------------------------------------------------\n";
	  $gitignore[] = "# END WP-CLI BUILD BLOCK\n";

	  return $gitignore;
	}

	private function save_gitignore( $custom_items = [] ) {
	  // .gitignore path.
	  $gitignore_path = ABSPATH . '.gitignore';
	  if ( $gitignore_path == '/.gitignore' ) {
	    $gitignore_path = realpath( '.' ) . '/.gitignore';
	  }

	  // Check if the file exists and load.
	  $gitignore_wp_build_block = $this->create_gitignore_wp_build_block( $custom_items );

	  if ( file_exists( $gitignore_path ) ) {
	    $gitignore = @file( $gitignore_path );

	    // Check if the path is already in ignore file.
	    if ( ! empty( $gitignore ) ) {
	      // search for existing block from gitignore.
	      $start = array_search( "# START WP-CLI BUILD BLOCK\n", $gitignore );
	      $end   = array_search( "# END WP-CLI BUILD BLOCK\n", $gitignore );

	      // if existing block is found it is replaced, if not block is appended
	      if ( ( is_int( $start ) ) && ( is_int( $end ) ) ) {
	        $before_wp_build_block = array_slice($gitignore, 0, $start - 1);
	        $after_wp_build_block = array_slice($gitignore, $end + 1);
	        $gitignore = array_merge($before_wp_build_block, $gitignore_wp_build_block, $after_wp_build_block);
	      } else {
	        $gitignore = array_merge($gitignore, $gitignore_wp_build_block);
	      }
	    }
	  } else {
	    $gitignore = $gitignore_wp_build_block;
	  }

	  // Put content in .gitignore.
	  if ( ! empty( $gitignore ) ) {
	    @file_put_contents( $gitignore_path, $gitignore );

	    return TRUE;
	  }

	  return FALSE;
	}


}
