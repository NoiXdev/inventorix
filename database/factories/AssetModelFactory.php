<?php

namespace Database\Factories;

use App\Models\AssetModel;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetModel> */
class AssetModelFactory extends Factory
{
    protected $model = AssetModel::class;

    public function definition(): array
    {
        return [
            'name'            => fake()->unique()->word(),
            'manufacturer_id' => Manufacturer::factory(),
        ];
    }
}
