<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\ShippingOption;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function stats()
    {
        return response()->json([
            'users'      => User::count(),
            'vendors'    => Vendor::count(),
            'pending_vendors' => Vendor::where('verification_status', 'PENDING')->count(),
            'orders'     => Order::count(),
            'orders_today' => Order::where('created_at', '>=', now()->startOfDay())->count(),
            'revenue'    => Order::whereIn('status', ['PROCESSING','IN_TRANSIT','ARRIVED','DONE'])->sum('total'),
            'pending_returns' => OrderReturn::where('status', 'PENDING')->count(),
        ]);
    }

    public function freshStartSummary()
    {
        return response()->json([
            'users_removed' => User::where('role', '!=', 'ADMIN')->count(),
            'admins_kept' => User::where('role', 'ADMIN')->count(),
            'vendors' => Vendor::count(),
            'products' => \App\Models\Product::count(),
            'orders' => Order::count(),
            'order_items' => \App\Models\OrderItem::count(),
            'payments' => \App\Models\Payment::count(),
            'returns' => OrderReturn::count(),
            'chats' => \App\Models\ChatThread::count(),
            'chat_messages' => \App\Models\ChatMessage::count(),
            'reports' => \App\Models\Report::count(),
            'notifications' => \App\Models\UserNotification::count(),
            'wishlists' => \App\Models\Wishlist::count(),
            'reviews' => \App\Models\Review::count(),
            'withdrawals' => \App\Models\Withdrawal::count(),
            'user_withdrawals' => \App\Models\UserWithdrawal::count(),
            'user_wallet_transactions' => \App\Models\UserWalletTransaction::count(),
            'vouchers' => \App\Models\SellerVoucher::count(),
            'kept' => [
                'settings',
                'shipping_options',
                'payment_methods',
                'faqs',
                'categories',
                'tags',
                'admin_users',
            ],
        ]);
    }

    public function freshStart(Request $request)
    {
        $data = $request->validate([
            'confirm' => 'required|in:FRESH_START',
            'password' => 'required|string',
        ]);

        if (!Hash::check($data['password'], $request->user()->password)) {
            return response()->json(['message' => 'Password admin tidak valid'], 422);
        }

        DB::transaction(function () {
            $nonAdminIds = User::where('role', '!=', 'ADMIN')->pluck('id');
            Schema::disableForeignKeyConstraints();
            try {
                foreach ([
                    'seller_voucher_product',
                    'seller_vouchers',
                    'product_tag',
                    'reviews',
                    'wishlists',
                    'payments',
                    'order_returns',
                    'order_items',
                    'orders',
                    'chat_messages',
                    'chat_threads',
                    'reports',
                    'user_notifications',
                    'user_withdrawals',
                    'user_wallet_transactions',
                    'withdrawals',
                    'vendor_followers',
                    'products',
                    'vendors',
                    'email_verifications',
                    'email_change_requests',
                    'password_resets',
                ] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }

                if (Schema::hasTable('personal_access_tokens')) {
                    DB::table('personal_access_tokens')->where('tokenable_type', User::class)
                        ->whereIn('tokenable_id', $nonAdminIds)
                        ->delete();
                }

                DB::table('addresses')->whereIn('user_id', $nonAdminIds)->delete();
                User::where('role', '!=', 'ADMIN')->delete();
                Tag::query()->update(['product_count' => 0]);
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        });

        return response()->json(['ok' => true, 'summary' => $this->freshStartSummary()->getData(true)]);
    }

    /* ---------- Users ---------- */
    public function users(Request $request)
    {
        $q = User::with('vendor:id,user_id,name,username,verification_status')->orderByDesc('id');
        if ($s = trim((string) $request->query('search'))) {
            $q->where(function ($qq) use ($s) {
                $qq->where('name', 'like', "%$s%")
                   ->orWhere('email', 'like', "%$s%")
                   ->orWhere('phone', 'like', "%$s%")
                   ->orWhereHas('vendor', fn($v) => $v->where('username', 'like', "%$s%")->orWhere('name', 'like', "%$s%"));
            });
        }
        if ($r = $request->query('role')) $q->where('role', $r);
        $per = min(50, max(5, (int) $request->query('per_page', 20)));
        return response()->json($q->paginate($per));
    }

    public function updateUser(Request $request, $id)
    {
        $u = User::findOrFail($id);
        // Role tidak bisa diubah dari API (security): hanya via DB manual / verify vendor flow
        $data = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);
        $u->update($data);
        return response()->json($u);
    }

    public function userDetail($id)
    {
        $user = User::with('vendor')->findOrFail($id);
        $orders = \App\Models\Order::where('user_id', $user->id)
            ->with('payment:id,order_id,method_name,status', 'items:id,order_id,product_name,quantity,price,variant_selection')
            ->orderByDesc('id')->take(50)->get();
        $vendorProducts = collect();
        $vendorOrders = collect();
        if ($user->vendor) {
            $vendorProducts = \App\Models\Product::where('vendor_id', $user->vendor->id)
                ->with('tagModels:id,slug')->withCount('reviews')->orderByDesc('id')->take(50)->get();
            $vendorOrders = \App\Models\OrderItem::where('vendor_id', $user->vendor->id)
                ->with('order:id,order_number,status,created_at')
                ->orderByDesc('id')->take(50)->get();
        }
        return response()->json([
            'user'           => $user,
            'orders'         => $orders,
            'vendor_products'=> $vendorProducts,
            'vendor_orders'  => $vendorOrders,
        ]);
    }

    public function deleteUser($id)
    {
        $u = User::findOrFail($id);
        if ($u->role === 'ADMIN') return response()->json(['message' => 'Tidak bisa hapus admin'], 422);
        $u->delete();
        return response()->json(['ok' => true]);
    }

    /* ---------- Vendors ---------- */
    public function vendors(Request $request)
    {
        $q = Vendor::with('user:id,name,email,phone')->orderByDesc('id');
        if ($s = $request->query('status')) $q->where('verification_status', $s);
        if ($k = trim((string) $request->query('search'))) {
            $q->where(function ($qq) use ($k) {
                $qq->where('name', 'like', "%$k%")
                   ->orWhere('username', 'like', "%$k%")
                   ->orWhere('city', 'like', "%$k%")
                   ->orWhereHas('user', fn($u) => $u->where('name','like',"%$k%")->orWhere('email','like',"%$k%")->orWhere('phone','like',"%$k%"));
            });
        }
        $per = min(50, max(5, (int) $request->query('per_page', 20)));
        $vendors = $q->paginate($per);
        $vendors->getCollection()->each(function ($v) {
            $v->setHidden([]);
            $v->makeVisible('ktp_image');
        });
        return response()->json($vendors);
    }

    public function verifyVendor(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:APPROVED,REJECTED',
            'note' => 'nullable|string',
        ]);
        $v = Vendor::with('user')->findOrFail($id);
        $v->update(['verification_status' => $data['status'], 'verification_note' => $data['note'] ?? null]);

        if ($v->user_id) {
            \App\Models\UserNotification::send(
                $v->user_id, 'VENDOR_VERIFICATION',
                $data['status'] === 'APPROVED' ? 'Toko Anda terverifikasi!' : 'Verifikasi toko ditolak',
                $data['status'] === 'APPROVED'
                    ? "Selamat! Toko \"{$v->name}\" sudah disetujui dan bisa mulai jualan."
                    : "Verifikasi toko \"{$v->name}\" ditolak admin." . ($data['note'] ? "\nAlasan: " . $data['note'] : ''),
                $data['status'] === 'APPROVED' ? '/seller/dashboard' : '/seller/pending',
                $data['status'] === 'APPROVED' ? 'SUCCESS' : 'WARNING'
            );
        }

        return response()->json($v);
    }

    public function setBadge(Request $request, $id)
    {
        $data = $request->validate([
            'badge' => 'nullable|in:VERIFIED,MALL,STAR',
        ]);
        $v = Vendor::findOrFail($id);
        $v->update(['badge' => $data['badge'] ?? null]);
        return response()->json($v);
    }

    /** Set mode moderasi vendor & pesan peringatan */
    public function setModeration(Request $request, $id)
    {
        $data = $request->validate([
            'moderation_mode' => 'required|in:NONE,LIMITED,DISABLED',
            'admin_warning'   => 'nullable|string|max:2000',
        ]);
        $v = Vendor::findOrFail($id);
        $v->moderation_mode = $data['moderation_mode'];
        // Kalau warning beda dari sebelumnya, reset dismissed
        if (($data['admin_warning'] ?? null) !== $v->admin_warning) {
            $v->admin_warning = $data['admin_warning'] ?? null;
            $v->warning_dismissed_at = null;
        }
        $v->save();
        return response()->json($v);
    }

    public function deleteVendor($id)
    {
        Vendor::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    /* ---------- Orders ---------- */
    public function orders(Request $request)
    {
        $q = Order::with(['user:id,name,email', 'address:id,city,recipient', 'payment:id,order_id,method_name,status', 'items'])
                  ->orderByDesc('id');
        if ($s = $request->query('status')) $q->where('status', $s);
        if ($k = trim((string) $request->query('search'))) {
            $q->where(function ($qq) use ($k) {
                $qq->where('order_number', 'like', "%$k%")
                   ->orWhere('tracking_no', 'like', "%$k%")
                   ->orWhereHas('user', fn($u) => $u->where('name','like',"%$k%")->orWhere('email','like',"%$k%"));
            });
        }
        $per = min(50, max(5, (int) $request->query('per_page', 20)));
        return response()->json($q->paginate($per));
    }

    public function updateOrder(Request $request, $id)
    {
        $o = Order::findOrFail($id);
        $data = $request->validate([
            'status' => 'sometimes|in:PENDING_PAYMENT,PAID,PROCESSING,IN_TRANSIT,ARRIVED,DONE,RETURN_REQUESTED,REFUNDED,CANCELLED,EXPIRED',
            'tracking_no' => 'nullable|string',
        ]);
        $o->update($data);
        return response()->json($o);
    }

    public function orderDetail($id)
    {
        $order = Order::with([
            'user:id,name,email,phone',
            'address',
            'payment',
            'items.product:id,name,slug,image',
            'items.vendor:id,name,username,user_id,avatar',
            'items.vendor.user:id,name,email,phone',
        ])->findOrFail($id);
        return response()->json($order);
    }

    /* ---------- Returns ---------- */
    public function returns(Request $request)
    {
        return response()->json(OrderReturn::with(['order:id,order_number,total,status', 'user:id,name,email'])->orderByDesc('id')->paginate(30));
    }

    public function approveReturn(Request $request, $id)
    {
        $data = $request->validate(['status' => 'required|in:APPROVED,REJECTED,REFUNDED', 'admin_note' => 'nullable|string']);
        $r = OrderReturn::with('order', 'user')->findOrFail($id);

        DB::transaction(function () use ($r, $data) {
            if ($data['status'] === 'REJECTED') {
                $r->update($data);
                if ($r->order?->status === 'RETURN_REQUESTED') $r->order->update(['status' => 'ARRIVED']);
                return;
            }

            $amount = (int) ($r->order?->total ?? 0);
            \App\Models\UserWalletTransaction::firstOrCreate(
                ['reference' => 'RETURN-' . $r->id],
                [
                    'user_id' => $r->user_id,
                    'amount' => $amount,
                    'type' => 'RETURN_REFUND',
                    'note' => 'Refund pesanan #' . ($r->order?->order_number ?? $r->order_id),
                ]
            );

            $r->update([
                'status' => 'REFUNDED',
                'admin_note' => $data['admin_note'] ?? null,
            ]);
            $r->order?->update(['status' => 'REFUNDED']);

            \App\Models\UserNotification::send(
                $r->user_id,
                'RETURN_REFUNDED',
                'Refund masuk saldo',
                'Refund pesanan #' . ($r->order?->order_number ?? $r->order_id) . ' sebesar Rp ' . number_format($amount, 0, ',', '.') . ' sudah masuk saldo profil Anda.',
                '/profile',
                'SUCCESS',
                ['order_id' => $r->order_id, 'return_id' => $r->id, 'amount' => $amount]
            );
        });

        return response()->json($r->fresh(['order', 'user']));
    }

    /* ---------- Shipping options ---------- */
    public function shippingOptions()
    {
        return response()->json(ShippingOption::orderBy('sort_order')->get());
    }

    public function saveShippingOptions(Request $request)
    {
        $items = $request->validate(['items' => 'required|array', 'items.*.name' => 'required|string', 'items.*.eta' => 'required|string', 'items.*.cost' => 'required|integer|min:0', 'items.*.is_active' => 'sometimes|boolean'])['items'];
        DB::transaction(function () use ($items) {
            ShippingOption::query()->delete();
            foreach ($items as $i => $it) {
                ShippingOption::create(array_merge($it, ['sort_order' => $i, 'is_active' => $it['is_active'] ?? true]));
            }
        });
        return response()->json(['ok' => true]);
    }

    /* ---------- Catalog navigation ---------- */
    public function tags(Request $request)
    {
        $q = Tag::query()->orderByDesc('product_count')->orderBy('name');
        if ($s = trim((string) $request->query('search'))) {
            $q->where(fn($w) => $w->where('slug', 'like', "%$s%")->orWhere('name', 'like', "%$s%"));
        }
        return response()->json($q->get());
    }

    public function saveTag(Request $request, $id = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'slug' => 'nullable|string|max:80',
        ]);
        $slug = Str::slug(strtolower($data['slug'] ?: $data['name']));
        if (!$slug) return response()->json(['message' => 'Slug tag tidak valid'], 422);
        $exists = Tag::where('slug', $slug)->when($id, fn($q) => $q->where('id', '!=', $id))->exists();
        if ($exists) return response()->json(['message' => 'Slug tag sudah dipakai'], 422);

        $tag = $id ? Tag::findOrFail($id) : new Tag();
        $tag->fill(['name' => $data['name'], 'slug' => $slug]);
        $tag->save();
        return response()->json($tag);
    }

    public function deleteTag($id)
    {
        $tag = Tag::findOrFail($id);
        $tag->products()->detach();
        $tag->delete();
        return response()->json(['ok' => true]);
    }

    public function adminCategories()
    {
        return response()->json(
            Category::whereNull('parent_id')
                ->with('children')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }

    public function saveCategory(Request $request, $id = null)
    {
        $data = $request->validate([
            'parent_id' => 'nullable|string|exists:categories,id',
            'name' => 'required|string|max:80',
            'slug' => 'nullable|string|max:80',
            'tag_slug' => 'nullable|string|max:80',
            'icon' => 'nullable|string|max:80',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
            'featured_home' => 'sometimes|boolean',
        ]);

        $slug = Str::slug(strtolower($data['slug'] ?: $data['name']));
        if (!$slug) return response()->json(['message' => 'Slug kategori tidak valid'], 422);
        $catId = $id ?: $slug;
        $exists = Category::where('slug', $slug)->when($id, fn($q) => $q->where('id', '!=', $id))->exists();
        if ($exists) return response()->json(['message' => 'Slug kategori sudah dipakai'], 422);
        if (($data['parent_id'] ?? null) === $catId) return response()->json(['message' => 'Parent kategori tidak valid'], 422);

        $category = $id ? Category::findOrFail($id) : new Category(['id' => $catId]);
        $category->fill([
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => $slug,
            'tag_slug' => $data['tag_slug'] ? Str::slug(strtolower($data['tag_slug'])) : $slug,
            'icon' => $data['icon'] ?? 'tag',
            'color' => $data['color'] ?? '#0a0a0a',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'featured_home' => $data['featured_home'] ?? true,
        ]);
        $category->save();

        return response()->json($category);
    }

    public function deleteCategory($id)
    {
        $category = Category::withCount(['products', 'children'])->findOrFail($id);
        if ($category->products_count || $category->children_count) {
            return response()->json(['message' => 'Kategori masih punya produk atau subkategori'], 422);
        }
        $category->delete();
        return response()->json(['ok' => true]);
    }
}
