<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpotifyClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spotify_clients', function (Blueprint $table) {
            $table->bigIncrements('id');

            //Spotify
            $table->string('spotify_id');
            $table->string('spotify_access_token')->nullable();
            $table->string('spotify_refresh_token')->nullable();

            //Other data
            $table->unsignedBigInteger('user_id')->references('id')->on('users');
            $table->dateTime('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spotify_clients');
    }
}
