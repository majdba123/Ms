<?php

namespace App\Console\Commands;

use App\Models\Subscribe;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckExpiredSubscribes extends Command
{
    protected $signature = 'subscribes:check-expired';
    protected $description = 'Update status of expired subscribes';

    public function handle()
    {
        $now = Carbon::now();

        // تحديث الاشتراكات التي انتهت صلاحيتها
        Subscribe::where('status', 'active')
                ->where('end_date', '<=', $now)
                ->update(['status' => 'expired']);

        $this->info('Expired subscribes have been updated successfully.');
    }
}
