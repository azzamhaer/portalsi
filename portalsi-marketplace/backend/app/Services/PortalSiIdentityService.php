<?php

namespace App\Services;

use App\Exceptions\PortalSiIdentityException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class PortalSiIdentityService
{
    private string $baseUrl;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.portal_si.api_url'), '/');
        $this->timeout = (int) config('services.portal_si.timeout', 12);
    }

    public function login(string $login, string $password, ?Request $request = null): array
    {
        return $this->send('post', '/login', [
            'login' => $login,
            'password' => $password,
        ], request: $request);
    }

    public function register(array $data): array
    {
        return $this->send('post', '/register', [
            'username' => $data['username'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'student',
        ]);
    }

    public function user(string $token): array
    {
        return $this->send('get', '/user', token: $token);
    }

    public function logout(?string $token): void
    {
        if (! $token) {
            return;
        }

        try {
            $this->send('post', '/logout', token: $token);
        } catch (PortalSiIdentityException) {
            // Marketplace logout must still clear the local token.
        }
    }

    public function resendVerification(string $token): array
    {
        return $this->send('post', '/email/verification-notification', token: $token);
    }

    public function forgotPassword(string $email): array
    {
        return $this->send('post', '/forgot-password', ['email' => $email]);
    }

    public function resetPassword(string $email, string $token, string $password): array
    {
        return $this->send('post', '/reset-password', [
            'email' => $email,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function updateProfile(string $token, array $data): array
    {
        return $this->send('post', '/account/settings', array_filter([
            'full_name' => $data['name'] ?? null,
        ], fn ($value) => $value !== null), token: $token);
    }

    public function changePassword(string $token, string $currentPassword, string $newPassword): array
    {
        return $this->send('put', '/account/password', [
            'current_password' => $currentPassword,
            'new_password' => $newPassword,
            'new_password_confirmation' => $newPassword,
        ], token: $token);
    }

    public function requestEmailChange(string $token, string $email): array
    {
        return $this->send('post', '/account/email/change', [
            'email' => $email,
        ], token: $token);
    }

    private function send(
        string $method,
        string $path,
        array $payload = [],
        ?string $token = null,
        ?Request $request = null,
    ): array {
        $client = Http::acceptJson()
            ->asJson()
            ->timeout($this->timeout);

        if ($token) {
            $client = $client->withToken($token);
        }

        if ($request) {
            $client = $client->withHeaders([
                'X-Real-Client-Ua' => (string) $request->userAgent(),
                'X-Real-Client-Ip' => (string) $request->ip(),
            ]);
        }

        $url = $this->baseUrl.$path;
        try {
            $response = match (strtolower($method)) {
                'get' => $client->get($url, $payload),
                'put' => $client->put($url, $payload),
                'delete' => $client->delete($url, $payload),
                default => $client->post($url, $payload),
            };
        } catch (Throwable) {
            throw new PortalSiIdentityException('Portal SI tidak dapat dihubungi. Coba lagi beberapa saat.', 503);
        }

        if ($response->failed()) {
            $this->throwPortalError($response);
        }

        return $response->json() ?? [];
    }

    private function throwPortalError(Response $response): never
    {
        $data = $response->json();
        $message = is_array($data)
            ? (string) ($data['message'] ?? $data['error'] ?? 'Portal SI menolak request ini.')
            : 'Portal SI tidak dapat dihubungi.';

        $errors = is_array($data) && isset($data['errors']) && is_array($data['errors'])
            ? $data['errors']
            : [];

        throw new PortalSiIdentityException($message, $response->status(), $errors);
    }
}
