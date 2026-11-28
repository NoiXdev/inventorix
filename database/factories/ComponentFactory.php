<?php

namespace Database\Factories;

use App\Models\Component;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Component> */
class ComponentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['RAM 16GB', 'SSD 512GB', 'Network card', 'Graphics card']).' '.fake()->numerify('##'),
            'qty' => fake()->numberBetween(5, 50),
        ];
    }
}
