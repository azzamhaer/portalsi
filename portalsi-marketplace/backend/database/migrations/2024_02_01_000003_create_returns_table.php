<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('reason');
            $table->string('status')->default('PENDING'); // PENDING | APPROVED | REJECTED | REFUNDED
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        Schema::create('shipping_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('eta');
            $table->unsignedBigInteger('cost');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('order_returns');
        Schema::dropIfExists('shipping_options');
    }
};
