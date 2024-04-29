<?php

namespace Bramato\FilamentStripeManageSubmissions\Tests;

use Bramato\FilamentStripeManageSubmissions\FilamentBlogServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            FilamentBlogServiceProvider::class,
        ];
    }
}
