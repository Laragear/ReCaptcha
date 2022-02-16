# ReCaptcha
[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/recaptcha.svg)](https://packagist.org/packages/laragear/recaptcha) [![Latest stable test run](https://github.com/Laragear/ReCaptcha/workflows/Tests/badge.svg)](https://github.com/Laragear/ReCaptcha/actions) [![Codecov coverage](https://codecov.io/gh/Laragear/ReCaptcha/branch/1.x/graph/badge.svg?token=5U6BJUEA4T)](https://codecov.io/gh/Laragear/ReCaptcha) [![Maintainability](https://api.codeclimate.com/v1/badges/b889decbc6af6a1cfd3a/maintainability)](https://codeclimate.com/github/Laragear/ReCaptcha/maintainability) [![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/9.x/octane#introduction)

Integrate reCAPTCHA using **async HTTP/2**, making your app **fast** with a few lines.

```php
use Illuminate\Support\Facades\Route;

Route::post('login', function () {
    // ...
})->middleware('recaptcha:checkbox');
```

## Keep this package free

[![](.assets/patreon.png)](https://patreon.com/packagesforlaravel)[![](.assets/ko-fi.png)](https://ko-fi.com/DarkGhostHunter)[![](.assets/buymeacoffee.png)](https://www.buymeacoffee.com/darkghosthunter)[![](.assets/paypal.png)](https://www.paypal.com/paypalme/darkghosthunter)

Your support allows me to keep this package free, up-to-date and maintainable. Alternatively, you can **[spread the word!](http://twitter.com/share?text=I%20am%20using%20this%20cool%20PHP%20package&url=https://github.com%2FLaragear%2FReCaptcha&hashtags=PHP,Laravel)**

## Requirements

* Laravel 9.x, or later
* PHP 8.0 or later

## Installation

You can install the package via Composer:

```bash
composer require laragear/recaptcha
```

## Set up

Add the reCAPTCHA keys for your site to the environment file of your project. You can add each of them for reCAPTCHA v2 **checkbox**, **invisible**, **Android**, and **score**.

If you don't have one, generate it in your [reCAPTCHA Admin panel](https://www.google.com/recaptcha/admin/).

```dotenv
RECAPTCHA_CHECKBOX_SECRET=6t5geA1UAAAAAN...
RECAPTCHA_CHECKBOX_KEY=6t5geA1UAAAAAN...

RECAPTCHA_INVISIBLE_SECRET=6t5geA2UAAAAAN...
RECAPTCHA_INVISIBLE_KEY=6t5geA2UAAAAAN...

RECAPTCHA_ANDROID_SECRET=6t5geA3UAAAAAN...
RECAPTCHA_ANDROID_KEY=6t5geA3UAAAAAN...

RECAPTCHA_SCORE_SECRET=6t5geA4UAAAAAN...
RECAPTCHA_SCORE_KEY=6t5geA4UAAAAAN...
```

This allows you to check different reCAPTCHA mechanisms using the same application, in different environments.

> ReCaptcha already comes with v2 keys for local development. For v3, you will need to create your own set of credentials once on production.

## Usage

Usage differs based on if you're using checkbox, invisible, Android challenges, or the v3 score-driven challenge.

### Checkbox, invisible and Android challenges

After you integrate reCAPTCHA into your frontend or Android app, set the ReCaptcha middleware in the `POST` routes where a form with reCAPTCHA is submitted. The middleware will catch the `g-recaptcha-response` input (you can change it later) and check if it's valid.

To declare the middleware just use the `ReCaptcha` middleware builder:

* `ReCaptcha::checkbox()` for explicitly rendered checkbox challenges.
* `ReCaptcha::invisible()` for invisible challenges.
* `ReCaptcha::android()` for Android app challenges.

```php
use App\Http\Controllers\ContactController;
use Laragear\ReCaptcha\Http\Middleware\Builders\ReCaptcha;
use Illuminate\Support\Facades\Route;

Route::post('contact', [ContactController::class, 'send'])
    ->middleware(ReCaptcha::invisible()->forGuests('web')->remember())
```

If for some reason the challenge is not a success, the validation will immediately kick in and throw a `ValidationException`, returning the user back to the form.

#### Remembering challenges

To avoid asking for challenges over and over again, you can "remember" the challenge for a given set of minutes. This can be [enabled globally](#remember), but you may prefer to do it in a per-route basis.

Simple use the `remember()` method. You can set the number of minutes to override the [global parameter](#remember). Alternatively, `rememberForever()` will remember the challenge forever.

```php
use App\Http\Controllers\Auth\LoginController;
use Laragear\ReCaptcha\Http\Middleware\Builders\ReCaptcha;
use Illuminate\Support\Facades\Route

Route::post('login', [LoginController::class, 'login'])
     ->middleware(ReCaptcha::invisible()->remember());

Route::post('message', [ChatController::class, 'login'])
     ->middleware(ReCaptcha::checkbox()->rememberForever());
```

You should use this in conjunction with the `@robot` directive in your Blade templates to render a challenge when the user has not successfully done one before.

```blade
@robot
  <div class="g-recaptcha"
       data-sitekey="{{ recaptcha('invisible') }}"
       data-callback="onSubmit"
       data-size="invisible">
  </div>
@endrobot
```

> Good places to remember a challenge for some minutes are forms which are expected to fail, or when you have multiple forms the user may jump between.

#### Changing the input name

You can change the input name from `g-recaptcha-response`, which is the default, to anything using `input()`.

```php
use App\Http\Controllers\Auth\LoginController;
use Laragear\ReCaptcha\Http\Middleware\Builders\ReCaptcha;
use Illuminate\Support\Facades\Route

Route::post('login', [LoginController::class, 'login'])
     ->middleware(ReCaptcha::checkbox()->input('recaptcha_input'));
```

### Score-driven challenge

The reCAPTCHA v3 middleware works differently from v2. This response is _always_ a success, but the challenge scores between `0.0` and `1.0`. Human-like interaction will be higher, while robots will score lower. The default threshold is `0.5`, but this can be changed globally or per-route.

To start using it, simply use the `ReCaptcha::score()` method to your route.

```php
use App\Http\Controllers\CommentController;
use Laragear\ReCaptcha\Http\Middleware\Builders\ReCaptcha;
use Illuminate\Support\Facades\Route

Route::post('comment', [CommentController::class, 'create'])
     ->middleware(ReCaptcha::score());
```

Once the challenge has been received in your controller, you will have access to two methods from the Request class or instance: `isHuman()` and `isRobot()`, which may return `true` or `false`:

```php
use App\Models\Post;
use Illuminate\Http\Request;

public function store(Request $request, Post $post)
{
    $request->validate([
        'body' => 'required|string|max:255'
    ]);
    
    $comment = $post->comment()->make($request->only('body'));
    
    // Flag the comment as "moderated" if it was a written by robot.
    if ($request->isRobot()) {
        $comment->markAsModerated();
    }
    
    $comment->save();
    
    return view('post.comment.show', ['comment' => $comment]);
}
```

You can also have access to the response from reCAPTCHA using the `response()` method of the `ReCaptcha` facade:

```php
use Laragear\ReCaptcha\Facades\ReCaptcha;

$response = ReCaptcha::response();

if ($response->score > 0.2) {
    return 'Try again!';
}
```

> Be careful of calling `response()`, as it will throw an exception on controllers without challenges.

#### Threshold, action and input name

The middleware accepts three additional parameters using the middleware helper.

1. `threshold()`: The value that must be **above or equal** to be considered human.
2. `action()`: The action name to optionally check against.
3. `input()`: The name of the reCAPTCHA input to verify.

```php
use App\Http\Controllers\CommentController;
use Laragear\ReCaptcha\Http\Middleware\Builders\ReCaptcha;
use Illuminate\Support\Facades\Route

Route::post('comment', [CommentController::class, 'store'])
     ->middleware(ReCaptcha::score()->threshold(0.7)->action('post-comment')->input('my_score_input');
```

> When checking the action name, ensure your frontend action matches with the expected in the middleware.

#### Bypassing on authenticated users

Sometimes you may want to bypass reCAPTCHA checks when there is an authenticated user, or automatically receive it as a "human" on score-driven challenges, specially on recurrent actions or when the user already has completed a challenge (like on logins).

To exclude authenticated user you can use `forGuests()`, and specify the guards if necessary.

```php
use App\Http\Controllers\CommentController;
use App\Http\Controllers\MessageController;
use DarkGhostHunter\Captcha\ReCaptcha;
use Illuminate\Support\Facades\Route

// Don't challenge users authenticated on the default (web) guard.
Route::post('message/send', [MessageController::class, 'send'])
     ->middleware(ReCaptcha::invisible()->forGuests());

// Don't challenge users authenticated on the "admin" and "moderator" guards.
Route::post('comment/store', [CommentController::class, 'store'])
     ->middleware(ReCaptcha::score(0.7)->action('comment.store')->forGuests('admin', 'moderator'));
```

Then, in your blade files, you can easily skip the challenge with the `@guest` or `@auth` directives.

```blade
<form id="comment" method="post">
    <textarea name="body"></textarea>

    @auth
        <button type="submit">Post comment</button>
    @else
        <button class="g-recaptcha" data-sitekey="{{ captchavel('invisible') }}" data-callback="onSubmit">
            Post comment
        </button>
    @endauth
</form>
```

#### Faking reCAPTCHA scores 

You can easily fake a reCAPTCHA response score in your local development by setting `RECAPTCHA_FAKE` to `true`.

```dotenv
RECAPTCHA_FAKE=true
```

This environment variable allows to fake a robot responses by filling the `is_robot` input in your form.

```blade
<form id="comment" method="post">
    <textarea name="body"></textarea>

    @env('local')
        <input type="checkbox" name="is_robot" checked>
    @endenv

    <button class="g-recaptcha" data-sitekey="{{ captchavel('invisible') }}" data-callback='onSubmit'>
        Post comment
    </button>
</form>
```

## Confirmation middleware

ReCaptcha comes with a handy middleware to confirm with a simple reCAPTCHA Checkbox challenge, much like the [password confirmation](https://laravel.com/docs/authentication#password-confirmation-protecting-routes) middleware included in Laravel.

First, set the route to protect using the `recaptcha.confirm` middleware.

```php
use Illuminate\Support\Facades\Route;

Route::get('/settings', function () {
    // ...
})->middleware('recaptcha.confirm');

Route::post('/settings', function () {
    // ...
})->middleware('recaptcha.confirm');
```

Once done, ensure you have also a `recaptcha.confirm` route to receive the redirected user, and one to receive the challenge at the same path (as the view `POST` request does). ReCaptcha comes with a controller and a basic view that you can use out of the box:

```php
use Illuminate\Support\Facades\Route;
use Laragear\ReCaptcha\Http\Controllers\ConfirmationController;

Route::get('recaptcha', [ConfirmationController::class, 'show'])
    ->name('recaptcha.confirm');

Route::post('recaptcha', [ConfirmationController::class, 'confirm'])
```

When the user tries to enter the route, it will be redirected to the view asking to resolve a reCAPTCHA challenge. Once done, it will be redirected to the intended URL.  

![img.png](img.png)

The middleware it's compatible with [remembering challenges](#remember), and will use the default amount of time to not ask again if remembering only when it's enabled globally, otherwise it will be asked to confirm every time.

You can configure the route name and the guards to bypass the confirmation if the user is authenticated after the first argument. 

```php
use Illuminate\Support\Facades\Route;

Route::get('/settings', function () {
    // ...
})->middleware('recaptcha.confirm:my-custom-route,web,admin');
```

## Frontend integration

[Check the official reCAPTCHA documentation](https://developers.google.com/recaptcha/intro) to integrate the reCAPTCHA script in your frontend, or inside your Android application.

You can use the `recaptcha()` helper to output the site key depending on the challenge version you want to render: `checkbox`,  `invisible`, `android` or `score` (v3).

```blade
<form id='login' method="POST">
    <input type="email" name="email">
    <input type="password" name="password">

    <button class="g-recaptcha" data-sitekey="{{ recaptcha('invisible') }}" data-callback='onSubmit'>
        Login
    </button>
</form>
```

## Advanced configuration

ReCaptcha is intended to work out-of-the-box, but you can publish the configuration file for fine-tuning the reCAPTCHA verification.

```bash
php artisan vendor:publish --provider="Laragear\ReCaptcha\ReCaptchaServiceProvider" --tag="config"
```

You will get a config file with this array:

```php
<?php

return [
    'enable'            => env('RECAPTCHA_ENABLE', false),
    'fake'              => env('RECAPTCHA_FAKE', false),
    'hostname'          => env('RECAPTCHA_HOSTNAME'),
    'apk_package_name'  => env('RECAPTCHA_APK_PACKAGE_NAME'),
    'threshold'         => 0.5,
    'remember' => [
        'enabled' => false,
        'key'     => '_recaptcha',
        'minutes' => 10,
    ],
    'client' => [
        'version' => 2.0,
    ],
    'credentials'       => [
        // ...
    ]
];
``` 

### Enable Switch

```php
return [
    'enable' => env('RECAPTCHA_ENABLE', false),
];
```

By default, ReCaptcha is disabled, so it doesn't check reCAPTCHA challenges, and on score-driven routes it will always resolve them as human interaction.

You can enable it with the `RECAPTCHA_ENABLE` environment variable.

```dotenv
RECAPTCHA_ENABLE=true
```

This can be handy to enable on some local or development environments to check real interaction using the included _localhost_ test keys, which only work on `localhost`.

> When switched off, the reCAPTCHA v2 challenges are not validated in the Request input, so you can safely disregard any frontend script or reCAPTCHA tokens or boxes.

### Fake responses

```dotenv
RECAPTCHA_FAKE=true
```

If ReCaptcha is [enabled](#enable-switch), setting this to true will allow your application to [fake v3-score responses from reCAPTCHA servers](#faking-recaptcha-scores). For v2 challenges, setting this to `true` bypasses the challenge verification.

You should enable it for [running unit tests](#developing-with-recaptcha-v3-score).

> **Warning** - Remember to disable faking on production. Not doing so will fake all score challenges as human, not requiring the challenge token.

### Hostname and APK Package Name

```dotenv
RECAPTCHA_HOSTNAME=myapp.com
RECAPTCHA_APK_PACKAGE_NAME=my.package.name
```

If you are not verifying the Hostname or APK Package Name in your [reCAPTCHA Admin Panel](https://www.google.com/recaptcha/admin/), may be because you use multiple hostnames or apps, you will have to issue them in the environment file.

When the reCAPTCHA response from the servers is retrieved, it will be checked against these values when present. In case of mismatch, a validation exception will be thrown.

### Threshold

```php
return [
    'threshold' => 0.4
];
```

The default threshold to check against reCAPTCHA v3 challenges. Values **equal or above** will be considered "human".

If you're not using reCAPTCHA v3, or you're fine with the default, leave this alone. You can still [override the default in a per-route basis](#threshold-action-and-input-name).

### Remember

```php
return [
    'remember' => [
        'enabled' => false,
        'key'     => '_recaptcha',
        'minutes' => 10,
    ],
];
```

Remembering the user once a V2 challenge is successful is disabled by default.

It's recommended to [use a per-route basis "remember"](#remembering-challenges) if you expect only some routes to remember challenges, instead of the whole application.

This also control how many minutes to set the "remember". You can set `INF` constant to remember the challenge forever (or until the session expires).

### HTTP Client options

```php
return [
    'client' => [
        'version' => 2.0,
    ],
];
```

This array sets the options for the outgoing request to reCAPTCHA servers. [This is handled by Guzzle](https://docs.guzzlephp.org/en/stable/request-options.html), which in turn will pass it to the underlying transport. Depending on your system, it will probably be cURL.

By default, it instructs Guzzle to use HTTP/2 whenever possible.

### Credentials

```php
return [
    'credentials' => [
        // ...
    ]
];
```

Here is the full array of [reCAPTCHA credentials](#set-up) to use depending on the version. Do not change the array unless you know what you're doing.

## Developing with ReCaptcha v2

On local development, let the default [testing keys](https://developers.google.com/recaptcha/docs/faq#id-like-to-run-automated-tests-with-recaptcha.-what-should-i-do) be used. These are meant to be used on local development, so in production you can easily change them for real keys.

On unit testing, the middleware will detect the environment and skip the mandatory challenge check. There is no need to [disable ReCaptcha](#enable-switch).

## Developing with ReCaptcha v3 (score)

On local development and unit testing, the middleware and will automatically create human responses. There is no need to [disable ReCaptcha](#enable-switch), but [enabling faking](#faking-recaptcha-scores) is mandatory to enable faking robot responses using `is_robot` on live requests.

Inside your tests, you can fake a response made by a human or robot by simply using the `fakeHuman()` and `fakeRobot()` methods, which will score `1.0` or `0.0` respectively for all subsequent requests.

```php
<?php

use Laragear\ReCaptcha\Facades\ReCaptcha;

// Let the user login normally.
ReCaptcha::fakeHuman();

$this->post('login', [
    'email' => 'test@test.com',
    'password' => '123456',
])->assertRedirect('user.welcome');

// ... but if it's a robot, force him to use 2FA.
ReCaptcha::fakeRobot();

$this->post('login', [
    'email' => 'test@test.com',
    'password' => '123456',
])->assertViewIs('login.2fa');
```

> Fake responses don't come with actions, hostnames or APK package names, so these are not validated.

Alternatively, `fakeScore()` method will fake responses with any score you set.

```php
<?php

use Laragear\ReCaptcha\Facades\ReCaptcha;

// A human comment should be public.
ReCaptcha::fakeScore(0.8);

$this->post('comment', [
    'body' => 'This comment was made by a human',
])->assertSee('Your comment has been posted!');

// Moderate a post if there is no clear separation.
ReCaptcha::fakeScore(0.4);

$this->post('comment', [
    'body' => 'Comment made by something.',
])->assertSee('Your comment will be reviewed before publishing.');

// A robot shouldn't be able to comment.
ReCaptcha::fakeScore(0.2);

$this->post('comment', [
    'body' => 'Comment made by robot.',
])->assertSee('Robots are not welcomed here! Go away!');
```

## PhpStorm stubs

For users of PhpStorm, there is a stub file and a meta file to aid in macro autocompletion for this package. You can publish them using the `phpstorm` tag:

```shell
php artisan vendor:publish --provider="Laragear\ReCaptcha\ReCaptchaServiceProvider" --tag="phpstorm"
```

The file gets published into the `.stubs` folder of your project, while the meta is located inside `.phpstorm.meta.php` directory. You should point your [PhpStorm to these stubs](https://www.jetbrains.com/help/phpstorm/php.html#advanced-settings-area).

## Laravel Octane compatibility

- The only singleton registered is the `ReCaptcha` class, which uses a stale config instance. **You shouldn't change the config**.
- There are no static properties written during a request.

There should be no problems using this package with Laravel Octane as intended.

## HTTP/3

Currently, HTTP/3 is [still on draft state](https://datatracker.ietf.org/doc/draft-ietf-quic-http/). Until it's [Internet Standard](https://en.wikipedia.org/wiki/Internet_Standard), cURL and Guzzle and reCAPTCHA servers must implement the finished state of the protocol, which as of today is still a moving target.

## Security

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

# License

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2022 Laravel LLC.
