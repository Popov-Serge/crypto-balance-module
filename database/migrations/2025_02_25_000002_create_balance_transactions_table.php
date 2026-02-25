<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crypto_balance_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // credit, debit
            $table->decimal('amount', 24, 8)->unsigned();
            $table->decimal('balance_before', 24, 8)->unsigned();
            $table->decimal('balance_after', 24, 8)->unsigned();
            $table->string('reference_type', 100)->nullable(); // withdrawal, payment, fee, deposit
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique(); // защита от дублей
            $table->string('description', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['crypto_balance_id', 'created_at']);
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_transactions');
    }
};
