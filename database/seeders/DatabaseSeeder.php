<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'id' => Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@kuchi.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        User::create([
            'id' => Str::uuid(),
            'name' => 'Sales User',
            'email' => 'sales@kuchi.com',
            'password' => Hash::make('password'),
            'role' => 'sales',
        ]);
    }
}