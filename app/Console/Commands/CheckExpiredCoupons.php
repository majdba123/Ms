<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckExpiredCoupons extends Command
{
    protected $signature = 'coupons:check-expired';
    protected $description = 'Update status of expired coupons';

    public function handle()
    {
        $now = Carbon::now();

        // تحديث الكوبونات المنتهية الصلاحية
        $expiredCount = Coupon::where('status', Coupon::STATUS_ACTIVE)
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', $now)
                            ->update(['status' => Coupon::STATUS_INACTIVE]);

        $this->info("{$expiredCount} coupons have been deactivated due to expiration.");
    }
}
