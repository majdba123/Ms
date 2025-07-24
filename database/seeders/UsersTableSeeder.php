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
            'lat' => '30.0444',
            'lang' => '31.2357',

        ]);

        User::create([
            'id' => 2,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'national_id' => '10000009000000',
            'lat' => '31.2001',
            'lang' => '29.9187',
            'password' => Hash::make('password'),
            'type' => 1,
        ]);

        User::create([
            'id' => 3,
            'name' => 'service',
            'email' => 'service@example.com',
            'national_id' => '12000000000000',
            'lat' => '29.9870',
            'lang' => '31.2118',
            'password' => Hash::make('password'),
            'type' => "service_provider",
        ]);

        User::create([
            'id' => 4,
            'name' => 'product1',
            'email' => 'product1@example.com',
            'national_id' => '00000000000000',
            'password' => Hash::make('password'),
            'type' => "product_provider",
            'lat' => '31.2565',
            'lang' => '32.2841',
        ]);
        User::create([
            'id' => 5,
            'name' => 'product2',
            'national_id' => '10008000000000',
            'email' => 'product2@example.com',
            'password' => Hash::make('password'),
            'type' => "product_provider",
            'lat' => '25.6872',
            'lang' => '32.6396',
        ]);

        User::create([
            'id' => 6,
            'name' => 'driver',
            'national_id' => '10000000000000',

            'email' => 'driver@example.com',
            'password' => Hash::make('password'),
            'type' => "driver",
            'lat' => '24.0889',
            'lang' => '32.8998',
        ]);
    }
}
