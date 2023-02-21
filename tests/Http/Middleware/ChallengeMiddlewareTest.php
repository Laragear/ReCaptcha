<?php

namespace Tests\Http\Middleware;

use Closure;
use Illuminate\Auth\GenericUser;
use Laragear\ReCaptcha\Http\ReCaptchaResponse;
use Laragear\ReCaptcha\ReCaptcha;
use LogicException;
use Tests\CreatesFulfilledResponse;
use Tests\TestCase;
use function now;
use const INF;

class ChallengeMiddlewareTest extends TestCase
{
    use UsesRoutesWithMiddleware;
    use CreatesFulfilledResponse;

    protected function defineEnvironment($app)
    {
        $app['env'] = 'production';

        $app->make('config')->set([
            'recaptcha.enable' => true,
            'recaptcha.fake' => false,
        ]);
    }

    public function test_exception_if_declaring_v2_middleware_as_score(): void
    {
        $this->app->make('router')->post('v2/score', function () {
            // ...
        })->middleware('recaptcha:score');

        $exception = $this->post('v2/score')->assertStatus(500)->exception;

        static::assertInstanceOf(LogicException::class, $exception);
        static::assertSame(
            'Use the [recaptcha.score] middleware to capture score-driven challenges.',
            $exception->getMessage()
        );
    }

    public function test_exception_if_no_challenge_specified(): void
    {
        $this->app->make('config')->set('app.debug', false);

        $this->app->make('router')->post('test', function () {
            return 'ok';
        })->middleware('recaptcha');

        $this->post('test')->assertStatus(500);

        $this->postJson('test')->assertJson(['message' => 'Server Error']);
    }

    public function test_bypass_if_not_enabled(): void
    {
        $this->app->make('config')->set('recaptcha.enable', false);

        $this->post('v2/checkbox')->assertOk();
        $this->post('v2/invisible')->assertOk();
        $this->post('v2/android')->assertOk();
    }

    public function test_bypasses_if_authenticated_on_default_guard(): void
    {
        $this->mock(ReCaptcha::class)->allows('getChallenge')->never();

        $this->actingAs(new GenericUser([]));

        $this->app->make('router')
            ->post('checkbox/auth', Closure::fromCallable([static::class, 'returnResponseIfExists']))
            ->middleware('recaptcha:checkbox,null,null,null');
        $this->app->make('router')
            ->post('invisible/auth', Closure::fromCallable([static::class, 'returnResponseIfExists']))
            ->middleware('recaptcha:invisible,null,null,null');
        $this->app->make('router')
            ->post('android/auth', Closure::fromCallable([static::class, 'returnResponseIfExists']))
            ->middleware('recaptcha:android,null,null,null');

        $this->post('/checkbox/auth')->assertOk();
        $this->post('/invisible/auth')->assertOk();
        $this->post('/android/auth')->assertOk();
    }

    public function test_bypasses_if_authenticated_on_one_of_given_guard(): void
    {
        $this->app->make('config')->set('auth.guards.api', [
            'driver' => 'session',
            'provider' => 'users',
        ]);

        $this->mock(ReCaptcha::class)->allows('getChallenge')->never();

        $this->actingAs(new GenericUser([]), 'api');

        $this->app->make('router')
            ->post('checkbox/auth', Closure::fromCallable([static::class, 'returnResponseIfExists']))
            ->middleware('recaptcha:checkbox,null,null,web,api');
        $this->app->make('router')
            ->post('invisible/auth', Closure::fromCallable([static::class, 'returnResponseIfExists']))
            ->middleware('recaptcha:invisible,null,null,web,api');
        $this->app->make('router')
            ->post('android/auth', Closure::fromCallable([static::class, 'returnResponseIfExists']))
            ->middleware('recaptcha:android,null,null,web,api');

        $this->post('/checkbox/auth')->assertOk();
        $this->post('/invisible/auth')->assertOk();
        $this->post('/android/auth')->assertOk();
    }

    public function test_error_if_guest(): void
    {
        $this->mock(ReCaptcha::class)->allows('getChallenge')->never();

        $this->app->make('router')->post('checkbox/auth', [__CLASS__, 'returnResponseIfExists'])
            ->middleware('recaptcha:checkbox,null,null,null');
        $this->app->make('router')->post('invisible/auth', [__CLASS__, 'returnResponseIfExists'])
            ->middleware('recaptcha:invisible,null,null,null');
        $this->app->make('router')->post('android/auth', [__CLASS__, 'returnResponseIfExists'])
            ->middleware('recaptcha:android,null,null,null');

        $this->post('/checkbox/auth')
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->post('/invisible/auth')
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->post('/android/auth')
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
    }

    public function test_error_if_guest_on_given_guard(): void
    {
        $this->app->make('config')->set('auth.guards.api', [
            'driver' => 'session',
            'provider' => 'users',
        ]);

        $this->mock(ReCaptcha::class)->allows('getChallenge')->never();

        $this->actingAs(new GenericUser([]));

        $this->app->make('router')->post('checkbox/auth', [__CLASS__, 'returnResponseIfExists'])
            ->middleware('recaptcha:checkbox,null,null,api');
        $this->app->make('router')->post('invisible/auth', [__CLASS__, 'returnResponseIfExists'])
            ->middleware('recaptcha:invisible,null,null,api');
        $this->app->make('router')->post('android/auth', [__CLASS__, 'returnResponseIfExists'])
            ->middleware('recaptcha:android,null,null,api');

        $this->post('/checkbox/auth')
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->post('/invisible/auth')
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->post('/android/auth')
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
    }

    public function test_success_when_enabled_and_fake(): void
    {
        $this->app->make('config')->set('recaptcha.enable', true);
        $this->app->make('config')->set('recaptcha.fake', true);

        $this->post('v2/checkbox')->assertOk();
        $this->post('v2/checkbox/input_bar')->assertOk();
        $this->post('v2/invisible')->assertOk();
        $this->post('v2/invisible/input_bar')->assertOk();
        $this->post('v2/android')->assertOk();
        $this->post('v2/android/input_bar')->assertOk();
    }

    public function test_success_when_disabled(): void
    {
        $this->app->make('config')->set('recaptcha.enable', false);

        $this->post('v2/checkbox')->assertOk();
        $this->post('v2/checkbox/input_bar')->assertOk();
        $this->post('v2/invisible')->assertOk();
        $this->post('v2/invisible/input_bar')->assertOk();
        $this->post('v2/android')->assertOk();
        $this->post('v2/android/input_bar')->assertOk();
    }

    public function test_validates_if_real(): void
    {
        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])->assertOk();
        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])->assertOk();
        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])->assertOk();
    }

    public function test_uses_custom_input(): void
    {
        $response = $this->fulfilledResponse([
            'success' => true,
            'score' => 0.5,
            'foo' => 'bar',
        ], 'bar');

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', 'bar', null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', 'bar', null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', 'bar', null)->andReturn($response);

        $this->post('v2/checkbox/input_bar', ['bar' => 'token'])->assertOk();
        $this->post('v2/invisible/input_bar', ['bar' => 'token'])->assertOk();
        $this->post('v2/android/input_bar', ['bar' => 'token'])->assertOk();
    }

    public function test_exception_when_token_absent(): void
    {
        $this->mock(ReCaptcha::class)->expects('getChallenge')->never();

        $this->post('v2/checkbox')
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->postJson('v2/checkbox')->assertJsonValidationErrors(ReCaptcha::INPUT);
        $this->post('v2/invisible')
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->postJson('v2/invisible')->assertJsonValidationErrors(ReCaptcha::INPUT);
        $this->post('v2/android')
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->postJson('v2/android')->assertJsonValidationErrors(ReCaptcha::INPUT);

        $this->post('v2/checkbox/input_bar')
            ->assertSessionHasErrors('bar', $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->postJson('v2/checkbox/input_bar')->assertJsonValidationErrors('bar');
        $this->post('v2/invisible/input_bar')
            ->assertSessionHasErrors('bar', $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->postJson('v2/invisible/input_bar')->assertJsonValidationErrors('bar');
        $this->post('v2/android/input_bar')
            ->assertSessionHasErrors('bar', $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->postJson('v2/android/input_bar')->assertJsonValidationErrors('bar');
    }

    public function test_exception_when_token_null(): void
    {
        $this->mock(ReCaptcha::class)->expects('getChallenge')->never();

        $this->post('v2/checkbox', [ReCaptcha::INPUT => null])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->postJson('v2/checkbox')->assertJsonValidationErrors(ReCaptcha::INPUT);
        $this->post('v2/invisible', [ReCaptcha::INPUT => null])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->postJson('v2/invisible')->assertJsonValidationErrors(ReCaptcha::INPUT);
        $this->post('v2/android', [ReCaptcha::INPUT => null])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->postJson('v2/android', [ReCaptcha::INPUT => null])->assertJsonValidationErrors(ReCaptcha::INPUT);

        $this->post('v2/checkbox/input_bar', ['bar' => null])
            ->assertSessionHasErrors('bar', $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->postJson('v2/checkbox/input_bar')->assertJsonValidationErrors('bar');
        $this->post('v2/invisible/input_bar', ['bar' => null])
            ->assertSessionHasErrors('bar', $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->postJson('v2/invisible/input_bar')->assertJsonValidationErrors('bar');
        $this->post('v2/android/input_bar', ['bar' => null])
            ->assertSessionHasErrors('bar', $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
        $this->postJson('v2/android/input_bar', ['bar' => null])->assertJsonValidationErrors('bar');
    }

    public function test_exception_when_response_failed(): void
    {
        $response = $this->fulfilledResponse([
            'success' => false,
            'foo' => 'bar',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->twice()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->twice()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->twice()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.error'))
            ->assertRedirect('/');
        $this->postJson('v2/checkbox', [ReCaptcha::INPUT => 'token'])->assertJsonValidationErrors(ReCaptcha::INPUT);
        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.error'))
            ->assertRedirect('/');
        $this->postJson('v2/invisible', [ReCaptcha::INPUT => 'token'])->assertJsonValidationErrors(ReCaptcha::INPUT);
        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.error'))
            ->assertRedirect('/');
        $this->postJson('v2/android', [ReCaptcha::INPUT => 'token'])->assertJsonValidationErrors(ReCaptcha::INPUT);
    }

    public function test_exception_when_response_invalid(): void
    {
        $response = $this->fulfilledResponse([
            'foo' => 'bar',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->twice()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->twice()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->twice()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.error'))
            ->assertRedirect('/');
        $this->postJson('v2/checkbox', [ReCaptcha::INPUT => 'token'])->assertJsonValidationErrors(ReCaptcha::INPUT);
        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.error'))
            ->assertRedirect('/');
        $this->postJson('v2/invisible', [ReCaptcha::INPUT => 'token'])->assertJsonValidationErrors(ReCaptcha::INPUT);
        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.error'))
            ->assertRedirect('/');
        $this->postJson('v2/android', [ReCaptcha::INPUT => 'token'])->assertJsonValidationErrors(ReCaptcha::INPUT);
    }

    public function test_no_error_if_not_hostname_issued(): void
    {
        $this->app->make('config')->set('recaptcha.hostname', null);

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])->assertOk();
        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])->assertOk();
        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])->assertOk();
    }

    public function test_no_error_if_not_hostname_same(): void
    {
        $this->app->make('config')->set('recaptcha.hostname', 'foo');

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
            'hostname' => 'foo',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])->assertOk();
        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])->assertOk();
        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])->assertOk();
    }

    public function test_exception_if_hostname_not_equal(): void
    {
        $this->app->make('config')->set('recaptcha.hostname', 'bar');

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
            'hostname' => 'foo',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->twice()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->twice()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->twice()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.match'))
            ->assertRedirect('/');
        $this->postJson('v2/checkbox', [ReCaptcha::INPUT => 'token'])->assertJsonValidationErrors(ReCaptcha::INPUT);
        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.match'))
            ->assertRedirect('/');
        $this->postJson('v2/invisible', [ReCaptcha::INPUT => 'token'])->assertJsonValidationErrors(ReCaptcha::INPUT);
        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.match'))
            ->assertRedirect('/');
        $this->postJson('v2/android', [ReCaptcha::INPUT => 'token'])->assertJsonValidationErrors(ReCaptcha::INPUT);
    }

    public function test_no_error_if_no_apk_issued(): void
    {
        $this->app->make('config')->set('recaptcha.apk_package_name', null);

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
            'apk_package_name' => 'foo',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])->assertOk();
        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])->assertOk();
        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])->assertOk();
    }

    public function test_no_error_if_no_apk_same(): void
    {
        $this->app->make('config')->set('recaptcha.apk_package_name', 'foo');

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
            'apk_package_name' => 'foo',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])->assertOk();
        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])->assertOk();
        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])->assertOk();
    }

    public function test_exception_if_apk_not_equal(): void
    {
        $this->app->make('config')->set('recaptcha.apk_package_name', 'bar');

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
            'apk_package_name' => 'foo',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->twice()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->twice()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->twice()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.match'))
            ->assertRedirect('/');
        $this->postJson('v2/checkbox', [ReCaptcha::INPUT => 'token'])->assertJsonValidationErrors(ReCaptcha::INPUT);
        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.match'))
            ->assertRedirect('/');
        $this->postJson('v2/invisible', [ReCaptcha::INPUT => 'token'])->assertJsonValidationErrors(ReCaptcha::INPUT);
        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.match'))
            ->assertRedirect('/');
        $this->postJson('v2/android', [ReCaptcha::INPUT => 'token'])->assertJsonValidationErrors(ReCaptcha::INPUT);
    }

    public function test_challenge_is_not_remembered_by_default(): void
    {
        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
            'apk_package_name' => 'foo',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionMissing('_recaptcha');
        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionMissing('_recaptcha');
        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionMissing('_recaptcha');
    }

    public function test_challenge_is_remembered_in_session(): void
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', true);

        $this->travelTo($now = now());

        $timestamp = $now->clone()->addMinutes(10)->getTimestamp();

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', $timestamp);

        $this->flushSession();

        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', $timestamp);

        $this->flushSession();

        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', $timestamp);
    }

    public function test_challenge_is_remembered_in_session_when_config_overridden(): void
    {
        $this->travelTo($now = now());

        $timestamp = $now->clone()->addMinutes(10)->getTimestamp();

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox/remember', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', $timestamp);

        $this->flushSession();

        $this->post('v2/invisible/remember', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', $timestamp);

        $this->flushSession();

        $this->post('v2/android/remember', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', $timestamp);
    }

    public function test_challenge_is_remembered_in_session_using_custom_key(): void
    {
        $this->app->make('config')->set([
            'recaptcha.remember.enabled' => true,
            'recaptcha.remember.key' => 'foo',
        ]);

        $this->travelTo($now = now());

        $timestamp = $now->clone()->addMinutes(10)->getTimestamp();

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('foo', $timestamp);

        $this->flushSession();

        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('foo', $timestamp);

        $this->flushSession();

        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('foo', $timestamp);
    }

    public function test_challenge_is_remembered_in_session_with_custom_key_when_config_overridden(): void
    {
        $this->app->make('config')->set('recaptcha.remember.key', 'foo');

        $this->travelTo($now = now());

        $timestamp = $now->clone()->addMinutes(10)->getTimestamp();

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox/remember', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('foo', $timestamp);

        $this->flushSession();

        $this->post('v2/invisible/remember', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('foo', $timestamp);

        $this->flushSession();

        $this->post('v2/android/remember', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('foo', $timestamp);
    }

    public function test_challenge_is_remembered_forever(): void
    {
        $this->app->make('config')->set([
            'recaptcha.remember.enabled' => true,
            'recaptcha.remember.minutes' => INF,
        ]);

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->app->make('router')->post('v2/checkbox/forever', function () {
            if ($this->app->has(ReCaptchaResponse::class)) {
                return $this->app->make(ReCaptchaResponse::class);
            }
        })->middleware('recaptcha:checkbox');

        $this->app->make('router')->post('v2/invisible/forever', function () {
            if ($this->app->has(ReCaptchaResponse::class)) {
                return $this->app->make(ReCaptchaResponse::class);
            }
        })->middleware('recaptcha:invisible');

        $this->app->make('router')->post('v2/android/forever', function () {
            if ($this->app->has(ReCaptchaResponse::class)) {
                return $this->app->make(ReCaptchaResponse::class);
            }
        })->middleware('recaptcha:android');

        $this->post('v2/checkbox/forever', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', INF);

        $this->flushSession();

        $this->post('v2/invisible/forever', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', INF);

        $this->flushSession();

        $this->post('v2/android/forever', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', INF);
    }

    public function test_challenge_is_remembered_forever_when_config_overridden(): void
    {
        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->app->make('router')->post('v2/checkbox/forever', function () {
            if ($this->app->has(ReCaptchaResponse::class)) {
                return $this->app->make(ReCaptchaResponse::class);
            }
        })->middleware('recaptcha:checkbox,inf');

        $this->app->make('router')->post('v2/invisible/forever', function () {
            if ($this->app->has(ReCaptchaResponse::class)) {
                return $this->app->make(ReCaptchaResponse::class);
            }
        })->middleware('recaptcha:invisible,infinite');

        $this->app->make('router')->post('v2/android/forever', function () {
            if ($this->app->has(ReCaptchaResponse::class)) {
                return $this->app->make(ReCaptchaResponse::class);
            }
        })->middleware('recaptcha:android,forever');

        $this->post('v2/checkbox/forever', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', INF);

        $this->flushSession();

        $this->post('v2/invisible/forever', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', INF);

        $this->flushSession();

        $this->post('v2/android/forever', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', INF);
    }

    public function test_challenge_is_remembered_with_different_offset(): void
    {
        $this->app->make('config')->set([
            'recaptcha.remember.enabled' => true,
            'recaptcha.remember.minutes' => 30,
        ]);

        $this->travelTo($now = now());

        $timestamp = $now->clone()->addMinutes(30)->getTimestamp();

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', $timestamp);

        $this->flushSession();

        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', $timestamp);

        $this->flushSession();

        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionHas('_recaptcha', $timestamp);
    }

    public function test_challenge_is_not_remembered_when_config_overridden(): void
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', true);

        $response = $this->fulfilledResponse([
            'success' => true,
            'foo' => 'bar',
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->app->make('router')->post('v2/checkbox/dont-remember', function () {
            if ($this->app->has(ReCaptchaResponse::class)) {
                return $this->app->make(ReCaptchaResponse::class);
            }
        })->middleware('recaptcha:checkbox,false');

        $this->app->make('router')->post('v2/invisible/dont-remember', function () {
            if ($this->app->has(ReCaptchaResponse::class)) {
                return $this->app->make(ReCaptchaResponse::class);
            }
        })->middleware('recaptcha:invisible,false');

        $this->app->make('router')->post('v2/android/dont-remember', function () {
            if ($this->app->has(ReCaptchaResponse::class)) {
                return $this->app->make(ReCaptchaResponse::class);
            }
        })->middleware('recaptcha:android,false');

        $this->post('v2/checkbox/dont-remember', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionMissing('_recaptcha');

        $this->post('v2/invisible/dont-remember', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionMissing('_recaptcha');

        $this->post('v2/android/dont-remember', [ReCaptcha::INPUT => 'token'])
            ->assertOk()->assertSessionMissing('_recaptcha');
    }

    public function test_bypasses_check_if_session_has_remember_not_expired(): void
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', true);

        $this->session([
            '_recaptcha' => now()->addMinute()->getTimestamp(),
        ]);

        $this->mock(ReCaptcha::class)->expects('getChallenge')->never();

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'])->assertOk()->assertSessionHas('_recaptcha');
        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'])->assertOk()->assertSessionHas('_recaptcha');
        $this->post('v2/android', [ReCaptcha::INPUT => 'token'])->assertOk()->assertSessionHas('_recaptcha');
    }

    public function test_bypasses_check_if_session_has_remember_forever(): void
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', true);

        $this->session([
            '_recaptcha' => INF,
        ]);

        $this->mock(ReCaptcha::class)->expects('getChallenge')->never();

        $this->post('v2/checkbox')->assertOk()->assertSessionHas('_recaptcha');
        $this->post('v2/invisible')->assertOk()->assertSessionHas('_recaptcha');
        $this->post('v2/android')->assertOk()->assertSessionHas('_recaptcha');
    }

    public function test_doesnt_bypasses_check_if_session_has_not_remember(): void
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', true);

        $this->mock(ReCaptcha::class)->expects('getChallenge')->never();

        $this->post('v2/checkbox')->assertSessionHasErrors();
        $this->post('v2/invisible')->assertSessionHasErrors();
        $this->post('v2/android')->assertSessionHasErrors();
    }

    public function test_doesnt_bypasses_check_if_remember_has_expired_and_deletes_key(): void
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', true);

        $this->session(['_recaptcha' => now()->subSecond()->getTimestamp()]);

        $this->mock(ReCaptcha::class)->expects('getChallenge')->never();

        $this->post('v2/checkbox')->assertSessionHasErrors()->assertSessionMissing('_recaptcha');
        $this->post('v2/invisible')->assertSessionHasErrors()->assertSessionMissing('_recaptcha');
        $this->post('v2/android')->assertSessionHasErrors()->assertSessionMissing('_recaptcha');
    }

    public function test_doesnt_bypasses_check_if_remember_disabled_when_config_overridden(): void
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', true);

        $this->mock(ReCaptcha::class)->expects('getChallenge')->never();

        $this->app->make('router')->post('v2/checkbox/dont-remember', function () {
            if ($this->app->has(ReCaptchaResponse::class)) {
                return $this->app->make(ReCaptchaResponse::class);
            }
        })->middleware('recaptcha:checkbox,false');

        $this->app->make('router')->post('v2/invisible/dont-remember', function () {
            if ($this->app->has(ReCaptchaResponse::class)) {
                return $this->app->make(ReCaptchaResponse::class);
            }
        })->middleware('recaptcha:invisible,false');

        $this->app->make('router')->post('v2/android/dont-remember', function () {
            if ($this->app->has(ReCaptchaResponse::class)) {
                return $this->app->make(ReCaptchaResponse::class);
            }
        })->middleware('recaptcha:android,false');

        $this->post('v2/checkbox/dont-remember')->assertSessionHasErrors();
        $this->post('v2/invisible/dont-remember')->assertSessionHasErrors();
        $this->post('v2/android/dont-remember')->assertSessionHasErrors();
    }

    public function test_unsuccessful_returns_redirect()
    {
        $referer = 'http://127.0.0.1/referer';

        $response = $this->fulfilledResponse([
            'success' => false,
            'errors' => ['foo', 'bar'],
        ]);

        $mock = $this->mock(ReCaptcha::class);

        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT, null)->andReturn($response);
        $mock->expects('getChallenge')
            ->once()->with('token', '127.0.0.1', 'android', ReCaptcha::INPUT, null)->andReturn($response);

        $this->post('v2/checkbox', [ReCaptcha::INPUT => 'token'], ['HTTP_REFERER' => $referer])
            ->assertRedirect($referer)
            ->assertSessionHasErrors();
        $this->post('v2/invisible', [ReCaptcha::INPUT => 'token'], ['HTTP_REFERER' => $referer])
            ->assertRedirect($referer)
        ->assertSessionHasErrors();
        $this->post('v2/android', [ReCaptcha::INPUT => 'token'], ['HTTP_REFERER' => $referer])
            ->assertRedirect($referer)
            ->assertSessionHasErrors();
    }
}
