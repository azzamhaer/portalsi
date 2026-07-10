<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('addresses', 'province_id')) {
                $table->string('province_id', 20)->nullable()->after('province');
            }
            if (!Schema::hasColumn('addresses', 'city_id')) {
                $table->string('city_id', 20)->nullable()->after('city');
            }
            if (!Schema::hasColumn('addresses', 'district_id')) {
                $table->string('district_id', 20)->nullable()->after('district');
            }
            if (!Schema::hasColumn('addresses', 'village_id')) {
                $table->string('village_id', 20)->nullable()->after('village');
            }
            if (!Schema::hasColumn('addresses', 'rajaongkir_destination_id')) {
                $table->unsignedBigInteger('rajaongkir_destination_id')->nullable()->after('postal_code');
            }
        });

        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'province_id')) {
                $table->string('province_id', 20)->nullable()->after('province');
            }
            if (!Schema::hasColumn('vendors', 'city_id')) {
                $table->string('city_id', 20)->nullable()->after('city');
            }
            if (!Schema::hasColumn('vendors', 'district_id')) {
                $table->string('district_id', 20)->nullable()->after('district');
            }
            if (!Schema::hasColumn('vendors', 'village_id')) {
                $table->string('village_id', 20)->nullable()->after('village');
            }
            if (!Schema::hasColumn('vendors', 'rajaongkir_destination_id')) {
                $table->unsignedBigInteger('rajaongkir_destination_id')->nullable()->after('postal_code');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'courier_code')) {
                $table->string('courier_code', 50)->nullable()->after('courier_name');
            }
            if (!Schema::hasColumn('orders', 'courier_service')) {
                $table->string('courier_service', 100)->nullable()->after('courier_code');
            }
            if (!Schema::hasColumn('orders', 'shipping_type')) {
                $table->string('shipping_type', 100)->nullable()->after('courier_service');
            }
            if (!Schema::hasColumn('orders', 'shipping_cashback')) {
                $table->unsignedBigInteger('shipping_cashback')->default(0)->after('shipping');
            }
            if (!Schema::hasColumn('orders', 'shipping_service_fee')) {
                $table->unsignedBigInteger('shipping_service_fee')->default(0)->after('shipping_cashback');
            }
            if (!Schema::hasColumn('orders', 'rajaongkir_order_no')) {
                $table->string('rajaongkir_order_no')->nullable()->after('tracking_no');
            }
            if (!Schema::hasColumn('orders', 'shipping_payload')) {
                $table->longText('shipping_payload')->nullable()->after('rajaongkir_order_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'courier_code',
                'courier_service',
                'shipping_type',
                'shipping_cashback',
                'shipping_service_fee',
                'rajaongkir_order_no',
                'shipping_payload',
            ]);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['province_id', 'city_id', 'district_id', 'village_id', 'rajaongkir_destination_id']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['province_id', 'city_id', 'district_id', 'village_id', 'rajaongkir_destination_id']);
        });
    }
};
