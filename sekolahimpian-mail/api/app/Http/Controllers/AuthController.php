<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
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

        return response()->json([
            'user' => $user->toApi(),
            'message' => 'Pendaftaran berhasil. Silakan masuk.',
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

        $token = $user->createToken('webmail')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->toApi(),
        ]);
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

    /** Kirim tautan reset kata sandi ke email akun. */
    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // Selalu balas generik (jangan bocorkan keberadaan email).
        try {
            Password::sendResetLink(['email' => strtolower($data['email'])]);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => 'Jika email terdaftar, tautan ganti kata sandi telah dikirim.',
        ]);
    }
}
