<?php

namespace Laragear\ReCaptcha\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Http\Request;
use Laragear\ReCaptcha\Facades\ReCaptcha as ReCaptchaFacade;
use Laragear\ReCaptcha\Http\ReCaptchaResponse;
use Laragear\ReCaptcha\ReCaptcha;

use function app;

class VerifyReCaptchaV3
{
    use VerifyHelpers;
    use ChecksGuards;

    /**
     * The alias of the middleware.
     */
    public const ALIAS = 'recaptcha.score';

    /**
     * Create a new middleware instance.
     */
    public function __construct(protected ConfigContract $config)
    {
        //
    }

    /**
     * Handle the incoming request.
     */
    public function handle(
        Request $request,
        Closure $next,
        string $threshold = null,
        string $action = null,
        string $input = ReCaptcha::INPUT,
        string ...$guards,
    ): mixed {
        $input = $this->normalizeInput($input);

        // Ensure responses are always faked as humans, unless disabled or real.
        if ($this->isAuth($guards) || $this->isDisabled() || $this->isFaking() || $this->isTesting()) {
            $this->fakeResponseScore($request);
        } else {
            $this->ensureChallengeIsPresent($request, $input);
        }

        $this->saveResponse(
            $request->input($input), $request->ip(), ReCaptcha::SCORE, $input, $this->normalizeAction($action)
        )->setThreshold($this->normalizeThreshold($threshold));

        return $next($request);
    }

    /**
     * Fakes a score ReCaptcha response.
     */
    protected function fakeResponseScore(Request $request): void
    {
        // Swap the implementation to the ReCaptcha Fake.
        $fake = ReCaptchaFacade::fake();

        // If we're faking scores, allow the user to fake it through the input.
        if ($this->isFaking()) {
            $fake->score ??= (float) $request->missing('is_robot');
        }
    }

    /**
     * Normalize the threshold string, or returns the default.
     */
    protected function normalizeThreshold(?string $threshold): float
    {
        return (float) (strtolower($threshold) === 'null' ? $this->config->get('recaptcha.threshold') : $threshold);
    }

    /**
     * Normalizes the action name, or returns null.
     */
    protected function normalizeAction(?string $action): ?string
    {
        return strtolower($action) === 'null' ? null : $action;
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(): void
    {
        // Terminate any dangling async request, and remove it from the container.
        if (app()->has(ReCaptchaResponse::class)) {
            app(ReCaptchaResponse::class)->terminate();
        }
    }
}
