<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MailLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /** Ubah nama lengkap & username. */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'min:3', 'max:32', 'regex:/^[a-zA-Z0-9._]+$/',
                'unique:users,username,' . $user->getKey(),
            ],
        ], [
            'username.unique' => 'Username sudah dipakai.',
            'username.regex' => 'Username hanya huruf, angka, titik, dan garis bawah.',
        ]);

        $user->full_name = $data['full_name'];
        $user->username = strtolower($data['username']);
        $user->save();

        return response()->json(['user' => $user->toApi(), 'message' => 'Profil diperbarui.']);
    }

    /** Unggah foto profil (maks 2MB, disimpan di disk lokal publik). */
    public function photo(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], // 2048 KB = 2MB
        ], [
            'photo.max' => 'Ukuran foto maksimal 2MB.',
            'photo.image' => 'File harus berupa gambar.',
        ]);

        // hapus foto lama milik user ini (kalau tersimpan lokal)
        $old = (string) $user->profile_picture_url;
        if ($old && str_contains($old, '/storage/avatars/')) {
            $rel = 'avatars/' . basename(parse_url($old, PHP_URL_PATH));
            Storage::disk('public')->delete($rel);
        }

        $ext = $request->file('photo')->extension();
        $name = 'u' . $user->getKey() . '_' . Str::random(8) . '.' . $ext;
        $request->file('photo')->storeAs('avatars', $name, 'public');

        $user->profile_picture_url = rtrim(config('app.url'), '/') . '/storage/avatars/' . $name;
        $user->save();

        return response()->json(['user' => $user->toApi(), 'message' => 'Foto profil diperbarui.']);
    }

    /** Minta ganti email pemulihan → kirim tautan konfirmasi ke email LAMA. */
    public function requestEmailChange(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
        ], [
            'email.unique' => 'Email itu sudah dipakai akun lain.',
        ]);

        if (strtolower($data['email']) === strtolower((string) $user->email)) {
            throw ValidationException::withMessages(['email' => ['Email baru sama dengan email sekarang.']]);
        }

        $user->pending_email = strtolower($data['email']);
        $user->email_change_token = Str::random(48);
        $user->save();

        try {
            MailLink::sendEmailChangeConfirm($user); // ke email LAMA
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal mengirim tautan konfirmasi. Coba lagi nanti.'], 502);
        }

        return response()->json([
            'message' => 'Tautan konfirmasi telah dikirim ke email lama kamu (' . $user->email . '). Klik tautan itu untuk menyelesaikan penggantian.',
        ]);
    }

    /** Konfirmasi ganti email (dibuka dari tautan di email LAMA, signed). */
    public function confirmEmailChange(Request $request, int $id, string $t)
    {
        $user = User::find($id);
        $ok = $user
            && $user->pending_email
            && $user->email_change_token
            && hash_equals($user->email_change_token, $t);

        if ($ok) {
            // pastikan email baru masih belum dipakai
            $taken = User::where('email', $user->pending_email)->where('id', '!=', $user->id)->exists();
            if (! $taken) {
                $user->email = $user->pending_email;
                $user->pending_email = null;
                $user->email_change_token = null;
                $user->save();
                try {
                    MailLink::sendEmailChangedNotice($user); // ke email BARU
                } catch (\Throwable $e) {
                    report($e);
                }
            } else {
                $ok = false;
            }
        }

        $to = MailLink::frontend() . '/login?emailchanged=' . ($ok ? '1' : '0');

        return redirect()->away($to);
    }
}
