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

    private const RESERVED = [
        'admin', 'administrator', 'root', 'postmaster', 'hostmaster', 'webmaster',
        'abuse', 'noreply', 'no-reply', 'service', 'support', 'info', 'mail',
        'mailer-daemon', 'daemon', 'security', 'ssl', 'sysadmin', 'contact',
        'billing', 'sales', 'help', 'test', 'testapi', 'sekolahimpian', 'null',
    ];

    private function unlockKey(int $userId): string
    {
        return "mail_unlocked:{$userId}";
    }

    private function isUnlocked(int $userId): bool
    {
        return (bool) Cache::get($this->unlockKey($userId), false);
    }

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

    public function unlock(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['master_password' => ['required', 'string']]);

        $settings = MailSetting::current();
        if (! $settings->gate_enabled) {
            return response()->json(['unlocked' => true]);
        }

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

    /** Peta email(@domain) → URL foto profil, untuk avatar sesama pengguna. */
    public function avatars(Request $request)
    {
        $emails = collect($request->input('emails', []))
            ->filter()
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->unique()
            ->take(300)
            ->values();

        if ($emails->isEmpty()) {
            return response()->json(['avatars' => (object) []]);
        }

        $accounts = MailAccount::whereIn('email', $emails->all())->get(['user_id', 'email']);
        $pics = \App\Models\User::whereIn('id', $accounts->pluck('user_id')->unique()->all())
            ->pluck('profile_picture_url', 'id');

        $out = [];
        foreach ($accounts as $a) {
            $url = $pics[$a->user_id] ?? null;
            if ($url) {
                $out[$a->email] = $url;
            }
        }

        return response()->json(['avatars' => (object) $out]);
    }

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
