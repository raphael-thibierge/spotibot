<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use SpotifyWebAPI\SpotifyWebAPI;

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
    protected $fillable = ['spotify_id', 'spotify_access_token', 'spotify_refresh_token', 'expires_at'];

    protected $dates = [
        'expires_at',
    ];

    //Manage Spotify API Attributes
    protected $apiClient, $session;

    public function user(): BelongsTo{
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    //Instantiate API Client
    public function createApiClient(){
        $this->apiClient = new SpotifyWebAPI();
        $this->apiClient->setAccessToken($this->spotify_access_token);
    }

    public function createSessionApiClient(){
        $this->session = new SpotifyWebAPI\Session(
            env('SPOTIFY_KEY'),
            env('SPOTIFY_SECRET')
        );
    }
    // Return Current API Client
    public function getApiClient(){
        return $this->apiClient;
    }

    //Enable auto refresh token
    public function enableAutoRefreshToken(){
        $this->apiClient->setOptions([
            'auto_refresh' => true,
        ]);
    }

    //Manually Refresh Token
    public function refreshToken(){
        $this->session->refreshAccessToken($this->refreshToken());
        $this->spotify_access_token = $this->session->getAccessToken();
        $this->spotify_refresh_token = $this->session->getRefreshToken();
    }

    //Get Track Infos From API
    //Test ID = 3n3Ppam7vgaVa1iaRUc9Lp
    public function getTrackInfos($trackId){
        return $this->apiClient->getTrack($trackId);
    }
    //Return Top Tracks from an artist
    //Test ID = 43ZHCT0cAZBISjO8DG9PnE
    public function getArtistTopTracks($artistId){
        return $this->apiClient->getArtistTopTracks($artistId, [ 'country' => 'fr']);
    }

}
