<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->enum('fee_type', ['enrollment', 'spp', 'other']);
            $table->string('description')->nullable();
            $table->unsignedInteger('amount');
            $table->date('due_date');
            $table->enum('status', ['draft', 'pending_payment', 'paid', 'expired', 'cancelled'])->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->enum('generation_source', ['manual', 'scheduled']);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'period', 'fee_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuition_invoices');
    }
};
