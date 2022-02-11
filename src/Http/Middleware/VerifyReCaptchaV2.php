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
    use VerificationHelpers;

    /**
     * The signature of the middleware.
     *
     * @var string
     */
    public const SIGNATURE = 'recaptcha';

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
        if ($version === ReCaptcha::SCORE) {
            throw new LogicException('Use the [recaptcha.score] middleware to capture score-driven challenges.');
        }

        if ($this->shouldCheckReCaptcha($remember, $guards)) {
            $this->ensureChallengeIsPresent($request, $input = $this->normalizeInput($input));

            $response = $this->saveResponse($request->input($input), $request->ip(), $version, $input)->wait();

            if (!$response->success) {

            }

            if ($this->shouldCheckRemember($remember)) {
                $this->storeRememberInSession($remember);
            }
        }

        return $next($request);
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
     * Check if the request "remember" should be checked.
     *
     * @return bool
     */
    protected function hasRemember(): bool
    {
        $timestamp = session($key = $this->rememberKey());

        if (null !== $timestamp) {
            if (!$timestamp || now()->getTimestamp() < $timestamp) {
                return true;
            }

            // Dispose of the expired session key if we have the opportunity.
            session()->forget($key);
        }

        return false;
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
