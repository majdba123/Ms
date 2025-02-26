<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Category::create([
            'name' => 'product1',
            'type' => 0,
            'price' => '20',
        ]);

        Category::create([
            'name' => 'product2',
            'type' => 0,
            'price' => '15',
        ]);
        Category::create([
            'name' => 'service1',
            'type' => 1,
            'price' => 0,
        ]);

        Category::create([
            'name' => 'service2',
            'type' => 1,
            'price' => 0,
        ]);

    }
}
