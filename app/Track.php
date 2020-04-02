<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Track extends Model
{
    protected $table = 'tracks';

    protected $fillable = ['name', 's_id', 'cover_url', 'duration', 'artist', 'album'];

    public function plays(): HasMany{
        return $this->hasMany('App\Play');
    }

}
