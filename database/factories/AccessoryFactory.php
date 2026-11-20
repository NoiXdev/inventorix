<?php

namespace Database\Factories;

use App\Models\Accessory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Accessory> */
class AccessoryFactory extends Factory
{
    protected $model = Accessory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'qty' => fake()->numberBetween(0, 50),
        ];
    }
}
