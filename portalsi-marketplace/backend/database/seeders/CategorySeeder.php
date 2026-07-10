<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['id'=>'elektronik',    'name'=>'Elektronik',         'emoji'=>'📱', 'color'=>'#0A0A0A'],
            ['id'=>'fashion-pria',  'name'=>'Fashion Pria',       'emoji'=>'👔', 'color'=>'#0A0A0A'],
            ['id'=>'fashion-wanita','name'=>'Fashion Wanita',     'emoji'=>'👗', 'color'=>'#0A0A0A'],
            ['id'=>'kecantikan',    'name'=>'Kecantikan',         'emoji'=>'💄', 'color'=>'#0A0A0A'],
            ['id'=>'kesehatan',     'name'=>'Kesehatan',          'emoji'=>'💊', 'color'=>'#0A0A0A'],
            ['id'=>'olahraga',      'name'=>'Olahraga',           'emoji'=>'⚽', 'color'=>'#0A0A0A'],
            ['id'=>'otomotif',      'name'=>'Otomotif',           'emoji'=>'🏍️', 'color'=>'#0A0A0A'],
            ['id'=>'makanan',       'name'=>'Makanan & Minuman',  'emoji'=>'🍔', 'color'=>'#0A0A0A'],
            ['id'=>'buku',          'name'=>'Buku & Hobi',        'emoji'=>'📚', 'color'=>'#0A0A0A'],
            ['id'=>'rumah',         'name'=>'Rumah Tangga',       'emoji'=>'🛋️', 'color'=>'#0A0A0A'],
            ['id'=>'mainan',        'name'=>'Mainan & Bayi',      'emoji'=>'🧸', 'color'=>'#0A0A0A'],
            ['id'=>'voucher',       'name'=>'Voucher & Pulsa',    'emoji'=>'🎟️', 'color'=>'#0A0A0A'],
        ];
        foreach ($items as $i => $row) {
            Category::create([
                'id'         => $row['id'],
                'name'       => $row['name'],
                'slug'       => $row['id'],
                'emoji'      => $row['emoji'],
                'color'      => $row['color'],
                'icon'       => Helpers::categoryIcon($row['emoji'], $row['color']),
                'sort_order' => $i,
            ]);
        }
    }
}
