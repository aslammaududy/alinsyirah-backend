<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempt_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tuition_invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('allocated_amount');
            $table->timestamps();

            $table->unique(['payment_attempt_id', 'tuition_invoice_id'], 'pai_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempt_invoices');
    }
};
