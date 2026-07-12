<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PortalSiIdentityException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PortalSiIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private readonly PortalSiIdentityService $portalSi) {}

    public function register(Request $request)
    {
        $request->merge([
            'full_name' => $request->input('full_name', $request->input('name')),
        ]);

        $data = $request->validate([
            'username' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._]+$/'],
            'full_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'password' => ['required', Password::min(6)],
        ], [
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, dan underscore.',
        ]);

        try {
            $portal = $this->portalSi->register($data);
            $user = $this->syncPortalUser($this->extractPortalUser($portal), $portal['token'] ?? null, $data['phone'] ?? null);
        } catch (PortalSiIdentityException $e) {
            return $this->portalError($e);
        }

        return response()->json([
            'user' => $this->userResource($user->load('vendor')),
            'token' => $user->createToken('marketplace')->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'required_without:email|string',
            'email' => 'required_without:login|string',
            'password' => 'required|string',
        ]);

        try {
            $portal = $this->portalSi->login($data['login'] ?? $data['email'], $data['password'], $request);
            $user = $this->syncPortalUser($this->extractPortalUser($portal), $portal['token'] ?? null);
        } catch (PortalSiIdentityException $e) {
            return $this->portalError($e);
        }

        $user->load('vendor');
        if ($user->vendor?->is_banned) {
            return response()->json([
                'message' => 'Akun toko Anda telah diban permanen.'.($user->vendor->ban_reason ? "\nAlasan: ".$user->vendor->ban_reason : ''),
            ], 403);
        }

        return response()->json([
            'user' => $this->userResource($user),
            'token' => $user->createToken('marketplace')->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $this->portalSi->logout($request->user()?->portal_access_token);
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('vendor');

        if ($user->portal_access_token) {
            try {
                $portalUser = $this->portalSi->user($user->portal_access_token);
                $user = $this->syncPortalUser($this->extractPortalUser($portalUser), $user->portal_access_token)->load('vendor');
            } catch (PortalSiIdentityException $e) {
                if (in_array($e->status, [401, 403], true)) {
                    $request->user()?->currentAccessToken()?->delete();

                    return response()->json(['message' => 'Sesi Portal SI kedaluwarsa. Silakan login ulang.'], 401);
                }
            }
        }

        return response()->json($this->userResource($user));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = $request->user();

        try {
            if ($user->portal_access_token) {
                $portal = $this->portalSi->updateProfile($user->portal_access_token, $data);
                $user = $this->syncPortalUser($this->extractPortalUser($portal), $user->portal_access_token);
            }
        } catch (PortalSiIdentityException $e) {
            return $this->portalError($e);
        }

        $user->phone = $data['phone'] ?? null;
        $user->save();

        return response()->json($this->userResource($user->fresh('vendor')));
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', Password::min(8)],
        ]);

        try {
            $this->portalSi->changePassword(
                $this->requirePortalToken($request->user()),
                $data['current_password'],
                $data['new_password'],
            );
        } catch (PortalSiIdentityException $e) {
            return $this->portalError($e);
        }

        $request->user()->forceFill(['password' => Str::password(48)])->save();
        \App\Models\UserNotification::send(
            $request->user()->id,
            'PASSWORD_CHANGED',
            'Password Portal SI Anda diubah',
            'Password akun Portal SI yang dipakai marketplace baru saja diubah.',
            '/profile',
            'WARNING'
        );

        return response()->json(['ok' => true]);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        try {
            $this->portalSi->forgotPassword($data['email']);
        } catch (PortalSiIdentityException $e) {
            if ($e->status !== 404) {
                return $this->portalError($e);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'new_password' => ['required', Password::min(8)],
        ]);

        try {
            $this->portalSi->resetPassword($data['email'], $data['token'], $data['new_password']);
        } catch (PortalSiIdentityException $e) {
            return $this->portalError($e);
        }

        return response()->json(['ok' => true]);
    }

    public function requestChangeEmail(Request $request)
    {
        $data = $request->validate(['new_email' => 'required|email']);

        try {
            $result = $this->portalSi->requestEmailChange($this->requirePortalToken($request->user()), $data['new_email']);
        } catch (PortalSiIdentityException $e) {
            return $this->portalError($e);
        }

        return response()->json([
            'ok' => true,
            'message' => $result['message'] ?? 'Link konfirmasi dikirim oleh Portal SI.',
            'pending_email' => $result['pending_email'] ?? $data['new_email'],
        ]);
    }

    public function resendVerification(Request $request)
    {
        try {
            $result = $this->portalSi->resendVerification($this->requirePortalToken($request->user()));
        } catch (PortalSiIdentityException $e) {
            return $this->portalError($e);
        }

        return response()->json([
            'ok' => true,
            'message' => $result['message'] ?? 'Email verifikasi dikirim ulang oleh Portal SI.',
            'remaining_today' => null,
            'cooldown_seconds' => $result['resend_cooldown_seconds'] ?? 60,
        ]);
    }

    public function verifyEmail()
    {
        return response()->json([
            'message' => 'Verifikasi email kini dilakukan lewat link resmi Portal SI. Silakan buka email verifikasi dari Portal SI, lalu klik "Cek status" di marketplace.',
        ], 410);
    }

    public function confirmChangeEmail()
    {
        return response()->json([
            'message' => 'Konfirmasi perubahan email kini dilakukan lewat link resmi Portal SI.',
        ], 410);
    }

    private function syncPortalUser(array $portalUser, ?string $portalToken = null, ?string $phone = null): User
    {
        $portalId = (int) ($portalUser['user_id'] ?? $portalUser['id'] ?? 0);
        $email = strtolower(trim((string) ($portalUser['email'] ?? '')));
        $username = strtolower(trim((string) ($portalUser['username'] ?? '')));
        $fullName = trim((string) ($portalUser['full_name'] ?? $portalUser['name'] ?? ''));

        if ($portalId <= 0) {
            throw new PortalSiIdentityException('Response user Portal SI tidak valid.', 502);
        }

        if ($email === '') {
            throw new PortalSiIdentityException('Akun Portal SI ini belum memiliki email. Tambahkan email di Portal SI sebelum memakai marketplace.', 422);
        }

        $user = User::where('portal_user_id', $portalId)->first()
            ?: User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user && $user->portal_user_id && (int) $user->portal_user_id !== $portalId) {
            throw new PortalSiIdentityException('Email ini sudah terhubung ke akun Portal SI lain.', 409);
        }

        if (! $user) {
            $user = new User([
                'password' => Str::password(48),
                'role' => 'BUYER',
            ]);
        }

        $user->fill([
            'portal_user_id' => $portalId,
            'portal_username' => $username ?: null,
            'portal_role' => $portalUser['role'] ?? null,
            'name' => $fullName ?: ($username ?: $email),
            'email' => $email,
            'email_verified_at' => $portalUser['email_verified_at'] ?? null,
        ]);

        if ($phone !== null) {
            $user->phone = $phone ?: null;
        }

        if ($portalToken) {
            $user->portal_access_token = $portalToken;
        }

        $user->save();

        return $user;
    }

    private function extractPortalUser(array $payload): array
    {
        return is_array($payload['user'] ?? null)
            ? $payload['user']
            : $payload;
    }

    private function requirePortalToken(User $user): string
    {
        if (! $user->portal_access_token) {
            throw new PortalSiIdentityException('Silakan login ulang dengan akun Portal SI.', 401);
        }

        return $user->portal_access_token;
    }

    private function portalError(PortalSiIdentityException $e)
    {
        return response()->json(array_filter([
            'message' => $e->getMessage(),
            'errors' => $e->errors ?: null,
        ]), $e->status);
    }

    private function userResource(User $u): array
    {
        return [
            'id' => $u->id,
            'portal_user_id' => $u->portal_user_id,
            'portal_username' => $u->portal_username,
            'name' => $u->name,
            'email' => $u->email,
            'email_verified_at' => $u->email_verified_at,
            'phone' => $u->phone,
            'role' => $u->role,
            'vendor_id' => $u->vendor?->id,
            'vendor_username' => $u->vendor?->username,
            'vendor_status' => $u->vendor?->verification_status,
            'vendor_tour_done' => $u->vendor?->tour_completed_at !== null,
            'vendor_is_banned' => (bool) ($u->vendor?->is_banned),
        ];
    }
}
