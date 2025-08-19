<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('first_vendor_delivery_data')->nullable()->after('delivery_fee');
            $table->text('second_vendor_delivery_data')->nullable()->after('first_vendor_delivery_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
                      $table->dropColumn(['first_vendor_delivery_fee', 'second_vendor_delivery_fee']);

        });
    }
};
