<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'messenger_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function ownedRooms(): HasMany {
        return $this->hasMany('App\Room', 'owner_id', 'id');
    }

    public function rooms(): BelongsToMany {
        return $this
            ->belongsToMany('App\Room', 'room_members', 'user_id', 'room_id')
            ->withPivot('active')
            ->withTimestamps();
    }

    public function votes(): HasMany {
        return $this->hasMany('App\Vote');
    }

    public function spotifyClient(): HasOne {
        return $this->hasOne('App\SpotifyClient');
    }

    public function getActiveRoom() {
        $room = $this->ownedRooms->where('open', true)->first();
        if($room == null){
            $room = $this->rooms->where('open', true)->first();
        }
        return $room;
    }

}
