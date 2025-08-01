<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(UsersTableSeeder::class);
        $this->call(ProductProviderSeeder::class);
        $this->call(ProductServicesSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(WebSubSeeder::class);
        $this->call(SubscribeSeeder::class);
        $this->call(DriverSeeder::class);
        $this->call(DriverPricesSeeder::class);


    }
}
