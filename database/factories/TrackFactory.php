<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Track;
use Faker\Generator as Faker;

$factory->define(Track::class, function (Faker $faker) {
    return [
        'name' => $faker->word(),
        's_id' => Str::random(10),
        'cover_url' => $faker->url,
        'duration' => $faker->randomNumber(2),
    ];
});
