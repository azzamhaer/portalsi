<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('address_id')->constrained();

            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping');
            $table->unsignedBigInteger('insurance')->default(0);
            $table->unsignedBigInteger('payment_fee')->default(0);
            $table->unsignedBigInteger('total');

            $table->string('courier_name');
            $table->string('courier_eta');

            $table->string('status')->default('PENDING_PAYMENT');
            // PENDING_PAYMENT | PAID | PROCESSING | SHIPPED | DONE | CANCELLED | EXPIRED
            $table->string('tracking_no')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('done_at')->nullable();

            $table->index(['user_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
