<?php

namespace App\Http\Controllers\Line;

use App\Service\Line\MessageService;
use App\Service\Other\ResponseService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class WebHookController extends Controller
{
    private $ResponseService;

    public function __construct()
    {
        $this->ResponseService = new ResponseService();
    }

    public function In(Request $request)
    {
        $Request = collect($request->all());

        $Events     = $Request['events'][0];
        $EventsType = $Events['type']; // follow || unfollow || message
        $UserID     = $Events['source']['userId'];

        switch ($EventsType) {
            case 'follow':
                $EventsReplyToken = $Events['replyToken'];
                // 儲存至資料庫中
                // $userId

                // 發送確認用戶已經加入本資料庫中
                $MessageService = new MessageService();
                $MessageService->SendReply($UserID, $EventsReplyToken);
                Log::info($Request);
            break;
            case 'message':
                // 進入表 用戶 發送文字進入 Do something
            break;
            case 'unfollow':
                // 進入表 用戶 解除與此群組的一切
                // 但用戶資訊是會被存下來 與 此用戶的 LOG
                $MessageService = new MessageService();
                $MessageService->SendUnFollow($UserID);
                Log::info($Request);
            break;
        }
        return $this->ResponseService->HTTP_OK('');
    }
}
