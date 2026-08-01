<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailSetting;
use App\Services\HestiaMailClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class MailController extends Controller
{
    private const UNLOCK_TTL_HOURS = 24;

    // Local part yang dilarang (alamat sistem/umum).
    private const RESERVED = [
        'admin', 'administrator', 'root', 'postmaster', 'hostmaster', 'webmaster',
        'abuse', 'noreply', 'no-reply', 'service', 'support', 'info', 'mail',
        'mailer-daemon', 'daemon', 'security', 'ssl', 'sysadmin', 'contact',
        'billing', 'sales', 'help', 'test', 'testapi', 'portalsi', 'null',
    ];

    private function unlockKey(int $userId): string
    {
        return "mail_unlocked:{$userId}";
    }

    private function isUnlocked(int $userId): bool
    {
        return (bool) Cache::get($this->unlockKey($userId), false);
    }

    /** Status untuk mail app: gate, sudah unlock, sudah punya akun. */
    public function status(Request $request)
    {
        $user = $request->user();
        $settings = MailSetting::current();
        $account = MailAccount::where('user_id', (int) $user->getKey())->first();

        return response()->json([
            'gate_enabled' => $settings->gate_enabled,
            'unlocked' => ! $settings->gate_enabled || $this->isUnlocked((int) $user->getKey()),
            'has_account' => (bool) $account,
            'account' => $account ? [
                'email' => $account->email,
                'local_part' => $account->local_part,
                'created_at' => $account->created_at,
            ] : null,
            'domain' => config('services.hestia.domain'),
        ]);
    }

    /** Verifikasi master password (gate beta). */
    public function unlock(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['master_password' => ['required', 'string']]);

        $settings = MailSetting::current();
        if (! $settings->gate_enabled) {
            return response()->json(['unlocked' => true]);
        }

        // Batasi brute force: 5 percobaan / menit / user.
        $throttleKey = 'mail_unlock:' . (int) $user->getKey();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'master_password' => ["Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik."],
            ]);
        }

        if (! $settings->hasMasterPassword() || ! Hash::check($data['master_password'], $settings->master_password)) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'master_password' => ['Master password salah.'],
            ]);
        }

        RateLimiter::clear($throttleKey);
        Cache::put($this->unlockKey((int) $user->getKey()), true, now()->addHours(self::UNLOCK_TTL_HOURS));

        return response()->json(['unlocked' => true]);
    }

    /** Akun email milik user (atau null). */
    public function account(Request $request)
    {
        $account = MailAccount::where('user_id', (int) $request->user()->getKey())->first();

        return response()->json([
            'account' => $account ? [
                'email' => $account->email,
                'local_part' => $account->local_part,
                'created_at' => $account->created_at,
            ] : null,
        ]);
    }

    /**
     * Kredensial mailbox (email + password terdekripsi) untuk dipakai server
     * webmail (mail app) login IMAP/SMTP atas nama user. Hanya server-to-server;
     * tidak pernah dikirim ke browser.
     */
    public function credentials(Request $request)
    {
        $account = MailAccount::where('user_id', (int) $request->user()->getKey())->first();
        if (! $account) {
            return response()->json(['message' => 'Belum ada akun email.'], 404);
        }

        return response()->json([
            'email' => $account->email,
            'password' => $account->password,
        ]);
    }

    /** Buat akun email @portalsi.com (maks 1 per user). */
    public function createAccount(Request $request)
    {
        $user = $request->user();
        $settings = MailSetting::current();

        if ($settings->gate_enabled && ! $this->isUnlocked((int) $user->getKey())) {
            return response()->json(['message' => 'Masukkan master password dulu.'], 403);
        }

        if (MailAccount::where('user_id', (int) $user->getKey())->exists()) {
            return response()->json(['message' => 'Kamu sudah memiliki satu akun email.'], 409);
        }

        $data = $request->validate([
            'local_part' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z][a-z0-9]{2,31}$/'],
        ], [
            'local_part.regex' => 'Nama email harus huruf kecil/angka, diawali huruf, minimal 3 karakter.',
        ]);

        $local = strtolower($data['local_part']);

        if (in_array($local, self::RESERVED, true)) {
            throw ValidationException::withMessages(['local_part' => ['Nama email ini tidak tersedia.']]);
        }

        $hestia = HestiaMailClient::make();
        $email = $local . '@' . $hestia->domain();

        // Cek keunikan di DB & di server mail.
        if (MailAccount::where('email', $email)->orWhere('local_part', $local)->exists()) {
            throw ValidationException::withMessages(['local_part' => ['Alamat ini sudah dipakai.']]);
        }

        try {
            if ($hestia->accountExists($local)) {
                throw ValidationException::withMessages(['local_part' => ['Alamat ini sudah dipakai.']]);
            }

            $password = HestiaMailClient::generatePassword();
            $hestia->createAccount($local, $password);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal membuat akun email di server. Coba lagi nanti.'], 502);
        }

        $account = MailAccount::create([
            'user_id' => (int) $user->getKey(),
            'local_part' => $local,
            'email' => $email,
            'password' => $password,
            'quota' => 'unlimited',
        ]);

        return response()->json([
            'account' => [
                'email' => $account->email,
                'local_part' => $account->local_part,
                'created_at' => $account->created_at,
            ],
        ], 201);
    }
}
