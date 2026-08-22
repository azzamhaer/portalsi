<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien tipis HestiaCP REST API (localhost) — khusus perintah mail.
 * Untuk Sekolah Impian Mail: user Hestia = sekim, domain = sekolahimpian.com.
 */
class HestiaMailClient
{
    public function __construct(
        private string $url,
        private string $accessKey,
        private string $secretKey,
        private string $user,
        private string $domain,
        private int $timeout = 15,
    ) {}

    public static function make(): self
    {
        $c = config('services.hestia');

        return new self(
            (string) ($c['url'] ?? 'https://127.0.0.1:8083/api/'),
            (string) ($c['access_key'] ?? ''),
            (string) ($c['secret_key'] ?? ''),
            (string) ($c['user'] ?? 'sekim'),
            (string) ($c['domain'] ?? 'sekolahimpian.com'),
            (int) ($c['timeout'] ?? 15),
        );
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function isConfigured(): bool
    {
        return $this->accessKey !== '' && $this->secretKey !== '';
    }

    private function send(string $cmd, array $args = [], bool $returncode = false): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('HestiaCP API belum dikonfigurasi (HESTIA_ACCESS_KEY/SECRET kosong).');
        }

        $payload = [
            'access_key' => $this->accessKey,
            'secret_key' => $this->secretKey,
            'cmd' => $cmd,
        ];
        $i = 1;
        foreach ($args as $arg) {
            $payload["arg{$i}"] = (string) $arg;
            $i++;
        }
        if ($returncode) {
            $payload['returncode'] = 'yes';
        }

        $res = Http::withOptions(['verify' => false])
            ->timeout($this->timeout)
            ->withBody(json_encode($payload), 'application/json')
            ->post($this->url);

        return trim((string) $res->body());
    }

    private function run(string $cmd, array $args): void
    {
        $body = $this->send($cmd, $args, true);
        if ($body !== '0') {
            throw new RuntimeException("HestiaCP {$cmd} gagal: " . ($body === '' ? 'kode tidak diketahui' : $body));
        }
    }

    /** @return array<string,array<string,mixed>> */
    public function listAccounts(): array
    {
        $body = $this->send('v-list-mail-accounts', [$this->user, $this->domain, 'json']);
        $data = json_decode($body, true);

        return is_array($data) ? $data : [];
    }

    public function accountExists(string $localPart): bool
    {
        return array_key_exists(strtolower($localPart), $this->listAccounts());
    }

    public function createAccount(string $localPart, string $password): void
    {
        $this->run('v-add-mail-account', [$this->user, $this->domain, strtolower($localPart), $password]);
    }

    public function deleteAccount(string $localPart): void
    {
        $this->run('v-delete-mail-account', [$this->user, $this->domain, strtolower($localPart)]);
    }

    public function changePassword(string $localPart, string $password): void
    {
        $this->run('v-change-mail-account-password', [$this->user, $this->domain, strtolower($localPart), $password]);
    }

    public static function generatePassword(int $length = 24): string
    {
        $sets = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnopqrstuvwxyz',
            '23456789',
            '@#%^*_-+=',
        ];
        $all = implode('', $sets);
        $chars = [];
        foreach ($sets as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }
        for ($i = count($chars); $i < $length; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($chars);

        return implode('', $chars);
    }
}
