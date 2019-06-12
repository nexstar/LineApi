<?php

namespace App\Http\Controllers;

use App\User;
use App\Social;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SocialController extends Controller
{
    // 20190612 Line login
    public function callback(Request $request)
    {
        // Line login authorization request error
        if ($request->has('error')) {
            Log::error($request->error);
            Log::error($request->error_description);
            Log::error($request->state);
            return redirect('/');
        }
        // Line login access token
        Log::info($request->code);
        Log::info($request->state);
        Log::info($request->friendship_status_changed);
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
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            return redirect('/');
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
        Log::info($output['token_type']);
        Log::info($output['scope']);
        if ($output['token_type'] != 'Bearer'){
            return redirect('/');
        }
        // Line login decod JWT header.payload.signature
        $idToken = explode('.', $output['id_token']);
        if (count($idToken) != 3){
            return redirect('/');
        }

        list($header64, $payload64, $signature) = $idToken;
        $header = json_decode($this->fnUrlsafeB64Decode($header64), JSON_OBJECT_AS_ARRAY);
        if (empty($header['typ']) || empty($header['alg'])){
            return redirect('/');
        }
        $channelSecret = mb_convert_encoding(env('LINE_CLIENT_SECRET'), "UTF-8");
        $httpRequestBody  = mb_convert_encoding($header64 . "." . $payload64, "UTF-8");
        $calcSignature  = hash_hmac('sha256', $httpRequestBody, $channelSecret, true);
        if ($calcSignature !== $this->fnUrlsafeB64Decode($signature)){
            return redirect('/');
        }
        $payload = json_decode($this->fnUrlsafeB64Decode($payload64), JSON_OBJECT_AS_ARRAY);
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
        $accessToken = $output['access_token'];
        $refreshToken = $output['refresh_token'];
        $exp = $payload['exp'];

        // Line login user profiles
        $url = 'https://api.line.me/v2/profile';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$accessToken));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            return redirect('/');
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
        Log::info($output['userId']);
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

        try {
            $user->name = $output['displayName'];
            $user->email = $payload['email'];
            $user->save();

            $social->picture = $output['pictureUrl'];
            $social->access_token = $accessToken;
            $social->refresh_token = $refreshToken;
            $social->exp = $exp;
            $user->social()->save($social);
        }catch(\Exception $e) {
            Log::error($e->getMessage());
        }

        // laravel login in
        Auth::loginUsingId($user->id);

        return redirect('/home');
    }
    // 20190612 Line login
    public function webhook(Request $request, $text)
    {
        // Validating the signature
        $header = $request->header();
        $xLineSignature = $header['x-line-signature'][0];

        $channelSecret = mb_convert_encoding(env('LINE_Messaging_CLIENT_SECRET'), "UTF-8");
        $httpRequestBody  = mb_convert_encoding(json_encode($request->all()), "UTF-8");
        $signature = base64_encode(hash_hmac('sha256', $httpRequestBody, $channelSecret, true));

        if ($xLineSignature != $signature) {
            return response('Not from LINE Platform', 401);
        }
        // Validating the signature

        Log::debug($request->all());
        $httpRequestBody = $request->all();

        // Message event
        if($httpRequestBody['events'][0]['type'] == 'message'){
            $messageType = $httpRequestBody['events'][0]['message']['type'];

            switch ($messageType) {
                case 'text':
                    $messageText =  $httpRequestBody['events'][0]['message']['text'];
                    break;
            }
        }
        // Message event
        return response('Success', 200);
    }


    // base64_decode
    private function fnUrlsafeB64Decode($data)
    {
        $remainder = strlen($data) % 4;

        if ($remainder > 0) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
    // base64_decode
    // Send reply message
    private function fnSRM()
    {
        $data = [

        ];

        $url = 'https://api.line.me/v2/bot/message/reply';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer '));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($httpcode != 200){
            return redirect('/');
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
    }
    // Send reply message
}
