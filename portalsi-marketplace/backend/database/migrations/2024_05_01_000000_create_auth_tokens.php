<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->id();
                $table->string('email')->index();
                $table->string('token', 80)->unique();
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }

        Schema::create('email_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('new_email');
            $table->string('token', 80)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 80)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        if (!Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            });
        }
        // Backfill: user lama (sebelum verification flow ada) dianggap sudah terverifikasi
        \Illuminate\Support\Facades\DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => \Illuminate\Support\Facades\DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');
        Schema::dropIfExists('email_change_requests');
        Schema::dropIfExists('password_resets');
    }
};
