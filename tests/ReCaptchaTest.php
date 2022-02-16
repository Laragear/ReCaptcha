<?php

namespace Tests;

use Illuminate\Http\Client\Factory;
use Laragear\ReCaptcha\Facades\ReCaptcha as ReCaptchaFacade;
use Laragear\ReCaptcha\Http\ReCaptchaResponse;
use Laragear\ReCaptcha\ReCaptcha;
use LogicException;

class ReCaptchaTest extends TestCase
{
    use CreatesFulfilledResponse;

    public function test_returns_response(): void
    {
        $mock = $this->mock(Factory::class);

        $mock->expects('asForm')->withNoArgs()->once()->andReturnSelf();
        $mock->expects('async')->withNoArgs()->once()->andReturnSelf();
        $mock->expects('withOptions')->with(['version' => 2.0])->once()->andReturnSelf();
        $mock->expects('post')
            ->with(
                ReCaptcha::SERVER_ENDPOINT,
                [
                    'secret'   => ReCaptcha::TEST_V2_SECRET,
                    'response' => 'token',
                    'remoteip' => '127.0.0.1',
                ]
            )
            ->once()
            ->andReturn(
                $this->fulfilledPromise([
                    'success' => true,
                    'foo'     => 'bar',
                ])
            );

        $recaptcha = $this->app->make(ReCaptcha::class);

        $instance = $recaptcha->getChallenge('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT, null);

        $this->instance(ReCaptchaResponse::class, $instance);
        static::assertSame($instance, ReCaptchaFacade::response());
    }

    public function test_checks_for_response(): void
    {
        $recaptcha = $this->app->make(ReCaptcha::class);

        static::assertFalse($recaptcha->hasResponse());

        app()->instance(ReCaptchaResponse::class, $instance = $this->fulfilledResponse());

        static::assertTrue($recaptcha->hasResponse());
        static::assertSame($instance, ReCaptchaFacade::response());
    }

    public function test_uses_v2_test_credentials_by_default(): void
    {
        $mock = $this->mock(Factory::class);

        $mock->expects('asForm')->withNoArgs()->times(3)->andReturnSelf();
        $mock->expects('async')->withNoArgs()->times(3)->andReturnSelf();
        $mock->expects('withOptions')->with(['version' => 2.0])->times(3)->andReturnSelf();
        $mock->expects('post')
            ->with(
                ReCaptcha::SERVER_ENDPOINT,
                [
                    'secret'   => ReCaptcha::TEST_V2_SECRET,
                    'response' => 'token',
                    'remoteip' => '127.0.0.1',
                ]
            )
            ->times(3)
            ->andReturn(
                $this->fulfilledPromise([
                    'success' => true,
                    'foo'     => 'bar',
                ])
            );

        /** @var \Laragear\ReCaptcha\ReCaptcha $instance */
        $instance = $this->app->make(ReCaptcha::class);

        $checkbox = $instance->getChallenge('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT);

        static::assertTrue($checkbox->success);
        static::assertNull($checkbox->score);
        static::assertSame('bar', $checkbox->foo);

        $invisible = $instance->getChallenge('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT);

        static::assertTrue($invisible->success);
        static::assertNull($checkbox->score);
        static::assertSame('bar', $invisible->foo);

        $android = $instance->getChallenge('token', '127.0.0.1', 'android', ReCaptcha::INPUT);

        static::assertTrue($android->success);
        static::assertNull($checkbox->score);
        static::assertSame('bar', $android->foo);
    }

    public function test_uses_v2_custom_credentials(): void
    {
        $this->app->make('config')->set('recaptcha.credentials', [
            'checkbox'  => ['secret' => 'secret-checkbox'],
            'invisible' => ['secret' => 'secret-invisible'],
            'android'   => ['secret' => 'secret-android'],
        ]);

        $mock = $this->mock(Factory::class);

        $mock->expects('asForm')->withNoArgs()->times(3)->andReturnSelf();
        $mock->expects('async')->withNoArgs()->times(3)->andReturnSelf();
        $mock->expects('withOptions')->with(['version' => 2.0])->times(3)->andReturnSelf();

        $mock->expects('post')
            ->with(
                ReCaptcha::SERVER_ENDPOINT,
                [
                    'secret'   => 'secret-checkbox',
                    'response' => 'token',
                    'remoteip' => '127.0.0.1',
                ]
            )
            ->once()
            ->andReturn(
                $this->fulfilledPromise([
                    'success' => true,
                    'foo'     => 'bar',
                ])
            );

        $mock->expects('post')
            ->with(
                ReCaptcha::SERVER_ENDPOINT,
                [
                    'secret'   => 'secret-invisible',
                    'response' => 'token',
                    'remoteip' => '127.0.0.1',
                ]
            )
            ->once()
            ->andReturn(
                $this->fulfilledPromise([
                    'success' => true,
                    'foo'     => 'bar',
                ])
            );

        $mock->expects('post')
            ->with(
                ReCaptcha::SERVER_ENDPOINT,
                [
                    'secret'   => 'secret-android',
                    'response' => 'token',
                    'remoteip' => '127.0.0.1',
                ]
            )
            ->once()
            ->andReturn(
                $this->fulfilledPromise([
                    'success' => true,
                    'foo'     => 'bar',
                ])
            );

        $instance = $this->app->make(ReCaptcha::class);

        static::assertEquals(
            'bar',
            $instance->getChallenge('token', '127.0.0.1', 'checkbox', ReCaptcha::INPUT)->foo
        );
        static::assertEquals(
            'bar',
            $instance->getChallenge('token', '127.0.0.1', 'invisible', ReCaptcha::INPUT)->foo
        );
        static::assertEquals(
            'bar',
            $instance->getChallenge('token', '127.0.0.1', 'android', ReCaptcha::INPUT)->foo
        );
    }

    public function test_exception_if_no_v3_secret_issued(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("The ReCaptcha secret for [score] doesn't exists or is not set.");

        $this->app->make(ReCaptcha::class)->getChallenge('token', '127.0.0.1', 'score', ReCaptcha::INPUT);
    }

    public function test_exception_when_invalid_credentials_issued(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("The ReCaptcha secret for [invalid] doesn't exists or is not set.");

        $this->app->make(ReCaptcha::class)->getChallenge('token', '127.0.0.1', 'invalid', ReCaptcha::INPUT);
    }

    public function test_receives_v3_secret(): void
    {
        $this->app->make('config')->set('recaptcha.credentials.score.secret', 'secret');

        $mock = $this->mock(Factory::class);

        $mock->expects('asForm')->withNoArgs()->once()->andReturnSelf();
        $mock->expects('async')->withNoArgs()->once()->andReturnSelf();
        $mock->expects('withOptions')->with(['version' => 2.0])->once()->andReturnSelf();
        $mock->expects('post')
            ->with(ReCaptcha::SERVER_ENDPOINT, [
                'secret'   => 'secret',
                'response' => 'token',
                'remoteip' => '127.0.0.1',
            ])
            ->once()
            ->andReturn(
                $this->fulfilledPromise([
                    'success' => true,
                    'score'   => 0.5,
                    'foo'     => 'bar',
                ])
            );

        /** @var \Laragear\ReCaptcha\ReCaptcha $instance */
        $instance = $this->app->make(ReCaptcha::class);

        $score = $instance->getChallenge('token', '127.0.0.1', 'score', ReCaptcha::INPUT);

        static::assertTrue($score->success);
        static::assertSame(0.5, $score->score);
        static::assertSame('bar', $score->foo);
    }

    public function test_http_factory_receives_config(): void
    {
        $this->app->make('config')->set([
            'recaptcha.credentials.score.secret' => 'secret',
            'recaptcha.client' => ['foo' => 'bar'],
        ]);

        $mock = $this->mock(Factory::class);

        $mock->expects('asForm')->withNoArgs()->once()->andReturnSelf();
        $mock->expects('async')->withNoArgs()->once()->andReturnSelf();
        $mock->expects('withOptions')->with(['foo' => 'bar'])->once()->andReturnSelf();
        $mock->expects('post')
            ->with(ReCaptcha::SERVER_ENDPOINT, [
                'secret'   => 'secret',
                'response' => 'token',
                'remoteip' => '127.0.0.1',
            ])
            ->once()
            ->andReturn($this->fulfilledPromise());

        $this->app->make(ReCaptcha::class)->getChallenge('token', '127.0.0.1', 'score', ReCaptcha::INPUT);
    }
}
