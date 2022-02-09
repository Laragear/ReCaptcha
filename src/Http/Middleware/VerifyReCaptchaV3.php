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
    use VerificationHelpers;

    /**
     * The signature of the middleware.
     *
     * @var string
     */
    public const SIGNATURE = 'recaptcha.score';

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
     * @param  string|null  $threshold
     * @param  string|null  $action
     * @param  string  $input
     * @param  string  ...$guards
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
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
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
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
     *
     * @param  string|null  $threshold
     * @return float
     */
    protected function normalizeThreshold(?string $threshold): float
    {
        return (float) (strtolower($threshold) === 'null' ? $this->config->get('recaptcha.threshold') : $threshold);
    }

    /**
     * Normalizes the action name, or returns null.
     *
     * @param  null|string  $action
     *
     * @return null|string
     */
    protected function normalizeAction(?string $action): ?string
    {
        return strtolower($action) === 'null' ? null : $action;
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     *
     * @return void
     */
    public function terminate(): void
    {
        // Terminate any dangling async request, and remove it from the container.
        if (app()->has(ReCaptchaResponse::class)) {
            app(ReCaptchaResponse::class)->terminate();
        }
    }
}
