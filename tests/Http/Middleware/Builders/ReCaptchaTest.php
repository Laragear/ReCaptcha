<?php

namespace Tests\Http\Middleware\Builders;

use Laragear\ReCaptcha\Http\Middleware\Builders\ReCaptcha;
use LogicException;
use Tests\TestCase;

class ReCaptchaTest extends TestCase
{
    public function test_creates_full_recaptcha_v2_checkbox_string(): void
    {
        static::assertEquals('recaptcha:checkbox', (string) ReCaptcha::checkbox());
        static::assertEquals('recaptcha:checkbox,null,foo', (string) ReCaptcha::checkbox()->input('foo'));
        static::assertEquals('recaptcha:checkbox,null,null,bar', (string) ReCaptcha::checkbox()->forGuests('bar'));
        static::assertEquals('recaptcha:checkbox,10,null,bar', (string) ReCaptcha::checkbox()->forGuests('bar')->remember());
        static::assertEquals('recaptcha:checkbox,20,null,bar', (string) ReCaptcha::checkbox()->forGuests('bar')->remember(20));
        static::assertEquals('recaptcha:checkbox,inf,null,bar', (string) ReCaptcha::checkbox()->forGuests('bar')->rememberForever());
        static::assertEquals('recaptcha:checkbox,false,null,bar', (string) ReCaptcha::checkbox()->forGuests('bar')->dontRemember());
        static::assertEquals('recaptcha:checkbox,false,foo,bar', (string) ReCaptcha::checkbox()->input('foo')->forGuests('bar')->dontRemember());
    }

    public function test_creates_full_recaptcha_v2_invisible_string(): void
    {
        static::assertEquals('recaptcha:invisible', (string) ReCaptcha::invisible());
        static::assertEquals('recaptcha:invisible,null,foo', (string) ReCaptcha::invisible()->input('foo'));
        static::assertEquals('recaptcha:invisible,null,null,bar', (string) ReCaptcha::invisible()->forGuests('bar'));
        static::assertEquals('recaptcha:invisible,10,null,bar', (string) ReCaptcha::invisible()->forGuests('bar')->remember());
        static::assertEquals('recaptcha:invisible,20,null,bar', (string) ReCaptcha::invisible()->forGuests('bar')->remember(20));
        static::assertEquals('recaptcha:invisible,inf,null,bar', (string) ReCaptcha::invisible()->forGuests('bar')->rememberForever());
        static::assertEquals('recaptcha:invisible,false,null,bar', (string) ReCaptcha::invisible()->forGuests('bar')->dontRemember());
        static::assertEquals('recaptcha:invisible,false,foo,bar', (string) ReCaptcha::invisible()->input('foo')->forGuests('bar')->dontRemember());
    }

    public function test_creates_full_recaptcha_v2_android_string(): void
    {
        static::assertEquals('recaptcha:android', (string) ReCaptcha::android());
        static::assertEquals('recaptcha:android,null,foo', (string) ReCaptcha::android()->input('foo'));
        static::assertEquals('recaptcha:android,null,null,bar', (string) ReCaptcha::android()->forGuests('bar'));
        static::assertEquals('recaptcha:android,10,null,bar', (string) ReCaptcha::android()->forGuests('bar')->remember());
        static::assertEquals('recaptcha:android,20,null,bar', (string) ReCaptcha::android()->forGuests('bar')->remember(20));
        static::assertEquals('recaptcha:android,inf,null,bar', (string) ReCaptcha::android()->forGuests('bar')->rememberForever());
        static::assertEquals('recaptcha:android,false,null,bar', (string) ReCaptcha::android()->forGuests('bar')->dontRemember());
        static::assertEquals('recaptcha:android,false,foo,bar', (string) ReCaptcha::android()->input('foo')->forGuests('bar')->dontRemember());
    }

    public function test_exception_if_using_remember_on_v3(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You cannot set [remember] for a [score] middleware.');
        ReCaptcha::score()->remember();
    }

    public function test_exception_if_using_dont_remember_on_v3(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You cannot set [dontRemember] for a [score] middleware.');
        ReCaptcha::score()->dontRemember();
    }

    public function test_exception_if_using_v3_methods_on_v2_checkbox(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You cannot set [threshold] for a [checkbox] middleware.');
        ReCaptcha::checkbox()->threshold(1);
    }

    public function test_exception_if_using_v3_methods_on_v2_invisible(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You cannot set [action] for a [invisible] middleware.');
        ReCaptcha::invisible()->action('route');
    }

    public function test_exception_if_using_v3_methods_on_v2_android(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You cannot set [threshold] for a [android] middleware.');
        ReCaptcha::android()->threshold(1);
    }

    public function test_creates_full_recaptcha_v3_score_string()
    {
        static::assertSame(
            'recaptcha.score:0.5',
            (string) ReCaptcha::score()
        );

        static::assertSame(
            'recaptcha.score:0.3,bar,foo',
            (string) ReCaptcha::score()->input('foo')->threshold(0.3)->action('bar')
        );

        static::assertSame(
            'recaptcha.score:0.3,bar,foo,quz,cougar',
            (string) ReCaptcha::score()->forGuests('quz', 'cougar')->threshold(0.3)->action('bar')->input('foo')
        );
    }

    public function test_uses_threshold_from_config()
    {
        static::assertSame(
            'recaptcha.score:0.5',
            (string) ReCaptcha::score()
        );

        $this->app->make('config')->set('recaptcha.threshold', 0.1);

        static::assertSame(
            'recaptcha.score:0.1',
            (string) ReCaptcha::score()
        );
    }

    public function test_normalizes_threshold(): void
    {
        static::assertSame(
            'recaptcha.score:1.0',
            (string) ReCaptcha::score(1.7)
        );

        static::assertSame(
            'recaptcha.score:0.0',
            (string) ReCaptcha::score(-9)
        );

        static::assertSame(
            'recaptcha.score:1.0',
            (string) ReCaptcha::score()->threshold(1.7)
        );

        static::assertSame(
            'recaptcha.score:0.0',
            (string) ReCaptcha::score()->threshold(-9)
        );
    }

    public function test_cast_to_string(): void
    {
        static::assertEquals('recaptcha.score:0.7', ReCaptcha::score(0.7)->toString());
        static::assertEquals('recaptcha.score:0.7', ReCaptcha::score(0.7)->__toString());
    }

    public function tests_uses_all_guards_as_exception(): void
    {
        static::assertEquals('recaptcha:checkbox,null,null,null', ReCaptcha::checkbox()->forGuests()->toString());
        static::assertEquals('recaptcha:invisible,null,null,null', ReCaptcha::invisible()->forGuests()->toString());
        static::assertEquals('recaptcha:android,null,null,null', ReCaptcha::android()->forGuests()->toString());
        static::assertEquals('recaptcha.score:0.5,null,null,null', ReCaptcha::score()->forGuests()->toString());
    }
}
