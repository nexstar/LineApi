<?php

namespace App\Http\Controllers;

use App\User;
use App\Social;
use App\BotUserProfile;
use App\Order;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SocialController extends Controller
{
    // 20190612 Line Login
    public function callback(Request $request)
    {
        // Line Login authorization request error
        if ($request->has('error')) {
            Log::error($request->error); // Error code.
            Log::error($request->error_description); // Human-readable ASCII encoded text description of the error.
            Log::error($request->state); // OAuth 2.0 state value. Required if the authorization Request included the state parameter.
            return redirect('/');
        }
        // Line Login authorization request error

//        Log::info($request->code); // Authorization code used to get an access token. Valid for 10 minutes. This authorization code can only be used once.
//        Log::info($request->state); // state parameter included in the authorization URL of original request. Your application should verify that this value matches the one in the original request.
//        Log::info($request->friendship_status_changed); // true if the friendship status between the user and the LINE official account changes during login. Otherwise, false. This value is only returned if the bot_prompt query parameter is specified in the authorization request and the consent screen with the option to add your LINE official account as a friend is displayed to the user. For more information, see Linking a LINE official account with your LINE Login channel.
        // Line Login access token
        $url = 'https://api.line.me/oauth2/v2.1/token';

        $header = ['Content-Type: application/x-www-form-urlencoded'];

        $data = [
            'grant_type' => 'authorization_code', // Specifies the grant type
            'code' => $request->code, // Authorization code
            'redirect_uri' => env('LINE_CALLBACK_URL'), // Callback URL
            'client_id' => env('LINE_CLIENT_ID'), // Channel ID
            'client_secret' => env('LINE_CLIENT_SECRET') // Channel secret
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            return redirect('/');
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);

//        $output['access_token']; // Access token. Valid for 30 days.
//        $output['expires_in']; // Amount of time in seconds until the access token expires.
//        $output['id_token']; // JSON Web Token (JWT) that includes information about the user. This field is returned only if openid is specified in the scope.
//        $output['refresh_token']; // Token used to get a new access token. Valid up until 10 days after the access token expires.
//        $output['scope']; // Permissions granted by the user.
//        $output['token_type']; // Bearer.
        if ($output['token_type'] != 'Bearer'){
            return redirect('/');
        }

        // Line Login decod JWT header.payload.signature
        $idToken = explode('.', $output['id_token']);
        if (count($idToken) != 3){
            return redirect('/');
        }

        list($header64, $payload64, $signature) = $idToken;
        // Header
        $header = json_decode($this->fnUrlsafeB64Decode($header64), JSON_OBJECT_AS_ARRAY);
        if (empty($header['typ']) || empty($header['alg'])){
            return redirect('/');
        }
        // Header
        // Payload
        $channelSecret = mb_convert_encoding(env('LINE_CLIENT_SECRET'), "UTF-8");
        $httpRequestBody  = mb_convert_encoding($header64 . "." . $payload64, "UTF-8");
        // Signature
        $calcSignature  = hash_hmac('sha256', $httpRequestBody, $channelSecret, true);
        // Signature

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
//        if(isset($payload['nonce']) && $payload['nonce'] != ){ // 之前https://access.line.me/oauth2/v2.1/authorize的nonce
//
//        }
        // Payload
        // Line Login decod JWT header.payload.signature
        $accessToken = $output['access_token']; // Access token. Valid for 30 days.
        $refreshToken = $output['refresh_token']; // Token used to get a new access token. Valid up until 10 days after the access token expires.
        $expiresIn = strtotime('now') + $output['expires_in']; // Amount of time in seconds until the access token expires.
        // Line Login access token

        // Line Login user profiles
        $url = 'https://api.line.me/v2/profile';

        $header = ['Authorization: Bearer '.$accessToken];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            return redirect('/');
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
        // insert/update user and social
        $social = Social::where('provider', 'like', 'Line')->where('provider_user_id', $output['userId'])->first(); // 判斷資料庫是否有資料
        if($social){ // 更新
            $user = User::where('id', $social->user_id)->first(); // 取得用戶資料

            $social->provider = 'Line';
            $social->provider_user_id = $social->provider_user_id;
        }else{ // 新增
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
        // insert/update user and social
        // Line Login user profiles

        // laravel login in
        Auth::loginUsingId($user->id);
        // laravel login in

        return redirect('/home');
    }
    // 20190612 Line Login
    // 20190613 Refresh access token
    public function retoken(Request $request)
    {
//        $socials = Social::whereBetween('expires_in', [strtotime(date('Y-m-d')), strtotime(date('Y-m-d').'+10 day')])->get(); // 判斷資料庫是否有資料
        $socials = Social::all(); // 取得所有用戶
        foreach ($socials as $social){
            // Verifying access tokens
            $url = 'https://api.line.me/oauth2/v2.1/verify?access_token='.$social->access_token;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($ch);
            $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($statuscode != 200){
                $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
                Log::error($output['returnCode']); // 結果代碼
                Log::error($output['returnMessage']); // 結果訊息或失敗理由
            }

            $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
//            $output['scope']; // Permissions obtained through the access token.
//            $output['client_id']; // Channel ID for which the access token is issued.
//            $output['expires_in']; // Expiration date of the access token. Expressed as the remaining number of seconds to expiry from when the API was called.
            // Verifying access tokens

            // Refreshing access tokens
            $url = 'https://api.line.me/oauth2/v2.1/token';

            $header = ['Content-Type: application/x-www-form-urlencoded'];

            $data = [
                'grant_type' => 'refresh_token', // refresh_token
                'refresh_token' => $social->refresh_token, // Refresh token. Valid up until 10 days after the access token expires. You must log in the user again if the refresh token expires.
                'client_id' => env('LINE_CLIENT_ID'), // Channel ID
                'client_secret' => env('LINE_CLIENT_SECRET') // Channel secret
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($ch);
            $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($statuscode != 200){
                $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
                Log::error($output['returnCode']); // 結果代碼
                Log::error($output['returnMessage']); // 結果訊息或失敗理由
            }

            $output = json_decode($output, JSON_OBJECT_AS_ARRAY);

//            $output['token_type']; // Bearer
//            $output['scope']; // Permissions obtained through the access token.
//            $output['access_token']; // Access token. Valid for 30 days.
//            $output['refresh_token']; // Token used to get a new access token. Valid up until 10 days after the access token expires.
//            $output['expires_in']; // Expiration date of the access token. Expressed in the remaining number of seconds to expiry from when the API was called.

            $social->access_token = $output['access_token'];
            $social->refresh_token = $output['refresh_token'];
            $social->expires_in = strtotime('now') + $output['expires_in'];
            $social->save();

            return redirect('/home');
            // Refreshing access tokens
        }
    }
    // 20190613 Refresh access token
    // 20190619 Line Messaging API
    public function webhook(Request $request, $text)
    {
        // Validating the signature
        $header = $request->header();
        $xLineSignature = $header['x-line-signature'][0];

        $httpRequestBody  = mb_convert_encoding(json_encode($request->all()), "UTF-8");
        $channelSecret = mb_convert_encoding(env('LINE_Messaging_CLIENT_SECRET'), "UTF-8");
        $signature = base64_encode(hash_hmac('sha256', $httpRequestBody, $channelSecret, true));

        if ($xLineSignature != $signature) {
            Log::error('Not from LINE Platform');
        }
        // Validating the signature

        // Webhook event types
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

//                $httpRequestBody['events'][0]['message']['id']; // Message ID
                $messageType = $httpRequestBody['events'][0]['message']['type'];
                switch ($messageType) {
                    // Text
                    case 'text':
                        $messageText = $httpRequestBody['events'][0]['message']['text']; // Message text
                        if ($messageText == 'Hello'){
                            $this->fnSRM($replyToken, 'text');
                        }else{
                            $this->fnSRM($replyToken, 'imagemap');
                        }
                        break;
                    // Image
                    case 'image':
                        $imageType = $httpRequestBody['events'][0]['message']['contentProvider']['type']; // Provider of the image file.
                        switch ($imageType) {
                            // line: The image was sent by a LINE user.
                            case 'line':
                                $this->fnMC($httpRequestBody['events'][0]['message']['id']); // Gets image, video, and audio data sent by users.
                                $this->fnSRM($replyToken, 'image');
                                break;
                            // external: The image was sent using the LIFF liff.sendMessages() method.
                            case 'external':
//                                $httpRequestBody['events'][0]['message']['contentProvider']['originalContentUrl']; // URL of the image file.
//                                $httpRequestBody['events'][0]['message']['contentProvider']['previewImageUrl']; // URL of the preview image.
                                break;
                        }
                        break;
                    // Video
                    case 'video':
//                        $httpRequestBody['events'][0]['message']['duration']; // Length of video file (milliseconds)
                        $videoType = $httpRequestBody['events'][0]['message']['contentProvider']['type']; // Provider of the video file.
                        switch ($videoType) {
                            // line: The video was sent by a LINE user.
                            case 'line':
                                $this->fnMC($httpRequestBody['events'][0]['message']['id']); // Gets image, video, and audio data sent by users.
                                break;
                            // external: The video was sent using the LIFF liff.sendMessages() method.
                            case 'external':
//                                $httpRequestBody['events'][0]['message']['contentProvider']['originalContentUrl']; // URL of the video file.
//                                $httpRequestBody['events'][0]['message']['contentProvider']['previewImageUrl']; // URL of the preview image.
                                break;
                        }
                        break;
                    // Audio
                    case 'audio':
//                        $httpRequestBody['events'][0]['message']['duration']; // Length of audio file (milliseconds)
                        $audioType = $httpRequestBody['events'][0]['message']['contentProvider']['type']; // Provider of the audio file.
                        switch ($audioType) {
                            // line: The audio file was sent by a LINE user.
                            case 'line':
                                $this->fnMC($httpRequestBody['events'][0]['message']['id']); // Gets image, video, and audio data sent by users.
                                break;
                            // external: The audio file was sent using the LIFF liff.sendMessages() method.
                            case 'external':
//                                $httpRequestBody['events'][0]['message']['contentProvider']['originalContentUrl']; // URL of the audio file.
                                break;
                        }
                        break;
                    // File
                    case 'file':
//                        $httpRequestBody['events'][0]['message']['fileName']; // File name
//                        $httpRequestBody['events'][0]['message']['fileSize']; // File size in bytes
                        $this->fnMC($httpRequestBody['events'][0]['message']['id']); // Gets image, video, and audio data sent by users.
                        break;
                    // Location
                    case 'location':
//                        $locationTitle = $httpRequestBody['events'][0]['message']['title']; // Title
//                        $locationAddress = $httpRequestBody['events'][0]['message']['address']; // Address
//                        $locationLatitude = $httpRequestBody['events'][0]['message']['latitude']; // Latitude
//                        $locationLongitude = $httpRequestBody['events'][0]['message']['longitude']; // Longitude
                        break;
                    // Sticker
                    case 'sticker':
//                        $httpRequestBody['events'][0]['message']['packageId']; // Package ID
//                        $httpRequestBody['events'][0]['message']['stickerId']; // Sticker ID
                        $this->fnSRM($replyToken, 'sticker');
                        break;
                }
                break;
            // Follow event
            case 'follow':
                $replyToken = $httpRequestBody['events'][0]['replyToken'];
                $this->fnSRM($replyToken, 'text');
                break;
            // Unfollow event
            case 'unfollow':

                break;
        }
        // Event


        return response('Success', 200);
        // Webhook event types
    }
    // 20190619 Line Messaging API

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

        $header = ['Content-Type: application/x-www-form-urlencoded'];

        $data = [
            'grant_type' => 'client_credentials', // client_credentials
            'client_id' => env('LINE_Messaging_CLIENT_ID'), // Channel ID
            'client_secret' => env('LINE_Messaging_CLIENT_SECRET') //Channel secret
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
            Log::error($output['returnCode']); // 結果代碼
            Log::error($output['returnMessage']); // 結果訊息或失敗理由
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);

//        $output['access_token']; // Short-lived channel access token. Valid for 30 days. Note: Channel access tokens cannot be refreshed.
//        $output['expires_in']; // Time until channel access token expires in seconds from time the token is issued
//        $output['token_type']; // Bearer

        return $output['access_token'];
    }
    // Get channel access token
    // Getting user profile information
    private function fnMUPI($userId)
    {
        $url = 'https://api.line.me/v2/bot/profile/'.$userId;

        $header = ['Authorization: Bearer '.$this->fnMCAT()];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
            Log::error($output['returnCode']); // 結果代碼
            Log::error($output['returnMessage']); // 結果訊息或失敗理由
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
        BotUserProfile::updateOrCreate( // 新增更新bot用戶
            [
                'bot_user_id' => $output['userId'] // Identifier of the user
            ],
            [
                'display_name' => mb_convert_encoding($output['displayName'], "UTF-8"), // User's display name
                'picture_url' => $output['pictureUrl'], // Profile image URL. "https" image URL. Not included in the response if the user doesn't have a profile image.
                'status_message' => mb_convert_encoding($output['statusMessage'], "UTF-8") // User's status message. Not included in the response if the user doesn't have a status message.
            ]
        );
    }
    // Getting user profile information
    // Gets image, video, audio and file data sent by users.
    private function fnMC($messageId)
    {
        $url = 'https://api.line.me/v2/bot/message/'.$messageId.'/content';

        $header = ['Authorization: Bearer '.$this->fnMCAT()];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
            Log::error($output['returnCode']); // 結果代碼
            Log::error($output['returnMessage']); // 結果訊息或失敗理由
        }

        $output = base64_encode($output);
    }
    // Gets image, video, audio and file data sent by users.
    // Send reply message
    private function fnSRM($replyToken, $type)
    {
        switch ($type) {
            // Text
            case 'text':

                $code = '100097'; // LINE original emoji
                $bin = hex2bin(str_repeat('0', 8 - strlen($code)) . $code);
                $emoticona =  mb_convert_encoding($bin, 'UTF-8', 'UTF-32BE');

                $code = '10008A'; // LINE original emoji
                $bin = hex2bin(str_repeat('0', 8 - strlen($code)) . $code);
                $emoticonb =  mb_convert_encoding($bin, 'UTF-8', 'UTF-32BE');

//                array('type' => 'text', 'text' => 'Message text Max: 2000 characters');
                $messages = [
                    array('type' => 'text', 'text' => 'May I help you ?'.$emoticona),
                    array('type' => 'text', 'text' => 'NO !!'.$emoticonb),
                    array(
                        'type' => 'image',
                        'originalContentUrl' => asset('images/preview_rdoraemon.jpg'), // Image URL (Max: 1000 characters) HTTPS JPEG Max: 4096 x 4096 Max: 1 MB
                        'previewImageUrl' => asset('images/preview_rdoraemon.jpg') // Preview image URL (Max: 1000 characters) HTTPS JPEG Max: 240 x 240 Max: 1 MB
                    ),
                    array(
                        'type' => 'location',
                        'title' => '元智大學', // Title Max: 100 characters
                        'address' => '320桃園市中壢區遠東路135號', // Address Max: 100 characters
                        'latitude' => 24.9713158, // Latitude
                        'longitude' => 121.2652293 // Longitude
                    )
                ];
                break;
            // Sticker
            case 'sticker':

                $messages = [
                    array(
                        'type' => 'sticker',
                        'packageId' => '11538', // Package ID for a set of stickers.
                        'stickerId' => '51626498' // Sticker ID
                    )
                ];
                break;
            // Image
            case 'image':

                $messages = [
                    array(
                        'type' => 'image',
                        'originalContentUrl' => asset('images/original_rdoraemon.jpg'), // Image URL (Max: 1000 characters) HTTPS JPEG Max: 4096 x 4096 Max: 1 MB
                        'previewImageUrl' => asset('images/original_rdoraemon.jpg') // Preview image URL (Max: 1000 characters) HTTPS JPEG Max: 240 x 240 Max: 1 MB
                    )
                ];
                break;
            // Video
            case 'video':

                $messages = [
                    array(
                        'type' => 'video',
                        'originalContentUrl' => '', // URL of video file (Max: 1000 characters) HTTPS mp4 Max: 1 minute Max: 10 MB
                        'previewImageUrl' => '') // URL of preview image (Max: 1000 characters) HTTPS JPEG Max: 240 x 240 Max: 1 MB
                ];
                break;
            // Audio
            case 'audio':

                $messages = [
                    array(
                        'type' => 'audio',
                        'originalContentUrl' => '', // URL of audio file (Max: 1000 characters) HTTPS m4a Max: 1 minute Max: 10 MB
                        'duration' => '' // Length of audio file (milliseconds)
                    )
                ];
                break;
            // Location
            case 'location':

                $messages = [
                    array(
                        'type' => 'location',
                        'title' => '元智大學', // Title Max: 100 characters
                        'address' => '320桃園市中壢區遠東路135號', // Address Max: 100 characters
                        'latitude' => 24.9713158, // Latitude
                        'longitude' => 121.2652293 // Longitude
                    )
                ];
                break;
            // Imagemap
            case 'imagemap':

                $messages = [
                    array(
                        'type' => 'imagemap',
                        'baseUrl' => asset('images/original_rdoraemon.jpg#'), // Base URL of the image Max: 1000 characters HTTPS
                        'altText' => 'This is an Imagemap message test', // Alternative text Max: 400 characters
                        'baseSize' => array(
                            'width' => 1040, // Width of base image in pixels. Set to 1040.
                            'height' => 1040 // Height of base image.
                        ),
                        'video' => array(
                            'originalContentUrl' => asset('images/ThatGirlDJCHENRemix.mp4'), // URL of the video file (Max: 1000 characters) HTTPS mp4 Max: 1 minute Max: 10 MB
                            'previewImageUrl' => asset('images/preview_rdoraemon.jpg'), // URL of the preview image (Max: 1000 characters) HTTPS JPEG Max: 240 x 240 pixels Max: 1 MB
                            'area' => array(
                                'x' => 0, // Horizontal position of the video area relative to the left edge of the imagemap area. Value must be 0 or higher.
                                'y' => 0, // Vertical position of the video area relative to the top of the imagemap area. Value must be 0 or higher
                                'width' => 520, // Width of the video area
                                'height' => 520 // Height of the video area
                            ),
                            'externalLink' => array(
                                'linkUri' => 'https://www.youtube.com/watch?v=43gkGthJmS8', // Webpage URL. Called when the label displayed after the video is tapped. Max: 1000 characters The available schemes are http, https, line, and tel.
                                'label' => 'See More' // Label. Displayed after the video is finished. Max: 30 characters
                            )
                        ),
                        'actions' => [ // Action when tapped Max: 50
                            array(
                                'type' => 'uri',
                                'label' => 'https://www.yzu.edu.tw/', // Label for the action. Spoken when the accessibility feature is enabled on the client device. Max: 50 characters.
                                'linkUri' => 'https://www.yzu.edu.tw/',  // Webpage URL Max: 1000 characters The available schemes are http, https, line, and tel.
                                'area' => array(
                                    'x' => 0,
                                    'y' => 520,
                                    'width' => 520,
                                    'height' => 520
                                )
                            ),
                            array(
                                'type' => 'message',
                                'label' => 'Hello', // Label for the action. Spoken when the accessibility feature is enabled on the client device. Max: 50 characters.
                                'text' => 'Hello', // Message to send. Max: 400 characters.
                                'area' => array(
                                    'x' => 520,
                                    'y' => 0,
                                    'width' => 520,
                                    'height' => 1040
                                )
                            )
                        ]
                    )
                ];
                break;
            // Template
            case 'template':
                $messages = [
                    array(
                        'type' => 'template',
                        'altText' => 'This is a carousel template', // Alternative text. Max: 400 characters
                        'template' => array( // A Buttons, Confirm, Carousel, or Image Carousel object.
                            'type' => 'carousel',
                            'columns' => [
                                array(
                                    'thumbnailImageUrl' => asset('images/original_rdoraemon.jpg#'), // Image URL (Max: 1000 characters) HTTPS JPEG or PNG Aspect ratio: 1:1.51 Max width: 1024px Max: 1 MB
                                    'imageBackgroundColor' => '#FFFFFF', // Background color of image. Specify a RGB color value. The default value is #FFFFFF (white).
                                    'title' => 'this is menu', // Title Max: 40 characters
                                    'text' => 'description', // Message text Max: 120 characters (no image or title) Max: 60 characters (message with an image or title)
                                    'defaultAction' => array( // Action when image is tapped; set for the entire image, title, and text area
                                        'type' => 'uri',
                                        'label' => 'View detail', // Label for the action
                                        'uri' => 'https://developers.line.biz/en/reference/messaging-api/#carousel' // URI opened when the action is performed (Max: 1000 characters) The available schemes are http, https, line, and tel.
                                    ),
                                    'actions' => [
                                        array(
                                            'type' => 'message',
                                            'label' => 'Hello', // Label for the action
                                            'text' => 'Hello' // Text sent when the action is performed Max: 300 characters
                                        ),
                                        array(
                                            'type' => 'camera',
                                            'label' => 'Camera', // Label for the action Max: 20 characters
                                        )
                                    ]
                                ),
                                array(
                                    'thumbnailImageUrl' => asset('images/preview_rdoraemon.jpg#'), // Image URL (Max: 1000 characters) HTTPS JPEG or PNG Aspect ratio: 1:1.51 Max width: 1024px Max: 1 MB
                                    'imageBackgroundColor' => '#FFFFFF', // Background color of image. Specify a RGB color value. The default value is #FFFFFF (white).
                                    'title' => 'this is menu', // Title Max: 40 characters
                                    'text' => 'description', // Message text Max: 120 characters (no image or title) Max: 60 characters (message with an image or title)
                                    'defaultAction' => array( // Action when image is tapped; set for the entire image, title, and text area
                                        'type' => 'uri',
                                        'label' => 'View detail', // Label for the action
                                        'uri' => 'https://developers.line.biz/en/reference/messaging-api/#carousel' // URI opened when the action is performed (Max: 1000 characters) The available schemes are http, https, line, and tel.
                                    ),
                                    'actions' => [
                                        array(
                                            'type' => 'message',
                                            'label' => 'Hello', // Label for the action
                                            'text' => 'Hello' // Text sent when the action is performed Max: 300 characters
                                        ),
                                        array(
                                            'type' => 'camera',
                                            'label' => 'Camera', // Label for the action Max: 20 characters
                                        )
                                    ]
                                )
                            ]
                        )
                    )
                ];
                break;
        }

        $header = [
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->fnMCAT()
        ];

        $data = [
            'replyToken' => $replyToken, // Reply token received via webhook
            'messages' => $messages, // Messages Max: 5
            'notificationDisabled' => false // true: The user doesn't receive a push notification when the message is sent. false: The user receives a push notification when the message is sent (unless they have disabled push notifications in LINE and/or their device).
        ];

        $url = 'https://api.line.me/v2/bot/message/reply';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($statuscode != 200){
            Log::error('Send reply message failed');
        }
    }
    // Send reply message
    // 20190619 Send push message
    public function spm(Request $request)
    {
        $botUserProfile = BotUserProfile::find($request->id); // 取得用戶

        $messages = [
            array(
                'type' => 'template',
                'altText' => 'This is a carousel template', // Alternative text. Max: 400 characters
                'template' => array( // A Buttons, Confirm, Carousel, or Image Carousel object.
                    'type' => 'carousel',
                    'columns' => [
                        array(
                            'thumbnailImageUrl' => asset('images/original_rdoraemon.jpg#'), // Image URL (Max: 1000 characters) HTTPS JPEG or PNG Aspect ratio: 1:1.51 Max width: 1024px Max: 1 MB
                            'imageBackgroundColor' => '#FFFFFF', // Background color of image. Specify a RGB color value. The default value is #FFFFFF (white).
                            'title' => 'this is menu', // Title Max: 40 characters
                            'text' => 'description', // Message text Max: 120 characters (no image or title) Max: 60 characters (message with an image or title)
                            'defaultAction' => array( // Action when image is tapped; set for the entire image, title, and text area
                                'type' => 'uri',
                                'label' => 'View detail', // Label for the action
                                'uri' => 'https://developers.line.biz/en/reference/messaging-api/#carousel' // URI opened when the action is performed (Max: 1000 characters) The available schemes are http, https, line, and tel.
                            ),
                            'actions' => [
                                array(
                                    'type' => 'message',
                                    'label' => 'Hello', // Label for the action
                                    'text' => 'Hello' // Text sent when the action is performed Max: 300 characters
                                ),
                                array(
                                    'type' => 'camera',
                                    'label' => 'Camera', // Label for the action Max: 20 characters
                                )
                            ]
                        ),
                        array(
                            'thumbnailImageUrl' => asset('images/preview_rdoraemon.jpg#'), // Image URL (Max: 1000 characters) HTTPS JPEG or PNG Aspect ratio: 1:1.51 Max width: 1024px Max: 1 MB
                            'imageBackgroundColor' => '#FFFFFF', // Background color of image. Specify a RGB color value. The default value is #FFFFFF (white).
                            'title' => 'this is menu', // Title Max: 40 characters
                            'text' => 'description', // Message text Max: 120 characters (no image or title) Max: 60 characters (message with an image or title)
                            'defaultAction' => array( // Action when image is tapped; set for the entire image, title, and text area
                                'type' => 'uri',
                                'label' => 'View detail', // Label for the action
                                'uri' => 'https://developers.line.biz/en/reference/messaging-api/#carousel' // URI opened when the action is performed (Max: 1000 characters) The available schemes are http, https, line, and tel.
                            ),
                            'actions' => [
                                array(
                                    'type' => 'message',
                                    'label' => 'Hello', // Label for the action
                                    'text' => 'Hello' // Text sent when the action is performed Max: 300 characters
                                ),
                                array(
                                    'type' => 'cameraRoll',
                                    'label' => 'Camera roll', // Label for the action Max: 20 characters
                                ),
                                array(
                                    'type' => 'location',
                                    'label' => 'Location', // Label for the action Max: 20 characters
                                )
                            ]
                        )
                    ]
                )
            )
        ];

        $url = 'https://api.line.me/v2/bot/message/push';

        $header = [
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->fnMCAT()
        ];

        $data = [
            'to' => $botUserProfile->bot_user_id, // ID of the target recipient. Use a userId, groupId, or roomId value returned in a webhook event object. Do not use the LINE ID found on LINE.
            'messages' => $messages, // Messages Max: 5
            'notificationDisabled' => false // true: The user doesn't receive a push notification when the message is sent. false: The user receives a push notification when the message is sent (unless they have disabled push notifications in LINE and/or their device).
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            Log::error('Send push message failed');
        }

        return back();
    }
    // 20190619 Send push message

    // Line Pay API
    public function reserveapi(Request $request) // 付款 reserve API
    {
        $header = [ // Request Header
            'Content-Type: application/json',
            'X-LINE-ChannelId: '.env('LINE_Pay_CLIENT_ID'), // Channel ID
            'X-LINE-ChannelSecret: '.env('LINE_Pay_CLIENT_SECRET'), // Channel Secret Key
//            'X-LINE-MerchantDeviceType: Device Type' // 離線支援(不知道這是什麼)
        ];

        $url = 'https://sandbox-api-pay.line.me/v2/payments/request'; // 付款 reserve API

        $orderId = date('YmdHis').'1000'; // 訂單編號

        $data = [
            'productName' => mb_convert_encoding('測試付款API', "UTF-8"), // 產品名稱
            'productImageUrl' => asset('images/f256x256.png'), // 產品影像 URL 84 x 84
            'amount' => 1000, // 付款金額
            'currency' => 'TWD', // 付款貨幣
//            'mid' => 'String', // 將要進行付款的 LINE 使用者之 mid(不知道怎麼取得)
//            'oneTimeKey' => 'String', // 是讀取 LINE Pay app 所提供之二維碼、條碼後之結果(不知道怎麼取得)
            'confirmUrl' => 'https://line.jnadtechoauth.com/api/line/confirmapi', // 商家可以呼叫付款confirm API 並完成付款，傳遞額外的 "transactionId" 參數
            'confirmUrlType' => 'CLIENT', // 被重新導向到的 URL 所屬的類型，CLIENT: 手機交易流，SERVER: 網站交易流程
            'checkConfirmUrlBrowser' => false, // 確認使用的瀏覽器相同與否
            'cancelUrl' => 'https://line.jnadtechoauth.com/', // 取消付款頁面的 URL
//            'packageName' => 'String', // 在 Android 各應用程式間轉換時，防止網路釣魚詐騙的資訊(不知道怎麼取得)
            'orderId' => $orderId, // 商家自行管理的唯一訂單編號
//            'deliveryPlacePhone' => 'String', // 收件人的聯絡資訊 (用於風險管理)
            'payType' => 'PREAPPROVED', // 付款類型，NORMAL: 單筆付款，PREAPPROVED: 自動付款
//            'langCd' => 'String', // 等待付款畫面 (paymentUrl) 的語言代碼。共支援六種語言
            'capture' => true, // 指定是否請款，true: 直接立即進行付款授權與請款，false: 要執行授權才能進行付款授權與請款
            'extras' => [
                'addFriends' => [ // 加好友清單
                    [
                        'type' => 'LINE_AT', // 服務類型
                        'idList' => ['@252hlgrz'] // ID 清單
                    ]
                ],
                'branchName' => 'Nianbao Line Pay' // 需要付款的商店/分店名稱(僅會顯示前 100 字元)
            ],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            Log::error('Reserve API failed');
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
        if($output['returnCode'] == '0000'){ // 結果代碼

            // 儲存訂單
            $order = new Order;
            $order->orderId = $orderId; // 訂單編號
            $order->transactionId = $output['info']['transactionId']; // 交易編號
            $order->amount = 1000; // 付款金額
            $order->currency = 'TWD'; // 付款貨幣
            $order->status = 'reserve'; // 付款狀態
            $order->regKey = null; // paytype 為 Preapproved 之時候，以後可直接選用的自動付款金鑰
            $order->save();
            // 儲存訂單

            return redirect($output['info']['paymentUrl']['web']); // 付款請求後所前往的網頁 URL
//            return redirect($output['info']['paymentUrl']['app']); // 前往付款畫面的應用程式 URL
        }else{
            Log::error($output['returnCode']); // 結果代碼
            Log::error($output['returnMessage']); // 結果訊息或失敗理由
        }
    }
    public function confirmapi(Request $request) // 付款 confirm API
    {
        $header = [ // Request Header
            'Content-Type: application/json',
            'X-LINE-ChannelId: '.env('LINE_Pay_CLIENT_ID'), // Channel ID
            'X-LINE-ChannelSecret: '.env('LINE_Pay_CLIENT_SECRET'), // Channel Secret Key
//            'X-LINE-MerchantDeviceType: Device Type' // 離線支援(不知道這是什麼)
        ];

        $url = 'https://sandbox-api-pay.line.me/v2/payments/'.$request->transactionId.'/confirm'; // 付款 confirm API

        // 查詢訂單
        $order = Order::where('transactionId', $request->transactionId)->first();
        if(!$order){
            return redirect('home')->with('confirm', '付款失敗:'.$request->transactionId);
        }
        // 查詢訂單

        $data = [
            'amount' => intval($order->amount), // 付款金額
            'currency' => $order->currency // 付款貨幣
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            Log::error('Confirm API failed');
        }

        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);
        if($output['returnCode'] == '0000'){ // 結果代碼

//            $output['info']['authorizationEx'] 當付款狀態為 AUTHORIZATION (capture=false) 時，可選擇的授權過期日
//            $output['info']['payInfo'][0]['method'] 使用的付款方式 (信用卡： CREDIT_CARD，餘額： BALANCE，折扣: DISCOUNT)
//            $output['info']['payInfo'][0]['amount'] 付款金額
//            $output['info']['payInfo'][0]['creditCardNickname'] (paytype = Preapproved 之時候) 信用卡暱稱
//            $output['info']['payInfo'][0]['creditCardBrand'] (paytype = Preapproved 之時候) 信用卡品牌

            // 更新訂單
            $order->status = 'confirm'; // 付款狀態
            $order->regKey = $output['info']['regKey']; // paytype 為 Preapproved 之時候，以後可直接選用的自動付款金鑰
            $order->save();
            // 更新訂單

            return redirect('home');
        }else{
            Log::error($output['returnCode']); // 結果代碼
            Log::error($output['returnMessage']); // 結果訊息或失敗理由
        }
    }
    public function paymentsapi(Request $request) // 查看付款紀錄 API
    {
        $header = [ // Request Header
            'Content-Type: application/json',
            'X-LINE-ChannelId: '.env('LINE_Pay_CLIENT_ID'), // Channel ID
            'X-LINE-ChannelSecret: '.env('LINE_Pay_CLIENT_SECRET'), // Channel Secret Key
//            'X-LINE-MerchantDeviceType: Device Type' // 離線支援(不知道這是什麼)
        ];

        // 擇一
        $data = http_build_query([
            'transactionId' => $request->transactionId // 由 LINE Pay 核發的交易編號，用於付款或退款
//            'orderId' => $request->orderId // 商家的訂單編號
        ]);

        $url = 'https://sandbox-api-pay.line.me/v2/payments/?'.$data;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            Log::error('Payments API failed');
        }

        $outputArr = json_decode($output, JSON_OBJECT_AS_ARRAY);
        if($outputArr['returnCode'] == '0000'){ // 結果代碼

//            $output['info']['transactionId'] 交易編號 (19 位數)
//            $output['info']['transactionDate'] 交易日期與時間
//            $output['info']['transactionType'] 交易類型 PAYMENT:付款 PAYMENT_REFUND:退款 PARTIAL_REFUND:部分退款
//            $output['info']['productName'] 產品名稱
//            $output['info']['merchantName'] Merchant Name
//            $output['info']['currency'] 貨幣
//            $output['info']['authorizationExpireD'] 授權過期日期與時間
//            $output['info']['payInfo'][0]['method'] 使用的付款方式(信用卡：CREDIT_CARD，餘額：BALANCE，折扣: DISCOUNT)
//            $output['info']['payInfo'][0]['amount'] 交易金額 (產生交易編號時的交易金額)
            // 擷取原始交易時的最終交易金額為sum(info[].payInfo[].amount) – sum(refundList[].refundAmount)
//            $output['info']['refundList'][0]['refundTransactionId'] 退款的交易編號 (19 位數)
//            $output['info']['refundList'][0]['transactionType'] 交易類型 PAYMENT_REFUND:退款 PARTIAL_REFUND:部分退款
//            $output['info']['refundList'][0]['refundAmount'] 退款金額
//            $output['info']['refundList'][0]['refundTransactionDate'] 退款的交易日期與時間

            // 如果是用refundTransactionId
//            $output['info']['originalTransactionId'] 原始付款的交易編號 (19 位數)

            return redirect('home')->with('payments', $output);
        }else{
            Log::error($output['returnCode']); // 結果代碼
            Log::error($output['returnMessage']); // 結果訊息或失敗理由
        }
    }
    public function refundapi(Request $request) // 退款 API
    {
        $header = [ // Request Header
            'Content-Type: application/json',
            'X-LINE-ChannelId: '.env('LINE_Pay_CLIENT_ID'), // Channel ID
            'X-LINE-ChannelSecret: '.env('LINE_Pay_CLIENT_SECRET'), // Channel Secret Key
//            'X-LINE-MerchantDeviceType: Device Type' // 離線支援(不知道這是什麼)
        ];

        // 查詢訂單
        $order = Order::where('transactionId', $request->transactionId)->first();
        if(!$order){
            return redirect('home')->with('refund', '退款失敗:'.$request->transactionId);
        }
        // 查詢訂單

        $url = 'https://sandbox-api-pay.line.me/v2/payments/'.$request->transactionId.'/refund';

        $data = [
            'refundAmount' => 100 // 退款金額，如果未傳遞此參數，則全額退款
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            Log::error('Refund API failed');
        }

        $outputArr = json_decode($output, JSON_OBJECT_AS_ARRAY);
        if($outputArr['returnCode'] == '0000'){ // 結果代碼

            // 更新訂單
            $order->status = 'refund'; // 付款狀態
            $order->save();
            // 更新訂單

//            $output['info']['refundTransactionId'] 退款的交易編號 (新核發的編號 - 19 位數)
//            $output['info']['refundTransactionDate'] 退款的交易日期與時間

            return redirect('home')->with('refund', $output);
        }else{
            Log::error($output['returnCode']); // 結果代碼
            Log::error($output['returnMessage']); // 結果訊息或失敗理由
            return redirect('home')->with('refund', $output);
        }
    }
    public function checkregkeyapi($regkey) // 查看 regKey 狀態 API
    {
        $header = [ // Request Header
            'Content-Type: application/json',
            'X-LINE-ChannelId: '.env('LINE_Pay_CLIENT_ID'), // Channel ID
            'X-LINE-ChannelSecret: '.env('LINE_Pay_CLIENT_SECRET'), // Channel Secret Key
//            'X-LINE-MerchantDeviceType: Device Type' // 離線支援(不知道這是什麼)
        ];

        $data = http_build_query([
            'creditCardAuth' => false // 試圖驗證買家在 regKey 設定之信用卡之最少金額與否
        ]);

        $url = 'https://sandbox-api-pay.line.me/v2/payments/preapprovedPay/'.$regkey.'/check?'.$data;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            Log::error('Check regKey status failed');
        }

        $outputArr = json_decode($output, JSON_OBJECT_AS_ARRAY);
        if($outputArr['returnCode'] == '0000'){ // 結果代碼
            return 1;
        }else{
            Log::error($output['returnCode']); // 結果代碼
            Log::error($output['returnMessage']); // 結果訊息或失敗理由
            return 0;
        }
    }
    public function regkeyapi(Request $request) // 自動付款 API
    {
        $order = Order::where('orderId', $request->orderId)->firstOrFail(); // 查詢訂單
        // 查看 regKey 狀態 API
        if($this->checkregkeyapi($order->regKey) == 0){
            return redirect('home')->with('regkey', 'Check regKey API failed');
        }
        // 查看 regKey 狀態 API

        $header = [ // Request Header
            'Content-Type: application/json',
            'X-LINE-ChannelId: '.env('LINE_Pay_CLIENT_ID'), // Channel ID
            'X-LINE-ChannelSecret: '.env('LINE_Pay_CLIENT_SECRET'), // Channel Secret Key
//            'X-LINE-MerchantDeviceType: Device Type' // 離線支援(不知道這是什麼)
        ];

        $url = 'https://sandbox-api-pay.line.me/v2/payments/preapprovedPay/'.$order->regKey.'/payment';

        $orderId = date('YmdHis').'0100'; // 訂單編號

        $data = [
            'productName' => mb_convert_encoding('測試自動付款API', "UTF-8"), // 產品名稱
            'amount' => 100, // 付款金額
            'currency' => 'TWD', // 付款貨幣
            'orderId' => $orderId, // 是商家自行管理的訂單編號
            'capture' => true // 指定是否請款，true: 直接立即進行付款授權與請款，false: 要執行授權才能進行付款授權與請款
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($statuscode != 200){
            Log::error('regKey API failed');
        }

        $outputArr = json_decode($output, JSON_OBJECT_AS_ARRAY);
        if($outputArr['returnCode'] == '0000'){ // 結果代碼

            // 更新訂單
            $order->status = 'regKey'; // 付款狀態
            $order->save();
            // 更新訂單

            return redirect('home')->with('regkey', $output);
        }else{
            Log::error($output['returnCode']); // 結果代碼
            Log::error($output['returnMessage']); // 結果訊息或失敗理由
            return redirect('home')->with('regkey', $output);
        }
    }
    // Line Pay API
}
