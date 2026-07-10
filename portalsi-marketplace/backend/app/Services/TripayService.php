<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Tripay Payment Gateway Client
 * Docs: https://tripay.co.id/developer
 *
 * Mode behaviour:
 * - Jika TRIPAY_API_KEY + PRIVATE_KEY + MERCHANT_CODE diisi → REAL API call
 * - Jika kosong → MOCK mode (simulasi pembayaran untuk dev)
 */
class TripayService
{
    public const METHODS = [
        ['group' => 'Virtual Account',    'code' => 'BRIVA',     'name' => 'BRI Virtual Account',       'fee_flat' => 4000, 'fee_pct' => 0,    'color' => '#003D79'],
        ['group' => 'Virtual Account',    'code' => 'MANDIRIVA', 'name' => 'Mandiri Virtual Account',   'fee_flat' => 4000, 'fee_pct' => 0,    'color' => '#003D79'],
        ['group' => 'Virtual Account',    'code' => 'BNIVA',     'name' => 'BNI Virtual Account',       'fee_flat' => 4000, 'fee_pct' => 0,    'color' => '#F47C20'],
        ['group' => 'Virtual Account',    'code' => 'BCAVA',     'name' => 'BCA Virtual Account',       'fee_flat' => 5500, 'fee_pct' => 0,    'color' => '#0066B3'],
        ['group' => 'Virtual Account',    'code' => 'PERMATAVA', 'name' => 'Permata Virtual Account',   'fee_flat' => 4000, 'fee_pct' => 0,    'color' => '#00A651'],
        ['group' => 'Virtual Account',    'code' => 'BSIVA',     'name' => 'BSI Virtual Account',       'fee_flat' => 4000, 'fee_pct' => 0,    'color' => '#00633F'],
        ['group' => 'Virtual Account',    'code' => 'CIMBVA',    'name' => 'CIMB Niaga Virtual Account','fee_flat' => 4000, 'fee_pct' => 0,    'color' => '#A6192E'],
        ['group' => 'E-Wallet',           'code' => 'OVO',       'name' => 'OVO',                       'fee_flat' => 0,    'fee_pct' => 1.5,  'color' => '#4C2A86'],
        ['group' => 'E-Wallet',           'code' => 'DANA',      'name' => 'DANA',                      'fee_flat' => 0,    'fee_pct' => 1.5,  'color' => '#108EE9'],
        ['group' => 'E-Wallet',           'code' => 'SHOPEEPAY', 'name' => 'ShopeePay',                 'fee_flat' => 0,    'fee_pct' => 2.0,  'color' => '#EE4D2D'],
        ['group' => 'E-Wallet',           'code' => 'LINKAJA',   'name' => 'LinkAja',                   'fee_flat' => 0,    'fee_pct' => 1.5,  'color' => '#E40000'],
        ['group' => 'QRIS',               'code' => 'QRIS',      'name' => 'QRIS',                      'fee_flat' => 0,    'fee_pct' => 0.7,  'color' => '#000000'],
        ['group' => 'Convenience Store',  'code' => 'ALFAMART',  'name' => 'Alfamart',                  'fee_flat' => 5000, 'fee_pct' => 0,    'color' => '#E60012'],
        ['group' => 'Convenience Store',  'code' => 'INDOMARET', 'name' => 'Indomaret',                 'fee_flat' => 5000, 'fee_pct' => 0,    'color' => '#FFD700'],
        ['group' => 'Convenience Store',  'code' => 'ALFAMIDI',  'name' => 'Alfamidi',                  'fee_flat' => 5000, 'fee_pct' => 0,    'color' => '#0066CC'],
        ['group' => 'Credit Card',        'code' => 'CREDITCARD','name' => 'Kartu Kredit (Visa/MC)',    'fee_flat' => 2000, 'fee_pct' => 2.95, 'color' => '#1E40AF'],
    ];

    public function __construct(
        private ?string $apiKey      = null,
        private ?string $privateKey  = null,
        private ?string $merchantCode= null,
        private string  $mode        = 'sandbox'
    ) {
        // Prefer admin Settings (database), fallback to env
        $this->apiKey       = $apiKey       ?? \App\Models\Setting::get('tripay_api_key',       config('services.tripay.api_key'));
        $this->privateKey   = $privateKey   ?? \App\Models\Setting::get('tripay_private_key',   config('services.tripay.private_key'));
        $this->merchantCode = $merchantCode ?? \App\Models\Setting::get('tripay_merchant_code', config('services.tripay.merchant_code'));
        $this->mode         = \App\Models\Setting::get('tripay_mode', config('services.tripay.mode', 'sandbox'));
    }

    public function isMockMode(): bool
    {
        return empty($this->apiKey) || empty($this->privateKey) || empty($this->merchantCode);
    }

    public function baseUrl(): string
    {
        return $this->mode === 'production'
            ? 'https://tripay.co.id/api'
            : 'https://tripay.co.id/api-sandbox';
    }

    public function getMethodByCode(string $code): ?array
    {
        foreach (self::METHODS as $m) if ($m['code'] === $code) return $m;
        return null;
    }

    public function calcFee(array $method, int $amount): int
    {
        return (int) round($method['fee_flat'] + ($amount * $method['fee_pct'] / 100));
    }

    public function generateSignature(string $merchantRef, int $amount): string
    {
        return hash_hmac('sha256', $this->merchantCode . $merchantRef . $amount, $this->privateKey);
    }

    public function verifyCallbackSignature(string $rawBody, string $signature): bool
    {
        $expected = hash_hmac('sha256', $rawBody, $this->privateKey);
        return hash_equals($expected, $signature);
    }

    public function getInstructions(string $code): array
    {
        return [
            'BRIVA' => ['Buka aplikasi BRImo / ATM BRI / Internet Banking BRI', 'Pilih menu Pembayaran > BRIVA', 'Masukkan nomor Virtual Account', 'Cek nominal & nama merchant', 'Konfirmasi pembayaran'],
            'BCAVA' => ['Login ke myBCA / BCA Mobile / KlikBCA', 'Pilih m-Transfer > BCA Virtual Account', 'Masukkan nomor Virtual Account', 'Konfirmasi nominal', 'Masukkan PIN'],
            'MANDIRIVA' => ["Buka Livin' by Mandiri", 'Pilih Bayar > Multipayment', 'Pilih penyedia: Tripay', 'Masukkan nomor VA & nominal', 'Konfirmasi & masukkan MPIN'],
            'BNIVA' => ['Buka BNI Mobile Banking', 'Pilih Transfer > Virtual Account Billing', 'Masukkan nomor VA', 'Cek detail tagihan', 'Masukkan password transaksi'],
            'PERMATAVA' => ['Buka Permata Mobile X', 'Pilih Pembayaran > Virtual Account', 'Masukkan nomor VA', 'Konfirmasi pembayaran'],
            'BSIVA' => ['Buka BSI Mobile', 'Pilih Pembayaran > Virtual Account', 'Masukkan nomor VA', 'Konfirmasi', 'Masukkan PIN'],
            'CIMBVA' => ['Buka OCTO Mobile (CIMB)', 'Pilih Bayar > Virtual Account', 'Masukkan nomor VA', 'Konfirmasi pembayaran'],
            'QRIS' => ['Buka aplikasi e-wallet (GoPay/OVO/DANA/ShopeePay/LinkAja/m-Banking)', 'Pilih menu Bayar / Scan QR', 'Scan QR Code yang ditampilkan', 'Pastikan nominal sudah benar', 'Konfirmasi pembayaran'],
            'OVO' => ['Anda akan diarahkan ke aplikasi OVO', 'Konfirmasi pembayaran', 'Masukkan PIN OVO', 'Pembayaran selesai'],
            'DANA' => ['Anda akan diarahkan ke halaman DANA', 'Konfirmasi pembayaran', 'Masukkan PIN DANA', 'Pembayaran berhasil'],
            'SHOPEEPAY' => ['Buka aplikasi Shopee / ShopeePay', 'Konfirmasi pembayaran', 'Masukkan PIN ShopeePay', 'Selesai'],
            'LINKAJA' => ['Buka aplikasi LinkAja', 'Pilih menu pembayaran', 'Masukkan kode pembayaran', 'Konfirmasi nominal & PIN'],
            'ALFAMART' => ['Datang ke Alfamart / Alfamidi / Lawson terdekat', 'Sebutkan ke kasir: bayar Tripay', 'Berikan kode pembayaran', 'Lakukan pembayaran tunai', 'Simpan struk'],
            'INDOMARET' => ['Datang ke Indomaret terdekat', 'Sebutkan ke kasir: bayar Tripay', 'Berikan kode pembayaran', 'Bayar sesuai nominal', 'Simpan struk'],
            'ALFAMIDI' => ['Kunjungi Alfamidi terdekat', 'Sampaikan ke kasir bayar Tripay', 'Tunjukkan kode pembayaran', 'Bayar tunai', 'Simpan struk'],
            'CREDITCARD' => ['Anda akan diarahkan ke halaman pembayaran kartu kredit', 'Masukkan nomor kartu, expiry, CVV', 'Verifikasi 3D Secure / OTP', 'Pembayaran selesai diproses'],
        ][$code] ?? ['Ikuti instruksi di halaman pembayaran.'];
    }

    public function createTransaction(array $params): array
    {
        $method = $this->getMethodByCode($params['method']);
        if (!$method) throw new RuntimeException('Metode pembayaran tidak valid');

        $amount      = (int) $params['amount'];
        $merchantRef = $params['merchant_ref'];
        $fee         = $this->calcFee($method, $amount);
        $expiredAt   = $params['expired_time'] ?? (time() + 24 * 3600);
        $instructions= $this->getInstructions($params['method']);

        if ($this->isMockMode()) {
            return [
                'reference'    => 'DEV-T' . strtoupper(base_convert(time(), 10, 36)) . substr(md5($merchantRef), 0, 6),
                'merchant_ref' => $merchantRef,
                'method'       => $params['method'],
                'method_name'  => $method['name'],
                'pay_code'     => $method['group'] === 'Virtual Account' ? $this->mockVa($params['method'])
                                : ($method['group'] === 'Convenience Store' ? $this->mockPaymentCode($params['method']) : null),
                'pay_url'      => in_array($method['group'], ['E-Wallet','Credit Card'])
                                ? config('app.frontend_url', config('app.url')) . '/orders' : null,
                'qr_string'    => $params['method'] === 'QRIS' ? "00020101021226...DEMO-{$merchantRef}" : null,
                'amount'       => $amount,
                'fee'          => $fee,
                'total'        => $amount + $fee,
                'status'       => 'UNPAID',
                'expired_at'   => $expiredAt,
                'instructions' => $instructions,
                'raw'          => ['mock' => true, 'message' => 'Tripay mock mode (set TRIPAY_API_KEY in .env)'],
            ];
        }

        $signature = $this->generateSignature($merchantRef, $amount);
        $body = [
            'method'         => $params['method'],
            'merchant_ref'   => $merchantRef,
            'amount'         => $amount,
            'customer_name'  => $params['customer_name'],
            'customer_email' => $params['customer_email'],
            'customer_phone' => $params['customer_phone'],
            'order_items'    => $params['order_items'],
            'callback_url'   => $params['callback_url'] ?? null,
            'return_url'     => $params['return_url'] ?? null,
            'expired_time'   => $expiredAt,
            'signature'      => $signature,
        ];

        try {
            $resp = Http::withToken($this->apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post($this->baseUrl() . '/transaction/create', $body);
        } catch (\Throwable $e) {
            Log::warning('Tripay connection failed: ' . $e->getMessage());
            throw new RuntimeException('Tripay tidak bisa dihubungi. Periksa mode, API key, private key, merchant code, dan koneksi server.');
        }

        $json = $resp->json();
        if (!is_array($json)) {
            Log::warning('Tripay returned non JSON response', ['status' => $resp->status(), 'body' => $resp->body()]);
            throw new RuntimeException('Tripay mengembalikan respons tidak valid.');
        }
        if (!($json['success'] ?? false)) {
            Log::warning('Tripay transaction rejected', ['status' => $resp->status(), 'body' => $json]);
            throw new RuntimeException($json['message'] ?? 'Tripay error');
        }
        $d = $json['data'];
        return [
            'reference'    => $d['reference'] ?? null,
            'merchant_ref' => $d['merchant_ref'] ?? $merchantRef,
            'method'       => $d['payment_method'] ?? $params['method'],
            'method_name'  => $d['payment_name'] ?? $method['name'],
            'pay_code'     => $d['pay_code'] ?? null,
            'pay_url'      => $d['pay_url'] ?? $d['checkout_url'] ?? null,
            'qr_string'    => $d['qr_string'] ?? null,
            'amount'       => $d['amount'] ?? $amount,
            'fee'          => $d['total_fee'] ?? $fee,
            'total'        => ($d['amount'] ?? $amount) + ($d['total_fee'] ?? $fee),
            'status'       => $d['status'] ?? 'UNPAID',
            'expired_at'   => $d['expired_time'] ?? $expiredAt,
            'instructions' => $instructions,
            'raw'          => $json,
        ];
    }

    public function getTransactionDetail(string $reference): array
    {
        if ($this->isMockMode()) return ['success' => false, 'message' => 'Mock mode'];
        $resp = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout(30)
            ->get($this->baseUrl() . '/transaction/detail', ['reference' => $reference]);
        return $resp->json();
    }

    private function mockVa(string $method): string
    {
        $prefix = ['BRIVA'=>'70011','MANDIRIVA'=>'89009','BNIVA'=>'88800','BCAVA'=>'10707',
                   'PERMATAVA'=>'70077','BSIVA'=>'90022','CIMBVA'=>'82277'][$method] ?? '99999';
        $body = '';
        for ($i = 0; $i < 11; $i++) $body .= mt_rand(0, 9);
        return $prefix . $body;
    }

    private function mockPaymentCode(string $method): string
    {
        $prefix = $method === 'ALFAMART' ? '88' : ($method === 'INDOMARET' ? '99' : '77');
        $body = '';
        for ($i = 0; $i < 12; $i++) $body .= mt_rand(0, 9);
        return $prefix . $body;
    }
}
