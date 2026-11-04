<?php

namespace Database\Factories;

use App\Models\CustomFieldset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomFieldset> */
class CustomFieldsetFactory extends Factory
{
    protected $model = CustomFieldset::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true)];
    }
}
