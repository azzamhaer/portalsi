<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom message opsional untuk notifikasi berpesan kustom
     * (mis. notifikasi moderasi admin: "Postingan kamu dihapus. Alasan: ...").
     * Kalau kosong, pesan digenerate dari `type` seperti biasa.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->text('message')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('message');
        });
    }
};
