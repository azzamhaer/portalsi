<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->longText('images')->nullable()->after('image'); // JSON array of data URIs
            $table->longText('variants')->nullable()->after('images'); // JSON: { "Warna": ["Merah","Biru"], "Ukuran": ["S","M","L"] }
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('variant_selection', 500)->nullable()->after('price'); // e.g. "Warna: Merah, Ukuran: M"
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['images', 'variants']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('variant_selection');
        });
    }
};
