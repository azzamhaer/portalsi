<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'is_draft')) {
                // Draft = sudah terunggah (media tersimpan) tapi hanya pemiliknya yang
                // boleh melihat. Dipisahkan dari is_archived karena maknanya beda:
                // arsip = pernah terbit lalu disembunyikan, draft = belum pernah terbit.
                $table->boolean('is_draft')->default(false)->after('is_archived');
                $table->index(['user_id', 'is_draft']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'is_draft')) {
                $table->dropIndex(['user_id', 'is_draft']);
                $table->dropColumn('is_draft');
            }
        });
    }
};
