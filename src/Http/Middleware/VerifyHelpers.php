<?php

namespace Laragear\ReCaptcha\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laragear\ReCaptcha\Facades\ReCaptcha as ReCaptchaFacade;
use Laragear\ReCaptcha\Http\ReCaptchaResponse;
use Laragear\ReCaptcha\ReCaptcha;
use function app;
use function back;
use function strtolower;
use function trans;

/**
 * @internal
 */
trait VerifyHelpers
{
    /**
     * Normalize the input name.
     */
    protected function normalizeInput(string $input): string
    {
        return strtolower($input) === 'null' ? ReCaptcha::INPUT : $input;
    }

    /**
     * Check if ReCaptcha is enabled globally.
     */
    protected function isDisabled(): bool
    {
        return ! $this->config->get('recaptcha.enable');
    }

    /**
     * Check if the application is running under unit testing.
     */
    protected function isTesting(): bool
    {
        return app()->runningUnitTests();
    }

    /**
     * Check if the reCAPTCHA services should be faked.
     */
    protected function isFaking(): bool
    {
        return $this->config->get('recaptcha.fake');
    }

    /**
     * Validate if this Request has the ReCaptcha challenge string.
     */
    protected function ensureChallengeIsPresent(Request $request, string $input): void
    {
        if ($request->isNotFilled($input)) {
            throw ValidationException::withMessages([
                $input => trans('recaptcha::validation.missing'),
            ])->redirectTo(back()->getTargetUrl());
        }
    }

    /**
     * Retrieve the response from reCAPTCHA servers.
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
