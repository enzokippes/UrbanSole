<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin UrbanSole',
            'email' => 'admin@urbansole.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Create demo customer
        User::create([
            'name' => 'Demo User',
            'email' => 'demo@urbansole.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
        ]);

        $this->call(ProductSeeder::class);
    }
}
