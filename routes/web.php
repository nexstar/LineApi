<?php

Route::get('/', function () {
    return view('welcome');
});

use App\Service\Line\MessageService;

Route::get('SingleSend',[
    'as' => 'notify.SingleSend'
    ,'users' => 'Line\NotificationController@SingleSend'
]);

Route::get('GroupSend',[
    'as' => 'notify.GroupSend'
    ,'users' => 'Line\NotificationController@GroupSend'
]);

Route::get('aa',function (){
    $MessageService = new MessageService();
    $Date = date('Ymd',time());
    $ReplyCount = $MessageService->GetReplyCountUrl($Date);
    $PushCount  = $MessageService->GetPushCountUrl($Date);
    $MultiCastCount = $MessageService->GetMultiCastCountUrl($Date);

    $Sum = 'ReplyCount: '.$ReplyCount;
    $Sum .= ' | PushCount: '.$PushCount;
    $Sum .= ' | MultiCastCount: '.$MultiCastCount;
    dd($Sum);
});

//Auth::routes();
//Route::get('/home', 'HomeController@index')->name('home');
//
//// Line Login
//Route::get('/auth/{provider}/callback', 'SocialController@callback')->where('provider', '[a-z]+');
//
//// Refresh access tokens
//Route::get('/{provider}/retoken', 'SocialController@retoken')->where('provider', '[a-z]+');
//// Refresh access tokens
//// Line Login
//
//Route::get('/{provider}/spm/{id}', 'SocialController@spm')->where('provider', '[a-z]+')->where('id', '[0-9]+');
//
//// Line Pay API
//Route::get('/{provider}/reserveapi', 'SocialController@reserveapi')->where('provider', '[a-z]+');
//Route::get('/{provider}/paymentsapi/{transactionId}', 'SocialController@paymentsapi')->where('provider', '[a-z]+')->where('transactionId', '[0-9]+');
//Route::get('/{provider}/refundapi/{transactionId}', 'SocialController@refundapi')->where('provider', '[a-z]+')->where('transactionId', '[0-9]+');
//Route::get('/{provider}/regkeyapi/{orderId}', 'SocialController@regkeyapi')->where('provider', '[a-z]+')->where('orderId', '[0-9]+');
//// Line Pay API