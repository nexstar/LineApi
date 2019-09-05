<?php
namespace App\Service\Line;

use Illuminate\Support\Collection;
use Ixudra\Curl\Facades\Curl;

class MessageService
{
    private $PushPerson   = 'https://api.line.me/v2/bot/message/push';
    public $RooTUserIDs   = ['U58c2fae184451125ab1863cb4c2b418b'];
    private $ReplyUrl     = 'https://api.line.me/v2/bot/message/reply';
    private $MultiCastUrl = 'https://api.line.me/v2/bot/message/multicast';
    private $ProfileUrl   = 'https://api.line.me/v2/bot/profile/';
    private $GetPushCountUrl  = 'https://api.line.me/v2/bot/message/delivery/push';
    private $GetReplyCountUrl = 'https://api.line.me/v2/bot/message/delivery/reply';
    private $GetMultiCastCountUrl = 'https://api.line.me/v2/bot/message/delivery/multicast';

    private $header;

    public function __construct()
    {
        // The Token has to change db no always go to Line
        $AccessTokenService = new AccessTokenService();
        $this->header = [
            'Content-Type: application/json',
            'Authorization: Bearer '.$AccessTokenService->GetToken()
        ];
    }

    /**
     * Date the messages were sent
     * Format: yyyyMMdd (Example: 20191231)
     * Timezone: UTC+9
     * @param $Date
     * @return mixed
     */
    public function GetReplyCountUrl($Date)
    {
        $Url = $this->GetReplyCountUrl.'?date='.$Date;
        $Response = json_decode($this->RestFulGet($Url, $this->header)->content,true);
        if( 'ready' === $Response['status']){
            return intval($Response['success']);
        }
        return 0;
    }

    /**
     * Date the messages were sent
     * Format: yyyyMMdd (Example: 20191231)
     * Timezone: UTC+9
     * @param $Date
     * @return mixed
     */
    public function GetPushCountUrl($Date)
    {
        $Url = $this->GetPushCountUrl.'?date='.$Date;
        $Response = json_decode($this->RestFulGet($Url, $this->header)->content,true);
        if( 'ready' === $Response['status']){
            return intval($Response['success']);
        }
        return 0;
    }

    /**
     * Date the messages were sent
     * Format: yyyyMMdd (Example: 20191231)
     * Timezone: UTC+9
     * @param $Date
     * @return mixed
     */
    public function GetMultiCastCountUrl($Date)
    {
        $Url = $this->GetMultiCastCountUrl.'?date='.$Date;
        $Response = json_decode($this->RestFulGet($Url, $this->header)->content,true);
        if( 'ready' === $Response['status']){
            return intval($Response['success']);
        }
        return 0;
    }

    /**
     * 讓管理者清楚知道誰已經 離開 群組
     * @param $UserID
     */
    public function SendUnFollow($UserID)
    {
//        $Speak  = $this->GetProfile($UserID)['displayName'].' 此人已離開群組';
        $Speak = '某人已離開';
        $this->SendMultiCast($this->RooTUserIDs, [
            [
                 'type' => 'text'
                ,'text' => $Speak
            ]
        ]);
    }

    /**
     * xID can use UserID or GroupID or RoomID
     * @param $xID
     * @param $Speak
     */
    public function SendPush($xID, $Speak)
    {
        $this->RestFulPost($this->PushPerson, $this->header, json_encode([
             'to'       => $xID
            ,'messages' => $Speak
            ,'notificationDisabled' => false
        ]));
    }

    /**
     * 指定用戶　進行　群眾發送
     * @param array $UserIDs
     * @param $Speak
     */
    public function SendMultiCast(array $UserIDs, $Speak)
    {
        $this->RestFulPost($this->MultiCastUrl, $this->header, json_encode([
             'to'       => $UserIDs
            ,'messages' => $Speak
            ,'notificationDisabled' => false
        ]));
    }

    /**
     * 利用 WebHook 特性 回應某人
     * @param $UserID
     * @param $EventsReplyToken
     */
    public function SendReply($UserID, $EventsReplyToken)
    {
        $Speak  = $this->GetProfile($UserID)['displayName'].' 歡迎您,加入主動式警報... 請勿關閉通知叮叮叮叮叮,';
        $Speak .= '請告知管理者,需要哪些警報業務; '.EmoJiService::ConvertIcon('100033');
        $this->RestFulPost($this->ReplyUrl, $this->header, json_encode([
            'replyToken' => $EventsReplyToken,
            'messages'   => [
                [
                    'type' => 'text'
                    ,'text' => $Speak
                ]
            ],
            'notificationDisabled' => false
        ]));
    }

    /**
     * 取得 Line 相關資料(唯一)
     * @param $UserID
     * @return Collection
     */
    public function GetProfile($UserID): Collection
    {
        $Url = $this->ProfileUrl.$UserID;
        return collect(json_decode($this->RestFulGet($Url, $this->header)->content));
    }

    public function RestFulGet($url, $header)
    {
        return Curl::to($url)
            ->withHeaders($header)
            ->returnResponseObject()
            ->get();
    }

    public function RestFulGetHasData($url, $header, array $data)
    {
        return Curl::to($url)
            ->withHeaders($header)
            ->withData($data)
            ->returnResponseObject()
            ->get();
    }

    public function RestFulPost($url, $header, $data)
    {
        return Curl::to($url)
            ->withHeaders($header)
            ->withData($data)
            ->returnResponseObject()
            ->post();
    }

}