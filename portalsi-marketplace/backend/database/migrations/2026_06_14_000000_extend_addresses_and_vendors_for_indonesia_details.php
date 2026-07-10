<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('addresses', 'country')) {
                $table->string('country', 80)->default('Indonesia')->after('phone');
            }
            if (!Schema::hasColumn('addresses', 'province')) {
                $table->string('province')->nullable()->after('country');
            }
            if (!Schema::hasColumn('addresses', 'district')) {
                $table->string('district')->nullable()->after('city');
            }
            if (!Schema::hasColumn('addresses', 'village')) {
                $table->string('village')->nullable()->after('district');
            }
            if (!Schema::hasColumn('addresses', 'address_note')) {
                $table->text('address_note')->nullable()->after('full_address');
            }
        });

        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'country')) {
                $table->string('country', 80)->default('Indonesia')->after('city');
            }
            if (!Schema::hasColumn('vendors', 'province')) {
                $table->string('province')->nullable()->after('country');
            }
            if (!Schema::hasColumn('vendors', 'district')) {
                $table->string('district')->nullable()->after('province');
            }
            if (!Schema::hasColumn('vendors', 'village')) {
                $table->string('village')->nullable()->after('district');
            }
            if (!Schema::hasColumn('vendors', 'postal_code')) {
                $table->string('postal_code', 10)->nullable()->after('village');
            }
            if (!Schema::hasColumn('vendors', 'address_note')) {
                $table->text('address_note')->nullable()->after('full_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['country', 'province', 'district', 'village', 'address_note']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['country', 'province', 'district', 'village', 'postal_code', 'address_note']);
        });
    }
};
