<?php

namespace Laragear\ReCaptcha;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * @internal
 */
class ReCaptchaServiceProvider extends ServiceProvider
{
    // These constants point to publishable files/directories.
    public const CONFIG = __DIR__.'/../config/recaptcha.php';
    public const LANG = __DIR__.'/../lang';
    public const STUBS = __DIR__.'/../.stubs/stubs';
    public const META = __DIR__.'/../.phpstorm.meta.php';
    public const VIEWS = __DIR__.'/../resources/views';

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(static::CONFIG, 'recaptcha');
        $this->loadTranslationsFrom(static::LANG, 'recaptcha');
        $this->loadViewsFrom(static::VIEWS, 'recaptcha');

        $this->app->singleton(ReCaptcha::class, static function (Application $app): ReCaptcha {
            return new ReCaptcha($app->make(Factory::class), $app->make('config'));
        });
    }

    /**
     * Bootstrap the application services.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function boot(Router $router): void
    {
        if ($this->app->runningInConsole()) {
            // @phpstan-ignore-next-line
            $this->publishes([static::VIEWS => $this->app->viewPath('vendor/recaptcha')], 'views');
            $this->publishes([static::CONFIG => $this->app->configPath('recaptcha.php')], 'config');
            $this->publishes([static::LANG => $this->app->langPath('vendor/recaptcha')], 'lang');
            $this->publishes([
                static::STUBS => $this->app->basePath('.stubs/recaptcha.php'),
                static::META => $this->app->basePath('.phpstorm.meta.php/recaptcha.php'),
            ], 'phpstorm');
        }

        $router->aliasMiddleware(Http\Middleware\VerifyReCaptchaV2::ALIAS, Http\Middleware\VerifyReCaptchaV2::class);
        $router->aliasMiddleware(Http\Middleware\VerifyReCaptchaV3::ALIAS, Http\Middleware\VerifyReCaptchaV3::class);
        $router->aliasMiddleware(Http\Middleware\ConfirmReCaptcha::ALIAS, Http\Middleware\ConfirmReCaptcha::class);

        Request::macro('isRobot', [RequestMacro::class, 'isRobot']);
        Request::macro('isHuman', [RequestMacro::class, 'isHuman']);

        $this->app->resolving('blade.compiler', static function (BladeCompiler $blade): void {
            $blade->if('robot', [Blade\Directives\Robot::class, 'directive']);
        });
    }
}
