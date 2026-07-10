<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ============ NOTIFICATIONS ============
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50); // ORDER_CREATED, ORDER_PAID, ORDER_SHIPPED, ORDER_DONE, EMAIL_CHANGED, PASSWORD_CHANGED, REPORT_ACTION, MODERATION, WITHDRAW_STATUS, dst.
            $table->string('title');
            $table->text('message');
            $table->string('action_url', 300)->nullable(); // link untuk klik
            $table->string('severity', 20)->default('INFO'); // INFO|SUCCESS|WARNING|DANGER
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        // ============ REPORTS ============
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type', 20); // PRODUCT | VENDOR
            $table->unsignedBigInteger('target_id');
            $table->string('category', 50); // PROHIBITED_GOODS | COUNTERFEIT | SCAM | INAPPROPRIATE_CONTENT | HARASSMENT | dst.
            $table->text('description');
            $table->json('attachments')->nullable();
            $table->string('status', 20)->default('OPEN'); // OPEN | REVIEWING | RESOLVED | REJECTED
            $table->text('admin_response')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['target_type', 'target_id']);
            $table->index('status');
        });

        // ============ VENDOR.tour_completed_at ============
        Schema::table('vendors', function (Blueprint $table) {
            $table->timestamp('tour_completed_at')->nullable()->after('warning_dismissed_at');
        });

        // ============ Tambahan moderation: BANNED status (untuk vendor permanently banned) ============
        // (Cukup pakai moderation_mode = DISABLED + flag tambahan)
        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('is_banned')->default(false)->after('moderation_mode');
            $table->text('ban_reason')->nullable()->after('is_banned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('user_notifications');
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['tour_completed_at', 'is_banned', 'ban_reason']);
        });
    }
};
