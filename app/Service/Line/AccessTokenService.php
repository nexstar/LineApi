<?php
/**
 * Created by PhpStorm.
 * User: AD-1-2
 * Date: 2019/9/4
 * Time: 下午 12:10
 */

namespace App\Service\Line;
use Ixudra\Curl\Facades\Curl;

class AccessTokenService
{
    public function GetToken(){
        $url = 'https://api.line.me/v2/oauth/accessToken';

        $header = ['Content-Type: application/x-www-form-urlencoded'];

        $data = [
            'grant_type'    => 'client_credentials', // client_credentials
            'client_id'     => '1615118687', // Channel ID
            'client_secret' => '4dc4b583e58a80a6a9fbcc58d979d2e3' //Channel secret
        ];

        $Response = Curl::to($url)
            ->withHeaders($header)
            ->withData($data)
            ->returnResponseObject()
            ->post();

        $Response = collect($Response);
        $Status   = $Response['status']; // http code 200
        $Content  = json_decode($Response['content'],true);
        //"access_token" => "token"
        //"expires_in"   => 2592000
        //"token_type"   => "Bearer"
        return $Content['access_token'];
    }
}