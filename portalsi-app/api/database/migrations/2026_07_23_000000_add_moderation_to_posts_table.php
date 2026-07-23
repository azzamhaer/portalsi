<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'moderated_at')) {
                // Waktu postingan dimoderasi (take-down). NULL = tidak dimoderasi.
                // Soft take-down: baris tetap ada ~30 hari sebelum dibersihkan command.
                $table->timestamp('moderated_at')->nullable()->after('is_draft');
                $table->text('moderation_reason')->nullable()->after('moderated_at');
                $table->unsignedBigInteger('moderated_by')->nullable()->after('moderation_reason');
                // Waktu pemilik menutup modal pemberitahuan (agar tidak muncul terus).
                $table->timestamp('moderation_ack_at')->nullable()->after('moderated_by');
                $table->index(['user_id', 'moderated_at']);
                $table->index('moderated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'moderated_at')) {
                $table->dropIndex(['user_id', 'moderated_at']);
                $table->dropIndex(['moderated_at']);
                $table->dropColumn(['moderated_at', 'moderation_reason', 'moderated_by', 'moderation_ack_at']);
            }
        });
    }
};
