<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_banned')) {
                $table->boolean('is_banned')->default(false)->after('is_verified');
            }
            if (! Schema::hasColumn('users', 'banned_at')) {
                $table->timestamp('banned_at')->nullable()->after('is_banned');
            }
            if (! Schema::hasColumn('users', 'banned_by')) {
                $table->unsignedBigInteger('banned_by')->nullable()->after('banned_at');
            }
            if (! Schema::hasColumn('users', 'ban_reason')) {
                $table->text('ban_reason')->nullable()->after('banned_by');
            }
            if (! Schema::hasColumn('users', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('ban_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['admin_notes', 'ban_reason', 'banned_by', 'banned_at', 'is_banned'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
