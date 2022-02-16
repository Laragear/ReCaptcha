<?php

namespace Tests\Http\Middleware;

use function app;
use Laragear\ReCaptcha\Http\ReCaptchaResponse;

trait UsesRoutesWithMiddleware
{
    protected function defineEnvironment($app)
    {
        $app->make('config')->set('recaptcha.enable', true);
    }

    protected function defineRoutes($router)
    {
        $router->post('v3/default', [__CLASS__, 'returnSameResponse'])->middleware('recaptcha.score');
        $router->post('v3/threshold_1', [__CLASS__, 'returnSameResponse'])->middleware('recaptcha.score:1.0');
        $router->post('v3/threshold_0', [__CLASS__, 'returnSameResponse'])->middleware('recaptcha.score:0');
        $router->post('v3/action_foo', [__CLASS__, 'returnSameResponse'])->middleware('recaptcha.score:null,foo');
        $router->post('v3/input_bar', [__CLASS__, 'returnSameResponse'])->middleware('recaptcha.score:null,null,bar');

        $router->post('v2/checkbox', [__CLASS__, 'returnResponseIfExists'])->middleware('recaptcha:checkbox');
        $router->post('v2/checkbox/input_bar', [__CLASS__, 'returnResponseIfExists'])->middleware('recaptcha:checkbox,null,bar');
        $router->post('v2/checkbox/remember', [__CLASS__, 'returnResponseIfExists'])->middleware('recaptcha:checkbox,10');
        $router->post('v2/invisible', [__CLASS__, 'returnResponseIfExists'])->middleware('recaptcha:invisible');
        $router->post('v2/invisible/input_bar', [__CLASS__, 'returnResponseIfExists'])->middleware('recaptcha:invisible,null,bar');
        $router->post('v2/invisible/remember', [__CLASS__, 'returnResponseIfExists'])->middleware('recaptcha:invisible,10');
        $router->post('v2/android', [__CLASS__, 'returnResponseIfExists'])->middleware('recaptcha:android');
        $router->post('v2/android/input_bar', [__CLASS__, 'returnResponseIfExists'])->middleware('recaptcha:android,null,bar');
        $router->post('v2/android/remember', [__CLASS__, 'returnResponseIfExists'])->middleware('recaptcha:android,10');
    }

    public static function returnSameResponse(ReCaptchaResponse $response): ReCaptchaResponse
    {
        return $response;
    }

    public static function returnResponseIfExists(): ?ReCaptchaResponse
    {
        return app()->has(ReCaptchaResponse::class) ? app(ReCaptchaResponse::class) : null;
    }
}
