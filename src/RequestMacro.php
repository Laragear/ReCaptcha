<?php

namespace Laragear\ReCaptcha;

use Laragear\ReCaptcha\Http\ReCaptchaResponse;
use function app;

/**
 * @internal
 */
class RequestMacro
{
    /**
     * Check if the ReCaptcha response is equal or above threshold score.
     */
    public static function isHuman(): bool
    {
        return app(ReCaptchaResponse::class)->isHuman();
    }

    /**
     * Check if the ReCaptcha response is below threshold score.
     */
    public static function isRobot(): bool
    {
        return ! static::isHuman();
    }
}
