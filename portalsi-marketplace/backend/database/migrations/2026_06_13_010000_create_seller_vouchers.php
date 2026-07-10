<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seller_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('type'); // FIXED | PERCENT
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('min_subtotal')->default(0);
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->unique(['vendor_id', 'code']);
        });

        Schema::create('seller_voucher_product', function (Blueprint $table) {
            $table->foreignId('seller_voucher_id')->constrained('seller_vouchers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['seller_voucher_id', 'product_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('voucher_code')->nullable()->after('variant_selection');
            $table->unsignedBigInteger('discount')->default(0)->after('voucher_code');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['voucher_code', 'discount']);
        });
        Schema::dropIfExists('seller_voucher_product');
        Schema::dropIfExists('seller_vouchers');
    }
};
