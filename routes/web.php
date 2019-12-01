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

# routes used by authentication module
Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');

Route::get('/spotify', 'Auth\LoginController@redirectToProvider');
Route::get('login/spotify/callback', 'Auth\LoginController@handleProviderCallback');

//Route::post('/dialogflow/webhook', 'BotManController@handleWebhookRequest');

// botman
Route::match(['get', 'post'], '/botman', 'BotManController@handleWebhookRequest')->middleware('botman');

//Route::match(['get', 'post'], '/botman/authorize', 'MessengerAccountLinkingController@showMessengerLoginForm')
//    ->middleware('botman')
//    ->name('botman.authorize');
//
//Route::post('/botman/authorize', 'MessengerAccountLinkingController@authorizePost')
//    ->middleware('botman')
//    ->name('botman.authorize.post');
//
//Route::get('/botman/confirm', 'MessengerAccountLinkingController@showConfirm')
//    ->middleware('botman')
//    ->name('botman.confirm.show');
//
//Route::post('/botman/confirm', 'MessengerAccountLinkingController@confirm')
//    ->middleware('botman')
//    ->name('botman.confirm');