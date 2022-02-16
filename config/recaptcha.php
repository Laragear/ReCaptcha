<?php

use Laragear\ReCaptcha\ReCaptcha;

return [

    /*
    |--------------------------------------------------------------------------
    | Main switch
    |--------------------------------------------------------------------------
    |
    | The switch enables the main ReCaptcha v2 middleware for detecting all the
    | incoming challenges. You should activate it on production environments.
    | On local development it can remain disabled unless testing responses.
    |
    */

    'enable' => env('RECAPTCHA_ENABLE', false),

    /*
    |--------------------------------------------------------------------------
    | Fake on local development
    |--------------------------------------------------------------------------
    |
    | Sometimes you may want to fake success or failed responses from reCAPTCHA
    | servers in local development. To do this, simply enable the environment
    | variable and then issue as a checkbox parameter is_robot to any form.
    |
    | For v2 middleware, faking means bypassing checks, like it were disabled.
    |
    */

    'fake' => env('RECAPTCHA_FAKE', false),

    /*
    |--------------------------------------------------------------------------
    | Constraints
    |--------------------------------------------------------------------------
    |
    | These default constraints allows further verification of the incoming
    | response from reCAPTCHA servers. Hostname and APK Package Name are
    | required if these are not verified in your reCAPTCHA admin panel.
    |
    */

    'hostname'         => env('RECAPTCHA_HOSTNAME'),
    'apk_package_name' => env('RECAPTCHA_APK_PACKAGE_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Threshold
    |--------------------------------------------------------------------------
    |
    | For reCAPTCHA v3, which is a score-driven interaction, this default
    | threshold is the slicing point between bots and humans. If a score
    | is below this threshold it means the request was made by a bot.
    |
    */

    'threshold' => 0.5,

    /*
    |--------------------------------------------------------------------------
    | Remember V2 Challenge
    |--------------------------------------------------------------------------
    |
    | Asking again and again for validation may become cumbersome when a form
    | is expected to fail. You can globally remember successful challenges
    | for the user for a given number of minutes to avoid asking again.
    |
    | To remember the challenge until the session dies, set "minutes" to zero.
    */

    'remember' => [
        'enabled' => false,
        'key'     => '_recaptcha',
        'minutes' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Options
    |--------------------------------------------------------------------------
    |
    | This array is passed down to the underlying HTTP Client which will make
    | the request to reCAPTCHA servers. By default, is set to use HTTP/2 for
    | the request. You can change, remove or add more options in the array.
    |
    | @see https://docs.guzzlephp.org/en/stable/request-options.html
    */

    'client' => [
        'version' => 2.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | The following is the array of credentials for each version and variant
    | of the reCAPTCHA services. You shouldn't need to edit this unless you
    | know what you're doing. On reCAPTCHA v2, it comes with testing keys.
    |
    */

    'credentials' => [
        ReCaptcha::CHECKBOX => [
            'secret' => env('RECAPTCHA_CHECKBOX_SECRET', ReCaptcha::TEST_V2_SECRET),
            'key'    => env('RECAPTCHA_CHECKBOX_KEY', ReCaptcha::TEST_V2_KEY),
        ],
        ReCaptcha::INVISIBLE => [
            'secret' => env('RECAPTCHA_INVISIBLE_SECRET', ReCaptcha::TEST_V2_SECRET),
            'key'    => env('RECAPTCHA_INVISIBLE_KEY', ReCaptcha::TEST_V2_KEY),
        ],
        ReCaptcha::ANDROID => [
            'secret' => env('RECAPTCHA_ANDROID_SECRET', ReCaptcha::TEST_V2_SECRET),
            'key'    => env('RECAPTCHA_ANDROID_KEY', ReCaptcha::TEST_V2_KEY),
        ],
        ReCaptcha::SCORE => [
            'secret' => env('RECAPTCHA_SCORE_SECRET'),
            'key'    => env('RECAPTCHA_SCORE_KEY'),
        ],
    ],
];
