<?php

namespace GaiaTools\ContentAccord\Tests;

use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            // Will add ContentAccordServiceProvider later
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        // Setup test environment if needed
    }
}
