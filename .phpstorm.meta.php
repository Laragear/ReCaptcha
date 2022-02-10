<?php

namespace Illuminate\Http
{
    class Request
    {
        /**
         * Check if the ReCaptcha response is equal or above threshold score.
         *
         * @return bool
         */
        public static function isHuman(): bool;

        /**
         * Check if the ReCaptcha response is below threshold score.
         *
         * @return bool
         */
        public static function isRobot(): bool;
    }
}
