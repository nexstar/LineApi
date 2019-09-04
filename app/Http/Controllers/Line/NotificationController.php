<?php

namespace App\Http\Controllers\Line;

use App\Service\Line\MessageService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{

    public function SingleSend(Request $request)
    {
        $UserID = $request['userid'];
        $MessageService = new MessageService();
        $MessageService->SendMultiCast([
            'U58c2fae184451125ab1863cb4c2b418b'
        ],[
            [
                'type' => 'text'
                ,'text' => 'Test 123'
            ]
        ]);
    }

    public function GroupSend(Request $request)
    {
        $Tag     = $request['tag'];
        $Message = $request['message'];

    }

}
