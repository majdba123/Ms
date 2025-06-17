<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {

        $schedule->command('discounts:check-expired')
                 ->everyTwelveHours()
                 ->appendOutputTo(storage_path('logs/expired_discounts.log'));

        $schedule->command('subscribes:check-expired')
                 ->dailyAt('00:00')
                 ->appendOutputTo(storage_path('logs/expired_subscribes.log'));

        // المهمة الجديدة للكوبونات (كل ساعة)
        $schedule->command('coupons:check-expired')
                 ->hourly()
                 ->appendOutputTo(storage_path('logs/expired_coupons.log'));
    }

    protected $commands = [
        \App\Console\Commands\CheckExpiredDiscounts::class,
        \App\Console\Commands\CheckExpiredSubscribes::class,
        \App\Console\Commands\CheckExpiredCoupons::class, // إضافة الأمر الجديد
    ];
}



/**php artisan coupons:check-expired
php artisan subscribes:check-expired
php artisan discounts:check-expired
php artisan products:check-expired */
