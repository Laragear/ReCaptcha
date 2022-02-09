<?php

if (!function_exists('recaptcha')) {
    /**
     * Returns the site key for the given ReCaptcha challenge mechanism.
     *
     * @param  string  $mode
     *
     * @return string
     * @throws \LogicException
     */
    function recaptcha(string $mode): string
    {
        return config("recaptcha.credentials.$mode.key")
            ?? throw new RuntimeException("The ReCaptcha site key for [$mode] doesn't exist.");
    }
}
