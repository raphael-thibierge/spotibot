<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameSpotifyClientTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spotify_clients', function (Blueprint $table) {
            $table->renameColumn('spotify_access_token', 'access_token');
            $table->renameColumn('spotify_refresh_token', 'refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spotify_clients', function (Blueprint $table) {
            $table->renameColumn('access_token', 'spotify_access_token');
            $table->renameColumn('refresh_token', 'spotify_refresh_token');
        });
    }
}
