<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kolom untuk fitur ganti email pemulihan (link konfirmasi ke email lama).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'pending_email')) {
                $table->string('pending_email', 190)->nullable()->after('profile_picture_url');
            }
            if (! Schema::hasColumn('users', 'email_change_token')) {
                $table->string('email_change_token', 64)->nullable()->after('pending_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pending_email', 'email_change_token']);
        });
    }
};
