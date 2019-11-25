<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Play;
use Faker\Generator as Faker;

$factory->define(Play::class, function (Faker $faker) {
    return [
        'played_at' => $faker->date(),
    ];
});
