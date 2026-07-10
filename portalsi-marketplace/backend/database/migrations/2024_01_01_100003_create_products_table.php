<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('category_id');
            $table->foreign('category_id')->references('id')->on('categories');

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->unsignedBigInteger('price'); // Rupiah
            $table->unsignedBigInteger('original_price')->nullable();
            $table->integer('stock')->default(0);
            $table->integer('sold')->default(0);
            $table->float('rating')->default(5.0);
            $table->integer('weight')->default(500); // gram
            $table->longText('image')->nullable(); // SVG/base64

            $table->boolean('is_active')->default(true);
            $table->boolean('is_flash_sale')->default(false);
            $table->timestamps();

            $table->index(['category_id']);
            $table->index(['vendor_id']);
            $table->index(['is_flash_sale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
