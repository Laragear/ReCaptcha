<?php

namespace Laragear\ReCaptcha;

use function app;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory;
use Laragear\ReCaptcha\Http\ReCaptchaResponse;
use LogicException;

class ReCaptcha
{
    // Constants to identify each reCAPTCHA service.
    public const CHECKBOX = 'checkbox';
    public const INVISIBLE = 'invisible';
    public const ANDROID = 'android';
    public const SCORE = 'score';

    /**
     * reCAPTCHA v2 secret for testing on "localhost".
     *
     * @var string
     */
    public const TEST_V2_SECRET = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

    /**
     * reCAPTCHA v2 site key for testing on "localhost".
     *
     * @var string
     */
    public const TEST_V2_KEY = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';

    /**
     * The URL where the ReCaptcha challenge should be verified.
     *
     * @var string
     */
    public const SERVER_ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * The name of the input for a reCAPTCHA frontend response.
     *
     * @var string
     */
    public const INPUT = 'g-recaptcha-response';

    /**
     * Create a new ReCaptcha instance.
     *
     * @param  \Illuminate\Http\Client\Factory  $http
     * @param  \Illuminate\Contracts\Config\Repository  $config
     */
    public function __construct(protected Factory $http, protected Repository $config)
    {
        //
    }

    /**
     * Resolves a ReCaptcha challenge.
     *
     * @param  string|null  $token
     * @param  string  $ip
     * @param  string  $version
     * @param  string  $input
     * @param  string|null  $action
     * @return \Laragear\ReCaptcha\Http\ReCaptchaResponse
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
     *
     * @return bool
     */
    public function hasResponse(): bool
    {
        return app()->has(ReCaptchaResponse::class);
    }

    /**
     * Returns the reCAPTCHA Response.
     *
     * An exception will be thrown when the response doesn't exist.
     *
     * @return \Laragear\ReCaptcha\Http\ReCaptchaResponse
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function response(): ReCaptchaResponse
    {
        return app()->make(ReCaptchaResponse::class);
    }

    /**
     * Creates a Pending Request or a Promise.
     *
     * @param  string  $challenge
     * @param  string  $ip
     * @param  string  $version
     * @return \GuzzleHttp\Promise\PromiseInterface<\Illuminate\Http\Client\Response>
     */
    protected function request(string $challenge, string $ip, string $version): PromiseInterface
    {
        return $this->http
            ->asForm()
            ->async()
            ->withOptions(['version' => 2.0])
            ->post(static::SERVER_ENDPOINT, [
                'secret'   => $this->secret($version),
                'response' => $challenge,
                'remoteip' => $ip,
            ]);
    }

    /**
     * Sets the correct credentials to use to retrieve the challenge results.
     *
     * @param  string  $version
     * @return string
     */
    protected function secret(string $version): string
    {
        return $this->config->get("recaptcha.credentials.$version.secret")
            ?? throw new LogicException("The ReCaptcha secret for [$version] doesn't exists or is not set.");
    }
}
