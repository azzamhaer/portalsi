<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Throttle login per-IP dengan eskalasi blokir + jejak akun yang dicoba.
        Schema::create('ip_login_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 64)->unique();
            $table->unsignedInteger('fail_count')->default(0);   // kegagalan beruntun sejak reset terakhir
            $table->unsignedTinyInteger('block_level')->default(0); // 0..4 (5m,30m,1j,1h)
            $table->timestamp('blocked_until')->nullable();
            $table->string('last_username')->nullable();          // akun yang terakhir dicoba
            $table->unsignedBigInteger('last_user_id')->nullable();
            $table->string('last_app', 32)->nullable();           // app / admin / marketplace / meet
            $table->unsignedInteger('total_failures')->default(0); // akumulasi (untuk statistik)
            $table->timestamps();
            $table->index('blocked_until');
        });

        // Log pendaftaran per-IP untuk batasi 3 akun/hari/IP + visibilitas admin.
        Schema::create('ip_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 64);
            $table->string('app', 32)->nullable();
            $table->string('username')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_login_blocks');
        Schema::dropIfExists('ip_registrations');
    }
};
