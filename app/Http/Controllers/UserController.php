<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use \App\Models\EmailLogin;
use Illuminate\Support\Facades\Auth;


class UserController extends Controller
{
    public function authenticateEmail($token)
    {
        $emailLogin = EmailLogin::validFromToken($token);

        Auth::login($emailLogin->user, true);

        return redirect(config('app.urlfront'));
    }
}
