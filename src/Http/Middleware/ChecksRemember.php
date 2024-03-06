<?php

namespace Laragear\ReCaptcha\Http\Middleware;

use function now;
use function session;

trait ChecksRemember
{
    /**
     * Check if the request "remember" should be checked.
     */
    protected function hasRemember(): bool
    {
        if (now()->getTimestamp() < session($key = $this->rememberKey())) {
            return true;
        }

        // Dispose of the expired session key if we have the opportunity.
        session()->forget($key);

        return false;
    }

    /**
     * Return the minutes time to remember the challenge.
     */
    protected function rememberMinutes(): int|float
    {
        return $this->config->get('recaptcha.remember.minutes');
    }

    /**
     * Check if the reCAPTCHA challenge remembering is enabled.
     */
    protected function shouldRemember(): bool
    {
        return $this->config->get('recaptcha.remember.enabled');
    }

    /**
     * Return the remember key.
     */
    protected function rememberKey(): string
    {
        return $this->config->get('recaptcha.remember.key');
    }
}
