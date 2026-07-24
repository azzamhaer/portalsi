<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bukti persetujuan pengguna atas sebuah kebijakan. Versi disimpan supaya bila admin
 * mengubah kebijakan (version naik), pengguna diminta menyetujui lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('policy_version')->default(1);
            $table->timestamp('accepted_at')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamps();

            $table->unique(['policy_id', 'user_id']);
            $table->index(['policy_id', 'policy_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_acceptances');
    }
};
