<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('vendor_id')->constrained();

            // snapshot fields
            $table->string('product_name');
            $table->longText('product_image')->nullable();
            $table->unsignedBigInteger('price');
            $table->integer('quantity');

            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
