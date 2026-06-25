<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('provider_order_id', 100)->unique();
            $table->string('payment_url')->nullable();
            $table->unsignedSmallInteger('usage_limit')->nullable();
            $table->timestamp('expiry_at')->nullable();
            $table->enum('status', ['creating', 'created', 'failed', 'pending', 'paid', 'expired', 'cancelled'])->default('creating');
            $table->unsignedInteger('discount_amount')->default(0);
            $table->json('provider_response')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
