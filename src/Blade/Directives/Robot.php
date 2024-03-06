<?php

namespace Laragear\ReCaptcha\Blade\Directives;

use function config;
use function now;
use function session;

class Robot
{
    /**
     * Check if the ReCaptcha challenge was remembered and not expired.
     */
    public static function directive(): bool
    {
        return now()->getTimestamp() > session()->get(config()->get('recaptcha.remember.key', '_recaptcha'));
    }
}
