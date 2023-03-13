<?php

namespace Laragear\ReCaptcha\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Http\Request;

use function redirect;
use function url;

class ConfirmReCaptcha
{
    use ChecksRemember;
    use ChecksGuards;

    /**
     * The alias of the middleware.
     *
     * @var string
     */
    public const ALIAS = 'recaptcha.confirm';

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Config\Repository  $config
     */
    public function __construct(protected ConfigContract $config)
    {
        //
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $route
     * @param  string  ...$guards
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $route = 'recaptcha.confirm', string ...$guards): mixed
    {
        if ($this->isAuth($guards) || $this->shouldRemember() && $this->hasRemember()) {
            return $next($request);
        }

        return redirect()->guest(url()->route($route));
    }
}
