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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('google_id')->nullable();
            $table->string('facebook_id')->nullable();
            $table->string('otp')->default(0);
            $table->string('status')->default("active");

            $table->string('lang')->nullable();
            $table->string('lat')->nullable();

            $table->string('email')->unique()->nullable();
            $table->string('national_id')->unique()->nullable();
            $table->string('image_path')->unique()->nullable();

            $table->string('phone')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('type')->default(0);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
