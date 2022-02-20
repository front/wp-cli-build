<?php

use PHPUnit\Framework\TestCase;
use WP_CLI_Build\Helper\WP_API;

class WP_API_Test extends TestCase
{

    public function test_core_version_check_without_argument(): void
    {
        $this->assertEquals(false, WP_API::core_version_check());
    }
}
