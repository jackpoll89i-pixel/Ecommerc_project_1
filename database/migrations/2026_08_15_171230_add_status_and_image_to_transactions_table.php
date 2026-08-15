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
    Schema::table('transactions', function (Blueprint $table) {
        // إضافة حالة الطلب (افتراضياً قيد الانتظار)
        $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
        // إضافة حقل لحفظ مسار صورة الحوالة
        $table->string('receipt_image')->nullable();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            //
        });
    }
};
