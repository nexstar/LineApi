<?php
/**
 * Created by PhpStorm.
 * User: AD-1-2
 * Date: 2019/9/4
 * Time: 下午 05:29
 */

namespace App\Service\Line;


use Illuminate\Support\Collection;

class ProfileService
{
    public function GetInfo($UserID)
    {
        $MessageService = new MessageService();
        return $MessageService->GetProfile($UserID);
    }
}