<?php

namespace Database\Factories;

use App\Enums\CustomFieldElement;
use App\Models\CustomField;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomField> */
class CustomFieldFactory extends Factory
{
    protected $model = CustomField::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'element' => CustomFieldElement::TEXT,
            'format' => 'ANY',
        ];
    }
}
