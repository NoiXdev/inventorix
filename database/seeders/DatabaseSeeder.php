<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'noix',
            'firstname' => 'noix',
            'lastname' => 'support',
            'email' => 'hello@noix.dev',
            'password' => 'Start23!',
            'login_enabled' => true,
        ]);
    }
}
