<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/', function () {
    return view('welcome');
});

use App\Service\Line\AccessTokenService;
use App\Service\Line\MessageService;
use App\Service\Line\ProfileService;
use Ixudra\Curl\Facades\Curl;

Route::get('aa',function (){

    $MessageService = new MessageService();
    $Profile = $MessageService->GetProfile('U62aa37ec1b9ae5a5591e9bc7cf852f79');
    dd($Profile);

//    $MessageService->SendMultiCast([
//        'U58c2fae184451125ab1863cb4c2b418b'
//    ],'Good Good Good');

});

Auth::routes();
Route::get('/home', 'HomeController@index')->name('home');

// Line Login
Route::get('/auth/{provider}/callback', 'SocialController@callback')->where('provider', '[a-z]+');

// Refresh access tokens
Route::get('/{provider}/retoken', 'SocialController@retoken')->where('provider', '[a-z]+');
// Refresh access tokens
// Line Login

Route::get('/{provider}/spm/{id}', 'SocialController@spm')->where('provider', '[a-z]+')->where('id', '[0-9]+');

// Line Pay API
Route::get('/{provider}/reserveapi', 'SocialController@reserveapi')->where('provider', '[a-z]+');
Route::get('/{provider}/paymentsapi/{transactionId}', 'SocialController@paymentsapi')->where('provider', '[a-z]+')->where('transactionId', '[0-9]+');
Route::get('/{provider}/refundapi/{transactionId}', 'SocialController@refundapi')->where('provider', '[a-z]+')->where('transactionId', '[0-9]+');
Route::get('/{provider}/regkeyapi/{orderId}', 'SocialController@regkeyapi')->where('provider', '[a-z]+')->where('orderId', '[0-9]+');
// Line Pay API