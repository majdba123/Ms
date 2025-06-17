<?php

namespace App\Console\Commands;

use App\Models\Disccount;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckExpiredDiscounts extends Command
{
    protected $signature = 'discounts:check-expired';
    protected $description = 'Update status of expired discounts';

    public function handle()
    {
        $now = Carbon::now();

        // تحديث الخصومات التي انتهى وقتها (totime)
        Disccount::where('status', 'active')
                 ->where('totime', '<=', $now)
                 ->update(['status' => 'inactive']);

        $this->info('Expired discounts have been updated successfully.');
    }
}
