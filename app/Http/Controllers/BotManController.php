<?php

namespace App\Http\Controllers;

use App\User;
use BotMan\BotMan\Middleware\Dialogflow;
use BotMan\Drivers\Dialogflow\DialogflowDriver;
use Doctrine\DBAL\Driver;
use Illuminate\Http\Request;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\BotMan\Messages\Incoming\Answer;
//use FilippoToso\BotMan\Middleware\Dialogflow;
use BotMan\Drivers\Facebook\Extensions\Element;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use BotMan\Drivers\Facebook\Extensions\GenericTemplate;
use BotMan\Drivers\Facebook\FacebookDriver;
use Illuminate\Support\Facades\Log;

class BotManController extends Controller
{
    public function handleWebhookRequest(Request $request){
        // todo process request
        DriverManager::loadDriver(FacebookDriver::class);
        //DriverManager::loadDriver(\BotMan\Drivers\Dialogflow\DialogflowDriver::class);
        $config = [
            'facebook' => [
                'token' => config('services.facebook.client_token'),
                'app_secret' => config('services.facebook.client_secret'),
                'verification' => config('services.facebook.verification'),
            ]
        ];
       // DriverManager::loadDriver(\BotMan\Drivers\Dialogflow\DialogflowDriver::class);
        $botman = BotManFactory::create($config);

        $dialogflow = Dialogflow::create(config('services.dialogflow.api_key'), 'fr')->listenForAction();

        //self::check_linking($request, $botman);

        // Apply global "received" middleware
       $botman->middleware->received($dialogflow);

        Log::info(print_r($botman, true));
        // welcome intent action
        $botman->hears('input.welcome', function (BotMan $bot) use ($request) {
            $extras = $bot->getMessage()->getExtras();
            $apiReply = $extras['apiReply'];
            $apiAction = $extras['apiAction'];
            $apiIntent = $extras['apiIntent'];

            $bot->reply($apiReply);
        })->middleware($dialogflow);

        $botman->hears('dialog', function (BotMan $bot){
            $extras = $bot->getMessage()->getExtras();
            $apiReply = $extras['apiReply'];
            $bot->reply($apiReply);
        })->middleware($dialogflow);

        // default response
        $botman->fallback(function (BotMan $bot) {
            $bot->reply("Je ne comprends pas...");
        });

        $botman->listen();
    }

    public static function check_linking(Request $request, &$botman){

        // try to get the message
        try {
            $message = $request->only(['entry'])['entry'][0]['messaging'][0];
            $sender_id = $message['sender']['id'];
        } catch (\Exception $e){
            $message = [];
        }

        if (isset($message['account_linking'])) {

            $account_linking = $message['account_linking'];


            // linking response
            if ($account_linking['status'] === "linked"){

                $user_id = $account_linking['authorization_code'];

                if ( ($user = User::find($user_id)) !== null){

                    $user->update(['messenger_sender_id' => $sender_id]);

                    $botman->say("Wecome {$user->name} ! You're account has been successfully linked",
                        $sender_id);
                }
            }
            // unlick response
            else if ($account_linking['status'] === "unlinked"){
                User::where('messenger_sender_id', $sender_id)->update(['messenger_sender_id' => null]);

                $botman->say("Your account has been successfully unlinked !",
                    $sender_id);
            }
        }
    }

}
