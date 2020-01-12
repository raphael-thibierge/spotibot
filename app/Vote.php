<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    protected $table = 'votes';

    protected $fillable = ['value', 'play_id', 'user_id'];

    public function play(){
        return $this->belongsTo('App\Play');
    }

    public function user(){
        return $this->belongsTo('App\User');
    }
}
