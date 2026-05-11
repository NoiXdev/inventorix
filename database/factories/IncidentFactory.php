<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Incident> */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            'asset_id'    => Asset::factory(),
            'title'       => fake()->sentence(3),
            'notes'       => fake()->paragraph(),
            'open_date'   => now(),
            'closed_date' => null,
        ];
    }
}
