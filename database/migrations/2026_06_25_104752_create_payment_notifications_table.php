<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('provider_order_id');
            $table->string('transaction_status');
            $table->boolean('signature_valid');
            $table->json('raw_payload');
            $table->timestamps();

            $table->index('provider_order_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notifications');
    }
};
