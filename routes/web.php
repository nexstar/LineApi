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

Auth::routes();
Route::get('/home', 'HomeController@index')->name('home');


Route::get('/auth/{provider}/callback', 'SocialController@callback')->where('provider', '[a-z]+');
Route::get('/{provider}/retoken', 'SocialController@retoken')->where('provider', '[a-z]+');
Route::get('/{provider}/spm/{id}', 'SocialController@spm')->where('provider', '[a-z]+')->where('id', '[0-9]+');

// Line Pay API
Route::get('/{provider}/reserveapi', 'SocialController@reserveapi')->where('provider', '[a-z]+');
Route::get('/{provider}/paymentsapi/{transactionId}', 'SocialController@paymentsapi')->where('provider', '[a-z]+')->where('transactionId', '[0-9]+');
Route::get('/{provider}/refundapi/{transactionId}', 'SocialController@refundapi')->where('provider', '[a-z]+')->where('transactionId', '[0-9]+');
Route::get('/{provider}/regkeyapi/{orderId}', 'SocialController@regkeyapi')->where('provider', '[a-z]+')->where('orderId', '[0-9]+');
// Line Pay API