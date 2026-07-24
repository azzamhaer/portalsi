<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menandai post video yang thumbnailnya DIPILIH sendiri oleh pengunggah (bukan hasil
 * ekstraksi otomatis). Dipakai agar pipeline media TIDAK menimpa thumbnail pilihan user
 * dengan frame detik-1 video.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'has_custom_thumbnail')) {
                $table->boolean('has_custom_thumbnail')->default(false)->after('thumbnail_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'has_custom_thumbnail')) {
                $table->dropColumn('has_custom_thumbnail');
            }
        });
    }
};
