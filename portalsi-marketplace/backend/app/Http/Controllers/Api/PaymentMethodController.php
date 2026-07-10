<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentMethodController extends Controller
{
    public function publicList()
    {
        return response()->json(
            PaymentMethod::where('is_active', true)->orderBy('group')->orderBy('sort_order')->get()
        );
    }

    public function adminList()
    {
        return response()->json(PaymentMethod::orderBy('group')->orderBy('sort_order')->get());
    }

    public function adminSave(Request $request)
    {
        $data = $request->validate([
            'items'              => 'required|array',
            'items.*.code'       => 'required|string|max:30',
            'items.*.name'       => 'required|string|max:100',
            'items.*.group'      => 'required|string|max:50',
            'items.*.icon'       => 'nullable|string',
            'items.*.color'      => 'sometimes|string|max:10',
            'items.*.fee_pct'    => 'sometimes|numeric|min:0|max:50',
            'items.*.fee_flat'   => 'sometimes|integer|min:0',
            'items.*.is_active'  => 'sometimes|boolean',
        ]);
        DB::transaction(function () use ($data) {
            PaymentMethod::query()->delete();
            foreach ($data['items'] as $i => $it) {
                PaymentMethod::create([
                    'code'       => $it['code'],
                    'name'       => $it['name'],
                    'group'      => $it['group'],
                    'icon'       => $it['icon'] ?? null,
                    'color'      => $it['color'] ?? '#0a0a0a',
                    'fee_pct'    => $it['fee_pct'] ?? 0,
                    'fee_flat'   => $it['fee_flat'] ?? 0,
                    'sort_order' => $i,
                    'is_active'  => $it['is_active'] ?? true,
                ]);
            }
        });
        return response()->json(['ok' => true]);
    }
}
