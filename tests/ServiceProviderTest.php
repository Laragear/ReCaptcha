<?php

namespace Tests;

use Illuminate\Support\ServiceProvider;
use Laragear\ReCaptcha\ReCaptcha;
use Laragear\ReCaptcha\ReCaptchaServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_merges_config(): void
    {
        static::assertSame(
            $this->app->make('files')->getRequire(ReCaptchaServiceProvider::CONFIG),
            $this->app->make('config')->get('recaptcha')
        );
    }

    public function test_loads_translations(): void
    {
        static::assertArrayHasKey('recaptcha', $this->app->make('translator')->getLoader()->namespaces());
    }

    public function test_registers_recaptcha(): void
    {
        static::assertTrue($this->app->bound(ReCaptcha::class));
    }

    public function test_publishes_config(): void
    {
        static::assertSame(
            [ReCaptchaServiceProvider::CONFIG => $this->app->configPath('recaptcha.php')],
            ServiceProvider::pathsToPublish(ReCaptchaServiceProvider::class, 'config')
        );
    }

    public function test_publishes_translation(): void
    {
        static::assertSame(
            [ReCaptchaServiceProvider::LANG => $this->app->langPath('vendor/recaptcha')],
            ServiceProvider::pathsToPublish(ReCaptchaServiceProvider::class, 'lang')
        );
    }
}
