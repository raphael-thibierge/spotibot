<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Play extends Model
{
    protected $table = 'plays';

    protected $dates = ['played_at'];

    public function addedBy(): BelongsTo {
        return $this->belongsTo('App\User', 'added_by_user_id');
    }

    public function room(): BelongsTo {
        return $this->belongsTo('App\Room');
    }

    public function track(): BelongsTo {
        return $this->belongsTo('App\Track');
    }

    public function votes(): HasMany {
        return $this->hasMany('App/Votes');
    }
}
