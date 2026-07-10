<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BrevoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    protected function brevo(): BrevoService { return new BrevoService(); }

    protected function frontendUrl(): string
    {
        return rtrim(config('services.frontend_url', 'http://localhost:5173'), '/');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'password' => ['required', Password::min(6)],
        ]);
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => $data['password'],
            'role'     => 'BUYER',
        ]);

        // Kirim email "selamat datang" + verification link
        $this->sendWelcomeEmail($user);

        $token = $user->createToken('auth')->plainTextToken;
        return response()->json([
            'user' => $this->userResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email atau password salah'], 401);
        }
        $user->load('vendor');
        // Block login kalau vendor permanently banned
        if ($user->vendor?->is_banned) {
            return response()->json([
                'message' => 'Akun toko Anda telah diban permanen.' . ($user->vendor->ban_reason ? "\nAlasan: " . $user->vendor->ban_reason : ''),
            ], 403);
        }
        $token = $user->createToken('auth')->plainTextToken;
        return response()->json([
            'user'  => $this->userResource($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        return response()->json($this->userResource($request->user()->load('vendor')));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);
        $request->user()->update($data);
        return response()->json($this->userResource($request->user()->fresh('vendor')));
    }

    /* ---------- Change Password (verifikasi password lama) ---------- */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password'     => ['required', Password::min(6)],
        ]);
        $user = $request->user();
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Password lama salah'], 422);
        }
        $user->update(['password' => $data['new_password']]); // model cast hashed
        \App\Models\UserNotification::send(
            $user->id, 'PASSWORD_CHANGED',
            'Password Anda diubah',
            "Password akun Anda baru saja diubah. Jika ini bukan Anda, segera hubungi admin.",
            '/profile', 'WARNING'
        );
        // Kirim notif email
        $this->brevo()->send(
            $user->email, $user->name,
            'Password Anda baru saja diubah',
            $this->brevo()->layout(
                'Password berubah',
                "<p>Hai <b>" . htmlspecialchars($user->name) . "</b>,</p>
                 <p>Password akun Anda baru saja diubah. Jika ini bukan Anda, segera hubungi tim support kami.</p>"
            )
        );
        return response()->json(['ok' => true]);
    }

    /* ---------- Forgot Password ---------- */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $user = User::where('email', $data['email'])->first();
        // Tetap return 200 supaya tidak membocorkan info user mana yang ada
        if (!$user) return response()->json(['ok' => true]);

        $token = Str::random(64);
        DB::table('password_resets')->where('email', $user->email)->delete();
        DB::table('password_resets')->insert([
            'email'      => $user->email,
            'token'      => $token,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = $this->frontendUrl() . '/reset-password?token=' . urlencode($token);
        $this->brevo()->send(
            $user->email, $user->name,
            'Reset Password',
            $this->brevo()->layout(
                'Permintaan reset password',
                "<p>Hai <b>" . htmlspecialchars($user->name) . "</b>,</p>
                 <p>Kami menerima permintaan untuk mereset password akun Anda. Klik tombol di bawah untuk mengatur password baru. Link berlaku selama 1 jam.</p>
                 <p style='font-size:11px;color:#888;'>Kalau Anda tidak meminta, abaikan email ini. Jika email tidak terlihat di inbox, cek folder Spam/Promosi.</p>",
                $url, 'Reset Password'
            )
        );
        return response()->json(['ok' => true]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token'        => 'required|string',
            'new_password' => ['required', Password::min(6)],
        ]);
        $row = DB::table('password_resets')->where('token', $data['token'])->first();
        if (!$row || $row->expires_at < now()) {
            return response()->json(['message' => 'Token reset sudah kadaluarsa atau tidak valid'], 422);
        }
        $user = User::where('email', $row->email)->first();
        if (!$user) return response()->json(['message' => 'User tidak ditemukan'], 422);
        $user->update(['password' => $data['new_password']]);
        DB::table('password_resets')->where('token', $data['token'])->delete();
        return response()->json(['ok' => true]);
    }

    /* ---------- Change Email (konfirmasi ke email lama terlebih dahulu) ---------- */
    public function requestChangeEmail(Request $request)
    {
        $data = $request->validate(['new_email' => 'required|email|unique:users,email']);
        $user = $request->user();

        $token = Str::random(64);
        DB::table('email_change_requests')->where('user_id', $user->id)->delete();
        DB::table('email_change_requests')->insert([
            'user_id'    => $user->id,
            'new_email'  => $data['new_email'],
            'token'      => $token,
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = $this->frontendUrl() . '/confirm-email?token=' . urlencode($token);
        $this->brevo()->send(
            $user->email, $user->name,
            'Konfirmasi Perubahan Email',
            $this->brevo()->layout(
                'Konfirmasi perubahan email',
                "<p>Hai <b>" . htmlspecialchars($user->name) . "</b>,</p>
                 <p>Anda meminta untuk mengganti email akun menjadi <b>" . htmlspecialchars($data['new_email']) . "</b>.</p>
                 <p>Demi keamanan, klik tombol di bawah dari email saat ini untuk menyetujui perubahan tersebut. Link berlaku selama 24 jam.</p>
                 <p style='font-size:12px;color:#666;'>Jika Anda tidak meminta perubahan ini, abaikan email ini dan segera ubah password.</p>",
                $url, 'Setujui Perubahan Email'
            )
        );

        return response()->json(['ok' => true]);
    }

    public function confirmChangeEmail(Request $request)
    {
        $data = $request->validate(['token' => 'required|string']);
        $row = DB::table('email_change_requests')->where('token', $data['token'])->first();
        if (!$row || $row->expires_at < now()) {
            return response()->json(['message' => 'Token konfirmasi sudah kadaluarsa atau tidak valid'], 422);
        }
        if (User::where('email', $row->new_email)->exists()) {
            return response()->json(['message' => 'Email sudah dipakai akun lain'], 422);
        }
        $user = User::find($row->user_id);
        if (!$user) return response()->json(['message' => 'User tidak ditemukan'], 422);
        $oldEmail = $user->email;
        $user->email = $row->new_email;
        $user->email_verified_at = now();
        $user->save();
        DB::table('email_change_requests')->where('token', $data['token'])->delete();
        \App\Models\UserNotification::send(
            $user->id, 'EMAIL_CHANGED',
            'Email akun Anda berubah',
            "Email akun Anda diubah dari {$oldEmail} menjadi {$row->new_email}.",
            '/profile', 'SUCCESS'
        );
        return response()->json(['ok' => true, 'email' => $row->new_email]);
    }

    /* ---------- Email Verification (welcome flow) ---------- */
    protected function sendWelcomeEmail(User $user): void
    {
        $token = Str::random(64);
        DB::table('email_verifications')->insert([
            'user_id'    => $user->id,
            'token'      => $token,
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $url = $this->frontendUrl() . '/verify-email?token=' . urlencode($token);
        $this->brevo()->send(
            $user->email, $user->name,
            'Selamat datang di ' . \App\Models\Setting::get('app_name', 'MPSI'),
            $this->brevo()->layout(
                'Selamat datang!',
                "<p>Hai <b>" . htmlspecialchars($user->name) . "</b>,</p>
                 <p>Akun Anda berhasil dibuat. Klik tombol di bawah untuk memverifikasi email Anda dan menyelesaikan pendaftaran.</p>
                 <p style='font-size:12px;color:#666;'>Jika email verifikasi tidak terlihat di inbox, silakan cek folder Spam/Promosi.</p>",
                $url, 'Verifikasi Email'
            )
        );
    }

    /**
     * Resend verification email — rate limited.
     * Aturan: minimal 60 detik dari kirim sebelumnya, maks 3 kali per hari (per user).
     */
    public function resendVerification(Request $request)
    {
        $user = $request->user();
        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email Anda sudah terverifikasi'], 422);
        }

        // Hitung pengiriman 24 jam terakhir
        $sentToday = DB::table('email_verifications')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($sentToday >= 3) {
            return response()->json([
                'message' => 'Sudah mencapai batas 3 kali pengiriman per 24 jam. Coba lagi besok.',
                'limit_reached' => true,
            ], 429);
        }

        // Cek cooldown 60 detik dari kirim terakhir
        $last = DB::table('email_verifications')
            ->where('user_id', $user->id)
            ->orderByDesc('id')->first();
        if ($last) {
            $secondsSince = now()->diffInSeconds($last->created_at);
            if ($secondsSince < 60) {
                $wait = 60 - $secondsSince;
                return response()->json([
                    'message' => "Mohon tunggu {$wait} detik lagi sebelum mengirim ulang.",
                    'cooldown_seconds' => $wait,
                ], 429);
            }
        }

        $this->sendWelcomeEmail($user);
        return response()->json([
            'ok' => true,
            'sent_today' => $sentToday + 1,
            'remaining_today' => max(0, 3 - ($sentToday + 1)),
            'cooldown_seconds' => 60,
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $data = $request->validate(['token' => 'required|string']);
        $row = DB::table('email_verifications')->where('token', $data['token'])->first();
        if (!$row || $row->expires_at < now()) {
            return response()->json(['message' => 'Token verifikasi kadaluarsa'], 422);
        }
        $user = User::find($row->user_id);
        if (!$user) return response()->json(['message' => 'User tidak ditemukan'], 422);
        // Direct assignment + save() bypass mass-assignment protection
        $user->email_verified_at = now();
        $user->save();
        DB::table('email_verifications')->where('user_id', $user->id)->delete();
        return response()->json(['ok' => true, 'verified_at' => $user->email_verified_at?->toIso8601String()]);
    }

    private function userResource(User $u): array
    {
        return [
            'id'                => $u->id,
            'name'              => $u->name,
            'email'             => $u->email,
            'email_verified_at' => $u->email_verified_at,
            'phone'             => $u->phone,
            'role'              => $u->role,
            'vendor_id'             => $u->vendor?->id,
            'vendor_username'       => $u->vendor?->username,
            'vendor_status'         => $u->vendor?->verification_status,
            'vendor_tour_done'      => $u->vendor?->tour_completed_at !== null,
            'vendor_is_banned'      => (bool) ($u->vendor?->is_banned),
        ];
    }
}
