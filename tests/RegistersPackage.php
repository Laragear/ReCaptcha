<?php

namespace Tests;

trait RegistersPackage
{
    protected function getPackageAliases($app)
    {
        return [
            'ReCaptcha' => 'Laragear\ReCaptcha\Facades\ReCaptcha',
        ];
    }

    protected function getPackageProviders($app)
    {
        return ['Laragear\ReCaptcha\ReCaptchaServiceProvider'];
    }
}
