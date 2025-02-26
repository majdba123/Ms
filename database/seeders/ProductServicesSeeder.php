<?php

namespace Database\Seeders;

use App\Models\Provider_Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductServicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Provider_Service::create([
            'user_id' => 3,
        ]);

    }
}
