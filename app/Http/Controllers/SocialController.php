<?php

namespace App\Http\Controllers;

use App\User;
use App\Social;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SocialController extends Controller
{
    public function callback(Request $request)
    {
        //print_r($request->all());
        // Line login authorization request error
        if ($request->has('error')) {
            return redirect('/');
        }
        // Line login access token
        $url = 'https://api.line.me/oauth2/v2.1/token';
        $data = [
            'grant_type' => 'authorization_code',
            'code' => $request->code,
            'redirect_uri' => env('LINE_CALLBACK_URL'),
            'client_id' => env('LINE_CLIENT_ID'),
            'client_secret' => env('LINE_CLIENT_SECRET')
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($httpcode != 200){
            return redirect('/');
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);

        // Line login decod JWT header.payload.signature
        $tokens = explode('.', $output['id_token']);
        if (count($tokens) != 3){
            return redirect('/');
        }

        list($header64, $payload64, $sign) = $tokens;

        $header = json_decode($this->urlsafeB64Decode($header64), JSON_OBJECT_AS_ARRAY);
        if (empty($header['alg'])){
            return redirect('/');
        }
//        if (hash_hmac('sha256', $header64 . '.' . $payload64, env('LINE_CLIENT_SECRET')) !== $this->urlsafeB64Decode($sign)){
//            return redirect('/');
//        }

        $payload = json_decode($this->urlsafeB64Decode($payload64), JSON_OBJECT_AS_ARRAY);

        if (isset($payload['aud']) && $payload['aud'] != env('LINE_CLIENT_ID')){
            return redirect('/');
        }
        if (isset($payload['iat']) && $payload['iat'] > strtotime('now')){
            return redirect('/');
        }
        if (isset($payload['exp']) && $payload['exp'] < strtotime('now')){
            return redirect('/');
        }
//        if(isset($payload['nonce']) && $payload['nonce'] != ){
//
//        }

        // Line login user profiles
        $url = 'https://api.line.me/v2/profile';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$output['access_token']));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($httpcode != 200){
            return redirect('/');
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);

        // insert/update user and social
        $social = Social::where('provider', 'like', 'Line')->where('provider_user_id', $output['userId'])->first();
        if($social){
            $user = User::where('id', $social->user_id)->first();

            $social->provider = 'Line';
            $social->provider_user_id = $social->provider_user_id;
        }else{
            $user = new User;

            $social = new Social;
            $social->provider = 'Line';
            $social->provider_user_id = $output['userId'];
        }
        $user->name = $output['displayName'];
        $user->email = $payload['email'];
        $user->save();

        $social->picture = $output['pictureUrl'];
        $user->social()->save($social);

        Auth::loginUsingId($user->id);

        return redirect('/home');
    }

    public static function urlsafeB64Decode(string $input)
    {
        $remainder = strlen($input) % 4;

        if ($remainder > 0)
        {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }
}
