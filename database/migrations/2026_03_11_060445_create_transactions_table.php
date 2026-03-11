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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->foreignId('wallet_id')->nullable()->index();
            $table->string('currency', 10)->comment('Валюта транзакции');
            $table->enum('type', ['deposit', 'withdraw', 'fee'])->comment('Тип транзакции (ввод, вывод, комиссия)');
            $table->decimal('amount', 36, 18)->comment('Сумма транзакции');
            $table->enum('status', ['pending', 'confirmed', 'failed'])->comment('Статус транзакции (ожидание, подтвержден, провален)');
            $table->string('tx_hash')->unique()->nullable()->comment('Хэш транзакции');
            $table->timestamps();

            $table->index(['tx_hash', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
