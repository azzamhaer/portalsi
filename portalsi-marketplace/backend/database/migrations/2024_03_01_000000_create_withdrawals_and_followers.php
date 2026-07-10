<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->string('bank_name');
            $table->string('bank_account');
            $table->string('bank_holder');
            $table->string('status')->default('PENDING'); // PENDING|APPROVED|REJECTED|PAID
            $table->text('admin_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('vendor_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['vendor_id', 'user_id']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('badge')->nullable()->after('is_official'); // NULL|VERIFIED|MALL|STAR
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('vendor_followers');
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('badge');
        });
    }
};
