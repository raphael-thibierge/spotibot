<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    protected $table = 'votes';

    protected $fillable = 'value';

    public function play(){
        $this->belongsTo('App\Play');
    }

    public function user(){
        $this->belongsTo('App\User');
    }
}
