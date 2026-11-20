<?php

namespace Database\Factories;

use App\Models\StatusLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StatusLabel> */
class StatusLabelFactory extends Factory
{
    protected $model = StatusLabel::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'deployable' => true,
        ];
    }
}
