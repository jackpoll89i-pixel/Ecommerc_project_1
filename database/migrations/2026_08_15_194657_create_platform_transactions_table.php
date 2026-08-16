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
    Schema::create('platform_transactions', function (Blueprint $table) {
        $table->id();
        $table->decimal('amount', 10, 2); // قيمة الربح (العمولة)
        $table->string('type'); // نوع الربح: featured_ad, center_payment, sale_commission
        $table->unsignedBigInteger('reference_id')->nullable(); // رقم العملية الأصلية (رقم الطلب أو الإعلان) لسهولة التتبع
        $table->string('notes')->nullable(); // ملاحظات إضافية لو لزم الأمر
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_transactions');
    }
};
