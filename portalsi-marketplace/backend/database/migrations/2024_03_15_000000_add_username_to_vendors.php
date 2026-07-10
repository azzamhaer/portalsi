<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('username', 32)->nullable()->unique()->after('slug');
            $table->timestamp('username_changed_at')->nullable()->after('username');
        });

        // Backfill username from slug for existing vendors
        DB::table('vendors')->orderBy('id')->each(function ($v) {
            $base = Str::slug($v->name) ?: ('toko-' . $v->id);
            // strip any path-like chars, keep only [a-z0-9-_]
            $base = preg_replace('/[^a-z0-9\-_]/', '', strtolower($base));
            if (strlen($base) < 3) $base = 'toko-' . $v->id;
            if (strlen($base) > 30) $base = substr($base, 0, 30);
            $candidate = $base;
            $n = 1;
            while (DB::table('vendors')->where('username', $candidate)->where('id', '!=', $v->id)->exists()) {
                $n++;
                $suffix = '-' . $n;
                $candidate = substr($base, 0, 30 - strlen($suffix)) . $suffix;
            }
            DB::table('vendors')->where('id', $v->id)->update(['username' => $candidate]);
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['username', 'username_changed_at']);
        });
    }
};
