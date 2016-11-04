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
