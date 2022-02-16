<?php

namespace Tests;

use Laragear\ReCaptcha\ReCaptcha;
use function recaptcha;
use RuntimeException;

class HelperTest extends TestCase
{
    public function test_exception_when_no_v3_key_loaded(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The ReCaptcha site key for [3] doesn\'t exist.');

        recaptcha(3);
    }

    public function test_retrieves_test_keys_by_default(): void
    {
        static::assertSame(ReCaptcha::TEST_V2_KEY, recaptcha('checkbox'));
        static::assertSame(ReCaptcha::TEST_V2_KEY, recaptcha('invisible'));
        static::assertSame(ReCaptcha::TEST_V2_KEY, recaptcha('android'));
    }

    public function test_retrieves_secrets(): void
    {
        $this->app->make('config')->set('recaptcha.credentials', [
            'checkbox' => ['key' => 'key-checkbox'],
            'invisible' => ['key' => 'key-invisible'],
            'android' => ['key' => 'key-android'],
            'score' => ['key' => 'key-score'],
        ]);

        static::assertSame('key-checkbox', recaptcha('checkbox'));
        static::assertSame('key-invisible', recaptcha('invisible'));
        static::assertSame('key-android', recaptcha('android'));
        static::assertSame('key-score', recaptcha('score'));
    }
}
