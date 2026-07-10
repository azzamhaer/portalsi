<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'     => 'Admin MPSI',
            'email'    => 'admin@portalsi.id',
            'password' => Hash::make('admin123'),
            'role'     => 'ADMIN',
            'phone'    => '08111111111',
        ]);

        $buyer = User::create([
            'name'     => 'Demo User',
            'email'    => 'demo@portalsi.id',
            'password' => Hash::make('demo123'),
            'role'     => 'BUYER',
            'phone'    => '08123456789',
        ]);

        Address::create([
            'user_id'      => $buyer->id,
            'recipient'    => 'Demo User',
            'phone'        => '08123456789',
            'country'      => 'Indonesia',
            'province'     => 'DKI Jakarta',
            'city'         => 'DKI Jakarta - Jakarta Pusat',
            'district'     => 'Tanah Abang',
            'village'      => 'Senayan',
            'full_address' => 'Jl. Sudirman No.10, RT 01/RW 02, Kel. Senayan, Kec. Tanah Abang',
            'postal_code'  => '10220',
            'latitude'     => -6.2087634,
            'longitude'    => 106.845599,
            'is_default'   => true,
        ]);
    }
}
