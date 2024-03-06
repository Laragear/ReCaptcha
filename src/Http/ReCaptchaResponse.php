<?php

namespace Laragear\ReCaptcha\Http;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use JsonSerializable;
use function array_key_exists;
use function json_encode;
use function value;
use const JSON_THROW_ON_ERROR;

/**
 * @property-read bool $success
 * @property-read string $hostname
 * @property-read string $challenge_ts
 * @property-read string $apk_package_name
 * @property-read string $action
 * @property-read float $score
 * @property-read array $error_codes
 */
class ReCaptchaResponse implements JsonSerializable, Arrayable, Jsonable
{
    use CheckScore;
    use ValidatesResponse;

    /**
     * The data from the ReCaptcha response.
     *
     * @var array{success: bool, hostname: string, challenge_ts: int, apk_package_name: string, action: string, score: float, error_codes: string[]}
     */
    protected array $attributes = [];

    /**
     * Creates a new ReCaptcha Response Container.
     */
    public function __construct(
        protected PromiseInterface $promise,
        protected string $input,
        protected ?string $expectedAction = null
    ) {
        $this->promise = $this->promise->then(function (Response $response): void {
            $this->attributes = $response->json();
            $this->validate();
        });
    }

    /**
     * Checks if the response has been resolved.
     */
    public function isResolved(): bool
    {
        return $this->promise->getState() === PromiseInterface::FULFILLED;
    }

    /**
     * Checks if the response has yet to be resolved.
     */
    public function isPending(): bool
    {
        return ! $this->isResolved();
    }

    /**
     * Returns the timestamp of the challenge as a Carbon instance.
     */
    public function carbon(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $this->get('challenge_ts'));
    }

    /**
     * Waits for this ReCaptcha to be resolved.
     *
     * @return $this
     */
    public function wait(): static
    {
        $this->promise->wait();

        return $this;
    }

    /**
     * Terminates the ReCaptcha response if still pending.
     */
    public function terminate(): void
    {
        $this->promise->cancel();
    }

    /**
     * Returns the raw attributes of the response, bypassing the promise resolving.
     *
     * @return array{success: bool, hostname: string, challenge_ts: int, apk_package_name: string, action: string, score: float, error_codes: string[]}
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get an attribute from the instance.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->wait();

        return $this->attributes[$key] ?? value($default);
    }

    /**
     * Convert the instance to an array.
     *
     * @return array{success: bool, hostname: string, challenge_ts: int, apk_package_name: string, action: string, score: float, error_codes: string[]}
     */
    public function toArray(): array
    {
        $this->wait();

        return $this->getAttributes();
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array{success: bool, hostname: string, challenge_ts: int, apk_package_name: string, action: string, score: float, error_codes: string[]}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the instance to JSON.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR | $options);
    }

    /**
     * Dynamically retrieve the value of an attribute.
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Dynamically set the value of an attribute.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->wait();

        $this->attributes[$key] = $value;
    }

    /**
     * Dynamically check if an attribute is set.
     */
    public function __isset(string $key): bool
    {
        $this->wait();

        return array_key_exists($key, $this->attributes);
    }

    /**
     * Dynamically unset an attribute.
     */
    public function __unset(string $key): void
    {
        $this->wait();

        unset($this->attributes[$key]);
    }
}
