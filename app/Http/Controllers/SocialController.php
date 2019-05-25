<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SocialController extends Controller
{
    public function callback(Request $request)
    {
        //print_r($request->all());

        if ($request->has('error')) {
            return redirect('/');
        }

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

        $output = json_decode($output);

        print_r($output);
    }
}
