<?php

namespace App\Http\Controllers\Line;

use App\Service\Line\ChannelService;
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
        $request = $request->all();

        collect($request['events'])->map(function ($Events){
            Log::info(collect($Events));
            $ChannelService = new ChannelService();
            $ChannelService->EventChannel($Events);
        });

        return $this->ResponseService->HTTP_OK('');
    }
}
