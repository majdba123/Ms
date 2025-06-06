<?php

use App\Models\Product;
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
        Schema::create('disccounts', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('active');
            $table->foreignIdFor(Product::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('value');
            $table->string('fromtime'); // Start time of the discount
            $table->string('totime'); // End time of the discount
            $table->morphs('providerable1');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disccounts');
    }
};
