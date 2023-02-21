<?php

namespace Tests\Http\Middleware;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Laragear\ReCaptcha\Facades\ReCaptcha as ReCaptchaFacade;
use Laragear\ReCaptcha\Http\Middleware\Builders\ReCaptcha as ReCaptchaBuilder;
use Laragear\ReCaptcha\Http\ReCaptchaResponse;
use Laragear\ReCaptcha\ReCaptcha;
use Laragear\ReCaptcha\ReCaptchaFake;
use Tests\CreatesFulfilledResponse;
use Tests\TestCase;
use function now;

class ScoreMiddlewareTest extends TestCase
{
    use UsesRoutesWithMiddleware;
    use CreatesFulfilledResponse;

    protected function defineEnvironment($app)
    {
        $app['env'] = 'production';
        $app->make('config')->set('recaptcha.enable', true);
    }

    public function test_local_fakes_human_response_automatically(): void
    {
        $this->app['env'] = 'local';
        $this->app->make('config')->set('recaptcha.fake', true);

        $this->travelTo(now());

        $this->post('v3/default')
            ->assertOk()
            ->assertExactJson(
                [
                    'success'          => true,
                    'score'            => 1,
                    'action'           => null,
                    'hostname'         => null,
                    'apk_package_name' => null,
                    'challenge_ts'     => now()->toAtomString(),
                ]
            );
    }

    public function test_local_fakes_robot_response_if_input_is_robot_present(): void
    {
        $this->app['env'] = 'local';
        $this->app->make('config')->set('recaptcha.fake', true);

        $this->travelTo(now());

        $this->post('v3/default', ['is_robot' => 'on'])
            ->assertOk()
            ->assertExactJson(
                [
                    'success'          => true,
                    'score'            => 0,
                    'action'           => null,
                    'hostname'         => null,
                    'apk_package_name' => null,
                    'challenge_ts'     => now()->toAtomString(),
                ]
            );
    }

    public function test_fakes_response_if_authenticated_in_guard(): void
    {
        $this->app->make('router')
            ->post('v3/guarded', Closure::fromCallable([static::class, 'returnSameResponse']))
            ->middleware(ReCaptchaBuilder::score()->forGuests('web'));

        $this->actingAs(User::make(), 'web');

        $this->post('v3/guarded')->assertOk();

        static::assertEquals(1.0, ReCaptchaFacade::response()->score);
        static::assertInstanceOf(ReCaptchaFake::class, $this->app->make(ReCaptcha::class));
    }

    public function test_fakes_response_if_not_enabled(): void
    {
        $this->app->make('config')->set('recaptcha.enable', false);

        $this->post('v3/default')->assertOk();

        static::assertEquals(1.0, $this->app->make(ReCaptchaResponse::class)->score);
        static::assertInstanceOf(ReCaptchaFake::class, $this->app->make(ReCaptcha::class));
    }

    public function test_faking_on_production_will_fake_the_response_anyway(): void
    {
        $this->app->make('config')->set('recaptcha.fake', true);

        $this->post('v3/default')->assertSessionHasNoErrors();

        $this->postJson('v3/default')
            ->assertOk()
            ->assertJsonFragment([
                'success' => true,
                'score'   => 1.0,
            ]);

        static::assertInstanceOf(ReCaptchaFake::class, $this->app->make(ReCaptcha::class));
    }

    public function test_validates_if_real(): void
    {
        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, null)
            ->andReturn(
                $this->fulfilledResponse([
                    'success' => true,
                    'score'   => 0.5,
                    'foo'     => 'bar',
                ])
            );

        $this->post('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'score'   => 0.5,
                'foo'     => 'bar',
            ]);
    }

    public function test_uses_custom_threshold(): void
    {
        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, null)
            ->andReturn(
                $this->fulfilledResponse(['success' => true, 'score' => 0.7, 'foo' => 'bar'])
            );

        $this->app->make('router')->post('test', function (ReCaptchaResponse $response) {
            return [$response->isHuman(), $response->isRobot(), $response->score];
        })->middleware('recaptcha.score:0.7');

        $this->post('test', [ReCaptcha::INPUT => 'token'])
            ->assertOk()
            ->assertExactJson([true, false, 0.7]);
    }

    public function test_uses_custom_input(): void
    {
        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, 'foo', null)
            ->andReturn(
                $this->fulfilledResponse(['success' => true, 'score' => 0.7, 'foo' => 'bar'])
            );

        $this->app->make('router')->post('test', Closure::fromCallable([static::class, 'returnSameResponse']))
            ->middleware('recaptcha.score:null,null,foo');

        $this->post('test', ['foo' => 'token'])
            ->assertOk()
            ->assertExactJson(['success' => true, 'score' => 0.7, 'foo' => 'bar']);
    }

    public function test_fakes_human_score_if_authenticated_in_default_guard(): void
    {
        $this->mock(ReCaptcha::class)->allows('getChallenge')->never();

        $this->actingAs(new GenericUser([]));

        $this->app->make('router')->post('score/auth', Closure::fromCallable([static::class, 'returnSameResponse']))
            ->middleware('recaptcha.score:0.5,null,null,null');

        $this->post('/score/auth')->assertOk();
    }

    public function test_fakes_human_score_if_authenticated_in_one_of_given_guards(): void
    {
        $this->app->make('config')->set('auth.guards.api', [
            'driver' => 'session',
            'provider' => 'users',
        ]);

        $this->mock(ReCaptcha::class)->expects('getChallenge')->never();

        $this->actingAs(new GenericUser([]), 'api');

        $this->app->make('router')->post('score/auth', Closure::fromCallable([static::class, 'returnSameResponse']))
            ->middleware('recaptcha.score:0.5,null,null,web,api');

        $this->post('/score/auth')->assertOk();
    }

    public function test_error_if_is_guest(): void
    {
        $this->app->make('config')->set('auth.guards.api', [
            'driver' => 'session',
            'provider' => 'users',
        ]);

        $this->mock(ReCaptcha::class)->expects('getChallenge')->never();

        $this->actingAs(new GenericUser([]));

        $this->app->make('router')->post('score/auth', Closure::fromCallable([static::class, 'returnSameResponse']))
            ->middleware('recaptcha.score:0.5,null,null,api');

        $this->post('/score/auth')
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.missing'))
            ->assertRedirect('/');
    }

    public function test_exception_when_token_absent(): void
    {
        $this->post('v3/default', ['foo' => 'bar'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.error'))
            ->assertRedirect('/');

        $this->postJson('v3/default', ['foo' => 'bar'])
            ->assertJsonValidationErrors(ReCaptcha::INPUT);
    }

    public function test_exception_when_token_null(): void
    {
        $this->post('v3/default', [ReCaptcha::INPUT => null])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.error'))
            ->assertRedirect('/');

        $this->postJson('v3/default', [ReCaptcha::INPUT => null])
            ->assertJsonValidationErrors(ReCaptcha::INPUT);
    }

    public function test_exception_when_response_invalid(): void
    {
        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, null)
            ->andReturn(
                $this->fulfilledResponse(['success' => false, 'score' => 0.7, 'foo' => 'bar'])
            );

        $this->post('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.error'))
            ->assertRedirect('/');

        $this->postJson('v3/default', ['foo' => 'bar'])
            ->assertJsonValidationErrors(ReCaptcha::INPUT);
    }

    public function test_no_error_if_not_hostname_issued(): void
    {
        $this->app->make('config')->set('recaptcha.hostname', null);

        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->twice()
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, null)
            ->andReturn(
                $this->fulfilledResponse(['success' => true, 'score' => 0.7, 'hostname' => 'foo'])
            );

        $this->post('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertOk();

        $this->postJson('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertOk();
    }

    public function test_no_error_if_hostname_same(): void
    {
        $this->app->make('config')->set('recaptcha.hostname', 'bar');

        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->twice()
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, null)
            ->andReturn(
                $this->fulfilledResponse(['success' => true, 'score' => 0.7, 'hostname' => 'bar'])
            );

        $this->post('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertOk();

        $this->postJson('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertOk();
    }

    public function test_exception_if_hostname_not_equal(): void
    {
        $this->app->make('config')->set('recaptcha.hostname', 'bar');

        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->twice()
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, null)
            ->andReturn(
                $this->fulfilledResponse(['success' => true, 'score' => 0.7, 'hostname' => 'foo'])
            );

        $this->post('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.match'))
            ->assertRedirect('/');

        $this->postJson('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertJsonValidationErrors(ReCaptcha::INPUT);
    }

    public function test_no_error_if_no_apk_issued(): void
    {
        $this->app->make('config')->set('recaptcha.apk_package_name', null);

        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->twice()
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, null)
            ->andReturn(
                $this->fulfilledResponse(['success' => true, 'score' => 0.7, 'apk_package_name' => 'foo'])
            );

        $this->post('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertOk();

        $this->postJson('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertOk();
    }

    public function test_no_error_if_apk_same(): void
    {
        $this->app->make('config')->set('recaptcha.apk_package_name', 'foo');

        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->twice()
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, null)
            ->andReturn(
                $this->fulfilledResponse(['success' => true, 'score' => 0.7, 'apk_package_name' => 'foo'])
            );

        $this->post('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertOk();

        $this->postJson('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertOk();
    }

    public function test_exception_if_apk_not_equal(): void
    {
        $this->app->make('config')->set('recaptcha.apk_package_name', 'bar');

        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->twice()
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, null)
            ->andReturn(
                $this->fulfilledResponse(['success' => true, 'score' => 0.7, 'apk_package_name' => null])
            );

        $this->post('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.match'))
            ->assertRedirect('/');

        $this->postJson('v3/default', [ReCaptcha::INPUT => 'token'])
            ->assertJsonValidationErrors(ReCaptcha::INPUT);
    }

    public function test_no_error_if_no_action(): void
    {
        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, null)
            ->andReturn(
                $this->fulfilledResponse(['success' => true, 'action' => 'foo', 'apk_package_name' => null])
            );

        $this->app->make('router')->post('test', Closure::fromCallable([static::class, 'returnSameResponse']))
            ->middleware('recaptcha.score:null,null');

        $this->post('test', [ReCaptcha::INPUT => 'token'])->assertOk();
    }

    public function test_no_error_if_action_same(): void
    {
        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, 'foo')
            ->andReturn(
                $this->fulfilledResponse(['success' => true, 'action' => 'foo', 'apk_package_name' => null])
            );

        $this->app->make('router')->post('test', Closure::fromCallable([static::class, 'returnSameResponse']))
            ->middleware('recaptcha.score:null,foo');

        $this->post('test', [ReCaptcha::INPUT => 'token'])->assertOk();
    }

    public function test_exception_if_action_not_equal(): void
    {
        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->twice()
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, 'bar')
            ->andReturn(
                $this->fulfilledResponse(
                    ['success' => true, 'action' => 'foo', 'apk_package_name' => null],
                    ReCaptcha::INPUT,
                    'bar'
                )
            );

        $this->app->make('router')->post('test', Closure::fromCallable([static::class, 'returnSameResponse']))
            ->middleware('recaptcha.score:null,bar');

        $this->post('test', [ReCaptcha::INPUT => 'token'])
            ->assertSessionHasErrors(ReCaptcha::INPUT, $this->app->make('translator')->get('recaptcha::validation.match'))
            ->assertRedirect('/');
        $this->postJson('test', [ReCaptcha::INPUT => 'token'])
            ->assertJsonValidationErrors(ReCaptcha::INPUT);
    }

    public function test_checks_for_human_score(): void
    {
        $this->app->make('config')->set([
            'recaptcha.credentials.score.secret' => 'secret',
        ]);

        $mock = $this->mock(Factory::class);

        $mock->expects('async')->withNoArgs()->times(4)->andReturnSelf();
        $mock->expects('asForm')->withNoArgs()->times(4)->andReturnSelf();
        $mock->expects('withOptions')->with(['version' => 2.0])->times(4)->andReturnSelf();
        $mock->expects('post')
            ->with(
                ReCaptcha::SERVER_ENDPOINT,
                [
                    'secret'   => 'secret',
                    'response' => 'token',
                    'remoteip' => '127.0.0.1',
                ]
            )
            ->times(4)
            ->andReturn(
                $this->fulfilledPromise([
                    'success' => true,
                    'score'   => 0.5,
                ])
            );

        $this->app->make('router')->post(
            'human_human',
            function (Request $request) {
                return $request->isHuman() ? 'true' : 'false';
            }
        )->middleware('recaptcha.score:0.7');

        $this->app->make('router')->post(
            'human_robot',
            function (Request $request) {
                return $request->isRobot() ? 'true' : 'false';
            }
        )->middleware('recaptcha.score:0.7');

        $this->app->make('router')->post(
            'robot_human',
            function (Request $request) {
                return $request->isHuman() ? 'true' : 'false';
            }
        )->middleware('recaptcha.score:0.3');

        $this->app->make('router')->post(
            'robot_robot',
            function (Request $request) {
                return $request->isRobot() ? 'true' : 'false';
            }
        )->middleware('recaptcha.score:0.3');

        $this->post('human_human', [ReCaptcha::INPUT => 'token'])->assertSee('false');
        $this->post('human_robot', [ReCaptcha::INPUT => 'token'])->assertSee('true');
        $this->post('robot_human', [ReCaptcha::INPUT => 'token'])->assertSee('true');
        $this->post('robot_robot', [ReCaptcha::INPUT => 'token'])->assertSee('false');
    }

    public function test_unsuccessful_returns_redirect()
    {
        $referer = 'http://127.0.0.1/referer';

        $response = $this->fulfilledResponse([
            'success' => false,
            'errors' => ['foo', 'bar'],
        ]);

        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->with('token', '127.0.0.1', ReCaptcha::SCORE, ReCaptcha::INPUT, null)
            ->andReturn($response);

        $this->post('v3/default', [ReCaptcha::INPUT => 'token'], ['HTTP_REFERER' => $referer])
            ->assertRedirect($referer)
            ->assertSessionHasErrors();
    }
}
