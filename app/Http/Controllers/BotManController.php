<?php

namespace App\Http\Controllers;

use App\Http\Services\SpotifyService;
use App\Playlist;
use App\Room;
use App\User;
use BotMan\BotMan\Middleware\Dialogflow;
use BotMan\Drivers\Dialogflow\DialogflowDriver;
use BotMan\Drivers\Facebook\Extensions\ListTemplate;
use Carbon\Carbon;
use ChristofferOK\LaravelEmojiOne\LaravelEmojiOne;
use Doctrine\DBAL\Driver;
use GPBMetadata\Google\Api\Auth;
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
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Keygen\Keygen;
use Symfony\Component\ErrorHandler\Debug;
use function foo\func;

class BotManController extends Controller
{
    public static function functionFindOrCreateUser($senderId)
    {
        $user = User::where('messenger_id', $_SERVER)->first();
        if ($user === null){
            $user = User::create([
                'messenger_id' => $senderId,
            ]);
        }
        return $user;
    }

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

        self::check_linking($request, $botman);

        // Apply global "received" middleware
        $botman->middleware->received($dialogflow);
        // welcome intent action
        $botman->hears('input.welcome', function (BotMan $bot) use ($request) {
            $extras = $bot->getMessage()->getExtras();
            $apiReply = $extras['apiReply'];
            $bot->reply($apiReply);
        })->middleware($dialogflow);

        $botman->hears('dialog', function (BotMan $bot){
            $extras = $bot->getMessage()->getExtras();
            $apiReply = $extras['apiReply'];
            $bot->reply($apiReply);
        })->middleware($dialogflow);

        /**
         * Spotify connect
         */
        $botman->hears('spotify.connect', function (BotMan $bot) {
            $bot->reply($this->link_spotify_button());
        })->middleware($dialogflow);

        /**
         * Login
         */
        $botman->hears('login', function (BotMan $bot) {
            $bot->reply($this->login_button());
        })->middleware($dialogflow);

        /**
         * Logout
         */
        $botman->hears('logout', function (BotMan $bot) {
            $bot->reply($this->logout_button());
        })->middleware($dialogflow);

        $botman->hears('song.search', function (BotMan $bot){
            $extras = $bot->getMessage()->getExtras();
            $apiReply = $extras['apiReply'];
            $apiParameters = $extras['apiParameters'];
            $bot->reply($apiReply);

            $user = $this->getUserFromSenderId($bot->getUser()->getId());

            if ($user == null)
                $bot->reply('Vous n\'êtes pas connecté');
            else if($user->spotifyClient != null){
                $activeRoom = $user->getActiveRoom();
                if($activeRoom == null){
                    $bot->reply('Vous devez vous connecter à une salle d\'abord.');
                    $bot->reply('Tapez "Rejoindre ###" où ### correspond au code reçu par le créateur de la playlist.');
                }

                $tracks = $user->spotifyClient->getApiClient()->search($apiParameters['title'], 'track')->tracks->items;
                if($tracks != null)
                    $bot->reply($this->searchResultTemplate($tracks));
                else
                    $bot->reply('Aucun résultat trouvé...');
            }
            else if ($user->getActiveRoom() != null){
                $api = $user->getActiveRoom()->owner->spotifyClient->getApiClient();
                $tracks = $api->search($apiParameters['title'], 'track')->tracks->items;
                if($tracks != null)
                    $bot->reply($this->searchResultTemplate($tracks));
                else
                    $bot->reply('Aucun résultat trouvé...');
            }
        })->middleware($dialogflow);

        $botman->hears('playlist.create', function (BotMan $bot) {
            $bot->types();
            $extras = $bot->getMessage()->getExtras();
            $apiReply = $extras['apiReply'];
            $apiParameters = $extras['apiParameters'];
            $user = $this->getUserFromSenderId($bot->getUser()->getId());
            if ($user === null) {
                $bot->reply('Vous n\êtes pas connecté.');
                return;
            }

            if ($user->rooms()->where('open', 1)->count() > 0) {
                $bot->reply("Tu dois d'abord fermer ta playlist actuelle pour en créer une nouvelle");
            } else {
                if($apiParameters['name'] != null)
                    $name = $apiParameters['name'];
                else
                    $name = 'Playlist - ' . Carbon::now();
                $room = new Room;
                $room->owner_id = $user->id;
                $room->pin = $this->generateRoomPin();
                $room->slug = $name;
                $room->open();
                $api = $user->spotifyClient->getApiClient();
                $room->spotify_data = json_encode($api->createPlaylist(['name' => $name]));
                $room->save();
                $bot->reply('La playlist ' . $name . ' a été créée !');
                $bot->reply('Copie ce code et envoie le aux participants : ');
                $bot->reply($room->pin);
            }
        })->middleware($dialogflow);

        $botman->hears('playlist.join', function (BotMan $bot){
            $extras = $bot->getMessage()->getExtras();
            $apiReply = $extras['apiReply'];
            $apiParameters = $extras['apiParameters'];
            $user = $this->getUserFromSenderId($bot->getUser()->getId());
            $room = Room::where('pin', $apiParameters['pin'])->first();
            if ($user == null){
                $bot->reply('Vous devez autoriser Spotibot à communiquer avec vous d\'abord.');
                $bot->reply($this->login_button());
            }
            else {
                if ($room->open == false)
                    $bot->reply('Cette playlist est fermée.');
                else {
                    if($room->members->where('id', $user->id)->first() == null) {
                        $room->members()->attach($user);
                        $room->save();
                        $bot->reply('Bienvenue dans la playlist ' . $room->slug . '.');
                    }
                }
            }
        })->middleware($dialogflow);

       $botman->hears('playlist.songs.add', function (BotMan $bot){
           $bot->types();
           $senderUser = $this->getUserFromSenderId($bot->getUser()->getId());
           if ($senderUser == null){
               $bot->reply('Vous devez autoriser Spotibot à communiquer avec vous d\'abord.');
               $bot->reply($this->login_button());
           }
           else{
               $activeRoom = $senderUser->getActiveRoom();
               if($activeRoom == null){
                   $bot->reply('Vous devez vous connecter à une salle d\'abord.');
                   $bot->reply('Tapez "Rejoindre ###" où ### correspond au code reçu par le créateur de la playlist.');
               }
               else{
                   $api = $activeRoom->owner->spotifyClient->getApiClient();
                   $extras = $bot->getMessage()->getExtras();
                   $id = $extras['apiParameters']['id'];
                   $api->addPlaylistTracks($activeRoom->getPlaylistId(), $id);
                   $track = $api->getTrack($id);

                   foreach ($activeRoom->members as $memberUser) {
                       if($senderUser->messenger_id != $memberUser->messenger_id) {
                           $bot->say($senderUser->name . ' a ajouté un nouveau morceau.', $memberUser->messenger_id);
                           $bot->say($this->trackVoteTemplate($track), $memberUser->messenger_id);
                       }
                   }
                   if($senderUser->messenger_id != $activeRoom->owner->messenger_id)
                       $bot->say($senderUser->name . ' a ajouté ' . $track->name . ' de ' . $track->artists[0]->name, $activeRoom->owner->messenger_id);
                   $bot->reply('Ajouté !');
               }
           }
       })->middleware($dialogflow);

        // default response
        $botman->fallback(function (BotMan $bot) {
            $bot->reply("Je ne comprends pas...");
        });

        $botman->listen();
    }

    private function login_button(){
        return GenericTemplate::create()
            ->addElements([
                Element::create('Connexion')
                    ->subtitle('Lier son compte Facebook.')
                    ->addButton(
                        ElementButton::create('Login')
                            ->url(env('APP_URL') . '/botman/authorize')
                            ->type('account_link')
                    )
            ]);
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

                    $user->update(['messenger_id' => $sender_id]);

                    $botman->say('"Bienvenue ' . $user->name .' ! Plus qu\'à connecter Spotify et en avant la musique. Si tu veux rejoindre une salle, tape "Rejoindre ###" où ### correspond au code de la playlist.',
                        $sender_id);
                }
            }
            // unlick response
            else if ($account_linking['status'] === "unlinked"){
                User::where('messenger_id', $sender_id)->update(['messenger_id' => null]);

                $botman->say("Ton compte n'est plus relié, au revoir !",
                    $sender_id);
            }
        }
    }

    private function link_spotify_button()
    {
        return GenericTemplate::create()
            ->addElements([
                Element::create('Spotify')
                    ->subtitle('Connexion au compte Spotify.')
                    ->addButton(
                        ElementButton::create('Connect')
                            ->url(route('spotify.login'))
                    )
            ]);
    }

    private function logout_button()
    {
        return GenericTemplate::create()
            ->addElements([
                Element::create('Déconnexion au compte')
                    ->addButton(
                        ElementButton::create('Log Out')
                            ->type('account_unlink')
                    )
            ]);
    }

    private function getUserFromSenderId($senderId){
        //$senderId = $request->get('message')[0]['sender']['id'];
        return User::where('messenger_id', $senderId)->first();
    }

    private function searchResultTemplate($tracks){
        $genericTemplate = GenericTemplate::create()->addImageAspectRatio(GenericTemplate::RATIO_SQUARE);
        for( $trackIndex =0; $trackIndex<4; $trackIndex++ ){
            $name = $tracks[$trackIndex]->name;
            if( sizeof($tracks[$trackIndex]->artists) != 1)
                foreach ($tracks[$trackIndex]->artists as $artist)
                    $artistName = $artist->name . ' ';
            else
                $artistName = $tracks[$trackIndex]->artists[0]->name;
            $albumName = $tracks[$trackIndex]->album->name;
            //Cover miniature
            $coverImgUrl = $tracks[$trackIndex]->album->images[0]->url;
            $genericTemplate->addElement(
                Element::create($name)->subtitle($artistName. ' - ' . $albumName)
                    ->image($coverImgUrl)
                    ->addButton(
                        ElementButton::create('Ajouter')
                            ->payload('playlist.songs.add.' . $tracks[$trackIndex]->id)
                            ->type('postback')));
        }
        return $genericTemplate;
    }

    private function trackVoteTemplate($track){
        $genericTemplate = GenericTemplate::create()->addImageAspectRatio(GenericTemplate::RATIO_SQUARE);
        return $genericTemplate->addElement(
            Element::create($track->name)->subtitle($track->artists[0]->name . ' - ' . $track->album->name)
                ->image($track->album->images[0]->url)
                ->addButtons([
                    ElementButton::create('👍')->payload('track.vote.plus')->type('postback'),
                    ElementButton::create('👎')->payload('track.vote.minus')->type('postback')
                ])
            );
    }

    private function generateRoomPin()
    {
        $pin = Keygen::numeric(10)->generate();
        while(Room::where('pin', $pin)->first() != null)
            $pin = Keygen::numeric(10)->generate();
        return $pin;
    }
}