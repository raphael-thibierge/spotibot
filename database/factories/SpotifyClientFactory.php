<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\SpotifyClient;
use Faker\Generator as Faker;

$factory->define(SpotifyClient::class, function (Faker $faker) {
    return [
        'spotify_id' => $faker->randomKey(),
        'spotify_access_token' => $faker->randomKey(),
        'spotify_refresh_token' => $faker->randomKey(),
        'expires_at' => now(),
    ];
});
