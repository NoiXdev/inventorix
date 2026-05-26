<?php

namespace Database\Factories;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'state' => AssetState::NEW->value,
            'asset_type_id' => AssetType::factory(),
            'model_id' => AssetModel::factory(),
            'owner_id' => User::factory(),
            'place_id' => Place::factory(),
            'serial_number' => fake()->bothify('SN-#####??'),
            'buy_date' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'buy_price' => fake()->randomFloat(2, 100, 5000),
            'guarantee_end' => fake()->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'invoice' => fake()->bothify('INV-####'),
        ];
    }
}
