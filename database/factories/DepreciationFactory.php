<?php

namespace Database\Factories;

use App\Enums\DepreciationType;
use App\Models\Depreciation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Depreciation> */
class DepreciationFactory extends Factory
{
    protected $model = Depreciation::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'months' => fake()->randomElement([12, 36, 60]),
            'depreciation_min' => 0,
            'fiscal_year_start_month' => 1,
            'rate_multiplier' => 1,
            'depreciation_type' => DepreciationType::AMOUNT,
        ];
    }
}
