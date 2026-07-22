<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'profile_picture_thumb_url')) {
                // Versi kecil (dipangkas) dari foto profil, dipakai untuk ditampilkan di
                // daftar/komentar/avatar kecil. Foto asli tetap di profile_picture_url untuk
                // pratinjau ukuran penuh. NULL = belum di-scan → pakai foto asli sebagai fallback.
                $table->string('profile_picture_thumb_url')->nullable()->after('profile_picture_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'profile_picture_thumb_url')) {
                $table->dropColumn('profile_picture_thumb_url');
            }
        });
    }
};
