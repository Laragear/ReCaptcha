<?php

namespace Laragear\ReCaptcha\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Laragear\ReCaptcha\Http\Middleware\Builders\ReCaptcha;
use function redirect;

class ConfirmationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware(ReCaptcha::checkbox()->remember()->toString())->only('confirm');
    }

    /**
     * Show the confirmation view.
     */
    public function show(): Response
    {
        return response()->view('recaptcha::confirm');
    }

    /**
     * Redirects the user after confirming the reCAPTCHA challenge.
     */
    public function confirm(): RedirectResponse
    {
        return redirect()->intended();
    }
}
