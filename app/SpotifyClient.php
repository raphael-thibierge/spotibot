<?php

namespace App;

use App\Exceptions\NullSpotifyAccessTokenException;
use Carbon\Carbon;
use GPBMetadata\Google\Api\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use SpotifyWebAPI\SpotifyWebAPI;

/**
 * @property mixed expires_at
 * @property string refresh_token
 * @property string access_token
 */
class SpotifyClient extends Model
{
    /**
     * SQL table name
     * @var string
     */
    protected $table = 'spotify_clients';

    /**
     * Table fillable attributes
     * @var array
     */
    protected $fillable = ['spotify_id', 'access_token', 'refresh_token', 'expires_at', 'user_data'];

    protected $dates = [
        'expires_at',
    ];

    /**
     * Spotify client to interact with API
     * @var SpotifyWebAPI
     */
    private $apiClient;

    /**
     * User relationship
     * @return BelongsTo
     */
    public function user(): BelongsTo{
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    /**
     * @throws NullSpotifyAccessTokenException
     */
    private function configureSpotifySession(): \SpotifyWebAPI\Session{

        // instantiate new Spotify session
        $session = new \SpotifyWebAPI\Session(
            config('services.spotify.client_id'),
            config('services.spotify.client_secret')
        );

        // check if access token exists
        if ($this->access_token == null){
            throw new NullSpotifyAccessTokenException();
        }

        // configure Spotify session
        $session->setAccessToken($this->access_token);
        $session->setRefreshToken($this->refresh_token);

        // return ready spotify session
        return $session;
    }

    /**
     * Return Current API Client
     * @return SpotifyWebAPI
     * @throws NullSpotifyAccessTokenException
     */
    public function getApiClient(): SpotifyWebAPI{
        // configure Spotify client if necessary
        if($this->apiClient === null){
            $this->apiClient = new SpotifyWebAPI();
            // prepare Spotify session
            $this->apiClient->setSession($this->configureSpotifySession());
            // activate auto refresh token
            $this->apiClient->setOptions([
                'auto_refresh' => true,
            ]);
            $this->user_data = json_encode($this->apiClient->me());
            $this->save();
        }

        return $this->apiClient;
    }

    /**
     * Refresh token manually
     * @throws NullSpotifyAccessTokenException
     * @throws \Exception
     */
    public function refreshToken(){
        $session = $this->configureSpotifySession();
        $session->refreshAccessToken();
        $this->access_token = $session->getAccessToken();
        $this->refresh_token = $session->getRefreshToken();
        $this->expires_at = new Carbon($session->getTokenExpiration());
        $this->user_data = json_encode($this->getApiClient()->me());
        $this->save();
    }

    public function isLinkedToSpotify(){
        return isset($this->access_token) && !empty($this->access_token);
    }

    public function getSpotifyId(){
        if ($this->isLinkedToSpotify()){
            return json_decode($this->user_data)->id;
        }
    }
}