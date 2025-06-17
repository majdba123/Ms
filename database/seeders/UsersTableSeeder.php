<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        User::create([
            'id' => 1,
            'name' => 'User',
            'email' => 'user@example.com',
                        'national_id' => '10000008600000',

            'password' => Hash::make('password'),
            'type' => 0,
        ]);

        User::create([
            'id' => 2,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'national_id' => '10000009000000',

            'password' => Hash::make('password'),
            'type' => 1,
        ]);

        User::create([
            'id' => 3,
            'name' => 'service',
            'email' => 'service@example.com',
            'national_id' => '12000000000000',

            'password' => Hash::make('password'),
            'type' => "service_provider",
        ]);

        User::create([
            'id' => 4,
            'name' => 'product',
            'email' => 'product@example.com',
            'national_id' => '00000000000000',
            'password' => Hash::make('password'),
            'type' => "product_provider",
        ]);

        User::create([
            'id' => 5,
            'name' => 'driver',
            'national_id' => '10000000000000',

            'email' => 'driver@example.com',
            'password' => Hash::make('password'),
            'type' => "driver",
        ]);
    }
}
