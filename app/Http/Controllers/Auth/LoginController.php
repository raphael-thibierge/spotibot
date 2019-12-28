<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use PHPUnit\Util\Json;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Redirect the user to the Spotify authentication page.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToProvider()
    {
        return Socialite::driver('spotify')->setScopes([
            'user-read-private',
            // playlists
            'playlist-read-private',
            'playlist-read-collaborative',
            'playlist-modify-public',
            'playlist-modify-private',
            // user infos
            'user-read-email',
            'user-top-read',
            // user's player
            'user-read-playback-state', // access to user's player
            'user-modify-playback-state',
            'user-read-currently-playing',
            'user-read-recently-played'
        ])->redirect();
    }

    /**
     * Obtain the user information from Spotify.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback()
    {
        $spotifyUserData = Socialite::driver('spotify')->user();
        $user = Auth::User();
        $dateExpirationToken = now()->addSeconds($spotifyUserData->expiresIn);
        if ($user->spotifyClient != null) {
            $user->spotifyClient->refreshToken();
        } else {
            $user->spotifyClient()->create([
                'spotify_id' => $spotifyUserData->id,
                'access_token' => $spotifyUserData->token,
                'refresh_token' => $spotifyUserData->refreshToken,
                'expires_at' => $dateExpirationToken,
                'user_data' => ''
            ]);
            $api = $user->spotifyClient->getApiClient();
            $user->spotifyClient->update(['user_data' => json_encode($api->me())]);
        }
        return redirect()->route('home');
    }
}