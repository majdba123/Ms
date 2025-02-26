<?php

namespace Database\Seeders;

use App\Models\WebSub;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WebSubSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        WebSub::create([
            'user_id' => 2, // تأكد من وجود هذا المستخدم
            'time' => 3,
            'price' => 29.99,
        ]);

        WebSub::create([
            'user_id' => 2, // تأكد من وجود هذا المستخدم
            'time' => 6,
            'price' => 49.99,
        ]);

        WebSub::create([
            'user_id' => 2, // تأكد من وجود هذا المستخدم
            'time' => 12,
            'price' => 89.99,
        ]);

        WebSub::create([
            'user_id' => 2, // تأكد من وجود هذا المستخدم
            'time' => 1,
            'price' => 9.99,
        ]);
    }
}
