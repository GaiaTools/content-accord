<?php

namespace GaiaTools\ContentAccord\Tests;

use GaiaTools\ContentAccord\ContentAccordServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ContentAccordServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        // Setup test environment if needed
    }
}
