<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'pinned_at')) {
                // Waktu post disematkan. NULL = tidak disematkan.
                // Pakai timestamp (bukan boolean) supaya urutan antar post yang disematkan
                // bisa ditentukan: yang paling baru disematkan tampil lebih dulu.
                $table->timestamp('pinned_at')->nullable()->after('is_archived');
                $table->index(['user_id', 'pinned_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'pinned_at')) {
                $table->dropIndex(['user_id', 'pinned_at']);
                $table->dropColumn('pinned_at');
            }
        });
    }
};
