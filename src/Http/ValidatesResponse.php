<?php

namespace Laragear\ReCaptcha\Http;

use function array_filter;
use function back;
use function config;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use function implode;
use function trans;

/**
 * @internal
 */
trait ValidatesResponse
{
    /**
     * Validates the response based on previously set expectations.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(): void
    {
        // If the "success" key is not explicitly true, bail out.
        if (Arr::get($this->attributes, 'success') !== true) {
            throw $this->validationException([
                $this->input => trans('recaptcha::validation.error', [
                    'errors' => implode(', ', Arr::wrap($this->attributes['errors'] ?? [])),
                ]),
            ]);
        }

        foreach ($this->expectations() as $key => $value) {
            $expectation = $this->attributes[$key] ?? null;

            if ($expectation !== '' && $expectation !== $value) {
                $errors[$key] = trans('ReCaptcha::validation.match');
            }
        }

        if (! empty($errors)) {
            throw $this->validationException([$this->input => $errors]);
        }
    }

    /**
     * Creates a new validation exceptions with messages.
     *
     * @param  array  $messages
     * @return \Illuminate\Validation\ValidationException
     */
    protected function validationException(array $messages): ValidationException
    {
        return ValidationException::withMessages($messages)->redirectTo(back()->getTargetUrl());
    }

    /**
     * Retrieve the expectations for the current response.
     *
     * @return array
     *
     * @internal
     */
    protected function expectations(): array
    {
        return array_filter(
            Arr::only(config('recaptcha'), ['hostname', 'apk_package_name']) +
            ['action' => $this->expectedAction]
        );
    }
}
