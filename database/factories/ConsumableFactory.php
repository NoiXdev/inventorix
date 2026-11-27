<?php

namespace Database\Factories;

use App\Models\Consumable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Consumable> */
class ConsumableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Toner HP 59A', 'USB-C cable 1m', 'AA batteries', 'Mouse pad']).' '.fake()->numerify('##'),
            'qty' => fake()->numberBetween(5, 50),
        ];
    }
}
