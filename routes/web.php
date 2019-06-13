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
Route::get('/auth/{provider}/callback', 'SocialController@callback')->where('provider', '[a-z]+');
Route::get('/{provider}/retoken', 'SocialController@retoken')->where('provider', '[a-z]+');

Route::get('/home', 'HomeController@index')->name('home');