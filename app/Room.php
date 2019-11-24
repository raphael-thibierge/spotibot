<?php

namespace App;

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
    protected $fillable = ['open', 'pin', 'slug'];

    /**
     * Date attributes to cast
     * @var array
     */
    protected $dates = ['created_at', 'updated_ad'];

    public function owner(): BelongsTo{
        return $this->belongsTo('App\User', 'owner_id', 'id');
    }

    public function members(): BelongsToMany {
        return $this
            ->belongsToMany('App\User', 'room_members', 'room_id', 'user_id')
            ->withTimestamps();;
    }

    public function room(): HasMany {
        return $this->hasMany('App\Plays');
    }

}
