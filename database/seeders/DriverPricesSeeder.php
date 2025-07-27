<?php

namespace Database\Seeders;

use App\Models\Driver_Price;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DriverPricesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
              $prices = [
            ['from_distance' => 0, 'to_distance' => 5, 'price' => 10],
            ['from_distance' => 6, 'to_distance' => 10, 'price' => 15],
            ['from_distance' => 11, 'to_distance' => 15, 'price' => 20],
            ['from_distance' => 16, 'to_distance' => 20, 'price' => 25],
            ['from_distance' => 21, 'to_distance' => 25, 'price' => 30],
            ['from_distance' => 26, 'to_distance' => 30, 'price' => 35],
            ['from_distance' => 31, 'to_distance' => 35, 'price' => 40],
            ['from_distance' => 36, 'to_distance' => 40, 'price' => 45],
            ['from_distance' => 41, 'to_distance' => 45, 'price' => 50],
            ['from_distance' => 46, 'to_distance' => 50, 'price' => 55],
            // يمكنك إضافة المزيد من النطاقات حسب الحاجة
        ];

        foreach ($prices as $price) {
            Driver_Price::create($price);
        }
    }
}
