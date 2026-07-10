<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // NONE  = normal (default)
            // LIMITED = toko & produk masih bisa dilihat, tapi tidak bisa dipesan/dichat (penalti ringan)
            // DISABLED = hidden total dari semua public listing & detail (penalti berat)
            $table->string('moderation_mode', 20)->default('NONE')->after('badge');
            $table->text('admin_warning')->nullable()->after('moderation_mode');
            $table->timestamp('warning_dismissed_at')->nullable()->after('admin_warning');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['moderation_mode', 'admin_warning', 'warning_dismissed_at']);
        });
    }
};
