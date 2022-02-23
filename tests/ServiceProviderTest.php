<?php

namespace Tests;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laragear\ReCaptcha\Http\Middleware\ConfirmReCaptcha;
use Laragear\ReCaptcha\Http\Middleware\VerifyReCaptchaV2;
use Laragear\ReCaptcha\Http\Middleware\VerifyReCaptchaV3;
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

    public function test_load_views(): void
    {
        static::assertArrayHasKey('recaptcha', $this->app->make('view')->getFinder()->getHints());
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

    public function test_publishes_views(): void
    {
        static::assertSame(
            [ReCaptchaServiceProvider::VIEWS => $this->app->viewPath('vendor/recaptcha')],
            ServiceProvider::pathsToPublish(ReCaptchaServiceProvider::class, 'views')
        );
    }

    public function test_publishes_phpstorm_files(): void
    {
        static::assertSame([
            ReCaptchaServiceProvider::STUBS => $this->app->basePath('.stubs/recaptcha.php'),
            ReCaptchaServiceProvider::META => $this->app->basePath('.phpstorm.meta.php/recaptcha.php'),
        ], ServiceProvider::pathsToPublish(ReCaptchaServiceProvider::class, 'phpstorm'));
    }

    public function test_publishes_middleware(): void
    {
        $middleware = $this->app->make('router')->getMiddleware();

        static::assertSame(VerifyReCaptchaV2::class, $middleware[VerifyReCaptchaV2::ALIAS]);
        static::assertSame(VerifyReCaptchaV3::class, $middleware[VerifyReCaptchaV3::ALIAS]);
        static::assertSame(ConfirmReCaptcha::class, $middleware[ConfirmReCaptcha::ALIAS]);
    }

    public function test_registers_macros(): void
    {
        static::assertTrue(Request::hasMacro('isRobot'));
        static::assertTrue(Request::hasMacro('isHuman'));
    }

    public function test_registers_blade_directive(): void
    {
        static::assertArrayHasKey('robot', $this->app->make('blade.compiler')->getCustomDirectives());
    }
}
