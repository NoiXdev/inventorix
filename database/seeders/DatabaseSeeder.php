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
            'id' => '0196a784-0621-73e0-9d6c-9a17800854d1',
            'name' => 'noix',
            'firstname' => 'noix',
            'lastname' => 'support',
            'email' => 'hello@noix.dev',
            'password' => 'Start23!',
            'login_enabled' => true,
        ]);
    }
}
