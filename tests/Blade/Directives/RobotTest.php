<?php

namespace Tests\Blade\Directives;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;
use function now;

class RobotTest extends TestCase
{
    public function test_registers_directive(): void
    {
        $directives = $this->app->make('blade.compiler')->getCustomDirectives();

        static::assertArrayHasKey('robot', $directives);
        static::assertArrayHasKey('unlessrobot', $directives);
        static::assertArrayHasKey('elserobot', $directives);
        static::assertArrayHasKey('endrobot', $directives);
    }

    public function test_false_if_remember_exists_and_not_expired(): void
    {
        $this->session(['_recaptcha' => now()->addMinute()->getTimestamp()]);

        static::assertFalse(Blade::check('robot'));
    }

    public function test_false_if_remember_exists_and_forever(): void
    {
        $this->session(['_recaptcha' => INF]);

        static::assertFalse(Blade::check('robot'));
    }

    public function test_true_if_remember_expired(): void
    {
        $this->session(['_recaptcha' => now()->subSecond()->getTimestamp()]);

        static::assertTrue(Blade::check('robot'));
    }

    public function test_true_if_remember_doesnt_exists(): void
    {
        static::assertTrue(Blade::check('robot'));
    }
}
