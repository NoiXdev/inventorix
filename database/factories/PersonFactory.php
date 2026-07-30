<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Person> */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'firstname' => $first,
            'lastname' => $last,
            'name' => $first.' '.$last,
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
