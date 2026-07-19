<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Struktur varian media:
            // {
            //   "original":  {"url":..,"key":..,"w":1920,"h":1080,"bytes":...},
            //   "medium":    {"url":..,"key":..,"w":1280,"h":720,"bytes":...},   // video saja
            //   "low":       {"url":..,"key":..,"w":854,"h":480,"bytes":...},    // video saja
            //   "thumbnail": {"url":..,"key":..,"w":480,"h":480,"bytes":...}
            // }
            $table->json('media_variants')->nullable()->after('thumbnail_url');
            // pending | processing | done | failed  (null = belum diproses/legacy)
            $table->string('media_status', 20)->nullable()->after('media_variants');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['media_variants', 'media_status']);
        });
    }
};
