<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MailLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-zA-Z0-9._]+$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ], [
            'username.unique' => 'Username sudah dipakai.',
            'email.unique' => 'Email sudah terdaftar.',
        ]);

        $user = User::create([
            'full_name' => $data['full_name'],
            'username' => strtolower($data['username']),
            'email' => strtolower($data['email']),
            'password' => $data['password'],
        ]);

        $status = 'sent';
        try {
            MailLink::sendVerification($user);
        } catch (\Throwable $e) {
            report($e);
            $status = 'failed';
        }

        return response()->json([
            'user' => $user->toApi(),
            'verification_email_status' => $status,
            'message' => 'Pendaftaran berhasil. Kami mengirim tautan verifikasi ke email kamu — klik dulu sebelum masuk.',
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = strtolower(trim($data['login']));
        $user = User::where('username', $login)->orWhere('email', $login)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => ['Username/email atau kata sandi salah.']]);
        }

        if (! $user->email_verified_at) {
            return response()->json([
                'message' => 'Email belum diverifikasi. Cek kotak masuk email pemulihanmu atau kirim ulang tautannya.',
                'unverified' => true,
                'email' => $user->email,
            ], 403);
        }

        $token = $user->createToken('webmail')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->toApi(),
        ]);
    }

    /** Verifikasi email pendaftaran (dibuka dari tautan email, signed). */
    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::find($id);
        $ok = $user && hash_equals(sha1($user->email), $hash);

        if ($ok && ! $user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        $to = MailLink::frontend() . '/login?verified=' . ($ok ? '1' : '0');

        return redirect()->away($to);
    }

    /** Kirim ulang email verifikasi. Respons selalu generik. */
    public function resendVerification(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', strtolower($data['email']))->first();

        if ($user && ! $user->email_verified_at) {
            try {
                MailLink::sendVerification($user);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['message' => 'Jika email terdaftar dan belum diverifikasi, tautan baru telah dikirim.']);
    }

    public function user(Request $request)
    {
        return response()->json(['user' => $request->user()->toApi()]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Keluar berhasil.']);
    }

    /** Kirim tautan reset kata sandi ke email pemulihan akun. */
    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', strtolower($data['email']))->first();
        if ($user) {
            try {
                MailLink::sendPasswordReset($user);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'message' => 'Jika email terdaftar, tautan ganti kata sandi telah dikirim.',
        ]);
    }

    /** Setel kata sandi baru dengan token dari email. */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'password_confirmation' => $data['password'],
                'token' => $data['token'],
            ],
            function (User $user, string $password) {
                $user->password = $password; // cast 'hashed' meng-hash otomatis
                $user->setRememberToken(Str::random(60));
                $user->save();
                $user->tokens()->delete(); // cabut semua sesi lama
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.'],
            ]);
        }

        return response()->json(['message' => 'Kata sandi berhasil diganti. Silakan masuk dengan kata sandi baru.']);
    }
}
