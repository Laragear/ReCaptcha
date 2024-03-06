<?php

namespace Laragear\ReCaptcha\Http\Middleware;

use function auth;

trait ChecksGuards
{
    /**
     * Checks if the user is not authenticated on the given guards.
     *
     * @param  string[]  $guards
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
     * @param  string[]  $guards
     */
    protected function isAuth(array $guards): bool
    {
        return ! $this->isGuest($guards);
    }
}
