<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushController extends Controller
{
    /** Simpan/segarkan langganan push untuk perangkat ini. */
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|string|max:1000',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
        ]);

        $endpoint = $data['endpoint'];

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'user_id' => Auth::id(),
                'endpoint' => $endpoint,
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]
        );

        return response()->json(['message' => 'Langganan notifikasi tersimpan.']);
    }

    /** Hapus langganan perangkat ini (mis. user menonaktifkan). */
    public function unsubscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|string|max:1000',
        ]);

        PushSubscription::where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json(['message' => 'Langganan notifikasi dihapus.']);
    }
}
