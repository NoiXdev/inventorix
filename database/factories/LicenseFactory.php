<?php

namespace Database\Factories;

use App\Models\License;
use App\Services\LicenseService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<License> */
class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Microsoft 365 E3', 'Adobe Acrobat Pro', 'JetBrains All Products', 'Windows 11 Pro']).' '.fake()->numerify('###'),
            'product_key' => fake()->bothify('?????-?????-?????-?????'),
            'reassignable' => true,
        ];
    }

    /** Creates the license with this many seats (rows included). */
    public function seats(int $seats): static
    {
        return $this->afterCreating(fn (License $license) => app(LicenseService::class)->syncSeats($license, $seats));
    }

    public function configure(): static
    {
        return $this->afterCreating(function (License $license) {
            if ($license->licenseSeats()->count() === 0) {
                app(LicenseService::class)->syncSeats($license, max(1, (int) $license->seats));
            }
        });
    }
}
