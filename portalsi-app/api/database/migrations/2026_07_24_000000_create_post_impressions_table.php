<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak tayang beranda: mencatat postingan yang SUDAH pernah muncul di beranda seorang
 * pengguna. Dipakai algoritma feed untuk mengurangi pengulangan — post yang baru dilihat
 * diberi peluang kecil untuk muncul lagi (tidak dihilangkan sepenuhnya), sementara post
 * yang belum pernah dilihat & konten baru diprioritaskan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_impressions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('post_id');
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('seen_count')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
            $table->index(['user_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_impressions');
    }
};
