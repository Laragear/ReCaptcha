<?php

namespace Laragear\ReCaptcha\Facades;

use Illuminate\Support\Facades\Facade;
use Laragear\ReCaptcha\ReCaptchaFake;

/**
 * @method static bool hasResponse()
 * @method static \Laragear\ReCaptcha\Http\ReCaptchaResponse getChallenge(?string $token, string $ip, string $version, string $input, string $action = null)
 * @method static \Laragear\ReCaptcha\Http\ReCaptchaResponse response()
 *
 * @method static \Laragear\ReCaptcha\ReCaptcha|\Laragear\ReCaptcha\ReCaptchaFake getFacadeRoot()
 */
class ReCaptcha extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \Laragear\ReCaptcha\ReCaptcha::class;
    }

    /**
     * Returns a new ReCaptcha service to fake responses.
     *
     * @return \Laragear\ReCaptcha\ReCaptchaFake
     */
    public static function fake(): ReCaptchaFake
    {
        $instance = static::getFacadeRoot();

        if ($instance instanceof ReCaptchaFake) {
            return $instance;
        }

        static::swap($instance = static::getFacadeApplication()->make(ReCaptchaFake::class));

        return $instance;
    }

    /**
     * Makes the fake ReCaptcha response with a fake score.
     *
     * @param  float  $score
     *
     * @return void
     */
    public static function fakeScore(float $score): void
    {
        static::fake()->fakeScore($score);
    }

    /**
     * Makes a fake ReCaptcha response made by a robot with "0" score.
     *
     * @return void
     */
    public static function fakeRobot(): void
    {
        static::fake()->fakeRobot();
    }

    /**
     * Makes a fake ReCaptcha response made by a human with "1.0" score.
     *
     * @return void
     */
    public static function fakeHuman(): void
    {
        static::fake()->fakeHuman();
    }
}
