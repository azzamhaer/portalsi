<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'app_name'           => 'MPSI',
            'palette'            => 'mono',
            'primary_color'      => '#0a0a0a',
            'primary_fg'         => '#ffffff',
            'accent_color'       => '#6366f1',
            'tagline'            => 'Marketplace untuk semua',
            'commission_percent' => '5',
        ];
        foreach ($defaults as $k => $v) {
            Setting::firstOrCreate(['key' => $k], ['value' => $v]);
        }
    }
}
