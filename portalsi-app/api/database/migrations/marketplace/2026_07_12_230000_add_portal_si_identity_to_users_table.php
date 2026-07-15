<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'marketplace';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'portal_user_id')) {
                $table->unsignedBigInteger('portal_user_id')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('users', 'portal_username')) {
                $table->string('portal_username')->nullable()->unique()->after('portal_user_id');
            }

            if (! Schema::hasColumn('users', 'portal_role')) {
                $table->string('portal_role')->nullable()->after('role');
            }

            if (! Schema::hasColumn('users', 'portal_access_token')) {
                $table->text('portal_access_token')->nullable()->after('portal_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['portal_access_token', 'portal_role', 'portal_username', 'portal_user_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
