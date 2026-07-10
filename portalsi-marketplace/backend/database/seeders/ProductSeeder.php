<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $vmap = cache()->get('seed.vendor_map', []);

        $products = [
            // Elektronik
            ['Smartphone X-Pro 5G 12/256GB',        'elektronik','v1', 5999000, 7299000, 48,  1234, 4.9, '📱','#0A0A0A','#1f1f1f','SmartPhone',true,'Smartphone flagship dengan kamera 108MP, baterai 5000mAh, fast charging 67W, layar AMOLED 120Hz.'],
            ['Laptop UltraBook 14" i7 16GB SSD 512GB','elektronik','v1', 14250000,16500000,12,  312, 4.8, '💻','#0A0A0A','#1f1f1f','Laptop',false,'Laptop tipis & ringan untuk profesional, prosesor Intel Core i7 generasi terbaru, RAM 16GB, SSD NVMe 512GB.'],
            ['TWS Earbuds Pro Active Noise Cancelling','elektronik','v10', 489000, 899000, 120, 5421, 4.7, '🎧','#1f1f1f','#374151','Earbuds',true,'Earbuds wireless dengan ANC, baterai 40 jam, IPX5 waterproof.'],
            ['Smartwatch Sport GPS Heart Rate',     'elektronik','v10', 759000, 1299000, 78,  2841, 4.8, '⌚','#0A0A0A','#374151','Watch',false,'Smartwatch dengan fitur GPS, monitor detak jantung, SpO2, 100+ mode olahraga.'],
            ['Power Bank 20000mAh Fast Charging 22.5W','elektronik','v10',189000, 299000, 340, 8920, 4.9, '🔋','#1f1f1f','#0A0A0A','PowerBank',false,'Power bank 20000mAh dengan PD & QC 22.5W, output 3 device sekaligus.'],
            ['Smart TV LED 55" 4K UHD Android',     'elektronik','v1', 6499000, 7999000, 18,  421, 4.8, '📺','#0A0A0A','#1f1f1f','SmartTV',false,'Smart TV 55 inch 4K UHD dengan Android TV, Netflix, YouTube, Google Assistant built-in.'],
            ['Mouse Gaming RGB 12000 DPI',          'elektronik','v1', 289000, 450000, 95,  3120, 4.8, '🖱️','#374151','#0A0A0A','Mouse',false,'Mouse gaming dengan sensor optik 12000 DPI, RGB customizable, 7 tombol macro.'],
            ['Keyboard Mechanical Hot-Swap RGB',    'elektronik','v1', 849000, 1250000, 42,  980, 4.9, '⌨️','#1f1f1f','#374151','Keyboard',false,'Keyboard mechanical TKL, hot-swappable switch, RGB per-key, full aluminium body.'],
            // Fashion Pria
            ['Kemeja Flannel Premium Lengan Panjang','fashion-pria','v2', 179000, 249000, 80,  2150, 4.7, '👔','#0A0A0A','#374151','Flannel',false,'Kemeja flannel kotak motif premium, bahan katun adem, tersedia size M-XXL.'],
            ['Celana Chino Slim Fit Stretch',       'fashion-pria','v2', 159000, 225000, 120, 1840, 4.8, '👖','#1f1f1f','#0A0A0A','Chino',false,'Celana chino bahan stretch nyaman seharian, slim fit modern, 6 pilihan warna.'],
            ['Sepatu Sneakers Casual Putih Original','fashion-pria','v2', 399000, 599000, 55,  980, 4.9, '👟','#374151','#1f1f1f','Sneakers',true,'Sneakers putih casual upper kulit sintetis premium, sole karet anti slip.'],
            ['Jaket Bomber Pria Outdoor Waterproof','fashion-pria','v2', 289000, 425000, 48,  720, 4.7, '🧥','#0A0A0A','#1f1f1f','Jacket',false,'Jaket bomber bahan parasut waterproof, lining mesh, 4 saku zipper.'],
            ['Jam Tangan Pria Classic Stainless',   'fashion-pria','v2', 329000, 499000, 65,  1380, 4.8, '⌚','#1f1f1f','#374151','Watch',false,'Jam tangan pria classic dial bulat, strap stainless steel, tahan air 30M.'],
            ['Kaos Polos Cotton Combed 30s',        'fashion-pria','v2', 59000,  89000,  520, 8420, 4.9, '👕','#0A0A0A','#374151','Tshirt',false,'Kaos polos cotton combed 30s, jahitan rapi, 12 pilihan warna, size S-XXL.'],
            // Fashion Wanita
            ['Dress Midi Floral Korean Style',      'fashion-wanita','v2', 189000, 289000, 90,  1840, 4.8, '👗','#1f1f1f','#374151','Dress',true,'Dress midi motif floral, gaya Korean elegan, bahan katun premium.'],
            ['Blouse Wanita Lengan Panjang Chiffon','fashion-wanita','v2', 119000, 169000, 140, 2310, 4.7, '👚','#374151','#1f1f1f','Blouse',false,'Blouse chiffon lembut, cutting longgar, cocok untuk kerja & hangout.'],
            ['Tas Wanita Sling Bag Premium PU',     'fashion-wanita','v2', 159000, 249000, 75,  1240, 4.8, '👜','#0A0A0A','#1f1f1f','Bag',false,'Sling bag PU leather premium, kompartemen 3 ruang, strap adjustable.'],
            ['Sepatu Heels Wanita Pointed Toe',     'fashion-wanita','v2', 259000, 389000, 48,  680, 4.7, '👠','#1f1f1f','#0A0A0A','Heels',false,'Heels pointed toe 7cm, sole anti slip, nyaman dipakai seharian.'],
            ['Hijab Pashmina Ceruty Premium',       'fashion-wanita','v2', 49000,  79000,  380, 6420, 4.9, '🧕','#374151','#1f1f1f','Hijab',false,'Pashmina bahan ceruty babydoll, lembut, jatuh, 30 pilihan warna.'],
            ['Rok Plisket Highwaist Premium',       'fashion-wanita','v2', 139000, 199000, 110, 1520, 4.8, '👗','#0A0A0A','#374151','Skirt',false,'Rok plisket highwaist, bahan jatuh tidak transparan, 5 warna basic.'],
            // Kecantikan
            ['Serum Vitamin C Brightening 30ml BPOM','kecantikan','v3', 89000,  139000, 240, 7820, 4.9, '💄','#374151','#1f1f1f','Serum',true,'Serum Vitamin C 10% + Niacinamide untuk wajah cerah merata, BPOM.'],
            ['Sunscreen SPF 50 PA++++ 50ml',        'kecantikan','v3', 79000,  119000, 320, 9210, 4.9, '☀️','#1f1f1f','#0A0A0A','SPF50',false,'Sunscreen ringan tidak whitecast, SPF 50 PA++++, cocok semua jenis kulit.'],
            ['Lipstik Matte Long Lasting 24 Jam',   'kecantikan','v3', 69000,  99000,  180, 5420, 4.8, '💋','#0A0A0A','#374151','Lipstick',false,'Lipstik matte tahan 24 jam, tidak bikin bibir kering, 12 shade pilihan.'],
            ['Toner Exfoliating BHA 2% 100ml',      'kecantikan','v3', 99000,  149000, 160, 3420, 4.8, '🧴','#374151','#1f1f1f','Toner',false,'Exfoliating toner BHA 2% untuk hilangkan komedo & jerawat.'],
            ['Foundation Cushion Matte SPF 30',     'kecantikan','v3', 139000, 199000, 120, 2840, 4.7, '🎨','#1f1f1f','#0A0A0A','Cushion',false,'Cushion full coverage matte, SPF 30, refill tersedia, 6 shade.'],
            ['Parfum Wanita Eau de Parfum 50ml',    'kecantikan','v3', 189000, 289000, 85,  1820, 4.9, '🌸','#0A0A0A','#374151','Parfum',false,'Parfum dengan top notes floral, base notes musk, tahan 8-10 jam.'],
            // Kesehatan
            ['Vitamin C 1000mg 100 Tablet',         'kesehatan','v4', 79000,  120000, 240, 5840, 4.9, '💊','#0A0A0A','#374151','VitC',true,'Vitamin C 1000mg, daya tahan tubuh, isi 100 tablet.'],
            ['Madu Hutan Asli 500ml',               'kesehatan','v4', 135000, 189000, 150, 2920, 4.8, '🍯','#374151','#1f1f1f','Madu',false,'Madu hutan murni 500ml, tanpa campuran gula, hasil panen lebah liar.'],
            ['Masker Medis 4 Ply 50pcs',            'kesehatan','v4', 35000,  65000,  480, 12420,4.9, '😷','#1f1f1f','#0A0A0A','Masker',false,'Masker medis 4 lapis BFE 99%, isi 50pcs per box.'],
            ['Tensimeter Digital Lengan',           'kesehatan','v4', 289000, 425000, 55,  740, 4.8, '🩺','#0A0A0A','#1f1f1f','Tensi',false,'Tensimeter digital lengan akurat, memori 90 record, 2 user.'],
            ['Multivitamin Anak Sirup 100ml',       'kesehatan','v4', 69000,  95000,  180, 3210, 4.9, '🧒','#374151','#0A0A0A','MultiVit',false,'Multivitamin anak rasa jeruk, mendukung tumbuh kembang & nafsu makan.'],
            // Olahraga
            ['Sepatu Lari Running Shoes Pro',       'olahraga','v5', 599000, 899000, 62,  1240, 4.8, '👟','#1f1f1f','#374151','Running',true,'Sepatu lari dengan boost cushioning, breathable mesh upper, anti slip.'],
            ['Jersey Sepak Bola Tim Nasional Replika','olahraga','v5', 179000, 249000, 140, 2820, 4.7, '👕','#0A0A0A','#1f1f1f','Jersey',false,'Jersey replika tim nasional, bahan dryfit, ada nama & nomor punggung.'],
            ['Bola Sepak Size 5 Original',          'olahraga','v5', 189000, 289000, 78,  980, 4.8, '⚽','#374151','#0A0A0A','Bola',false,'Bola sepak size 5 standar FIFA, jahitan rapi, awet untuk lapangan.'],
            ['Yoga Mat Anti Slip 6mm',              'olahraga','v5', 99000,  149000, 240, 3420, 4.9, '🧘','#0A0A0A','#374151','YogaMat',false,'Yoga mat 6mm, anti slip dua sisi, ringan dilengkapi tali.'],
            ['Dumbbell Adjustable 20kg',            'olahraga','v5', 489000, 725000, 32,  520, 4.8, '🏋️','#1f1f1f','#0A0A0A','Dumbbell',false,'Dumbbell adjustable 20kg per pcs, plate karet, bar chrome.'],
            ['Sepeda Lipat Folding Bike 20"',       'olahraga','v5', 2499000,3299000, 18,  120, 4.9, '🚲','#374151','#1f1f1f','Bike',false,'Sepeda lipat 20 inch, 7 speed Shimano, frame alloy ringan.'],
            // Otomotif
            ['Helm Half Face SNI Standar',          'otomotif','v6', 189000, 275000, 120, 1820, 4.7, '🪖','#0A0A0A','#1f1f1f','Helm',false,'Helm half face SNI, busa empuk, kaca anti UV.'],
            ['Oli Mesin Motor Full Synthetic 10W-40','otomotif','v6', 79000,  115000, 340, 5420, 4.9, '🛢️','#1f1f1f','#374151','Oli',false,'Oli mesin full synthetic 10W-40 untuk motor matic & bebek, 800ml.'],
            ['Ban Motor Tubeless 80/90-14',         'otomotif','v6', 259000, 349000, 95,  1240, 4.8, '🛞','#374151','#0A0A0A','Ban',false,'Ban motor tubeless 80/90-14, kompon soft, awet & grip mantap.'],
            ['Cover Motor Anti Air Premium',        'otomotif','v6', 79000,  129000, 180, 2840, 4.7, '🛵','#0A0A0A','#374151','Cover',false,'Cover motor parasut waterproof, tahan UV, tersedia size M-XXL.'],
            ['Aki Motor Kering Maintenance Free',   'otomotif','v6', 189000, 265000, 65,  840, 4.8, '🔋','#1f1f1f','#0A0A0A','Aki',false,'Aki motor kering MF, garansi 12 bulan, tinggal pasang.'],
            // Makanan
            ['Kopi Arabica Premium Roasted 250g',   'makanan','v7', 89000,  129000, 240, 4820, 4.9, '☕','#0A0A0A','#374151','Kopi',true,'Biji kopi arabica single origin, medium roast, fresh roasted setiap minggu.'],
            ['Snack Box Korean Mix 1kg',            'makanan','v7', 159000, 225000, 120, 2820, 4.8, '🍿','#374151','#1f1f1f','Snack',false,'Box snack import Korea isi 30+ varian, halal, ready stock.'],
            ['Cokelat Belgian Dark 70% 200g',       'makanan','v7', 79000,  115000, 180, 3210, 4.9, '🍫','#1f1f1f','#0A0A0A','Coklat',false,'Cokelat hitam Belgian 70% cocoa, premium, gluten free.'],
            ['Teh Hijau Premium 100 Bag',           'makanan','v7', 55000,  79000,  240, 4120, 4.8, '🍵','#0A0A0A','#1f1f1f','Teh',false,'Teh hijau premium pilihan, isi 100 teabag, antioksidan tinggi.'],
            ['Frozen Dimsum Mix 30pcs Halal',       'makanan','v7', 99000,  139000, 140, 2410, 4.9, '🥟','#374151','#0A0A0A','Dimsum',false,'Frozen dimsum aneka rasa 30pcs, halal, tinggal kukus 10 menit.'],
            // Buku
            ['Atomic Habits - James Clear (ID)',    'buku','v8', 89000,  120000, 240, 6820, 4.9, '📕','#0A0A0A','#374151','Buku',true,'Buku best seller Atomic Habits versi Indonesia, hardcover.'],
            ['Set Alat Tulis Lengkap 50pcs',        'buku','v8', 79000,  125000, 180, 2840, 4.8, '✏️','#1f1f1f','#0A0A0A','Stationery',false,'Set alat tulis pelajar isi 50pcs lengkap dengan kotak pensil.'],
            ['Sapiens - Yuval Noah Harari (ID)',    'buku','v8', 99000,  135000, 120, 3210, 4.9, '📗','#374151','#1f1f1f','Sapiens',false,'Sapiens versi Indonesia, sejarah singkat umat manusia, soft cover.'],
            ['Buku Tulis A5 100 Lembar 5pcs',       'buku','v8', 39000,  60000,  520, 8420, 4.9, '📓','#0A0A0A','#1f1f1f','Buku',false,'Buku tulis A5 hard cover, isi 100 lembar, paket 5pcs hemat.'],
            ['Cat Akrilik Set 24 Warna + Kuas',     'buku','v8', 139000, 199000, 80,  1240, 4.8, '🎨','#1f1f1f','#374151','Cat',false,'Cat akrilik 24 warna + 6 kuas, untuk kanvas, kayu, kertas.'],
            // Rumah
            ['Rice Cooker Digital 1.8L 8-in-1',     'rumah','v9', 489000, 725000, 48,  920, 4.8, '🍚','#0A0A0A','#374151','RiceCooker',false,'Rice cooker digital 8 fungsi: nasi, bubur, kue, soup, dll.'],
            ['Air Fryer 5L Touch Screen',           'rumah','v9', 799000, 1199000, 32, 621, 4.9, '🍳','#374151','#1f1f1f','AirFryer',true,'Air fryer kapasitas 5L touchscreen, 12 preset menu, basket non-stick.'],
            ['Set Sprei Premium 180x200 Microfiber','rumah','v9', 189000, 289000, 140, 2410, 4.8, '🛏️','#1f1f1f','#0A0A0A','Sprei',false,'Set sprei microfiber premium, isi 1 sprei + 1 bedcover + 2 sarung bantal.'],
            ['Vacuum Cleaner Cordless Stick 20kPa', 'rumah','v9', 1290000,1850000, 24, 412, 4.9, '🧹','#0A0A0A','#1f1f1f','Vacuum',false,'Vacuum cordless 20kPa, baterai 60 menit, HEPA filter.'],
            ['Lampu LED Bulb 12W (Pack 4)',         'rumah','v9', 79000,  120000, 340, 5420, 4.9, '💡','#374151','#0A0A0A','Lampu',false,'Lampu LED 12W putih cool daylight, hemat 80%, garansi 1 tahun, isi 4.'],
            ['Set Pisau Dapur Stainless 6pcs + Block','rumah','v9', 259000, 389000, 78, 1240, 4.8, '🔪','#0A0A0A','#374151','Pisau',false,'Set 6 pisau stainless premium + block kayu, gagang ergonomis.'],
            // Mainan
            ['Lego Set Building Blocks 500pcs',     'mainan','v11', 289000, 425000, 65, 920, 4.9, '🧱','#1f1f1f','#374151','Lego',true,'Building blocks 500pcs kompatibel Lego, edukasi anak 6+.'],
            ['Boneka Beruang Jumbo 80cm',           'mainan','v11', 189000, 289000, 48, 680, 4.9, '🧸','#374151','#1f1f1f','Boneka',false,'Boneka beruang jumbo super halus, hadiah valentine & anniversary.'],
            ['Stroller Bayi 3-in-1 Foldable',       'mainan','v11', 1290000,1850000,18, 241, 4.8, '👶','#0A0A0A','#1f1f1f','Stroller',false,'Stroller bayi 3in1 (kursi-bouncer-rocker), foldable, ringan.'],
            ['Mainan Edukasi Puzzle Kayu 100pcs',   'mainan','v11', 89000,  139000, 140, 1820, 4.9, '🧩','#1f1f1f','#0A0A0A','Puzzle',false,'Puzzle kayu edukasi 100pcs, kayu pinus, cat non toxic.'],
            ['Botol Susu Bayi Anti Kolik 250ml',    'mainan','v11', 69000,  99000,  240, 3210, 4.9, '🍼','#374151','#0A0A0A','Botol',false,'Botol susu PP food grade, dot silikon anti kolik, BPA free.'],
            // Voucher
            ['Voucher Game Mobile Legends 86 Diamond','voucher','v12', 24500, 30000,  9999, 32100,5.0, '💎','#0A0A0A','#1f1f1f','MLBB',true,'Voucher 86 diamond Mobile Legends, masuk otomatis ke akun.'],
            ['Voucher Game Free Fire 100 Diamond',  'voucher','v12', 14500,  18000,  9999, 28400,5.0, '🔥','#1f1f1f','#374151','FF',false,'Voucher 100 diamond Free Fire, instant top up via UID.'],
            ['Pulsa Telkomsel 50.000',              'voucher','v12', 51000,  52000,  9999, 42100,5.0, '📞','#374151','#0A0A0A','Tsel',false,'Pulsa Telkomsel 50.000 langsung masuk dalam hitungan menit.'],
            ['Paket Data XL 10GB / 30 Hari',        'voucher','v12', 62000,  75000,  9999, 18400,5.0, '📶','#0A0A0A','#374151','XL',false,'Paket data XL 10GB berlaku 30 hari, full kuota utama.'],
            ['Token Listrik PLN 100.000',           'voucher','v12', 101500, 102000, 9999, 24100,5.0, '⚡','#1f1f1f','#0A0A0A','PLN',false,'Token listrik PLN 100.000, 20 digit langsung dikirim setelah pembayaran.'],
        ];

        $i = 0;
        foreach ($products as $p) {
            $i++;
            [$name,$cat,$vk,$price,$orig,$stock,$sold,$rating,$em,$bg1,$bg2,$lbl,$flash,$desc] = $p;
            $product = Product::create([
                'vendor_id'      => $vmap[$vk] ?? 1,
                'category_id'    => $cat,
                'name'           => $name,
                'slug'           => Str::slug($name) . '-' . $i,
                'description'    => $desc,
                'price'          => $price,
                'original_price' => $orig,
                'stock'          => $stock,
                'sold'           => $sold,
                // Mulai dengan 0 — rating real dihitung dari ulasan pembeli
                'rating'         => 0,
                'weight'         => 500,
                'image'          => Helpers::productImage($em, $bg1, $bg2, $lbl),
                'is_active'      => true,
                'is_flash_sale'  => $flash,
            ]);

            // Seed tags from category + label
            $tagSlugs = [strtolower($cat), strtolower(Str::slug($lbl))];
            $tagIds = [];
            foreach ($tagSlugs as $slug) {
                if (!$slug) continue;
                $t = Tag::firstOrCreate(['slug' => $slug], ['name' => $slug]);
                $tagIds[] = $t->id;
            }
            $product->tagModels()->sync(array_unique($tagIds));
        }

        // Update product counts
        Tag::all()->each(function ($t) {
            $t->product_count = $t->products()->count();
            $t->save();
        });
    }
}
