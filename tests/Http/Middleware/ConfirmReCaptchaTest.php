<?php

namespace Tests\Http\Middleware;

use Illuminate\Auth\GenericUser;
use Laragear\ReCaptcha\Http\Controllers\ConfirmationController;
use Laragear\ReCaptcha\ReCaptcha;
use Tests\CreatesFulfilledResponse;
use Tests\TestCase;

use function now;

use const INF;

class ConfirmReCaptchaTest extends TestCase
{
    use CreatesFulfilledResponse;

    protected function defineWebRoutes($router): void
    {
        $router->get('intended', function () {
            return 'ok';
        })->middleware('recaptcha.confirm');

        $router->get('intended/guard', function () {
            return 'ok';
        })->middleware('recaptcha.confirm:recaptcha.confirm,web');

        $router->get('confirm', [ConfirmationController::class, 'show'])
            ->name('recaptcha.confirm');

        $router->post('confirm', [ConfirmationController::class, 'confirm']);
    }

    protected function defineEnvironment($app)
    {
        $app->make('config')->set([
            'recaptcha.enable' => true,
            'auth.guards.api' => ['driver' => 'session', 'provider' => 'users'],
        ]);
    }

    public function test_redirects_to_form_with_view(): void
    {
        $this->followingRedirects()->get('intended')->assertViewIs('recaptcha::confirm');
    }

    public function test_redirects_to_form_with_remember_and_remember_disabled(): void
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', false);
        $this->session([
            '_recaptcha' => INF,
        ]);

        $this->get('intended')->assertRedirect('confirm');
    }

    public function test_redirects_to_form_without_remember_and_remember_enabled(): void
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', true);

        $this->get('intended')->assertRedirect('confirm');
    }

    public function test_redirects_to_form_with_remember_expired_and_remember_enabled(): void
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', true);
        $this->session([
            '_recaptcha' => 0,
        ]);

        $this->get('intended')->assertRedirect('confirm');
    }

    public function test_doesnt_redirects_if_has_remember_and_remember_enabled(): void
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', true);
        $this->session([
            '_recaptcha' => INF,
        ]);

        $this->get('intended')->assertSee('ok');
    }

    public function test_redirects_even_authenticated(): void
    {
        $this->actingAs(new GenericUser([]));

        $this->get('intended')->assertRedirect('confirm');
    }

    public function test_redirects_if_guards_not_in_middleware_declaration()
    {
        $this->actingAs(new GenericUser([]), 'api');

        $this->get('intended/guard')->assertRedirect('confirm');
    }

    public function test_doesnt_redirects_if_user_in_guard(): void
    {
        $this->actingAs(new GenericUser([]), 'web');

        $this->get('intended/guard')->assertSee('ok');
    }

    public function test_confirmation_redirects_to_intended(): void
    {
        $this->followingRedirects()
            ->get('intended')
            ->assertViewIs('recaptcha::confirm')
            ->assertSessionHas('url.intended');

        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->with('token', '127.0.0.1', ReCaptcha::CHECKBOX, ReCaptcha::INPUT, null)
            ->andReturn($this->fulfilledResponse());

        $this->post('confirm', [ReCaptcha::INPUT => 'token'])
            ->assertRedirect('/intended')
            ->assertSessionMissing('url.intended');
    }

    public function test_confirmation_sets_remember_when_enabled()
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', true);
        $this->session([
            'url.intended' => '/intended',
        ]);

        $this->travelTo(now());

        $this->mock(ReCaptcha::class)
            ->expects('getChallenge')
            ->with('token', '127.0.0.1', ReCaptcha::CHECKBOX, ReCaptcha::INPUT, null)
            ->andReturn($this->fulfilledResponse());

        $this->post('confirm', [ReCaptcha::INPUT => 'token'])
            ->assertRedirect('/intended')
            ->assertSessionHas('_recaptcha', now()->addMinutes(10)->getTimestamp());
    }

    public function test_confirmation_without_checkbox_returns_error(): void
    {
        $this->mock(ReCaptcha::class)->expects('getChallenge')->never();

        $this->post('confirm')
            ->assertSessionHasErrorsIn(ReCaptcha::INPUT);
    }

    public function test_confirmation_bypass_if_remembered(): void
    {
        $this->app->make('config')->set('recaptcha.remember.enabled', true);
        $this->session([
            '_recaptcha' => INF,
            'url.intended' => '/intended',
        ]);

        $this->mock(ReCaptcha::class)->expects('getChallenge')->never();

        $this->post('confirm', [ReCaptcha::INPUT => 'token'])
            ->assertRedirect('/intended')
            ->assertSessionHas('_recaptcha', INF);
    }
}
