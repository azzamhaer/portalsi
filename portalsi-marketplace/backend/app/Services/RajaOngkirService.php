<?php

namespace App\Services;

use App\Models\Setting;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RajaOngkirService
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            // Timeout dikecilkan agar request tidak menggantung lama saat upstream RajaOngkir lambat/mati.
            'timeout'         => 6,
            'connect_timeout' => 3,
            'http_errors'     => false,
        ]);
    }

    /**
     * Cek apakah integrasi RajaOngkir BENAR-BENAR diaktifkan oleh admin.
     * Wajib API key terisi DAN toggle enabled = true di Settings.
     */
    public function isConfigured(): bool
    {
        return $this->enabled() && trim((string) $this->apiKey()) !== '';
    }

    /**
     * Toggle on/off integrasi RajaOngkir.
     * Default OFF — kalau admin belum eksplisit menyalakan, integrasi diabaikan.
     */
    public function enabled(): bool
    {
        // Setting bisa boolean atau string '1'/'0'/'true'/'false'
        $raw = Setting::get('rajaongkir_enabled', config('services.rajaongkir.enabled', false));
        if (is_bool($raw)) return $raw;
        $val = strtolower((string) $raw);
        return in_array($val, ['1', 'true', 'on', 'yes', 'aktif'], true);
    }

    public function mode(): string
    {
        return Setting::get('rajaongkir_mode', config('services.rajaongkir.mode', 'sandbox')) ?: 'sandbox';
    }

    public function apiKey(): ?string
    {
        return Setting::get('rajaongkir_api_key', config('services.rajaongkir.api_key'));
    }

    public function tariffBaseUrl(): string
    {
        return rtrim((string) Setting::get(
            'rajaongkir_tariff_base_url',
            config('services.rajaongkir.tariff_base_url', $this->mode() === 'production'
                ? 'https://api.collaborator.komerce.id/tariff/api/v1'
                : 'https://api-sandbox.collaborator.komerce.id/tariff/api/v1')
        ), '/');
    }

    public function orderBaseUrl(): string
    {
        return rtrim((string) Setting::get(
            'rajaongkir_order_base_url',
            config('services.rajaongkir.order_base_url', $this->mode() === 'production'
                ? 'https://api.collaborator.komerce.id/order/api/v1'
                : 'https://api-sandbox.collaborator.komerce.id/order/api/v1')
        ), '/');
    }

    public function searchDestination(string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') return [];
        if (!$this->isConfigured()) return [];

        $json = $this->request('GET', $this->tariffBaseUrl() . '/destination/search', [
            'query' => ['keyword' => $keyword],
        ]);

        return array_values(array_filter($json['data'] ?? [], fn($row) => !empty($row['id'])));
    }

    public function resolveDestinationId(array|object|null $address): ?int
    {
        if (!$address) return null;
        $value = fn(string $key) => is_array($address) ? ($address[$key] ?? null) : ($address->{$key} ?? null);

        $existing = $value('rajaongkir_destination_id');
        if ($existing) return (int) $existing;

        $parts = array_filter([
            $value('postal_code'),
            $value('village'),
            $value('district'),
            $value('city'),
        ]);
        $results = $this->searchDestination(implode(' ', $parts));
        return !empty($results[0]['id']) ? (int) $results[0]['id'] : null;
    }

    public function calculate(array $params): array
    {
        if (!$this->isConfigured()) {
            return ['configured' => false, 'options' => $this->fallbackRates($params)];
        }

        $query = [
            'shipper_destination_id' => (int) $params['shipper_destination_id'],
            'receiver_destination_id' => (int) $params['receiver_destination_id'],
            'weight' => max(0.1, round(((int) $params['weight_gram']) / 1000, 2)),
            'item_value' => (int) $params['item_value'],
            'cod' => $params['cod'] ?? 'no',
        ];
        if (!empty($params['origin_pin_point'])) $query['origin_pin_point'] = $params['origin_pin_point'];
        if (!empty($params['destination_pin_point'])) $query['destination_pin_point'] = $params['destination_pin_point'];

        $json = $this->request('GET', $this->tariffBaseUrl() . '/calculate', ['query' => $query]);
        return [
            'configured' => true,
            'raw' => $json,
            'options' => $this->normalizeCalculateResponse($json),
        ];
    }

    public function createOrder(array $payload): array
    {
        if (!$this->isConfigured()) {
            return ['configured' => false, 'raw' => null];
        }

        $json = $this->request('POST', $this->orderBaseUrl() . '/orders/store', [
            'json' => $payload,
        ]);

        return ['configured' => true, 'raw' => $json, 'data' => $json['data'] ?? null];
    }

    private function request(string $method, string $url, array $options = []): array
    {
        $options['headers'] = array_merge([
            'Accept' => 'application/json',
            'x-api-key' => (string) $this->apiKey(),
        ], $options['headers'] ?? []);

        try {
            $res = $this->http->request($method, $url, $options);
        } catch (\Throwable $e) {
            Log::warning('RajaOngkir connection failed', ['url' => $url, 'message' => $e->getMessage()]);
            throw new RuntimeException('RajaOngkir tidak bisa dihubungi. Periksa API key, mode, dan whitelist IP server.');
        }
        $body = (string) $res->getBody();
        $json = json_decode($body, true);
        if ($res->getStatusCode() >= 400 || !is_array($json)) {
            Log::warning('RajaOngkir API failed', ['status' => $res->getStatusCode(), 'body' => $body]);
            throw new RuntimeException('RajaOngkir tidak merespons dengan benar. Periksa API key, mode, dan whitelist IP server.');
        }
        if (($json['meta']['status'] ?? null) === false || ($json['meta']['status'] ?? null) === 'error') {
            throw new RuntimeException($json['meta']['message'] ?? 'Request RajaOngkir gagal');
        }
        return $json;
    }

    private function normalizeCalculateResponse(array $json): array
    {
        $data = $json['data'] ?? [];
        $groups = [
            'calculate_reguler' => 'REGULAR',
            'calculate_cargo' => 'CARGO',
            'calculate_instant' => 'INSTANT',
        ];

        $options = [];
        foreach ($groups as $key => $kind) {
            foreach (($data[$key] ?? []) as $row) {
                $cost = (int) (Arr::get($row, 'shipping_cost') ?? Arr::get($row, 'shipping_price') ?? Arr::get($row, 'price') ?? 0);
                if ($cost < 0) continue;
                $name = (string) (Arr::get($row, 'shipping_name') ?? Arr::get($row, 'courier_name') ?? Arr::get($row, 'name') ?? 'Kurir');
                $service = (string) (Arr::get($row, 'service_name') ?? Arr::get($row, 'shipping_type') ?? Arr::get($row, 'service') ?? $kind);
                $options[] = [
                    'courier_code' => strtolower((string) (Arr::get($row, 'shipping_code') ?? Arr::get($row, 'courier_code') ?? $name)),
                    'name' => trim($name . ' ' . $service),
                    'courier_name' => $name,
                    'service' => $service,
                    'type' => $kind,
                    'eta' => (string) (Arr::get($row, 'etd') ?? Arr::get($row, 'estimate') ?? Arr::get($row, 'shipping_etd') ?? '-'),
                    'cost' => $cost,
                    'cashback' => (int) (Arr::get($row, 'shipping_cashback') ?? 0),
                    'service_fee' => (int) (Arr::get($row, 'service_fee') ?? 0),
                    'raw' => $row,
                ];
            }
        }

        return $options;
    }

    private function fallbackRates(array $params): array
    {
        $kg = max(1, (int) ceil(((int) ($params['weight_gram'] ?? 1000)) / 1000));
        $base = 9000 + (($kg - 1) * 4500);
        return [
            ['courier_code' => 'jne', 'name' => 'JNE Reguler', 'courier_name' => 'JNE', 'service' => 'REG', 'type' => 'REGULAR', 'eta' => '2-4 hari', 'cost' => $base + 3000, 'cashback' => 0, 'service_fee' => 0, 'raw' => null],
            ['courier_code' => 'sicepat', 'name' => 'SiCepat REG', 'courier_name' => 'SiCepat', 'service' => 'REG', 'type' => 'REGULAR', 'eta' => '2-4 hari', 'cost' => $base + 1000, 'cashback' => 0, 'service_fee' => 0, 'raw' => null],
            ['courier_code' => 'jnt', 'name' => 'J&T Express', 'courier_name' => 'J&T', 'service' => 'EZ', 'type' => 'REGULAR', 'eta' => '2-3 hari', 'cost' => $base + 4000, 'cashback' => 0, 'service_fee' => 0, 'raw' => null],
        ];
    }
}
