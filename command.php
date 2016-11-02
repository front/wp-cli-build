<?php
namespace WP_CLI_Build;

use WP_CLI;

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

require_once dirname( __FILE__ ) . '/src/Build_Command.php';
require_once dirname( __FILE__ ) . '/src/Build_Task.php';
require_once dirname( __FILE__ ) . '/src/Build_File.php';
require_once dirname( __FILE__ ) . '/src/Build_Gitignore.php';
require_once dirname( __FILE__ ) . '/src/Build_Helper.php';

WP_CLI::add_command( 'build', Build_Command::class, array(
	'shortdesc' => 'Process YAML configuration file to build wordpress.'
) );


class Teste extends \WP_CLI_Command {

	/**
	 * Builds WordPress. Installs core, plugins, themes.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Specify custom build file (default: build.yml)
	 *
	 * ## EXAMPLES
	 *
	 *     wp build all
	 *     wp build all --file=production.yml
	 *
	 * @when before_wp_load
	 */
	public function run() {
		var_dump( file_exists( ABSPATH . 'wp-config-sample.php' ) );
		//WP_CLI::run_command( [ 'core', 'download' ] );
		WP_CLI::launch_self('core', ['download']);
		var_dump( file_exists( '/Users/fabioneves/Development/teste.dev/wp-config-sample.php' ) );
		$result = WP_CLI::launch_self('core', ['config'],[], FALSE, TRUE);
		var_dump($result);
		die();
	}
}

WP_CLI::add_command( 'teste', Teste::class );