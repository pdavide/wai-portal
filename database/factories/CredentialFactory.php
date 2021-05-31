<?php

namespace Database\Factories;

/* @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Credential;
use Faker\Generator as Faker;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

$factory->define(Credential::class, function (Faker $faker) {
    $clientName = $faker->words(2, true);

    return [
        'client_name' => Str::slug($clientName),
        'consumer_id' => Uuid::uuid4()->toString(),
    ];
});
