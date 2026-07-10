<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->longText('ktp_image')->nullable()->after('banner');
            $table->string('verification_status')->default('PENDING')->after('ktp_image'); // PENDING|APPROVED|REJECTED
            $table->text('verification_note')->nullable()->after('verification_status');
            $table->decimal('latitude', 10, 7)->nullable()->after('city');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('full_address')->nullable()->after('longitude');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('city');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }
    public function down(): void {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['ktp_image', 'verification_status', 'verification_note', 'latitude', 'longitude', 'full_address']);
        });
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
