<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    // 20190525 Line login
    public function showLoginForm()
    {
        $url = 'https://access.line.me/oauth2/v2.1/authorize';
        $url .= '?response_type=code';
        $url .= '&client_id='.env('LINE_CLIENT_ID');
        $url .= '&redirect_uri='.env('LINE_CALLBACK_URL');
        $url .= '&state='.$this->randtext(6);
        $url .= '&scope=openid%20profile%20email';
        $url .= '&nonce='.$this->randtext(6);


        return view('auth.login', compact('url'));
    }

    private function randtext($length) {
        $password_len = $length;    //字串長度
        $password = '';
        $word = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';   //亂數內容
        $len = strlen($word);
        for ($i = 0; $i < $password_len; $i++) {
            $password .= $word[rand() % $len];
        }
        return $password;
    }
}
