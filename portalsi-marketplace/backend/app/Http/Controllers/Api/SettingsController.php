<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /** Settings publik (untuk siapa saja, dipakai branding frontend) */
    public function publicSettings()
    {
        return response()->json([
            'appName'   => Setting::get('app_name', ''),
            'logo'      => Setting::get('logo_url', ''),
            'palette'   => Setting::get('palette', 'mono'),
            'primary'   => Setting::get('primary_color', '#0a0a0a'),
            'primaryFg' => Setting::get('primary_fg', '#ffffff'),
            'accent'    => Setting::get('accent_color', '#6366f1'),
            'tagline'   => Setting::get('tagline', 'Marketplace untuk semua'),
            // Custom page content
            'heroTitle'     => Setting::get('hero_title', 'Belanja yang membuat hidup lebih mudah.'),
            'heroSubtitle'  => Setting::get('hero_subtitle', 'Ribuan produk pilihan dari toko terverifikasi. Pembayaran aman, pengiriman cepat.'),
            'heroCtaLabel'  => Setting::get('hero_cta_label', 'Mulai belanja'),
            'heroCtaHref'   => Setting::get('hero_cta_href', '/products'),
            'heroImage'     => Setting::get('hero_image', ''),
            'heroEnabled'   => filter_var(Setting::get('hero_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
            'paymentIntro'  => Setting::get('payment_intro', ''),
            'helpIntro'     => Setting::get('help_intro', ''),
            'footerColumns' => json_decode(Setting::get('footer_columns', '[]'), true) ?: [],
            'footerBottom'  => Setting::get('footer_bottom', ''),
            'footerContact' => Setting::get('footer_contact', ''),
            'footerDesc'    => Setting::get('footer_desc', ''),
            'hiddenPages'   => json_decode(Setting::get('hidden_pages', '[]'), true) ?: [],
            'seoTitle'       => Setting::get('seo_title', Setting::get('app_name', 'MPSI')),
            'seoDescription' => Setting::get('seo_description', 'Marketplace MPSI untuk belanja produk lokal, elektronik, kebutuhan harian, dan toko terpercaya dengan pembayaran aman.'),
            'seoImage'       => Setting::get('seo_image', ''),
            'seoHomeTitle'   => Setting::get('seo_home_title', ''),
            'seoHomeDescription' => Setting::get('seo_home_description', ''),
            'seoHomeImage'   => Setting::get('seo_home_image', ''),
            'seoProductsTitle' => Setting::get('seo_products_title', ''),
            'seoProductsDescription' => Setting::get('seo_products_description', ''),
            'seoProductsImage' => Setting::get('seo_products_image', ''),
        ]);
    }

    /** Settings admin (lengkap dengan tripay env) */
    public function adminSettings()
    {
        return response()->json([
            'app_name'      => Setting::get('app_name', 'MPSI'),
            'logo_url'      => Setting::get('logo_url', ''),
            'palette'       => Setting::get('palette', 'mono'),
            'primary_color' => Setting::get('primary_color', '#0a0a0a'),
            'primary_fg'    => Setting::get('primary_fg', '#ffffff'),
            'accent_color'  => Setting::get('accent_color', '#6366f1'),
            'tagline'       => Setting::get('tagline', 'Marketplace untuk semua'),
            'tripay_mode'   => Setting::get('tripay_mode', config('services.tripay.mode')),
            'tripay_api_key'=> Setting::get('tripay_api_key', ''),
            'tripay_private_key' => Setting::get('tripay_private_key', ''),
            'tripay_merchant_code' => Setting::get('tripay_merchant_code', ''),
            // Default OFF — supaya install baru tidak otomatis hit RajaOngkir API saat seller ship order.
            'rajaongkir_enabled' => filter_var(Setting::get('rajaongkir_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
            'rajaongkir_mode' => Setting::get('rajaongkir_mode', config('services.rajaongkir.mode', 'sandbox')),
            'rajaongkir_api_key' => Setting::get('rajaongkir_api_key', ''),
            'rajaongkir_tariff_base_url' => Setting::get('rajaongkir_tariff_base_url', ''),
            'rajaongkir_order_base_url' => Setting::get('rajaongkir_order_base_url', ''),
            'commission_percent' => (float) Setting::get('commission_percent', 5),
            // Brevo (email)
            'brevo_api_key'      => Setting::get('brevo_api_key', ''),
            'brevo_sender_email' => Setting::get('brevo_sender_email', ''),
            'brevo_sender_name'  => Setting::get('brevo_sender_name', ''),
            // Custom page content
            'hero_title'    => Setting::get('hero_title', 'Belanja yang membuat hidup lebih mudah.'),
            'hero_subtitle' => Setting::get('hero_subtitle', 'Ribuan produk pilihan dari toko terverifikasi. Pembayaran aman, pengiriman cepat.'),
            'hero_cta_label'=> Setting::get('hero_cta_label', 'Mulai belanja'),
            'hero_cta_href' => Setting::get('hero_cta_href', '/products'),
            'hero_image'    => Setting::get('hero_image', ''),
            'hero_enabled'  => filter_var(Setting::get('hero_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
            'payment_intro' => Setting::get('payment_intro', ''),
            'help_intro'    => Setting::get('help_intro', ''),
            'footer_columns'=> json_decode(Setting::get('footer_columns', '[]'), true) ?: [],
            'footer_bottom' => Setting::get('footer_bottom', ''),
            'footer_contact'=> Setting::get('footer_contact', ''),
            'footer_desc'   => Setting::get('footer_desc', ''),
            'hidden_pages'  => json_decode(Setting::get('hidden_pages', '[]'), true) ?: [],
            'seo_title'       => Setting::get('seo_title', Setting::get('app_name', 'MPSI')),
            'seo_description' => Setting::get('seo_description', 'Marketplace MPSI untuk belanja produk lokal, elektronik, kebutuhan harian, dan toko terpercaya dengan pembayaran aman.'),
            'seo_image'       => Setting::get('seo_image', ''),
            'seo_home_title'  => Setting::get('seo_home_title', ''),
            'seo_home_description' => Setting::get('seo_home_description', ''),
            'seo_home_image'  => Setting::get('seo_home_image', ''),
            'seo_products_title' => Setting::get('seo_products_title', ''),
            'seo_products_description' => Setting::get('seo_products_description', ''),
            'seo_products_image' => Setting::get('seo_products_image', ''),
            'palettes' => self::PALETTES,
        ]);
    }

    public const PALETTES = [
        ['key'=>'mono',    'name'=>'Mono Black',     'primary'=>'#0a0a0a', 'primaryFg'=>'#ffffff', 'accent'=>'#6366f1'],
        ['key'=>'indigo',  'name'=>'Royal Indigo',   'primary'=>'#4f46e5', 'primaryFg'=>'#ffffff', 'accent'=>'#f59e0b'],
        ['key'=>'forest',  'name'=>'Forest Green',   'primary'=>'#166534', 'primaryFg'=>'#ffffff', 'accent'=>'#eab308'],
        ['key'=>'sunset',  'name'=>'Warm Sunset',    'primary'=>'#ea580c', 'primaryFg'=>'#ffffff', 'accent'=>'#0891b2'],
        ['key'=>'rose',    'name'=>'Rose Romance',   'primary'=>'#be185d', 'primaryFg'=>'#ffffff', 'accent'=>'#0ea5e9'],
        ['key'=>'midnight','name'=>'Midnight Blue',  'primary'=>'#1e3a8a', 'primaryFg'=>'#ffffff', 'accent'=>'#22d3ee'],
    ];

    public function adminSave(Request $request)
    {
        $data = $request->validate([
            'app_name'      => 'sometimes|string|max:50',
            'palette'       => 'sometimes|string',
            'primary_color' => 'sometimes|string',
            'primary_fg'    => 'sometimes|string',
            'accent_color'  => 'sometimes|string',
            'tagline'       => 'sometimes|nullable|string|max:200',
            'tripay_mode'   => 'sometimes|in:sandbox,production',
            'tripay_api_key'=> 'sometimes|string',
            'tripay_private_key' => 'sometimes|string',
            'tripay_merchant_code' => 'sometimes|string',
            'rajaongkir_enabled' => 'sometimes|boolean',
            'rajaongkir_mode' => 'sometimes|in:sandbox,production',
            'rajaongkir_api_key' => 'sometimes|nullable|string',
            'rajaongkir_tariff_base_url' => 'sometimes|nullable|string|max:255',
            'rajaongkir_order_base_url' => 'sometimes|nullable|string|max:255',
            'commission_percent'   => 'sometimes|numeric|min:0|max:50',
            'brevo_api_key'        => 'sometimes|nullable|string',
            'brevo_sender_email'   => 'sometimes|nullable|email',
            'brevo_sender_name'    => 'sometimes|nullable|string|max:100',
            'hero_title'    => 'sometimes|string|max:200',
            'hero_subtitle' => 'sometimes|string|max:500',
            'hero_cta_label'=> 'sometimes|string|max:50',
            'hero_cta_href' => 'sometimes|string|max:200',
            'hero_image'    => 'sometimes|nullable|string',
            'hero_enabled'  => 'sometimes|boolean',
            'payment_intro' => 'sometimes|nullable|string|max:5000',
            'help_intro'    => 'sometimes|nullable|string|max:5000',
            'footer_columns'=> 'sometimes|nullable|array',
            'footer_columns.*.title' => 'required|string|max:50',
            'footer_columns.*.links' => 'required|array',
            'footer_columns.*.links.*.label' => 'required|string|max:50',
            'footer_columns.*.links.*.href'  => 'required|string|max:200',
            'footer_bottom' => 'sometimes|nullable|string|max:300',
            'footer_contact'=> 'sometimes|nullable|string|max:200',
            'footer_desc'   => 'sometimes|nullable|string|max:500',
            'hidden_pages'  => 'sometimes|nullable|array',
            'hidden_pages.*'=> 'string|in:payment-info,help,about,vendors',
            'seo_title'       => 'sometimes|nullable|string|max:120',
            'seo_description' => 'sometimes|nullable|string|max:300',
            'seo_image'       => 'sometimes|nullable|string|max:1000',
            'seo_home_title'  => 'sometimes|nullable|string|max:120',
            'seo_home_description' => 'sometimes|nullable|string|max:300',
            'seo_home_image'  => 'sometimes|nullable|string|max:1000',
            'seo_products_title' => 'sometimes|nullable|string|max:120',
            'seo_products_description' => 'sometimes|nullable|string|max:300',
            'seo_products_image' => 'sometimes|nullable|string|max:1000',
        ]);
        // Convert arrays to JSON for storage
        if (isset($data['footer_columns']))  $data['footer_columns']  = json_encode($data['footer_columns']);
        if (isset($data['hidden_pages']))    $data['hidden_pages']    = json_encode($data['hidden_pages']);

        // Apply preset palette if provided
        if (!empty($data['palette'])) {
            foreach (self::PALETTES as $p) {
                if ($p['key'] === $data['palette']) {
                    $data['primary_color'] = $p['primary'];
                    $data['primary_fg']    = $p['primaryFg'];
                    $data['accent_color']  = $p['accent'];
                    break;
                }
            }
        }
        foreach ($data as $k => $v) Setting::put($k, $v);
        return response()->json(['ok' => true]);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => 'required|file|mimes:png,jpg,jpeg,webp|max:1024']);
        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $path = $file->storeAs('branding', 'app-logo-' . time() . '.' . $ext, 'public');
        $url = Storage::disk('public')->url($path);
        if (!str_starts_with($url, 'http')) {
            $url = rtrim(config('app.url'), '/') . $url;
        }
        Setting::put('logo_url', $url);
        return response()->json([
            'logo_url' => $url,
            'email_logo_url' => rtrim(config('app.url'), '/') . '/api/settings/email-logo.png?v=' . time(),
        ]);
    }

    public function emailLogo()
    {
        $logo = trim((string) Setting::get('logo_url', ''));

        if (preg_match('#^data:(image/(png|jpe?g|gif|webp));base64,(.+)$#i', $logo, $m)) {
            $bytes = base64_decode($m[3], true);
            if ($bytes !== false) {
                return response($bytes, 200)
                    ->header('Content-Type', strtolower($m[1]) === 'image/jpg' ? 'image/jpeg' : $m[1])
                    ->header('Cache-Control', 'public, max-age=86400');
            }
        }

        if ($this->isRasterLogoUrl($logo)) {
            if (str_contains($logo, '/storage/')) {
                $relative = ltrim(parse_url($logo, PHP_URL_PATH) ?: '', '/');
                $relative = preg_replace('#^storage/#', '', $relative);
                if ($relative && Storage::disk('public')->exists($relative)) {
                    $mime = Storage::disk('public')->mimeType($relative) ?: 'image/png';
                    return response(Storage::disk('public')->get($relative), 200)
                        ->header('Content-Type', $mime)
                        ->header('Cache-Control', 'public, max-age=86400');
                }
            }
            return redirect()->away($logo, 302);
        }

        return $this->generatedEmailLogo();
    }

    private function isRasterLogoUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, 'data:')) return false;
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?: $url);
        return (bool) preg_match('/\.(png|jpe?g|gif|webp)$/', $path);
    }

    private function generatedEmailLogo()
    {
        $appName = Setting::get('app_name', 'MPSI');
        $letter = strtoupper(mb_substr(trim($appName), 0, 1) ?: 'M');
        $primary = Setting::get('primary_color', '#0a0a0a');
        $fg = Setting::get('primary_fg', '#ffffff');

        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(144, 144);
            imagealphablending($img, true);
            imagesavealpha($img, true);

            [$r, $g, $b] = $this->hexToRgb($primary);
            [$fr, $fgc, $fb] = $this->hexToRgb($fg);
            $bg = imagecolorallocate($img, $r, $g, $b);
            $text = imagecolorallocate($img, $fr, $fgc, $fb);
            imagefilledrectangle($img, 0, 0, 144, 144, $bg);

            $font = 5;
            $tw = imagefontwidth($font) * strlen($letter);
            $th = imagefontheight($font);
            imagestring($img, $font, (int) ((144 - $tw) / 2), (int) ((144 - $th) / 2), $letter, $text);

            ob_start();
            imagepng($img);
            $png = ob_get_clean();
            imagedestroy($img);

            return response($png, 200)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'public, max-age=86400');
        }

        [$r, $g, $b] = $this->hexToRgb($primary);
        return response($this->solidPng(144, 144, $r, $g, $b), 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function solidPng(int $width, int $height, int $r, int $g, int $b): string
    {
        $pixel = chr($r) . chr($g) . chr($b) . chr(255);
        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\0" . str_repeat($pixel, $width);
        }

        return "\x89PNG\r\n\x1a\n"
            . $this->pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0))
            . $this->pngChunk('IDAT', gzcompress($raw, 9))
            . $this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) return [10, 10, 10];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    public function uploadHero(Request $request)
    {
        $request->validate(['hero' => 'required|file|image|max:4096']);
        $file = $request->file('hero');
        $contents = base64_encode(file_get_contents($file->getRealPath()));
        $mime = $file->getMimeType();
        $dataUri = "data:{$mime};base64,{$contents}";
        Setting::put('hero_image', $dataUri);
        return response()->json(['hero_image' => $dataUri]);
    }

    public function uploadSeoImage(Request $request)
    {
        $request->validate(['seo_image' => 'required|file|image|max:4096']);
        $file = $request->file('seo_image');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $path = $file->storeAs('branding', 'seo-share-' . time() . '.' . $ext, 'public');
        $url = Storage::disk('public')->url($path);
        if (!str_starts_with($url, 'http')) {
            $url = rtrim(config('app.url'), '/') . $url;
        }
        Setting::put('seo_image', $url);
        return response()->json(['seo_image' => $url]);
    }
}
