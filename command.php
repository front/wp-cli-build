<?php

namespace WP_CLI_Build;

use WP_CLI;

if (!class_exists('WP_CLI')) {
	return;
}

require_once dirname(__FILE__) . '/src/Build_Command.php';
require_once dirname(__FILE__) . '/src/Build_Generate_Command.php';
require_once dirname(__FILE__) . '/src/Build_Parser.php';
require_once dirname(__FILE__) . '/src/Processor/Core.php';
require_once dirname(__FILE__) . '/src/Processor/Generate.php';
require_once dirname(__FILE__) . '/src/Processor/Item.php';
require_once dirname(__FILE__) . '/src/Helper/Utils.php';
require_once dirname(__FILE__) . '/src/Helper/WP_API.php';

WP_CLI::add_command('build', Build_Command::class, array(
	'shortdesc' => 'Parse the build file and download, install or update the core, plugins or themes on it.'
));

WP_CLI::add_command('build-generate', Build_Generate_Command::class, array(
	'shortdesc' => 'Generates a build file according to current WordPress installation.'
));
