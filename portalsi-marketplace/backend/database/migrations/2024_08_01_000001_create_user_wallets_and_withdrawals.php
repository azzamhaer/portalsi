<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->string('type', 40);
            $table->string('reference')->unique();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type']);
        });

        Schema::create('user_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->string('bank_name');
            $table->string('bank_account');
            $table->string('bank_holder');
            $table->string('status')->default('PENDING');
            $table->text('admin_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_withdrawals');
        Schema::dropIfExists('user_wallet_transactions');
    }
};
