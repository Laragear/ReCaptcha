<?php

namespace Tests;

use Laragear\ReCaptcha\Facades\ReCaptcha;
use Laragear\ReCaptcha\ReCaptchaServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ReCaptchaServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'ReCaptcha' => ReCaptcha::class,
        ];
    }
}
