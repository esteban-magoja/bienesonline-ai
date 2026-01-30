<?php

namespace App\Http\Controllers\Auth;

use Wave\Http\Controllers\Auth\RegisterController as AuthRegisterController;

class RegisterController extends AuthRegisterController
{
    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    public $redirectTo = '/dashboard';
}
