<?php

use App\Models\WebSub;
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
        Schema::table('subscribes', function (Blueprint $table) {
            $table->foreignIdFor(WebSub::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribes', function (Blueprint $table) {
            $table->dropForeign(['web_sub_id']);
            $table->dropColumn('web_sub_id');
        });
    }
};
