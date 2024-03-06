<?php

namespace Laragear\ReCaptcha;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Http\Client\Factory;
use Laragear\ReCaptcha\Http\ReCaptchaResponse;
use LogicException;

use function app;

class ReCaptcha
{
    // Constants to identify each reCAPTCHA service.
    public const CHECKBOX = 'checkbox';
    public const INVISIBLE = 'invisible';
    public const ANDROID = 'android';
    public const SCORE = 'score';

    /**
     * reCAPTCHA v2 secret for testing on "localhost".
     */
    public const TEST_V2_SECRET = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

    /**
     * reCAPTCHA v2 site key for testing on "localhost".
     */
    public const TEST_V2_KEY = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';

    /**
     * The URL where the ReCaptcha challenge should be verified.
     */
    public const SERVER_ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * The name of the input for a reCAPTCHA frontend response.
     */
    public const INPUT = 'g-recaptcha-response';

    /**
     * Create a new ReCaptcha instance.
     */
    public function __construct(protected Factory $http, protected ConfigContract $config)
    {
        //
    }

    /**
     * Resolves a ReCaptcha challenge.
     */
    public function getChallenge(
        ?string $token,
        string $ip,
        string $version,
        string $input,
        string $action = null,
    ): ReCaptchaResponse {
        return new ReCaptchaResponse($this->request($token, $ip, $version), $input, $action);
    }

    /**
     * Check if the response was resolved.
     */
    public function hasResponse(): bool
    {
        return app()->has(ReCaptchaResponse::class);
    }

    /**
     * Returns the reCAPTCHA Response.
     *
     * An exception will be thrown when the response doesn't exist.
     */
    public function response(): ReCaptchaResponse
    {
        return app()->make(ReCaptchaResponse::class);
    }

    /**
     * Creates a Pending Request or a Promise.
     */
    protected function request(string $challenge, string $ip, string $version): PromiseInterface
    {
        // @phpstan-ignore-next-line
        return $this->http
            ->asForm()
            ->async()
            ->withOptions($this->config->get('recaptcha.client'))
            ->post(static::SERVER_ENDPOINT, [
                'secret' => $this->secret($version),
                'response' => $challenge,
                'remoteip' => $ip,
            ]);
    }

    /**
     * Sets the correct credentials to use to retrieve the challenge results.
     */
    protected function secret(string $version): string
    {
        return $this->config->get("recaptcha.credentials.$version.secret")
            ?? throw new LogicException("The ReCaptcha secret for [$version] doesn't exists or is not set.");
    }
}
