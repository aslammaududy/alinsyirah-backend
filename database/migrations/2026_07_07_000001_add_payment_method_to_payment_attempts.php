<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->enum('payment_method', ['qris', 'bank_transfer'])->nullable()->after('payment_url');
            $table->unsignedInteger('fee_amount')->default(0)->after('discount_amount');
            $table->decimal('fee_percentage', 5, 2)->default(0)->after('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'fee_amount', 'fee_percentage']);
        });
    }
};
