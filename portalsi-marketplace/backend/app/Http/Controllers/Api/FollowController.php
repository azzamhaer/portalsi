<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorFollower;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function toggle(Request $request, $vendorId)
    {
        $user = $request->user();
        $vendor = Vendor::findOrFail($vendorId);
        if ($vendor->user_id === $user->id) {
            return response()->json(['message' => 'Tidak bisa follow toko sendiri'], 422);
        }

        $existing = VendorFollower::where('vendor_id', $vendor->id)->where('user_id', $user->id)->first();
        if ($existing) {
            $existing->delete();
            $following = false;
        } else {
            VendorFollower::create(['vendor_id' => $vendor->id, 'user_id' => $user->id]);
            $following = true;
        }

        // Sync count
        $count = VendorFollower::where('vendor_id', $vendor->id)->count();
        $vendor->update(['followers' => $count]);

        return response()->json(['following' => $following, 'followers' => $count]);
    }

    public function status(Request $request, $vendorId)
    {
        $user = $request->user();
        $following = VendorFollower::where('vendor_id', $vendorId)->where('user_id', $user->id)->exists();
        $count = VendorFollower::where('vendor_id', $vendorId)->count();
        return response()->json(['following' => $following, 'followers' => $count]);
    }
}
