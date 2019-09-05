<?php
/**
 * Created by PhpStorm.
 * User: nianbao
 * Date: 2019/9/5
 * Time: 上午12:04
 */

namespace App\Service\Line;


use Illuminate\Support\Facades\Log;

class ChannelService
{

    public function EventChannel($Events)
    {
        //{
        //  "events": [
        //    {
        //      "type": "unfollow",
        //      "source": {
        //        "userId": "U62aa37ec1b9ae5a5591e9bc7cf852f79",
        //        "type": "user"
        //      },
        //      "timestamp": 1567593886174
        //    }
        //  ],
        //  "destination": "Ued9be21ca52204a78422d8ae5822476e"
        //}
        $MessageService = new MessageService();
        $EventsType = $Events['type']; // follow || unfollow || message

        switch ($EventsType) {
            case 'follow':
                $EventsReplyToken = $Events['replyToken'];
                // 儲存至資料庫中; 單純紀錄用戶些許個資
                // 尚未撰寫
                // 發送確認用戶已經加入本資料庫中
                $MessageService->SendReply($Events['source']['userId'], $EventsReplyToken);
            break;
            case 'unfollow':
                // 進入表 用戶 解除與此群組的一切
                // 但用戶資訊是會被存下來 與 此用戶的 LOG
                $MessageService->SendUnFollow($Events['source']['userId']);
            break;
            case 'message':      // 進入表 用戶 發送文字進入 Do something
            case 'join':         // 觸發屬於 聊天室 or 群組 建立
            case 'memberLeft':   // 觸發屬於自己離開聊天室
            case 'memberJoined': // 觸發屬於 聊天室 or 群組 邀請
                $this->SourceChannel($Events);
            break;
        }

    }

    public function SourceChannel($Events)
    {
        switch ($Events['source']['type']) {
            case 'user':// 個人
                $UserId  = $Events['source']['userId'];
                $this->MessageChannel($UserId, $Events['message']);
            break;
            case 'group': // 群組 需向邀請人同意之使用
            case 'room':  // 聊天室 (屬於非基於群組創建類型使用) 不需向邀請人同意之使用
                $this->MemberChannel($Events);
            break;
        }
    }

    public function MessageChannel($UserId, $Message)
    {
        switch ($Message['type']){
            case 'text':
                $Text = $Message['text'];
                $MessageService = new MessageService();

                if('DeepSenXeRoot' !== $Text){
                    return;
                }
                $Date = date('Ymd',time());

                $ReplyCount     = $MessageService->GetReplyCountUrl($Date);
                $PushCount      = $MessageService->GetPushCountUrl($Date);
                $MultiCastCount = $MessageService->GetMultiCastCountUrl($Date);

                $Sum = 'ReplyCount: '.$ReplyCount;
                $Sum .= "\r\nPushCount: ".$PushCount;
                $Sum .= "\r\nMultiCastCount: ".$MultiCastCount;

                $MessageService->SendMultiCast([$UserId],[
                    [
                         'type' => 'text'
                        ,'text' => $Sum
                    ]
                ]);
            break;
        }
    }

    private function MemberChannel($Events)
    {
        // 如 未來 需要針對 memberJoined or memberLeft 進行 Group/Room, 在繼續往下拆
        switch ($Events['type'])
        {
            case 'join': // 新群建立時傳送的資料
                $Source = $Events['source'];
                if('group' == $Source['type']){
                    $MessageService = new MessageService();
                    $MessageService->SendMultiCast($MessageService->RooTUserIDs, [
                        [
                            'type' => 'text'
                            ,'text' => '新加入的群組:代號 '.$Source['groupId']
                        ]
                    ]);
                }
            break;
            case 'memberJoined': // 加入 聊天室
            break;
            case 'memberLeft': // 離開 聊天室
            break;
        }
    }

}