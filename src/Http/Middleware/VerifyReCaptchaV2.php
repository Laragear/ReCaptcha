<?php

namespace Laragear\ReCaptcha\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Http\Request;
use Laragear\ReCaptcha\ReCaptcha;
use LogicException;

use function now;
use function session;
use function strtolower;

use const INF;

class VerifyReCaptchaV2
{
    use VerifyHelpers;
    use ChecksRemember;
    use ChecksGuards;

    /**
     * The alias of the middleware.
     *
     * @var string
     */
    public const ALIAS = 'recaptcha';

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
     * @param  string  $version
     * @param  string  $remember
     * @param  string  $input
     * @param  string  ...$guards
     * @return mixed
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(
        Request $request,
        Closure $next,
        string $version,
        string $remember = 'null',
        string $input = ReCaptcha::INPUT,
        string ...$guards
    ): mixed {
        $this->ensureValidVersion($version);

        if ($this->shouldCheckReCaptcha($remember, $guards)) {
            $this->ensureChallengeIsPresent($request, $input = $this->normalizeInput($input));

            $this->saveResponse($request->input($input), $request->ip(), $version, $input)->wait();

            if ($this->shouldCheckRemember($remember)) {
                $this->storeRememberInSession($remember);
            }
        }

        return $next($request);
    }

    /**
     * Ensure the developer has the correct version.
     *
     * @param  string  $input
     * @return void
     */
    protected function ensureValidVersion(string $input): void
    {
        if ($input === ReCaptcha::SCORE) {
            throw new LogicException(
                'Use the ['.VerifyReCaptchaV3::ALIAS.'] middleware to capture score-driven challenges.'
            );
        }
    }

    /**
     * Check if the ReCaptcha should be checked for this request.
     *
     * @param  string  $remember
     * @param  array  $guards
     * @return bool
     */
    protected function shouldCheckReCaptcha(string $remember, array $guards): bool
    {
        if ($this->isDisabled() || $this->isFaking()) {
            return false;
        }

        if ($this->shouldCheckRemember($remember) && $this->hasRemember()) {
            return false;
        }

        return $this->isGuest($guards);
    }

    /**
     * Check if the "remember" should be checked.
     *
     * @param  string  $remember
     * @return bool
     */
    protected function shouldCheckRemember(string $remember): bool
    {
        if ($remember === 'null') {
            return $this->shouldRemember();
        }

        return $remember !== 'false';
    }

    /**
     * Stores the ReCaptcha remember expiration time in the session.
     *
     * @param  string|int  $offset
     * @return void
     */
    protected function storeRememberInSession(string|int $offset): void
    {
        $offset = strtolower($offset);

        if ($offset === 'null') {
            $offset = $this->rememberMinutes();
        }

        $offset = match ($offset) {
            INF, 'inf', 'infinite', 'forever' => INF,
            default => now()->addMinutes($offset)->getTimestamp(),
        };

        session()->put($this->rememberKey(), $offset);
    }
}
