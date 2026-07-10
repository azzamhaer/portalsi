<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'address_snapshot')) {
                $table->json('address_snapshot')->nullable()->after('address_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            try {
                $table->dropForeign(['address_id']);
            } catch (Throwable $e) {
                // Older/local databases may not have the conventional FK name.
            }
            $table->unsignedBigInteger('address_id')->nullable()->change();
            $table->foreign('address_id')->references('id')->on('addresses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            try {
                $table->dropForeign(['address_id']);
            } catch (Throwable $e) {
            }
            $table->unsignedBigInteger('address_id')->nullable(false)->change();
            $table->foreign('address_id')->references('id')->on('addresses');
            if (Schema::hasColumn('orders', 'address_snapshot')) {
                $table->dropColumn('address_snapshot');
            }
        });
    }
};
