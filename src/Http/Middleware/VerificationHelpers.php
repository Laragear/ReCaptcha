<?php

namespace Laragear\ReCaptcha\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laragear\ReCaptcha\Facades\ReCaptcha as ReCaptchaFacade;
use Laragear\ReCaptcha\Http\ReCaptchaResponse;
use Laragear\ReCaptcha\ReCaptcha;
use function app;
use function auth;
use function back;
use function strtolower;
use function trans;

/**
 * @internal
 */
trait VerificationHelpers
{
    /**
     * Normalize the input name.
     *
     * @param  string  $input
     * @return string
     */
    protected function normalizeInput(string $input): string
    {
        return strtolower($input) === 'null' ? ReCaptcha::INPUT : $input;
    }

    /**
     * Check if ReCaptcha is enabled globally.
     *
     * @return bool
     */
    protected function isDisabled(): bool
    {
        return !$this->config->get('recaptcha.enable');
    }

    /**
     * Check if the application is running under unit testing.
     *
     * @return bool
     */
    protected function isTesting(): bool
    {
        return app()->runningUnitTests();
    }

    /**
     * Check if the application is nor running under unit testing.
     *
     * @return bool
     */
    protected function isNotTesting(): bool
    {
        return ! $this->isTesting();
    }

    /**
     * Check if the reCAPTCHA services should be faked.
     *
     * @return bool
     */
    protected function isFaking(): bool
    {
        return $this->config->get('recaptcha.fake');
    }

    /**
     * Check if the reCAPTCHA challenge remembering is enabled.
     *
     * @return bool
     */
    protected function shouldRemember(): bool
    {
        return $this->config->get('recaptcha.remember.enabled');
    }

    /**
     * Return the remember key.
     *
     * @return string
     */
    protected function rememberKey(): string
    {
        return $this->config->get('recaptcha.remember.key');
    }

    /**
     * Return the minutes time to remember the challenge.
     *
     * @return int|float
     */
    protected function rememberMinutes(): int|float
    {
        return $this->config->get('recaptcha.remember.minutes');
    }

    /**
     * Checks if the user is not authenticated on the given guards.
     *
     * @param  array  $guards
     * @return bool
     */
    protected function isGuest(array $guards): bool
    {
        $auth = auth();

        if ($guards === ['null']) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if ($auth->guard($guard)->check()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if the user is authenticated on the given guards.
     *
     * @param  array  $guards
     * @return bool
     */
    protected function isAuth(array $guards): bool
    {
        return !$this->isGuest($guards);
    }

    /**
     * Validate if this Request has the ReCaptcha challenge string.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $input
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureChallengeIsPresent(Request $request, string $input): void
    {
        if ($request->isNotFilled($input)) {
            throw ValidationException::withMessages([
                $input => trans('recaptcha::validation.missing')
            ])->redirectTo(back()->getTargetUrl());
        }
    }


    /**
     * Retrieve the response from reCAPTCHA servers.
     *
     * @param  string|null  $token
     * @param  string  $ip
     * @param  string  $version
     * @param  string  $input
     * @param  string|null  $action
     * @return \Laragear\ReCaptcha\Http\ReCaptchaResponse
     */
    protected function saveResponse(
        ?string $token,
        string $ip,
        string $version,
        string $input,
        ?string $action = null
    ): ReCaptchaResponse {
        return app()->instance(
            ReCaptchaResponse::class, ReCaptchaFacade::getChallenge($token, $ip, $version, $input, $action)
        );
    }
}
