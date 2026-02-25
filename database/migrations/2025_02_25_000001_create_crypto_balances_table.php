<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 20)->default('USDT'); // USDT, BTC, ETH, etc.
            $table->string('network', 50)->nullable(); // ERC20, TRC20, etc.
            $table->decimal('balance', 24, 8)->unsigned()->default(0);
            $table->decimal('locked_balance', 24, 8)->unsigned()->default(0); // заблокировано
            $table->timestamps();

            $table->unique(['user_id', 'currency', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_balances');
    }
};
