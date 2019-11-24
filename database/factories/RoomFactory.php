<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Room::class, function (Faker $faker) {
    return [
        'slug' => $faker->slug(),
        'pin' => $faker->randomNumber(),
        'open' => true,
    ];
});
