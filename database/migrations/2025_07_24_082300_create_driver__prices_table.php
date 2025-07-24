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
        Schema::create('driver__prices', function (Blueprint $table) {
            $table->id();
            $table->decimal('from_distance', 10, 2); // المسافة البدائية (كيلومتر)
            $table->decimal('to_distance', 10, 2);   // المسافة النهائية (كيلومتر)
            $table->decimal('price', 10, 2);         // السعر
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver__prices');
    }
};
