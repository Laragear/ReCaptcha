<?php

namespace Tests;

use Laragear\ReCaptcha\Facades\ReCaptcha;
use Laragear\ReCaptcha\Http\ReCaptchaResponse;

class ReCaptchaFakeTest extends TestCase
{
    protected function defineRoutes($router)
    {
        $router->post('test', static function (ReCaptchaResponse $response): array {
            return [$response->score, $response->isRobot(), $response->isHuman()];
        })->middleware('recaptcha.score:0.6');
    }

    public function test_makes_fake_score(): void
    {
        ReCaptcha::fakeScore(0.3);

        $this->post('test')->assertOk()->assertExactJson([0.3, true, false]);
    }

    public function test_makes_human_score_one(): void
    {
        ReCaptcha::fakeHuman();

        $this->post('test')->assertOk()->assertExactJson([1.0, false, true]);
    }

    public function test_makes_robot_score_zero(): void
    {
        ReCaptcha::fakeRobot();

        $this->post('test')->assertOk()->assertExactJson([0.0, true, false]);
    }

    public function test_can_fake_twice_in_same_test(): void
    {
        ReCaptcha::fakeScore(0.7);

        $this->postJson('test')->assertOk()->assertExactJson([0.7, false, true]);

        ReCaptcha::fakeScore(0.3);

        $this->postJson('test')->assertOk()->assertExactJson([0.3, true, false]);
    }
}
