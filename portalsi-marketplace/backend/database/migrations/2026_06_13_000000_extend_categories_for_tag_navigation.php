<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('parent_id')->nullable()->after('id');
            $table->string('tag_slug')->nullable()->after('slug');
            $table->boolean('is_active')->default(true)->after('icon');
            $table->boolean('featured_home')->default(true)->after('is_active');

            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['parent_id', 'is_active', 'featured_home']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id', 'is_active', 'featured_home']);
            $table->dropColumn(['parent_id', 'tag_slug', 'is_active', 'featured_home']);
        });
    }
};
