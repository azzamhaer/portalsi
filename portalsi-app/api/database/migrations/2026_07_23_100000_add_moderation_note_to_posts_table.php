<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'moderation_note')) {
                // Catatan bebas moderator. Hanya ditampilkan di DETAIL post, TIDAK di notifikasi.
                $table->text('moderation_note')->nullable()->after('moderation_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'moderation_note')) {
                $table->dropColumn('moderation_note');
            }
        });
    }
};
