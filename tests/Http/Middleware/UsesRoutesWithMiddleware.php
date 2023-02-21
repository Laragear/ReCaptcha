<?php

namespace Tests\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Laragear\ReCaptcha\Http\ReCaptchaResponse;

trait UsesRoutesWithMiddleware
{
    protected function defineEnvironment($app): void
    {
        $app->make('config')->set('recaptcha.enable', true);
    }

    protected function defineRoutes($router): void
    {
        $router->post('v3/default', Closure::fromCallable([static::class, 'returnSameResponse']))->middleware('recaptcha.score');
        $router->post('v3/threshold_1', Closure::fromCallable([static::class, 'returnSameResponse']))->middleware('recaptcha.score:1.0');
        $router->post('v3/threshold_0', Closure::fromCallable([static::class, 'returnSameResponse']))->middleware('recaptcha.score:0');
        $router->post('v3/action_foo', Closure::fromCallable([static::class, 'returnSameResponse']))->middleware('recaptcha.score:null,foo');
        $router->post('v3/input_bar', Closure::fromCallable([static::class, 'returnSameResponse']))->middleware('recaptcha.score:null,null,bar');

        $router->post('v2/checkbox', Closure::fromCallable([static::class, 'returnResponseIfExists']))->middleware('recaptcha:checkbox');
        $router->post('v2/checkbox/input_bar', Closure::fromCallable([static::class, 'returnResponseIfExists']))->middleware('recaptcha:checkbox,null,bar');
        $router->post('v2/checkbox/remember', Closure::fromCallable([static::class, 'returnResponseIfExists']))->middleware('recaptcha:checkbox,10');
        $router->post('v2/invisible', Closure::fromCallable([static::class, 'returnResponseIfExists']))->middleware('recaptcha:invisible');
        $router->post('v2/invisible/input_bar', Closure::fromCallable([static::class, 'returnResponseIfExists']))->middleware('recaptcha:invisible,null,bar');
        $router->post('v2/invisible/remember', Closure::fromCallable([static::class, 'returnResponseIfExists']))->middleware('recaptcha:invisible,10');
        $router->post('v2/android', Closure::fromCallable([static::class, 'returnResponseIfExists']))->middleware('recaptcha:android');
        $router->post('v2/android/input_bar', Closure::fromCallable([static::class, 'returnResponseIfExists']))->middleware('recaptcha:android,null,bar');
        $router->post('v2/android/remember', Closure::fromCallable([static::class, 'returnResponseIfExists']))->middleware('recaptcha:android,10');
    }

    public static function returnSameResponse(ReCaptchaResponse $response): ReCaptchaResponse
    {
        return $response;
    }

    public static function returnResponseIfExists(Application $app): ?ReCaptchaResponse
    {
        return $app->has(ReCaptchaResponse::class) ? $app->make(ReCaptchaResponse::class) : null;
    }
}
