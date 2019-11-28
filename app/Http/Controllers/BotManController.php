<?php

namespace App\Http\Controllers;

use Doctrine\DBAL\Driver;
use Illuminate\Http\Request;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Middleware\ApiAi;
use BotMan\Drivers\Facebook\Extensions\Element;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use BotMan\Drivers\Facebook\Extensions\GenericTemplate;
use BotMan\Drivers\Facebook\FacebookDriver;

class BotManController extends Controller
{

    public function handleWebhookRequest(Request $request){
        // todo process request
        // check Edsound
        DriverManager::loadDriver(FacebookDriver::class);
        $config = [
            'facebook' => [
                'token' => config('services.facebook.client_token'),
                'app_secret' => config('services.facebook.client_secret'),
                'verification' => config('services.facebook.verification'),
            ]
        ];

        $botman = BotManFactory::create($config);

        $dialogflow = ApiAi::create(config('services.dialogflow.api_key'))->listenForAction();

        return response()->json($botman);
    }

}
