<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('method');         // BRIVA, BCAVA, OVO, QRIS, dll
            $table->string('method_name');
            $table->string('reference')->nullable(); // dari Tripay
            $table->string('pay_code')->nullable();  // VA / kode bayar retail
            $table->text('pay_url')->nullable();     // untuk e-wallet/CC
            $table->text('qr_string')->nullable();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee');
            $table->unsignedBigInteger('total');
            $table->string('status')->default('UNPAID'); // UNPAID|PAID|EXPIRED|FAILED|REFUND
            $table->timestamp('expired_at');
            $table->timestamp('paid_at')->nullable();
            $table->longText('raw_response')->nullable(); // JSON

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
