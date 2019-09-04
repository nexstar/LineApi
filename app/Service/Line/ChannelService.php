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
        $Source     = $Events['source'];

        switch ($EventsType) {
            case 'follow':
                $EventsReplyToken = $Events['replyToken'];
                // 儲存至資料庫中; 單純紀錄用戶些許個資
                // 尚未撰寫
                // 發送確認用戶已經加入本資料庫中
                $MessageService->SendReply($Source['userId'], $EventsReplyToken);
            break;
            case 'message':

                // 進入表 用戶 發送文字進入 Do something
                $this->SourceChannel($Source, $Events);
            break;
            case 'unfollow':
                // 進入表 用戶 解除與此群組的一切
                // 但用戶資訊是會被存下來 與 此用戶的 LOG
                $MessageService->SendUnFollow($Source['userId']);
            break;
        }
    }

    public function SourceChannel($Source, $Events)
    {
        switch ($Source['type']) {
            case 'user':// 個人
                $UserId  = $Source['userId'];
                $this->MessageChannel($UserId, $Events['message']);
            break;
            case 'group':// 群組暫時不考慮 沒需求
            break;
            case 'room'://  聊天室暫時不考慮:  (屬於非基於群組創建類型使用)
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

}