<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subscribe;

class SubscribeSeeder extends Seeder
{
    public function run()
    {
        Subscribe::create([
            'provider__service_id' => 1,
            'web_sub_id' => 1,
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'status' => 'active',
        ]);
    }
}
