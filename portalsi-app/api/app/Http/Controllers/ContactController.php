<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Services\SecurityThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    private const MAX_PER_DAY = 3;

    /** Captcha matematika sederhana (server-side, tanpa layanan eksternal). */
    public function captcha()
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $token = Str::random(40);
        Cache::put('contact_captcha:'.$token, $a + $b, now()->addMinutes(10));

        return response()->json([
            'token' => $token,
            'question' => "$a + $b",
        ]);
    }

    public function store(Request $request, SecurityThrottle $throttle)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|string|max:40',
            'message' => 'required|string|max:2000',
            'captcha_token' => 'required|string',
            'captcha_answer' => 'required',
        ]);

        // 1) Captcha — sekali pakai (pull menghapus dari cache).
        $expected = Cache::pull('contact_captcha:'.$data['captcha_token']);
        if ($expected === null || (int) $data['captcha_answer'] !== (int) $expected) {
            return response()->json([
                'message' => 'Captcha salah atau kedaluwarsa. Coba lagi.',
                'field' => 'captcha',
            ], 422);
        }

        // 2) IP klien yang andal (header X-Real-Client-Ip / CF-Connecting-IP → fallback ip()).
        $ip = $throttle->clientIp($request);

        // 3) Batas 3 kiriman per hari per IP.
        $today = ContactMessage::where('ip_address', $ip)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($today >= self::MAX_PER_DAY) {
            return response()->json([
                'message' => 'Batas 3 pesan per hari dari IP ini sudah tercapai. Coba lagi besok.',
            ], 429);
        }

        ContactMessage::create([
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'phone' => $data['phone'] ? trim($data['phone']) : null,
            'message' => trim($data['message']),
            'ip_address' => $ip,
        ]);

        return response()->json(['message' => 'Terima kasih! Saran Anda sudah kami terima.']);
    }
}
