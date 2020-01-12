<?php

namespace App\Http\Controllers;

use App\Http\Services\SpotifyService;
use App\Play;
use App\Playlist;
use App\Room;
use App\Track;
use App\User;
use App\Vote;
use BotMan\BotMan\Middleware\Dialogflow;
use BotMan\Drivers\Dialogflow\DialogflowDriver;
use GPBMetadata\Google\Api\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
//use FilippoToso\BotMan\Middleware\Dialogflow;
use BotMan\Drivers\Facebook\Extensions\Element;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use BotMan\Drivers\Facebook\Extensions\GenericTemplate;
use BotMan\Drivers\Facebook\FacebookDriver;
//use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Keygen\Keygen;

class BotManController extends Controller
{
    public static function findOrCreateUser($botUser)
    {
        $user = User::where('messenger_id', $botUser->getId())->first();
        if ($user === null){
            $user = User::create([
                'name' => $botUser->getFirstName() . ' ' . $botUser->getLastName(),
                'messenger_id' => $botUser->getId(),
            ]);
            $user->save();
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
            $this->findOrCreateUser($bot->getUser());
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
            $bot->reply($this->link_spotify_button($bot->getUser()->getId()));
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

            $user = $this->findOrCreateUser($bot->getUser());

            if($user->spotifyClient != null){
                $activeRoom = $user->getActiveRoom();
                if($activeRoom == null){
                    $bot->reply('Vous devez vous connecter à une salle d\'abord.');
                    $bot->reply('Créez en une, ou tapez "Rejoindre ###" où ### correspond au code reçu par le créateur de la playlist.');
                }
                else {
                    $tracks = $user->spotifyClient->getApiClient()->search($apiParameters['title'], 'track')->tracks->items;
                    if ($tracks != null)
                        $bot->reply($this->searchResultTemplate($tracks));
                    else
                        $bot->reply('Aucun résultat trouvé...');
                }
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
            $user = $this->findOrCreateUser($bot->getUser());
            if($user->spotifyClient === null) {
                $bot->reply('Vous devez d\'abord vous connecter à Spotify.');
            }
            else {
                if ($user->ownedRooms->where('open', true)->count() > 0) {
                    $bot->reply("Tu dois d'abord fermer ta playlist actuelle pour en créer une nouvelle.");
                }
                else {
                    if ($apiParameters['name'] != null)
                        $name = $apiParameters['name'];
                    else
                        $name = 'Playlist - ' . Carbon::now('Europe/Paris');
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
            }
        })->middleware($dialogflow);

        $botman->hears('playlist.songs.add', function (BotMan $bot){
            $bot->reply('Je fais ça...');
            $bot->types();
            $senderUser = $this->findOrCreateUser($bot->getUser());
            $activeRoom = $senderUser->getActiveRoom();
            if($activeRoom == null){
                $bot->reply('Vous devez vous connecter à une salle d\'abord.');
                $bot->reply('Tapez "Rejoindre ###" où ### correspond au code reçu par le créateur de la playlist.');
            }
            else {
                $api = $activeRoom->owner->spotifyClient->getApiClient();
                $extras = $bot->getMessage()->getExtras();
                $id = $extras['apiParameters']['id'];
                $track = Track::where('s_id', $id)->first();
                if($track === null ) {
                    $newTrack = $api->getTrack($id);
                    $track = new Track([
                        'name' => $newTrack->name,
                        's_id' => $id,
                        'cover_url' => $newTrack->album->images[0]->url,
                        'duration' => $newTrack->duration_ms,
                        'artist' => $newTrack->artists[0]->name,
                        'album' => $newTrack->album->name
                    ]);
                    $track->save();
                }
                $play = new Play(['played_at' => Carbon::now(),
                    'track_id' => $track->id,
                    'room_id' => $activeRoom->id,
                    'added_by_user_id' => $senderUser->id]);
                $play->save();
                $api->addPlaylistTracks($activeRoom->getPlaylistId(), $id);
                foreach ($activeRoom->activeMembers as $memberUser){
                    if ($senderUser->messenger_id != $memberUser->messenger_id){
                        $bot->say($senderUser->name . ' a ajouté un nouveau morceau.', $memberUser->messenger_id, FacebookDriver::class);
                        $bot->say($this->trackVoteTemplate($track, $play), $memberUser->messenger_id, FacebookDriver::class);
                    }
                }
                if ($senderUser->messenger_id != $activeRoom->owner->messenger_id){
                    $bot->say($senderUser->name . ' a ajouté un nouveau morceau.', $activeRoom->owner->messenger_id, FacebookDriver::class);
                    $bot->say($this->trackVoteTemplate($track, $play), $activeRoom->owner->messenger_id, FacebookDriver::class);
                }
                $bot->reply('Ajouté !');
            }
        })->middleware($dialogflow);

        $botman->hears('playlist.join', function (BotMan $bot){
            $extras = $bot->getMessage()->getExtras();
            $apiReply = $extras['apiReply'];
            $apiParameters = $extras['apiParameters'];
            $user = $this->findOrCreateUser($bot->getUser());
            $room = Room::where('pin', $apiParameters['pin'])->first();
            if ($room->open == false)
                $bot->reply('Cette playlist est fermée.');
            else if($room->owner->id == $user->id) {
                $bot->reply('Vous êtes déjà propriétaire de la salle.');
            }
            else{
                if($user->getActiveRoom() != null) {
                    if ($room->members->where('id', $user->id)->first() == null){
                        $room->members()->attach($user, ['active' => true]);
                        $room->save();
                        $bot->reply('Bienvenue dans la playlist ' . $room->slug . '. N\'hésitez pas à me demander quelle musique vous voulez passer.');
                    }
                    else
                        $bot->reply('Vous êtes déja dans la playlist.');
                }
                else {
                    $bot->reply('Vous participez déjà à une autre playlist, voulez vous la quitter ?');
                    $bot->reply(self::exitRoomTemplate());
                }
            }
        })->middleware($dialogflow);

        $botman->hears('playlist.close', function (BotMan $bot){
            $extras = $bot->getMessage()->getExtras();
            $apiReply = $extras['apiReply'];
            $user = self::findOrCreateUser($bot->getUser());
            $room = $user->getActiveRoom();
            if($room == null){
                $bot->reply('Vous ne participez à aucune playlist.');
            }
            else{
                if($room->owner->id == $user->id){
                    $room->close();
                    $room->save();
                    foreach ($room->members as $memberUser) {
                        $memberUser->rooms()->updateExistingPivot($room->id, ['active' => false]);
                    }
                    $bot->reply($apiReply);
                }
                else{
                    $user->rooms()->updateExistingPivot($room->id, ['active' =>  false]);
                }
            }
        })->middleware($dialogflow);

        $botman->hears('how.to', function (BotMan $bot){
            $apiReply = $bot->getMessage()->getExtras()['apiReply'];
            $bot->reply($apiReply);
        })->middleware($dialogflow);

        $botman->hears('playlist.id.get', function (BotMan $bot){
            $activeRoom = self::findOrCreateUser($bot->getUser())->getActiveRoom();
            if($activeRoom != null) {
                $bot->reply($activeRoom->pin);
                if($activeRoom->password != null) {
                    $bot->reply('🔽Mot de passe🔽');
                    $bot->reply($activeRoom->password);
                }
            }
            else
                $bot->reply('Vous devez rejoindre une salle d\'abord.');
        })->middleware($dialogflow);

        $botman->hears('vote.plus', function (BotMan $bot){
            $extras = $bot->getMessage()->getExtras();
            $apiParameters = $extras['apiParameters'];
            $user = self::findOrCreateUser($bot->getUser());
            $vote = Vote::where(['play_id' => $apiParameters['playId'], 'user_id' => $user->id])->first();
            if( $vote === null ) {
                $vote = new Vote(['value' => 1,
                    'play_id' => $apiParameters['playId'],
                    'user_id' => $user->id]);
                $vote->save();
                $bot->reply('+1');
            }
            else if( $vote->value == false ){
                $vote->value = true;
                $vote->save();
                $bot->reply('+1');
            }
            else
                $bot->reply('Vous avez déjà voté +1.');
        })->middleware($dialogflow);

        $botman->hears('vote.minus', function (BotMan $bot){
            $extras = $bot->getMessage()->getExtras();
            $apiParameters = $extras['apiParameters'];
            $user = self::findOrCreateUser($bot->getUser());
            $vote = Vote::where(['play_id' => $apiParameters['playId'], 'user_id' => $user->id])->first();
            if( $vote === null ) {
                $vote = new Vote(['value' => 0,
                    'play_id' => $apiParameters['playId'],
                    'user_id' => $user->id]);
                $vote->save();
                $bot->reply('-1');
            }
            else if( $vote->value == true ) {
                $vote->value = false;
                $vote->save();
                $bot->reply('-1');
            }
            else
                $bot->reply('Vous avez déjà voté -1.');
        })->middleware($dialogflow);


        // default response
        $botman->fallback(function (BotMan $bot){
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

    private function link_spotify_button($senderID)
    {
        return GenericTemplate::create()
            ->addElements([
                Element::create('Spotify')
                    ->subtitle('Connexion au compte Spotify.')
                    ->addButton(
                        ElementButton::create('Connect')
                            ->url(route('spotify.login', $senderID))
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

    private static function exitRoomTemplate()
    {
        return GenericTemplate::create()
            ->addElements([
                Element::create('Quitter la playlist ?')
                    ->addButtons([
                        ElementButton::create('Oui')->payload('Quitter la playlist.')->type('postback'),
                        ElementButton::create('Non')->payload('Ne rien faire.')->type('postback')])
            ]);
    }

    private function searchResultTemplate($tracks){
        $genericTemplate = GenericTemplate::create()->addImageAspectRatio(GenericTemplate::RATIO_SQUARE);
        for( $trackIndex =0; $trackIndex<10; $trackIndex++ ){
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

    private function trackVoteTemplate($track, $play){
        $genericTemplate = GenericTemplate::create()->addImageAspectRatio(GenericTemplate::RATIO_SQUARE);
        return $genericTemplate->addElement(
            Element::create($track->name)->subtitle($track->artist . ' - ' . $track->album)
                ->image($track->cover_url)
                ->addButtons([
                    ElementButton::create('👍')->payload('vote.plus.' . $play->id)->type('postback'),
                    ElementButton::create('👎')->payload('vote.minus.' . $play->id)->type('postback')
                ])
            );
    }

    private function generateRoomPin(){
        $pin = Keygen::numeric(10)->generate();
        while(Room::where('pin', $pin)->first() != null)
            $pin = Keygen::numeric(10)->generate();
        return $pin;
    }
}