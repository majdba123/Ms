<?php

namespace Database\Seeders;

use App\Models\Provider_Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Provider_Product::create([
            'user_id' => 4,
            'status' =>"active",
        ]);

        Provider_Product::create([
            'user_id' => 5,
                        'status' =>"active",

        ]);

    }
}
