<?php

namespace Database\Seeders;

class Helpers
{
    /**
     * Mapping kategori → keyword Unsplash + Unsplash photo ID (foto produk asli).
     * Pakai picsum.photos sebagai fallback dengan seed deterministik dari label.
     */
    /**
     * Gambar produk asli dari loremflickr.com — keyword diturunkan dari label.
     * Deterministik via ?lock=N — gambar yang sama untuk seed yang sama.
     */
    public static function productImage(string $emoji, string $bg1, string $bg2, string $label): string
    {
        // Pemetaan kategori → keyword Unsplash/Flickr relevan
        $keyword = self::labelToKeyword($label);
        $lock = abs(crc32($label . $bg1)) % 100000;
        return "https://loremflickr.com/600/600/" . urlencode($keyword) . "?lock={$lock}";
    }

    private static function labelToKeyword(string $label): string
    {
        $l = strtolower($label);
        // Pemetaan eksplisit untuk kategori populer marketplace
        $map = [
            'elektronik'   => 'electronics,gadget',
            'fashion'      => 'fashion,clothing',
            'baju'         => 'shirt,clothing',
            'kemeja'       => 'shirt',
            'sepatu'       => 'shoes,sneakers',
            'tas'          => 'bag,handbag',
            'hp'           => 'smartphone',
            'smartphone'   => 'smartphone,phone',
            'laptop'       => 'laptop,computer',
            'kamera'       => 'camera',
            'headphone'    => 'headphones',
            'earbuds'      => 'earbuds',
            'jam'          => 'watch,wristwatch',
            'jam-tangan'   => 'watch,wristwatch',
            'kacamata'     => 'glasses,eyewear',
            'kosmetik'     => 'cosmetics,makeup',
            'skincare'     => 'skincare,beauty',
            'parfum'       => 'perfume',
            'makanan'      => 'food',
            'snack'        => 'snack,food',
            'minuman'      => 'drink,beverage',
            'kopi'         => 'coffee',
            'buku'         => 'book',
            'alat-tulis'   => 'stationery,pen',
            'olahraga'     => 'sport,fitness',
            'bola'         => 'football,ball',
            'sepeda'       => 'bicycle,bike',
            'rumah'        => 'home,decor',
            'furniture'    => 'furniture',
            'dapur'        => 'kitchen',
            'mainan'       => 'toy,toys',
            'kesehatan'    => 'health,vitamin',
            'vitamin'      => 'vitamin,supplement',
            'otomotif'     => 'automotive,car',
            'motor'        => 'motorcycle',
        ];
        foreach ($map as $k => $v) {
            if (str_contains($l, $k)) return $v;
        }
        // Default — generic product
        return 'product';
    }

    public static function categoryIcon(string $emoji, string $color): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>"
            . "<rect width='100' height='100' rx='24' fill='{$color}'/>"
            . "<text x='50' y='68' text-anchor='middle' font-size='50' font-family='Apple Color Emoji,Segoe UI Emoji'>{$emoji}</text></svg>";
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    public static function avatar(string $initial, string $color): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>"
            . "<rect width='100' height='100' rx='50' fill='{$color}'/>"
            . "<text x='50' y='65' text-anchor='middle' font-size='42' font-weight='800' fill='#fff' font-family='Inter,sans-serif'>{$initial}</text></svg>";
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    public static function banner(string $emoji, string $bg1, string $bg2, string $title, string $sub): string
    {
        // Tanpa emoji — gradient + dots + ornament untuk look premium
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 300'>"
            . "<defs>"
            . "<linearGradient id='b' x1='0' y1='0' x2='1' y2='1'><stop offset='0%' stop-color='{$bg1}'/><stop offset='100%' stop-color='{$bg2}'/></linearGradient>"
            . "<pattern id='dots' x='0' y='0' width='40' height='40' patternUnits='userSpaceOnUse'>"
            . "<circle cx='20' cy='20' r='1.5' fill='rgba(255,255,255,0.18)'/></pattern>"
            . "</defs>"
            . "<rect width='1200' height='300' fill='url(#b)'/>"
            . "<rect width='1200' height='300' fill='url(#dots)'/>"
            . "<circle cx='1050' cy='80' r='180' fill='rgba(255,255,255,0.08)'/>"
            . "<circle cx='1150' cy='250' r='90'  fill='rgba(0,0,0,0.10)'/>"
            . "<text x='80' y='150' font-size='52' font-weight='900' fill='#fff' font-family='Inter,sans-serif' letter-spacing='-1.2'>" . htmlspecialchars($title) . "</text>"
            . "<text x='80' y='195' font-size='22' fill='rgba(255,255,255,0.85)' font-family='Inter,sans-serif'>" . htmlspecialchars($sub) . "</text>"
            . "</svg>";
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}
