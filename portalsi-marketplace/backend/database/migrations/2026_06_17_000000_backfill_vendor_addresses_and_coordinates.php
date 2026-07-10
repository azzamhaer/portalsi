<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('vendors') || !Schema::hasTable('addresses')) {
            return;
        }

        $now = now();

        DB::table('vendors')
            ->where(function ($query) {
                $query->whereNull('latitude')->orWhereNull('longitude');
            })
            ->orderBy('id')
            ->chunkById(100, function ($vendors) {
                foreach ($vendors as $vendor) {
                    $address = DB::table('addresses')
                        ->where('user_id', $vendor->user_id)
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->orderByDesc('is_default')
                        ->orderByDesc('id')
                        ->first();

                    if (!$address) continue;

                    DB::table('vendors')->where('id', $vendor->id)->update([
                        'country' => $vendor->country ?: ($address->country ?: 'Indonesia'),
                        'province' => $vendor->province ?: $address->province,
                        'province_id' => $vendor->province_id ?: $address->province_id,
                        'city' => $vendor->city ?: $address->city,
                        'city_id' => $vendor->city_id ?: $address->city_id,
                        'district' => $vendor->district ?: $address->district,
                        'district_id' => $vendor->district_id ?: $address->district_id,
                        'village' => $vendor->village ?: $address->village,
                        'village_id' => $vendor->village_id ?: $address->village_id,
                        'postal_code' => $vendor->postal_code ?: $address->postal_code,
                        'rajaongkir_destination_id' => $vendor->rajaongkir_destination_id ?: $address->rajaongkir_destination_id,
                        'latitude' => $address->latitude,
                        'longitude' => $address->longitude,
                        'full_address' => $vendor->full_address ?: $address->full_address,
                        'address_note' => $vendor->address_note ?: $address->address_note,
                        'updated_at' => now(),
                    ]);
                }
            });

        DB::table('addresses')
            ->where('city', 'DKI Jakarta - Jakarta Pusat')
            ->where('full_address', 'Jl. Sudirman No.10, RT 01/RW 02, Kel. Senayan, Kec. Tanah Abang')
            ->where(function ($query) {
                $query->whereNull('latitude')->orWhereNull('longitude');
            })
            ->update([
                'country' => 'Indonesia',
                'province' => 'DKI Jakarta',
                'district' => 'Tanah Abang',
                'village' => 'Senayan',
                'postal_code' => '10220',
                'latitude' => -6.2087634,
                'longitude' => 106.845599,
                'updated_at' => $now,
            ]);

        $demoVendorDefaults = [
            'TechMart Official' => ['Jakarta Pusat', 'DKI Jakarta', 'Gambir', 'Gambir', '10110', -6.1753924, 106.8271528],
            'Fashion Hub Indonesia' => ['Bandung', 'Jawa Barat', 'Bandung Wetan', 'Cihapit', '40114', -6.9174639, 107.6191228],
            'Beauty Box ID' => ['Surabaya', 'Jawa Timur', 'Genteng', 'Embong Kaliasin', '60271', -7.2574719, 112.7520883],
            'SehatSelalu Store' => ['Yogyakarta', 'DI Yogyakarta', 'Gedongtengen', 'Sosromenduran', '55271', -7.7955798, 110.3694896],
            'SportZone Indonesia' => ['Jakarta Barat', 'DKI Jakarta', 'Grogol Petamburan', 'Tomang', '11440', -6.1683295, 106.7588494],
            'AutoPro Garage' => ['Bekasi', 'Jawa Barat', 'Bekasi Timur', 'Margahayu', '17113', -6.2382699, 106.9755726],
            'Foodie Paradise' => ['Tangerang', 'Banten', 'Tangerang', 'Sukarasa', '15111', -6.1783056, 106.6318889],
            'Buku Pintar Store' => ['Depok', 'Jawa Barat', 'Pancoran Mas', 'Depok', '16431', -6.4024844, 106.7942405],
            'Rumah Idaman' => ['Semarang', 'Jawa Tengah', 'Semarang Tengah', 'Sekayu', '50132', -6.9903988, 110.4229104],
            'Gadget Galaxy' => ['Medan', 'Sumatera Utara', 'Medan Kota', 'Pusat Pasar', '20212', 3.5951956, 98.6722227],
            'Toys Wonderland' => ['Bali', 'Bali', 'Denpasar Barat', 'Pemecutan', '80119', -8.6704582, 115.2126293],
            'Voucher Express' => ['Jakarta Selatan', 'DKI Jakarta', 'Kebayoran Baru', 'Selong', '12110', -6.2614927, 106.8105998],
        ];

        foreach ($demoVendorDefaults as $name => [$city, $province, $district, $village, $postalCode, $lat, $lng]) {
            $vendors = DB::table('vendors')
                ->where('name', $name)
                ->where(function ($query) {
                    $query->whereNull('latitude')->orWhereNull('longitude');
                })
                ->get();

            foreach ($vendors as $vendor) {
                DB::table('vendors')->where('id', $vendor->id)->update([
                    'country' => 'Indonesia',
                    'province' => $province,
                    'city' => $vendor->city ?: $city,
                    'district' => $district,
                    'village' => $village,
                    'postal_code' => $postalCode,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'full_address' => $vendor->full_address ?: "Pusat operasional {$vendor->name}, {$city}",
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('vendors')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('full_address')
            ->orderBy('id')
            ->chunkById(100, function ($vendors) use ($now) {
                foreach ($vendors as $vendor) {
                    $exists = DB::table('addresses')
                        ->where('user_id', $vendor->user_id)
                        ->where('full_address', $vendor->full_address)
                        ->where('city', $vendor->city)
                        ->exists();

                    if ($exists) continue;

                    $hasAddress = DB::table('addresses')
                        ->where('user_id', $vendor->user_id)
                        ->exists();

                    $user = DB::table('users')->where('id', $vendor->user_id)->first();

                    DB::table('addresses')->insert([
                        'user_id' => $vendor->user_id,
                        'recipient' => $user?->name ?: $vendor->name,
                        'phone' => $user?->phone ?: '-',
                        'country' => $vendor->country ?: 'Indonesia',
                        'province' => $vendor->province,
                        'province_id' => $vendor->province_id,
                        'city' => $vendor->city,
                        'city_id' => $vendor->city_id,
                        'district' => $vendor->district,
                        'district_id' => $vendor->district_id,
                        'village' => $vendor->village,
                        'village_id' => $vendor->village_id,
                        'postal_code' => $vendor->postal_code,
                        'rajaongkir_destination_id' => $vendor->rajaongkir_destination_id,
                        'latitude' => $vendor->latitude,
                        'longitude' => $vendor->longitude,
                        'full_address' => $vendor->full_address,
                        'address_note' => $vendor->address_note,
                        'is_default' => !$hasAddress,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Data repair only. Leave restored coordinates and generated addresses in place.
    }
};
