<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('section')->default('Umum');
            $table->string('question');
            $table->text('answer');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('group')->default('Virtual Account'); // VA, E-Wallet, QRIS, Convenience Store, Credit Card
            $table->longText('icon')->nullable(); // data URI atau URL gambar
            $table->string('color', 10)->default('#0a0a0a');
            $table->decimal('fee_pct', 5, 2)->default(0);
            $table->integer('fee_flat')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('payment_methods');
    }
};
