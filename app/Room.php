<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{

    /**
     * SQL table name
     * @var string
     */
    protected $table = 'rooms';

    /**
     * Table fillable attributes
     * @var array
     */
    protected $fillable = ['open', 'pin', 'slug', 'spotify_data'];

    /**
     * Date attributes to cast
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    /**
     * Playlist's creator
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner(): BelongsTo{
        return $this->belongsTo('App\User', 'owner_id', 'id');
    }

    /**
     * Playlist's guests
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function members(): BelongsToMany {
        return $this
            ->belongsToMany('App\User', 'room_members', 'room_id', 'user_id')
            ->withPivot('active')
            ->withTimestamps();
    }

    public function activeMembers() : BelongsToMany {
        return $this
            ->belongsToMany('App\User', 'room_members', 'room_id', 'user_id')
            ->wherePivot('active', 1)
            ->withTimestamps();
    }

    public function plays(): HasMany {
        return $this->hasMany('App\Play');
    }


    /**
     * Open playlist set status to open and save de timestamp
     */
    public function open(){
        $this->open = true;
    }

    /**
     * Open playlist set status to open and save de timestamp
     */
    public function close(){
        $this->open = false;
    }

    /**
     * Return the Spotify playlist's ID
     * @return int
     */
    public function getPlaylistId(){
        if(isset($this->spotify_data))
            return json_decode($this->spotify_data)->id;
        return '';
    }
}
