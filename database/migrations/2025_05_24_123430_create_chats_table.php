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
        Schema::create('chats', function (Blueprint $table) {
            $table->id(); // معرف الرسالة
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade'); // معرف المرسل
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade'); // معرف المستقبل
            $table->text('message'); // نص الرسالة
            $table->boolean('is_read')->default(false); // نص الرسالة
            $table->timestamps(); // الطوابع الزمنية
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
