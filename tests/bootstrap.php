<?php

/**
 * Bootstrap the CLI dependencies
 *
 * This is important to test the CLI classes.
 */

require_once __DIR__ . '/../vendor/autoload.php';

\WP_CLI\Utils\load_dependencies();
