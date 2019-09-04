<?php

Route::post('WebHook', 'Line\WebHookController@In');
//Route::post('/{provider}/webhook', 'SocialController@webhook')->where('provider', '[a-z]+');

// Line Pay API
Route::get('/{provider}/confirmapi', 'SocialController@confirmapi')->where('provider', '[a-z]+');
// Line Pay API
