<?php

namespace App\Http\Controllers;

use App\User;
use App\Social;
use App\BotUserProfile;

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
        $expiresIn = strtotime('now') + $output['expires_in'];

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
            $social->expires_in = $expiresIn;
            $user->social()->save($social);
        }catch(\Exception $e) {
            Log::error($e->getMessage());
        }

        // laravel login in
        Auth::loginUsingId($user->id);

        return redirect('/home');
    }
    // 20190612 Line login
    // 20190613 Refresh access token
    public function retoken(Request $request)
    {
//        $socials = Social::whereBetween('expires_in', [strtotime(date('Y-m-d')), strtotime(date('Y-m-d').'+10 day')])->get();
        $socials = Social::all();
        foreach ($socials as $social){
            // Verifying access tokens
            $url = 'https://api.line.me/oauth2/v2.1/verify?access_token='.$social->access_token;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($ch);
            $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($statuscode == 400){
                $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
                Log::info($output['error']);
                Log::info($output['error_description']);
            }
            if($statuscode == 200) {
                $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
                Log::info($output['scope']);
                Log::info($output['client_id']);
                Log::info($output['expires_in']);
            }
            // Verifying access tokens

            // Refreshing access tokens
            $url = 'https://api.line.me/oauth2/v2.1/token';
            $data = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $social->refresh_token,
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
                $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
                Log::error($output['error']);
                Log::error($output['error_description']);
            }

            $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
            Log::info($output['token_type']);
            Log::info($output['scope']);

            $social->access_token = $output['access_token'];
            $social->refresh_token = $output['refresh_token'];
            $social->expires_in = strtotime('now') + $output['expires_in'];
            $social->save();

            return redirect('/');
            // Refreshing access tokens
        }
    }
    // 20190613 Refresh access token
    // 20190619 Send push message
    public function spm(Request $request)
    {
        $botUserProfile = BotUserProfile::find(1);

        $code = '100097';
        $bin = hex2bin(str_repeat('0', 8 - strlen($code)) . $code);
        $emoticon =  mb_convert_encoding($bin, 'UTF-8', 'UTF-32BE');

        $messages = [
            array('type' => 'text', 'text' => 'May I help you?'.$emoticon)
        ];

        $url = 'https://api.line.me/v2/bot/message/push';
        $data = [
            'to' => $botUserProfile->bot_user_id,
            'messages' => $messages,
            'notificationDisabled' => false
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer '.$this->fnMCAT()));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($httpcode != 200){
            Log::error('Send push message failed');
        }

        Log::info('Send push message success');
    }
    // 20190619 Send push message
    // 20190619 webhook
    public function webhook(Request $request, $text)
    {
        // Validating the signature
        $header = $request->header();
        $xLineSignature = $header['x-line-signature'][0];

        Log::info($xLineSignature);
        $channelSecret = mb_convert_encoding(env('LINE_Messaging_CLIENT_SECRET'), "UTF-8");
        $httpRequestBody  = mb_convert_encoding(json_encode($request->all()), "UTF-8");
        $signature = base64_encode(hash_hmac('sha256', $httpRequestBody, $channelSecret, true));

        if ($xLineSignature != $signature) {
            Log::error('Not from LINE Platform');
        }
        // Validating the signature

        $httpRequestBody = $request->all();

        // Common properties
        $sourceType =  $httpRequestBody['events'][0]['source']['type'];
        switch ($sourceType) {
            // user
            case 'user':
                $this->fnMUPI($httpRequestBody['events'][0]['source']['userId']);
                break;
            // group
            case 'group':

                break;
            // room
            case 'room':

                break;
        }
        // Common properties

        // Event
        $type =  $httpRequestBody['events'][0]['type'];
        switch ($type) {
            // Message event
            case 'message':
                $replyToken = $httpRequestBody['events'][0]['replyToken'];
                $messageType = $httpRequestBody['events'][0]['message']['type'];

                switch ($messageType) {
                    // Text
                    case 'text':
                        $messageText = $httpRequestBody['events'][0]['message']['text'];
                        $this->fnSRM($replyToken, 'template');
                        break;
                    // Image
                    case 'image':
                        $imageType = $httpRequestBody['events'][0]['message']['contentProvider']['type'];
                        switch ($imageType) {
                            case 'line':
                                $this->fnMC($httpRequestBody['events'][0]['message']['id']);
                                $this->fnSRM($replyToken, 'image');
                                break;
                        }
                        break;
                    // Video
                    case 'video':
                        $videoType = $httpRequestBody['events'][0]['message']['contentProvider']['type'];
                        $videoDuration = $httpRequestBody['events'][0]['message']['duration'];
                        switch ($videoType) {
                            case 'line':
                                $this->fnMC($httpRequestBody['events'][0]['message']['id']);
                                $this->fnSRM($replyToken, 'video');
                                break;
                        }
                        break;
                    // Audio
                    case 'audio':
                        $audioType = $httpRequestBody['events'][0]['message']['contentProvider']['type'];
                        $audioDuration = $httpRequestBody['events'][0]['message']['duration'];
                        switch ($audioType) {
                            case 'line':
                                $this->fnMC($httpRequestBody['events'][0]['message']['id']);
                                break;
                        }
                        break;
                    // File
                    case 'file':
                        $fileType = $httpRequestBody['events'][0]['message']['contentProvider']['type'];
                        $audiofileName = $httpRequestBody['events'][0]['message']['fileName'];
                        $audiofileSize = $httpRequestBody['events'][0]['message']['fileSize'];
                        $this->fnMC($httpRequestBody['events'][0]['message']['id']);
                        break;
                    // Location
                    case 'location':
                        $locationTitle = $httpRequestBody['events'][0]['message']['title'];
                        $locationAddress = $httpRequestBody['events'][0]['message']['address'];
                        $locationLatitude = $httpRequestBody['events'][0]['message']['latitude'];
                        $locationLongitude = $httpRequestBody['events'][0]['message']['longitude'];
                        $this->fnSRM($replyToken, 'location');
                        break;
                    // Sticker
                    case 'sticker':
                        $this->fnSRM($replyToken, 'sticker');
                        break;
                }
                break;
            // Follow event
            case 'follow':
                
                break;
        }
        // Event
        return response('Success', 200);
    }
    // 20190619 webhook

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
    // Get channel access token
    private function fnMCAT()
    {
        $url = 'https://api.line.me/v2/oauth/accessToken';
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => env('LINE_Messaging_CLIENT_ID'),
            'client_secret' => env('LINE_Messaging_CLIENT_SECRET')
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
            $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
            Log::error($output['error']);
            Log::error($output['error_description']);
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
        Log::info($output['token_type']);
        Log::info($output['expires_in']);

        return $output['access_token'];
    }
    // Get channel access token
    // Getting user profile information
    private function fnMUPI($userId)
    {
        $url = 'https://api.line.me/v2/bot/profile/'.$userId;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$this->fnMCAT()));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
            Log::error($output['error']);
            Log::error($output['error_description']);
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
        BotUserProfile::updateOrCreate(
            [
                'bot_user_id' => $output['userId']
            ],
            [
                'display_name' => mb_convert_encoding($output['displayName'], "UTF-8"),
                'picture_url' => $output['pictureUrl'],
                'status_message' => mb_convert_encoding($output['statusMessage'], "UTF-8")
            ]
        );

        Log::info('save user profile information');
    }
    // Getting user profile information
    // Get content
    private function fnMC($messageId)
    {
        $url = 'https://api.line.me/v2/bot/message/'.$messageId.'/content';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$this->fnMCAT()));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
            Log::error($output['error']);
            Log::error($output['error_description']);
        }

        $output = base64_encode($output);
        Log::info('save user profile information');
    }
    // Get content
    // Send reply message
    private function fnSRM($replyToken, $type)
    {
        switch ($type) {
            // Text
            case 'text':

                $code = '100097';
                $bin = hex2bin(str_repeat('0', 8 - strlen($code)) . $code);
                $emoticon =  mb_convert_encoding($bin, 'UTF-8', 'UTF-32BE');

                $messages = [
                    array('type' => 'text', 'text' => 'May I help you?'.$emoticon)
                ];
                break;
            // Sticker
            case 'sticker':
                $messages = [
                    array('type' => 'sticker', 'packageId' => '11538', 'stickerId' => '51626498')
                ];
                break;
            // Image
            case 'image':
                $messages = [
                    array('type' => 'image',
                        'originalContentUrl' => 'https://gss0.bdstatic.com/-4o3dSag_xI4khGkpoWK1HF6hhy/baike/crop%3D5%2C4%2C178%2C178%3Bh%3D240%3Bq%3D95/sign=158935c6ca11728b2462d662f5c9effa/c8ea15ce36d3d5397289b5943d87e950352ab035.jpg',
                        'previewImageUrl' => 'https://gss0.bdstatic.com/-4o3dSag_xI4khGkpoWK1HF6hhy/baike/crop%3D5%2C4%2C178%2C178%3Bh%3D240%3Bq%3D95/sign=158935c6ca11728b2462d662f5c9effa/c8ea15ce36d3d5397289b5943d87e950352ab035.jpg')
                ];
                break;
            // Video
            case 'video':
                $messages = [
                    array('type' => 'video',
                        'originalContentUrl' => '',
                        'previewImageUrl' => 'https://gss0.bdstatic.com/-4o3dSag_xI4khGkpoWK1HF6hhy/baike/crop%3D5%2C4%2C178%2C178%3Bh%3D240%3Bq%3D95/sign=158935c6ca11728b2462d662f5c9effa/c8ea15ce36d3d5397289b5943d87e950352ab035.jpg')
                ];
                break;
            // Audio
            case 'audio':
                $messages = [
                    array('type' => 'audio',
                        'originalContentUrl' => '',
                        'duration' => 600)
                ];
                break;
            // Location
            case 'location':
                $messages = [
                    array('type' => 'location',
                        'title' => '元智大學',
                        'address' => '320桃園市中壢區遠東路135號',
                        'latitude' => 24.9713158,
                        'longitude' => 121.2652293)
                ];
                break;
            // Imagemap
            case 'imagemap':
                $messages = [
                    array('type' => 'imagemap',
                        'baseUrl' => 'https://gss0.bdstatic.com/-4o3dSag_xI4khGkpoWK1HF6hhy/baike/crop%3D5%2C4%2C178%2C178%3Bh%3D240%3Bq%3D95/sign=158935c6ca11728b2462d662f5c9effa/c8ea15ce36d3d5397289b5943d87e950352ab035.jpg',
                        'altText' => 'This is an imagemap',
                        'baseSize' => array('width' => 1040,
                            'height' => 1040
                        ),
                        'video' => array('originalContentUrl' => 'https://www.youtube.com/watch?v=uuX_2MDaEzY',
                            'previewImageUrl' => 'https://gss0.bdstatic.com/-4o3dSag_xI4khGkpoWK1HF6hhy/baike/crop%3D5%2C4%2C178%2C178%3Bh%3D240%3Bq%3D95/sign=158935c6ca11728b2462d662f5c9effa/c8ea15ce36d3d5397289b5943d87e950352ab035.jpg',
                            'area' => array('x' => 0,
                                'y' => 0,
                                'width' => 1040,
                                'height' => 585
                            ),
                            'externalLink' => array('linkUri' => 'https://www.yzu.edu.tw/',
                                'label' => 'See More',
                            )
                        ),
                        'actions' => [
                            array('type' => 'uri',
                                'linkUri' => 'https://www.yzu.edu.tw/',
                                'area' => array('x' => 0,
                                    'y' => 0,
                                    'width' => 520,
                                    'height' => 454
                                )
                            ),
                            array('type' => 'message',
                                'text' => 'Hello',
                                'area' => array('x' => 520,
                                    'y' => 586,
                                    'width' => 520,
                                    'height' => 454
                                )
                            )
                        ]
                    )
                ];
                break;
            // Template
            case 'template':
                $messages = [
                    array('type' => 'template',
                        'altText' => 'This is a buttons template',
                        'template' => array(
                            'type' => 'buttons',
                            'thumbnailImageUrl' => 'https://gss0.bdstatic.com/-4o3dSag_xI4khGkpoWK1HF6hhy/baike/crop%3D5%2C4%2C178%2C178%3Bh%3D240%3Bq%3D95/sign=158935c6ca11728b2462d662f5c9effa/c8ea15ce36d3d5397289b5943d87e950352ab035.jpg',
                            'imageAspectRatio' => 'rectangle',
                            'imageSize' => 'cover',
                            'imageBackgroundColor' => '#FFFFFF',
                            'title' => 'Menu',
                            'text' => 'Please select',
                            'defaultAction' => array(
                                'type' => 'uri',
                                'label' => 'View detail',
                                'uri' => 'https://www.youtube.com/watch?v=qFDB57T_5BM&list=RDBrRog8JncTc&index=3',
                            ),
                            'actions' => [
                                array(
                                    'type' => 'camera',
                                    'label' => 'Camera'
                                ),
                                array(
                                    'type' => 'message',
                                    'label' => 'Yes',
                                    'text' => 'Yes'
                                )
                            ]
                        )
                    )
                ];
                break;
        }

        $data = [
            'replyToken' => $replyToken,
            'messages' => $messages,
            'notificationDisabled' => false
        ];

//        Log::debug(json_encode($data));

        $url = 'https://api.line.me/v2/bot/message/reply';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer '.$this->fnMCAT()));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($httpcode != 200){
            Log::error('Send reply message failed');
        }

        Log::info('Send reply message success');
    }
    // Send reply message
}
