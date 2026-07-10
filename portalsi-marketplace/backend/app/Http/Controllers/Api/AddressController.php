<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->addresses()->orderByDesc('is_default')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'recipient'    => 'required|string|max:255',
            'phone'        => 'required|string|max:20',
            'country'      => 'nullable|string|max:80',
            'province'     => 'required|string|max:255',
            'province_id'  => 'nullable|string|max:20',
            'city'         => 'required|string|max:255',
            'city_id'      => 'nullable|string|max:20',
            'district'     => 'required|string|max:255',
            'district_id'  => 'nullable|string|max:20',
            'village'      => 'required|string|max:255',
            'village_id'   => 'nullable|string|max:20',
            'full_address' => 'required|string',
            'postal_code'  => 'required|string|max:10',
            'rajaongkir_destination_id' => 'nullable|integer',
            'address_note' => 'nullable|string|max:1000',
            'latitude'     => 'required|numeric',
            'longitude'    => 'required|numeric',
            'is_default'   => 'sometimes|boolean',
        ]);
        $data['country'] = $data['country'] ?? 'Indonesia';
        if (!empty($data['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }
        $addr = $request->user()->addresses()->create($data);
        return response()->json($addr, 201);
    }

    public function update(Request $request, $id)
    {
        $addr = $request->user()->addresses()->findOrFail($id);
        $data = $request->validate([
            'recipient'    => 'sometimes|string|max:255',
            'phone'        => 'sometimes|string|max:20',
            'country'      => 'nullable|string|max:80',
            'province'     => 'sometimes|string|max:255',
            'province_id'  => 'nullable|string|max:20',
            'city'         => 'sometimes|string|max:255',
            'city_id'      => 'nullable|string|max:20',
            'district'     => 'sometimes|string|max:255',
            'district_id'  => 'nullable|string|max:20',
            'village'      => 'sometimes|string|max:255',
            'village_id'   => 'nullable|string|max:20',
            'full_address' => 'sometimes|string',
            'postal_code'  => 'sometimes|string|max:10',
            'rajaongkir_destination_id' => 'nullable|integer',
            'address_note' => 'nullable|string|max:1000',
            'latitude'     => 'required|numeric',
            'longitude'    => 'required|numeric',
            'is_default'   => 'sometimes|boolean',
        ]);
        if (array_key_exists('country', $data) && !$data['country']) $data['country'] = 'Indonesia';
        if (!empty($data['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }
        $addr->update($data);
        return response()->json($addr);
    }

    public function destroy(Request $request, $id)
    {
        $addr = $request->user()->addresses()->findOrFail($id);
        try {
            $addr->delete();
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Alamat belum bisa dihapus karena masih direferensikan pesanan lama. Jalankan migrasi terbaru agar pesanan memakai snapshot alamat.',
            ], 409);
        }
        return response()->json(['ok' => true]);
    }
}
