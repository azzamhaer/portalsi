<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerVoucher;
use App\Models\Tag;
use App\Models\Vendor;
use App\Services\RajaOngkirService;
use App\Services\ProductStockNotificationService;
use App\Support\ReservedUsernames;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellerController extends Controller
{
    public function __construct(private ProductStockNotificationService $stockNotifications) {}

    public function register(Request $request)
    {
        $user = $request->user();
        if ($user->vendor()->exists()) return response()->json(['message' => 'Anda sudah punya toko'], 422);

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'country'      => 'nullable|string|max:80',
            'province'     => 'required|string|max:255',
            'province_id'  => 'nullable|string|max:20',
            'city'         => 'required|string|max:255',
            'city_id'      => 'nullable|string|max:20',
            'district'     => 'required|string|max:255',
            'district_id'  => 'nullable|string|max:20',
            'village'      => 'required|string|max:255',
            'village_id'   => 'nullable|string|max:20',
            'postal_code'  => 'required|string|max:10',
            'rajaongkir_destination_id' => 'nullable|integer',
            'description'  => 'required|string',
            'bank_name'    => 'nullable|string|max:50',
            'bank_account' => 'nullable|string|max:30',
            'bank_holder'  => 'nullable|string|max:255',
            'ktp_image'    => 'required|string', // base64 data URI
            'latitude'     => 'required|numeric',
            'longitude'    => 'required|numeric',
            'full_address' => 'required|string',
            'address_note' => 'nullable|string|max:1000',
        ]);

        $color = '#' . substr(md5($data['name']), 0, 6);

        // Generate unique username dari nama toko
        $username = $this->generateUsername($data['name']);

        $vendor = DB::transaction(function () use ($user, $data, $username, $color) {
            $vendor = Vendor::create([
                'user_id'     => $user->id,
                'name'        => $data['name'],
                'slug'        => Str::slug($data['name']) . '-' . Str::random(4),
                'username'    => $username,
                'country'     => $data['country'] ?? 'Indonesia',
                'province'    => $data['province'],
                'province_id' => $data['province_id'] ?? null,
                'city'        => $data['city'],
                'city_id'     => $data['city_id'] ?? null,
                'district'    => $data['district'],
                'district_id' => $data['district_id'] ?? null,
                'village'     => $data['village'],
                'village_id'  => $data['village_id'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'rajaongkir_destination_id' => $data['rajaongkir_destination_id'] ?? null,
                'latitude'    => $data['latitude'],
                'longitude'   => $data['longitude'],
                'full_address'=> $data['full_address'],
                'address_note'=> $data['address_note'] ?? null,
                'description' => $data['description'],
                'avatar'      => $this->makeAvatar($data['name'][0] ?? 'S', $color),
                'banner'      => $this->makeBanner($data['name'], $color),
                'ktp_image'   => $data['ktp_image'],
                'verification_status' => 'PENDING',
                'bank_name'   => $data['bank_name'] ?? null,
                'bank_account'=> $data['bank_account'] ?? null,
                'bank_holder' => $data['bank_holder'] ?? null,
            ]);

            $user->update(['role' => 'SELLER']);
            $this->syncVendorAddress($vendor);

            return $vendor;
        });

        // Notifikasi ke SEMUA admin agar review pendaftaran ini
        $admins = \App\Models\User::where('role', 'ADMIN')->pluck('id');
        foreach ($admins as $adminId) {
            \App\Models\UserNotification::send(
                $adminId,
                'VENDOR_PENDING_APPROVAL',
                'Pendaftaran vendor baru',
                "Toko \"{$vendor->name}\" (oleh {$user->name}) menunggu approval verifikasi KTP.",
                '/admin/vendors?status=PENDING',
                'INFO',
                ['vendor_id' => $vendor->id, 'user_id' => $user->id]
            );
        }

        return response()->json($vendor, 201);
    }

    public function dashboard(Request $request)
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 404);

        if ($vendor->verification_status !== 'APPROVED') {
            return response()->json([
                'vendor' => $vendor,
                'stats' => [
                    'orders_24h' => 0,
                    'revenue_30d' => 0,
                    'active_products' => 0,
                    'rating' => $vendor->rating,
                ],
                'recent_orders' => [],
            ]);
        }

        return response()->json([
            'vendor' => $vendor,
            'stats' => [
                'orders_24h'  => Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))->where('created_at', '>=', now()->subDay())->count(),
                'revenue_30d' => OrderItem::where('vendor_id', $vendor->id)
                                  ->whereHas('order', fn($q) => $q->whereIn('status', ['PROCESSING','IN_TRANSIT','ARRIVED','DONE'])->where('created_at', '>=', now()->subDays(30)))
                                  ->sum(\DB::raw('price * quantity')),
                'active_products' => Product::where('vendor_id', $vendor->id)->where('is_active', true)->count(),
                'rating'          => $vendor->rating,
            ],
            'recent_orders' => OrderItem::where('vendor_id', $vendor->id)->with('order:id,order_number,status,created_at')->orderByDesc('id')->take(10)->get(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        $updatesAddress = $this->requestTouchesVendorAddress($request);
        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'country'      => 'nullable|string|max:80',
            'province'     => 'sometimes|string|max:255',
            'province_id'  => 'nullable|string|max:20',
            'city'         => 'sometimes|string|max:255',
            'city_id'      => 'nullable|string|max:20',
            'district'     => 'sometimes|string|max:255',
            'district_id'  => 'nullable|string|max:20',
            'village'      => 'sometimes|string|max:255',
            'village_id'   => 'nullable|string|max:20',
            'postal_code'  => 'nullable|string|max:10',
            'rajaongkir_destination_id' => 'nullable|integer',
            'description'  => 'sometimes|string',
            'latitude'     => ($updatesAddress ? 'required' : 'sometimes|nullable') . '|numeric',
            'longitude'    => ($updatesAddress ? 'required' : 'sometimes|nullable') . '|numeric',
            'full_address' => 'nullable|string',
            'address_note' => 'nullable|string|max:1000',
            'avatar'       => 'nullable|string',
            'banner'       => 'nullable|string',
            'bank_name'    => 'nullable|string|max:50',
            'bank_account' => 'nullable|string|max:30',
            'bank_holder'  => 'nullable|string|max:255',
        ]);

        foreach (['avatar' => 2, 'banner' => 3] as $field => $maxMb) {
            if (array_key_exists($field, $data) && $data[$field]) {
                $this->validateInlineImage($data[$field], $field === 'avatar' ? 'Foto profil' : 'Banner', $maxMb);
            }
        }

        $vendor->update($data);
        if ($updatesAddress) {
            $this->syncVendorAddress($vendor->fresh());
        }

        return response()->json($vendor);
    }

    private function requestTouchesVendorAddress(Request $request): bool
    {
        $fields = [
            'country', 'province', 'province_id', 'city', 'city_id',
            'district', 'district_id', 'village', 'village_id',
            'postal_code', 'rajaongkir_destination_id',
            'latitude', 'longitude', 'full_address', 'address_note',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) return true;
        }

        return false;
    }

    private function syncVendorAddress(Vendor $vendor): void
    {
        $vendor->loadMissing('user');
        $user = $vendor->user;
        if (!$user || $vendor->latitude === null || $vendor->longitude === null || !$vendor->full_address) return;

        $payload = [
            'recipient' => $user->name ?: $vendor->name,
            'phone' => $user->phone ?: '-',
            'country' => $vendor->country ?: 'Indonesia',
            'province' => $vendor->province,
            'province_id' => $vendor->province_id,
            'city' => $vendor->city,
            'city_id' => $vendor->city_id,
            'district' => $vendor->district,
            'district_id' => $vendor->district_id,
            'village' => $vendor->village,
            'village_id' => $vendor->village_id,
            'postal_code' => $vendor->postal_code,
            'rajaongkir_destination_id' => $vendor->rajaongkir_destination_id,
            'latitude' => $vendor->latitude,
            'longitude' => $vendor->longitude,
            'full_address' => $vendor->full_address,
            'address_note' => $vendor->address_note,
        ];

        $existing = $user->addresses()
            ->where('full_address', $vendor->full_address)
            ->where('city', $vendor->city)
            ->first();

        if ($existing) {
            $existing->update($payload);
            return;
        }

        $payload['is_default'] = !$user->addresses()->exists();
        $user->addresses()->create($payload);
    }

    public function products(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        return response()->json(Product::where('vendor_id', $vendor->id)->with(['tagModels:id,slug'])->orderByDesc('id')->get());
    }

    public function storeProduct(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'required|string',
            'price'          => 'required|integer|min:1',
            'original_price' => 'nullable|integer|min:0',
            'stock'          => 'required|integer|min:0',
            'weight'         => 'required|integer|min:1|max:1000000',
            'image'          => 'nullable|string',
            'images'         => 'nullable|array|max:8',
            'images.*'       => 'string',
            'variants'       => 'nullable|array',
            'is_active'      => 'sometimes|boolean',
            'tags'           => 'required|array|min:1',
            'tags.*'         => 'string|max:50',
        ]);

        $images = $data['images'] ?? [];
        $coverImage = $data['image'] ?: ($images[0] ?? $this->makeProductImage($data['name'], '#0a0a0a'));

        $product = Product::create([
            'vendor_id'  => $vendor->id,
            'category_id'=> 'elektronik',
            'name'       => $data['name'],
            'slug'       => $this->makeProductSlug($data['name']),
            'description'=> $data['description'],
            'price'      => $data['price'],
            'original_price' => $data['original_price'] ?? null,
            'stock'      => $data['stock'],
            'weight'     => $data['weight'],
            'image'      => $coverImage,
            'images'     => $images,
            'variants'   => $this->normalizeVariants($data['variants'] ?? null),
            'is_active'  => $data['is_active'] ?? true,
        ]);

        $this->syncTags($product, $data['tags']);
        return response()->json($product->load('tagModels'), 201);
    }

    public function updateProduct(Request $request, $id)
    {
        $vendor = $this->requireApprovedVendor($request);
        $product = Product::where('vendor_id', $vendor?->id)->findOrFail($id);
        $data = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'description'    => 'sometimes|string',
            'price'          => 'sometimes|integer|min:1',
            'original_price' => 'nullable|integer|min:0',
            'stock'          => 'sometimes|integer|min:0',
            'weight'         => 'sometimes|integer|min:1|max:1000000',
            'image'          => 'nullable|string',
            'images'         => 'nullable|array|max:8',
            'images.*'       => 'string',
            'variants'       => 'nullable|array',
            'is_active'      => 'sometimes|boolean',
            'tags'           => 'sometimes|array',
            'tags.*'         => 'string|max:50',
        ]);
        $tags = $data['tags'] ?? null;
        unset($data['tags']);
        if (array_key_exists('variants', $data)) {
            $data['variants'] = $this->normalizeVariants($data['variants']);
        }
        $oldStock = (int) $product->stock;
        $product->update($data);
        if (array_key_exists('stock', $data) && $oldStock <= 0 && (int) $product->stock > 0) {
            $this->stockNotifications->notifyWishlistRestocked($product);
        }
        if ($tags !== null) $this->syncTags($product, $tags);
        return response()->json($product->load('tagModels'));
    }

    public function deleteProduct(Request $request, $id)
    {
        $vendor = $this->requireApprovedVendor($request);
        $product = Product::where('vendor_id', $vendor?->id)->findOrFail($id);
        if (OrderItem::where('product_id', $product->id)->exists()) {
            $product->update(['is_active' => false, 'stock' => 0]);
            return response()->json(['ok' => true, 'soft' => true]);
        }
        $product->delete();
        return response()->json(['ok' => true]);
    }

    public function orders(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        $search = trim((string) $request->query('search'));
        $page = OrderItem::where('vendor_id', $vendor->id)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('product_name', 'like', "%{$search}%")
                        ->orWhereHas('order', function ($order) use ($search) {
                            $order->where('order_number', 'like', "%{$search}%")
                                ->orWhere('tracking_no', 'like', "%{$search}%")
                                ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                        });
                });
            })
            ->with([
                'product:id,name,slug,image',
                'order.user:id,name,email,phone',
                'order.address',
                'order.payment:id,order_id,method_name,status,paid_at',
            ])
            ->orderByDesc('id')->paginate(30);

        $page->getCollection()->each(function ($item) {
            if ($item->order) $this->applyAddressSnapshot($item->order);
        });

        return response()->json($page);
    }

    public function vouchers(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        return response()->json(
            SellerVoucher::where('vendor_id', $vendor->id)
                ->with('products:id,name,image,price')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function storeVoucher(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        $data = $this->validateVoucher($request);
        $productIds = $data['product_ids'] ?? [];
        unset($data['product_ids']);
        $data['vendor_id'] = $vendor->id;
        $data['code'] = strtoupper(trim($data['code']));

        if (SellerVoucher::where('vendor_id', $vendor->id)->where('code', $data['code'])->exists()) {
            return response()->json(['message' => 'Kode voucher sudah dipakai'], 422);
        }

        $voucher = SellerVoucher::create($data);
        $this->syncVoucherProducts($voucher, $vendor->id, $productIds);
        return response()->json($voucher->load('products:id,name,image,price'), 201);
    }

    public function updateVoucher(Request $request, $id)
    {
        $vendor = $this->requireApprovedVendor($request);
        $voucher = SellerVoucher::where('vendor_id', $vendor->id)->findOrFail($id);
        $data = $this->validateVoucher($request, true);
        $productIds = $data['product_ids'] ?? null;
        unset($data['product_ids']);
        if (isset($data['code'])) $data['code'] = strtoupper(trim($data['code']));
        if (isset($data['code']) && SellerVoucher::where('vendor_id', $vendor->id)->where('code', $data['code'])->where('id', '!=', $voucher->id)->exists()) {
            return response()->json(['message' => 'Kode voucher sudah dipakai'], 422);
        }
        $voucher->update($data);
        if ($productIds !== null) $this->syncVoucherProducts($voucher, $vendor->id, $productIds);
        return response()->json($voucher->load('products:id,name,image,price'));
    }

    public function deleteVoucher(Request $request, $id)
    {
        $vendor = $this->requireApprovedVendor($request);
        SellerVoucher::where('vendor_id', $vendor->id)->findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    public function processOrder(Request $request, $id)
    {
        $vendor = $this->requireApprovedVendor($request);
        $order = Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor?->id))
            ->with(['user'])
            ->findOrFail($id);

        if (!in_array($order->status, ['PAID', 'PROCESSING'])) {
            return response()->json(['message' => 'Pesanan hanya bisa diproses setelah pembayaran berhasil.'], 422);
        }
        if ($order->status === 'PAID') {
            $order->update(['status' => 'PROCESSING']);
        }

        return response()->json(['ok' => true, 'status' => $order->fresh()->status]);
    }

    public function shipOrder(Request $request, $id)
    {
        $vendor = $this->requireApprovedVendor($request);
        $order = Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor?->id))
            ->with(['user', 'address', 'items' => fn($q) => $q->where('vendor_id', $vendor->id)->with('product:id,weight')])
            ->findOrFail($id);

        if ($order->status !== 'PROCESSING') {
            return response()->json(['message' => 'Pesanan hanya bisa masuk pengiriman setelah pembayaran berhasil dan statusnya Diproses.'], 422);
        }
        $address = $this->orderAddress($order);
        if (!$this->hasCoords($vendor)) {
            return response()->json(['message' => 'Lengkapi pin lokasi toko di Profil Toko sebelum mengirim pesanan.'], 422);
        }
        if (!$address || !$this->hasCoords($address)) {
            return response()->json(['message' => 'Alamat penerima belum punya koordinat. Minta buyer melengkapi pin alamat di profilnya sebelum pesanan dikirim.'], 422);
        }

        $tracking = $order->tracking_no ?: $this->makeTrackingNumber($order->courier_name ?? 'JNE');
        $rajaongkirOrderNo = $order->rajaongkir_order_no;
        $shippingPayload = $order->shipping_payload ?: [];

        // Integrasi RajaOngkir SEPENUHNYA opsional.
        // Hanya dipanggil saat admin mengaktifkan toggle DAN API key terisi.
        // Kalau gagal apapun (network, response error, exception) — kita IGNORE dan
        // lanjutkan manual update status. Tidak boleh bikin user dapat 500.
        try {
            $rajaongkir = app(RajaOngkirService::class);
            if (!$rajaongkirOrderNo && $rajaongkir->isConfigured() && $address) {
                if (!$vendor->rajaongkir_destination_id) {
                    try {
                        $resolved = $rajaongkir->resolveDestinationId($vendor);
                        if ($resolved) $vendor->forceFill(['rajaongkir_destination_id' => $resolved])->save();
                    } catch (\Throwable $e) { \Log::warning('RajaOngkir resolveDestinationId(vendor): ' . $e->getMessage()); }
                }
                if (!$address->rajaongkir_destination_id && $order->address) {
                    try {
                        $resolved = $rajaongkir->resolveDestinationId($address);
                        if ($resolved) $order->address->forceFill(['rajaongkir_destination_id' => $resolved])->save();
                    } catch (\Throwable $e) { \Log::warning('RajaOngkir resolveDestinationId(address): ' . $e->getMessage()); }
                }
                $create = $rajaongkir->createOrder($this->buildRajaOngkirOrderPayload($order, $vendor));
                $raw = $create['raw'] ?? null;
                $data = $create['data'] ?? [];
                $rajaongkirOrderNo = $data['order_no'] ?? $data['order_number'] ?? $data['order_id'] ?? null;
                $tracking = $data['awb'] ?? $data['tracking_no'] ?? $tracking;
                $shippingPayload['rajaongkir_create_order'] = $raw;
            }
        } catch (\Throwable $e) {
            \Log::warning('RajaOngkir create order failed: ' . $e->getMessage(), ['order_id' => $order->id]);
            $shippingPayload['rajaongkir_error'] = $e->getMessage();
            // JANGAN re-throw — biarkan pesanan tetap update manual.
        }

        $order->update([
            'status' => 'IN_TRANSIT',
            'shipped_at' => now(),
            'tracking_no' => $tracking,
            'rajaongkir_order_no' => $rajaongkirOrderNo,
            'shipping_payload' => $shippingPayload,
        ]);

        // Notifikasi ke pembeli — best-effort, tidak boleh bikin endpoint gagal.
        if ($order->user) {
            try {
                \App\Models\UserNotification::send(
                    $order->user->id,
                    'ORDER_IN_TRANSIT',
                    'Pesanan dalam perjalanan',
                    "Pesanan #{$order->order_number} sudah masuk perjalanan dengan nomor resi {$tracking}.",
                    '/orders/' . $order->id,
                    'INFO',
                    ['order_id' => $order->id, 'order_number' => $order->order_number, 'tracking_no' => $tracking]
                );
            } catch (\Throwable $e) {
                \Log::warning('Notifikasi ORDER_SHIPPED gagal: ' . $e->getMessage(), ['order_id' => $order->id]);
            }
        }

        return response()->json(['ok' => true, 'tracking_no' => $tracking]);
    }

    public function arriveOrder(Request $request, $id)
    {
        $vendor = $this->requireApprovedVendor($request);
        $order = Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor?->id))
            ->with(['user'])
            ->findOrFail($id);

        if ($order->status !== 'IN_TRANSIT') {
            return response()->json(['message' => 'Pesanan hanya bisa ditandai sampai jika sedang dalam perjalanan.'], 422);
        }

        $payload = $order->shipping_payload ?: [];
        $payload['arrived_at'] = now()->toIso8601String();
        $payload['arrived_by_vendor_id'] = $vendor->id;

        $order->update([
            'status' => 'ARRIVED',
            'shipping_payload' => $payload,
        ]);

        if ($order->user) {
            try {
                \App\Models\UserNotification::send(
                    $order->user->id,
                    'ORDER_ARRIVED',
                    'Pesanan telah sampai',
                    "Seller menandai pesanan #{$order->order_number} telah sampai. Silakan konfirmasi diterima atau ajukan belum diterima jika barang belum Anda terima.",
                    '/orders/' . $order->id,
                    'INFO',
                    ['order_id' => $order->id, 'order_number' => $order->order_number]
                );
            } catch (\Throwable $e) {
                \Log::warning('Notifikasi ORDER_ARRIVED gagal: ' . $e->getMessage(), ['order_id' => $order->id]);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function applyAddressSnapshot(Order $order): void
    {
        if (!$order->address_snapshot) return;
        $order->setRelation('address', new Address($order->address_snapshot));
    }

    private function orderAddress(Order $order): ?Address
    {
        if ($order->address_snapshot) {
            return new Address($order->address_snapshot);
        }
        return $order->address;
    }

    private function hasCoords($model): bool
    {
        return $model && $model->latitude !== null && $model->longitude !== null;
    }

    private function buildRajaOngkirOrderPayload(Order $order, Vendor $vendor): array
    {
        $address = $this->orderAddress($order);
        $user = $order->user;
        $items = $order->items->map(function ($item) {
            return [
                'product_name' => $item->product_name,
                'product_variant_name' => $item->variant_selection ?: '-',
                'product_price' => (int) $item->price,
                'product_weight' => max(1, (int) ($item->product?->weight ?? 500)),
                'product_width' => 10,
                'product_height' => 10,
                'product_length' => 10,
                'qty' => (int) $item->quantity,
                'subtotal' => (int) $item->price * (int) $item->quantity,
            ];
        })->values()->all();

        return [
            'order_date' => now()->toDateString(),
            'brand_name' => $vendor->name,
            'shipper_name' => $vendor->name,
            'shipper_phone' => $vendor->user?->phone ?: '081234567890',
            'shipper_destination_id' => (int) ($vendor->rajaongkir_destination_id ?: 0),
            'shipper_address' => $vendor->full_address ?: trim("{$vendor->village}, {$vendor->district}, {$vendor->city}"),
            'origin_pin_point' => $vendor->latitude && $vendor->longitude ? "{$vendor->latitude}, {$vendor->longitude}" : null,
            'shipper_email' => $vendor->user?->email ?: config('mail.from.address', 'admin@example.com'),
            'receiver_name' => $address?->recipient,
            'receiver_phone' => $address?->phone,
            'receiver_destination_id' => (int) ($address?->rajaongkir_destination_id ?: 0),
            'receiver_address' => $address?->full_address,
            'receiver_email' => $user?->email,
            'destination_pin_point' => $address?->latitude && $address?->longitude ? "{$address->latitude}, {$address->longitude}" : null,
            'shipping' => $order->courier_name,
            'shipping_type' => $order->courier_service ?: $order->shipping_type ?: 'REG',
            'payment_method' => 'BANK TRANSFER',
            'shipping_cost' => (int) $order->shipping,
            'shipping_cashback' => (int) $order->shipping_cashback,
            'service_fee' => (int) $order->shipping_service_fee,
            'additional_cost' => 0,
            'grand_total' => (int) $order->total,
            'cod_value' => 0,
            'insurance_value' => (float) $order->insurance,
            'order_details' => $items,
        ];
    }

    public function dismissWarning(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        $vendor->update(['warning_dismissed_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function finishTour(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        if (!$vendor->tour_completed_at) {
            $vendor->update(['tour_completed_at' => now()]);
        }
        return response()->json(['ok' => true]);
    }

    public function updateUsername(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
        ], [
            'username.regex' => 'Username hanya boleh huruf kecil, angka, garis bawah, dan tanda hubung. Harus mulai dengan huruf/angka.',
        ]);

        $newUsername = strtolower(trim($data['username']));

        if (ReservedUsernames::isReserved($newUsername)) {
            return response()->json(['message' => 'Username ini tidak diperbolehkan (dipakai untuk halaman sistem).'], 422);
        }

        if (Vendor::where('username', $newUsername)->where('id', '!=', $vendor->id)->exists()) {
            return response()->json(['message' => 'Username sudah dipakai toko lain.'], 422);
        }

        // Cooldown 1 minggu (kecuali belum pernah set / username masih default auto-generated)
        if ($vendor->username !== $newUsername && $vendor->username_changed_at) {
            $next = $vendor->username_changed_at->copy()->addDays(7);
            if ($next->isFuture()) {
                $diff = now()->diffForHumans($next, ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]);
                return response()->json([
                    'message'     => "Username terakhir diubah {$vendor->username_changed_at->diffForHumans()}. Coba lagi dalam {$diff}.",
                    'next_change' => $next->toIso8601String(),
                ], 422);
            }
        }

        $vendor->update([
            'username'            => $newUsername,
            'username_changed_at' => now(),
        ]);

        return response()->json(['ok' => true, 'username' => $newUsername]);
    }

    private function makeTrackingNumber(string $courierName): string
    {
        // Map courier → prefix kode
        $name = strtoupper($courierName);
        $prefix = match (true) {
            str_contains($name, 'JNE')      => 'JNE',
            str_contains($name, 'J&T') || str_contains($name, 'JNT') => 'JNT',
            str_contains($name, 'SICEPAT')  => 'SCP',
            str_contains($name, 'ANTERAJA') => 'ATJ',
            str_contains($name, 'POS')      => 'POS',
            str_contains($name, 'GOSEND') || str_contains($name, 'GO-JEK') => 'GSD',
            str_contains($name, 'GRAB')     => 'GRB',
            str_contains($name, 'NINJA')    => 'NJV',
            str_contains($name, 'TIKI')     => 'TKI',
            str_contains($name, 'WAHANA')   => 'WHN',
            default                         => 'EXP',
        };
        $number = str_pad((string) random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT);
        return $prefix . $number;
    }

    private function requireApprovedVendor(Request $request): Vendor
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) abort(404, 'Belum punya toko');
        if ($vendor->verification_status === 'PENDING') {
            abort(403, 'Toko Anda masih menunggu verifikasi admin');
        }
        if ($vendor->verification_status === 'REJECTED') {
            abort(403, 'Verifikasi toko ditolak' . ($vendor->verification_note ? ': ' . $vendor->verification_note : ''));
        }
        if ($vendor->verification_status !== 'APPROVED') {
            abort(403, 'Toko belum terverifikasi');
        }
        return $vendor;
    }

    private function validateVoucher(Request $request, bool $partial = false): array
    {
        if ($request->has('code')) {
            $request->merge([
                'code' => strtoupper(preg_replace('/\s+/', '-', trim((string) $request->input('code')))),
            ]);
        }

        $sometimes = $partial ? 'sometimes|' : '';
        $data = $request->validate([
            'code' => $sometimes . 'required|string|max:30|regex:/^[A-Za-z0-9_-]+$/',
            'type' => $sometimes . 'required|in:FIXED,PERCENT',
            'value' => $sometimes . 'required|integer|min:1',
            'min_subtotal' => 'sometimes|integer|min:0',
            'max_discount' => 'nullable|integer|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'product_ids' => 'sometimes|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $type = $data['type'] ?? $request->input('type');
        if ($type === 'PERCENT' && isset($data['value']) && (int) $data['value'] > 100) {
            throw ValidationException::withMessages([
                'value' => 'Voucher persen maksimal 100%.',
            ]);
        }

        if (!empty($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return $data;
    }

    private function syncVoucherProducts(SellerVoucher $voucher, int $vendorId, array $productIds): void
    {
        $validIds = Product::where('vendor_id', $vendorId)->whereIn('id', $productIds)->pluck('id')->all();
        $voucher->products()->sync($validIds);
    }

    private function makeProductSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'produk';
        if (strlen($base) > 50) $base = substr($base, 0, 50);
        do {
            $candidate = $base . '-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Product::where('slug', $candidate)->exists());
        return $candidate;
    }

    private function generateUsername(string $name): string
    {
        $base = preg_replace('/[^a-z0-9\-_]/', '', Str::slug($name)) ?: 'toko';
        if (strlen($base) < 3) $base = 'toko-' . $base;
        if (strlen($base) > 28) $base = substr($base, 0, 28);
        if (ReservedUsernames::isReserved($base)) $base = $base . '-id';
        $candidate = $base;
        $n = 1;
        while (Vendor::where('username', $candidate)->exists()) {
            $n++;
            $suffix = '-' . $n;
            $candidate = substr($base, 0, 30 - strlen($suffix)) . $suffix;
        }
        return $candidate;
    }

    private function syncTags(Product $product, array $rawTags): void
    {
        $oldIds = $product->tagModels()->pluck('tags.id')->all();
        $ids = [];
        foreach ($rawTags as $raw) {
            $slug = Str::slug(strtolower(trim($raw)));
            if (!$slug) continue;
            $tag = Tag::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $ids[] = $tag->id;
        }
        $product->tagModels()->sync(array_unique($ids));
        // Update product_count
        Tag::whereIn('id', array_unique(array_merge($oldIds, $ids)))->each(function ($t) {
            $t->product_count = $t->products()->count();
            $t->save();
        });
    }

    private function makeAvatar(string $initial, string $color): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='50' fill='{$color}'/><text x='50' y='65' text-anchor='middle' font-size='42' font-weight='800' fill='#fff' font-family='Inter,sans-serif'>" . htmlspecialchars($initial) . "</text></svg>";
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
    private function makeBanner(string $name, string $color): string
    {
        // Cakep gradient + soft pattern dots, tidak butuh upload manual
        $hash = md5($name);
        $c1 = '#' . substr($hash, 0, 6);
        $c2 = '#' . substr($hash, 6, 6);
        $label = htmlspecialchars(mb_substr($name, 0, 24));
        $svg = <<<SVG
<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 300'>
  <defs>
    <linearGradient id='g' x1='0' y1='0' x2='1' y2='1'>
      <stop offset='0%' stop-color='{$c1}'/>
      <stop offset='100%' stop-color='{$c2}'/>
    </linearGradient>
    <pattern id='dots' x='0' y='0' width='40' height='40' patternUnits='userSpaceOnUse'>
      <circle cx='20' cy='20' r='1.5' fill='rgba(255,255,255,0.18)'/>
    </pattern>
  </defs>
  <rect width='1200' height='300' fill='url(#g)'/>
  <rect width='1200' height='300' fill='url(#dots)'/>
  <circle cx='1050' cy='80' r='180' fill='rgba(255,255,255,0.08)'/>
  <circle cx='1150' cy='250' r='90'  fill='rgba(0,0,0,0.10)'/>
  <text x='80' y='170' font-size='52' font-weight='900' fill='#fff' font-family='Inter,sans-serif' letter-spacing='-1.2'>{$label}</text>
</svg>
SVG;
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
    private function makeProductImage(string $name, string $color): string
    {
        $label = htmlspecialchars(mb_substr($name, 0, 16));
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 400'><rect width='400' height='400' fill='{$color}'/><text x='200' y='220' text-anchor='middle' font-size='28' font-weight='700' fill='#fff' font-family='Inter,sans-serif'>{$label}</text></svg>";
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    private function normalizeVariants(?array $variants): ?array
    {
        if (!$variants) return null;

        $clean = [];
        foreach ($variants as $name => $options) {
            $name = trim((string) $name);
            if ($name === '' || !is_array($options)) continue;

            $normalizedOptions = [];
            foreach ($options as $option) {
                if (is_string($option)) {
                    $label = trim($option);
                    $image = null;
                } elseif (is_array($option)) {
                    $label = trim((string) ($option['label'] ?? $option['name'] ?? $option['value'] ?? ''));
                    $image = isset($option['image']) ? trim((string) $option['image']) : null;
                } else {
                    continue;
                }
                if ($label === '' || mb_strlen($label) > 50) continue;
                $row = ['label' => $label];
                if ($image) $row['image'] = $image;
                $normalizedOptions[] = $row;
            }

            if ($normalizedOptions) {
                $clean[mb_substr($name, 0, 50)] = $normalizedOptions;
            }
        }

        return $clean ?: null;
    }

    private function validateInlineImage(string $value, string $label, int $maxMb): void
    {
        if (str_starts_with($value, 'data:image/svg+xml;utf8,')) {
            if (strlen($value) > 200 * 1024) {
                throw ValidationException::withMessages([
                    strtolower(str_replace(' ', '_', $label)) => "{$label} terlalu besar.",
                ]);
            }
            return;
        }

        if (!preg_match('#^data:(image/(?:jpeg|jpg|png|webp|gif));base64,([A-Za-z0-9+/=\r\n]+)$#i', $value, $m)) {
            throw ValidationException::withMessages([
                strtolower(str_replace(' ', '_', $label)) => "{$label} harus berupa gambar JPG, PNG, WebP, atau GIF.",
            ]);
        }

        $bytes = base64_decode($m[2], true);
        if ($bytes === false) {
            throw ValidationException::withMessages([
                strtolower(str_replace(' ', '_', $label)) => "{$label} tidak bisa dibaca. Coba pilih file lain.",
            ]);
        }

        if (strlen($bytes) > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessages([
                strtolower(str_replace(' ', '_', $label)) => "{$label} maksimal {$maxMb}MB.",
            ]);
        }

        if (@getimagesizefromstring($bytes) === false) {
            throw ValidationException::withMessages([
                strtolower(str_replace(' ', '_', $label)) => "{$label} bukan file gambar yang valid.",
            ]);
        }
    }
}
