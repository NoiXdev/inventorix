<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Group> */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'permissions' => [],
        ];
    }

    /** @param list<string> $values permission values to grant */
    public function withPermissions(array $values): static
    {
        return $this->state(fn () => ['permissions' => array_fill_keys($values, 1)]);
    }
}
