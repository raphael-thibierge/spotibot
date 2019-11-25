<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function user(): BelongsTo{
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

}
