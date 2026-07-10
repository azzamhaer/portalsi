<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Vendor;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories()
    {
        return response()->json(
            Category::whereNull('parent_id')
                ->where('is_active', true)
                ->with(['children' => fn($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }

    /** Suggestion live untuk search bar — produk + toko + tag */
    public function searchSuggest(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (strlen($q) < 1) {
            return response()->json(['products' => [], 'vendors' => [], 'tags' => []]);
        }
        $products = $this->rankedProductsForSearch($q, 6)
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'image' => $p->image,
                'price' => $p->price,
            ])
            ->values();
        $vendors = $this->rankedVendorsForSearch($q, 4)
            ->map(fn($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'username' => $v->username,
                'avatar' => $v->avatar,
                'city' => $v->city,
            ])
            ->values();
        $tags = $this->rankedTagsForSearch($q, 6)
            ->map(fn($t) => [
                'slug' => $t->slug,
                'name' => $t->name,
                'product_count' => $t->product_count,
            ])
            ->values();
        return response()->json([
            'products' => $products,
            'vendors'  => $vendors,
            'tags'     => $tags,
        ]);
    }

    public function products(Request $request)
    {
        $q = Product::query()->where('is_active', true)
            ->whereHas('vendor', fn($v) => $v->where('moderation_mode', '!=', 'DISABLED')->where('verification_status', 'APPROVED'))
            ->with(['vendor:id,name,slug,username,city,is_official,moderation_mode', 'tagModels:id,slug'])
            ->withCount('reviews');

        if ($cat = $request->query('category')) {
            $category = Category::where('slug', $cat)->first();
            if ($category?->tag_slug) {
                $q->whereHas('tagModels', fn($t) => $t->where('slug', $category->tag_slug));
            } else {
                $q->whereHas('category', fn($c) => $c->where('slug', $cat));
            }
        }
        if ($tag = $request->query('tag')) {
            $q->whereHas('tagModels', fn($t) => $t->where('slug', $tag));
        }
        if ($tags = $request->query('tags')) {
            $list = array_filter(array_map('trim', explode(',', $tags)));
            if ($list) $q->whereHas('tagModels', fn($t) => $t->whereIn('slug', $list));
        }
        if ($vendor = $request->query('vendor')) {
            $q->where('vendor_id', $vendor);
        }
        $searchIds = null;
        if ($search = trim((string) $request->query('search', $request->query('q', '')))) {
            $searchIds = $this->rankedProductIdsForSearch($search);
            if ($searchIds) {
                $q->whereIn('id', $searchIds);
            } else {
                $q->whereRaw('1 = 0');
            }
        }
        if ($min = $request->query('min_price')) $q->where('price', '>=', (int) $min);
        if ($max = $request->query('max_price')) $q->where('price', '<=', (int) $max);
        if ($rating = $request->query('min_rating')) $q->where('rating', '>=', (float) $rating);
        if ($city = trim((string) $request->query('city'))) {
            $q->whereHas('vendor', fn($v) => $v->where('city', 'like', "%$city%"));
        }
        if ($request->boolean('official')) {
            $q->whereHas('vendor', fn($v) => $v->where('is_official', true));
        }
        if ($stock = $request->query('stock')) {
            if ($stock === 'available') $q->where('stock', '>', 0);
            if ($stock === 'empty') $q->where('stock', '<=', 0);
        }
        if ($request->boolean('flash')) $q->where('is_flash_sale', true);
        if ($ids = $request->query('ids')) $q->whereIn('id', explode(',', $ids));

        $sort = $request->query('sort', 'popular');
        if ($searchIds && $sort === 'popular') {
            $idsOrder = implode(',', array_map('intval', $searchIds));
            $q->orderByRaw("FIELD(id, {$idsOrder})")->orderByDesc('sold');
        } else {
            match ($sort) {
                'cheap'   => $q->orderBy('price'),
                'exp'     => $q->orderByDesc('price'),
                'rating'  => $q->orderByDesc('rating'),
                'newest'  => $q->orderByDesc('id'),
                default   => $q->orderByDesc('sold'),
            };
        }

        $perPage = min(100, (int) $request->query('per_page', 24));
        return response()->json($q->paginate($perPage));
    }

    public function product($idOrSlug)
    {
        $query = Product::with([
            'vendor',
            'category',
            'tagModels:id,slug',
            'reviews' => fn($q) => $q->latest()->with('user:id,name'),
        ])->withCount('reviews');
        $product = is_numeric($idOrSlug)
            ? $query->find((int) $idOrSlug)
            : $query->where('slug', $idOrSlug)->first();
        if (!$product) abort(404, 'Produk tidak ditemukan');
        $related = Product::where('id', '!=', $product->id)
            ->where('is_active', true)
            ->whereHas('tagModels', function ($q) use ($product) {
                $q->whereIn('id', $product->tagModels->pluck('id'));
            })
            ->with('tagModels:id,slug')
            ->orderByDesc('sold')
            ->take(12)
            ->get();
        return response()->json(['product' => $product, 'related' => $related]);
    }

    public function productShareImage($idOrSlug)
    {
        $product = is_numeric($idOrSlug)
            ? Product::find((int) $idOrSlug)
            : Product::where('slug', $idOrSlug)->first();
        if (!$product) abort(404, 'Produk tidak ditemukan');

        $images = is_array($product->images) ? array_values(array_filter($product->images)) : [];
        $image = trim((string) ($images[0] ?? $product->image ?? ''));

        if ($image === '') {
            return $this->generatedProductShareImage($product->name);
        }

        if (preg_match('#^data:(image/(png|jpe?g|gif|webp));base64,([A-Za-z0-9+/=\r\n]+)$#i', $image, $m)) {
            $bytes = base64_decode($m[3], true);
            if ($bytes !== false) {
                return response($bytes, 200)
                    ->header('Content-Type', strtolower($m[1]) === 'image/jpg' ? 'image/jpeg' : $m[1])
                    ->header('Cache-Control', 'public, max-age=86400');
            }
        }

        if (str_starts_with($image, 'data:image/svg+xml;utf8,')) {
            return response(rawurldecode(substr($image, strlen('data:image/svg+xml;utf8,'))), 200)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Cache-Control', 'public, max-age=86400');
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return redirect()->away($image, 302);
        }

        if (str_starts_with($image, '/')) {
            return redirect()->away(rtrim(config('app.url'), '/') . $image, 302);
        }

        return $this->generatedProductShareImage($product->name);
    }

    public function vendors(Request $request)
    {
        $q = Vendor::query();
        if ($request->query('filter') === 'official' || $request->boolean('official')) $q->where('is_official', true);
        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn($w) => $w->where('name', 'like', "%$search%")->orWhere('username', 'like', "%$search%")->orWhere('description', 'like', "%$search%"));
        }
        if ($city = trim((string) $request->query('city'))) {
            $q->where('city', 'like', "%$city%");
        }
        if ($rating = $request->query('min_rating')) $q->where('rating', '>=', (float) $rating);
        match ($request->query('filter')) {
            'rating' => $q->orderByDesc('rating'),
            'sold'   => $q->orderByDesc('total_sold'),
            'newest' => $q->orderByDesc('id'),
            default  => $q->orderByDesc('is_official')->orderByDesc('total_sold'),
        };
        // Hanya tampilkan vendor APPROVED & tidak DISABLED ke publik
        $q->where('verification_status', 'APPROVED')->where('moderation_mode', '!=', 'DISABLED');
        return response()->json($q->get());
    }

    public function vendor(Request $request, $idOrUsername)
    {
        $key = strtolower((string) $idOrUsername);
        $vendor = is_numeric($idOrUsername)
            ? Vendor::find((int) $idOrUsername)
            : Vendor::where('username', $key)->orWhere('slug', $key)->first();

        if (!$vendor) abort(404, 'Toko tidak ditemukan');

        // Block halaman publik kalau vendor belum APPROVED atau DISABLED total — kecuali admin atau pemilik toko
        $viewer = auth('sanctum')->user();
        $isOwner = $viewer && $viewer->id === $vendor->user_id;
        $isAdmin = $viewer && $viewer->role === 'ADMIN';
        if (($vendor->verification_status !== 'APPROVED' || $vendor->moderation_mode === 'DISABLED') && !$isOwner && !$isAdmin) {
            abort(404, 'Toko tidak ditemukan');
        }

        // Rating toko = AVG rating semua produk yang punya review
        $avgRating = (float) \App\Models\Product::where('vendor_id', $vendor->id)
            ->whereHas('reviews')->avg('rating');
        if (abs($vendor->rating - $avgRating) > 0.01) {
            $vendor->update(['rating' => round($avgRating, 2)]);
        }

        $products = $vendor->products()->where('is_active', true)->with('tagModels:id,slug')->withCount('reviews')->orderByDesc('sold')->get();
        // Try to detect auth (sanctum guard) silently
        $user = auth('sanctum')->user();
        $isFollowing = false;
        if ($user) {
            $isFollowing = \App\Models\VendorFollower::where('vendor_id', $vendor->id)
                            ->where('user_id', $user->id)->exists();
        }
        return response()->json([
            'vendor' => $vendor,
            'products' => $products,
            'is_following' => $isFollowing,
        ]);
    }

    public function homeData()
    {
        return response()->json([
            'tags'        => Tag::orderByDesc('product_count')->take(20)->get(['slug', 'name', 'product_count as count']),
            'categories'  => Category::whereNull('parent_id')->where('is_active', true)->where('featured_home', true)
                ->with(['children' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
                ->orderBy('sort_order')->orderBy('name')->take(12)->get(),
            'flashSale'   => Product::where('is_flash_sale', true)->where('is_active', true)->with('tagModels:id,slug')->withCount('reviews')->take(12)->get(),
            'recommended' => Product::where('is_active', true)->with('tagModels:id,slug')->withCount('reviews')->orderByDesc('sold')->take(24)->get(),
            'official'    => Vendor::where('is_official', true)->where('verification_status', 'APPROVED')->take(6)->get(),
        ]);
    }

    private function rankedProductIdsForSearch(string $query): array
    {
        return $this->rankedProductsForSearch($query, 500)->pluck('id')->all();
    }

    private function generatedProductShareImage(string $name)
    {
        $safeName = htmlspecialchars(mb_strimwidth($name, 0, 72, '...'), ENT_QUOTES, 'UTF-8');
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='1200' height='630' viewBox='0 0 1200 630'>"
            . "<rect width='1200' height='630' fill='#f5f5f5'/>"
            . "<rect x='90' y='110' width='1020' height='410' rx='36' fill='#ffffff'/>"
            . "<text x='600' y='310' text-anchor='middle' font-size='56' font-weight='800' fill='#171717' font-family='Arial,sans-serif'>{$safeName}</text>"
            . "<text x='600' y='380' text-anchor='middle' font-size='28' fill='#737373' font-family='Arial,sans-serif'>MPSI Marketplace</text>"
            . "</svg>";

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function rankedProductsForSearch(string $query, int $limit)
    {
        $search = $this->prepareSearch($query);
        if (!$search['tokens']) return collect();

        $direct = $search['direct'];
        $products = Product::where('is_active', true)
            ->whereHas('vendor', fn($v) => $v->where('moderation_mode', '!=', 'DISABLED')->where('verification_status', 'APPROVED'))
            ->with(['vendor:id,name,username,city,moderation_mode,verification_status', 'category:id,name,slug,tag_slug', 'tagModels:id,slug,name,product_count'])
            ->select('id', 'vendor_id', 'category_id', 'name', 'slug', 'description', 'image', 'price', 'sold', 'rating')
            ->where(function ($w) use ($direct) {
                $w->where('name', 'like', "%{$direct}%")
                  ->orWhere('description', 'like', "%{$direct}%")
                  ->orWhereHas('tagModels', fn($t) => $t->where('slug', 'like', "%{$direct}%")->orWhere('name', 'like', "%{$direct}%"))
                  ->orWhereHas('category', fn($c) => $c->where('name', 'like', "%{$direct}%")->orWhere('slug', 'like', "%{$direct}%"))
                  ->orWhereHas('vendor', fn($v) => $v->where('name', 'like', "%{$direct}%")->orWhere('username', 'like', "%{$direct}%"));
            })
            ->limit(1400)
            ->get();

        if ($products->count() < 20) {
            $fallback = Product::where('is_active', true)
                ->whereHas('vendor', fn($v) => $v->where('moderation_mode', '!=', 'DISABLED')->where('verification_status', 'APPROVED'))
                ->with(['vendor:id,name,username,city,moderation_mode,verification_status', 'category:id,name,slug,tag_slug', 'tagModels:id,slug,name,product_count'])
                ->select('id', 'vendor_id', 'category_id', 'name', 'slug', 'description', 'image', 'price', 'sold', 'rating')
                ->orderByDesc('sold')
                ->limit(1200)
                ->get();
            $products = $products->merge($fallback)->unique('id')->values();
        }

        return $products
            ->map(function ($product) use ($search) {
                $product->search_score = $this->scoreSearchText($search, $this->productSearchText($product)) + min(15, (int) $product->sold / 3);
                return $product;
            })
            ->filter(fn($product) => $product->search_score >= max(18, $search['base_count'] * 14))
            ->sort(fn($a, $b) => [$b->search_score, $b->sold, $b->rating] <=> [$a->search_score, $a->sold, $a->rating])
            ->take($limit)
            ->values();
    }

    private function rankedVendorsForSearch(string $query, int $limit)
    {
        $search = $this->prepareSearch($query);
        if (!$search['tokens']) return collect();

        return Vendor::where('verification_status', 'APPROVED')
            ->where('moderation_mode', '!=', 'DISABLED')
            ->select('id', 'name', 'username', 'avatar', 'city', 'description', 'total_sold', 'rating')
            ->orderByDesc('total_sold')
            ->limit(400)
            ->get()
            ->map(function ($vendor) use ($search) {
                $vendor->search_score = $this->scoreSearchText($search, "{$vendor->name} {$vendor->username} {$vendor->city} {$vendor->description}") + min(10, (int) $vendor->total_sold / 5);
                return $vendor;
            })
            ->filter(fn($vendor) => $vendor->search_score >= max(15, $search['base_count'] * 12))
            ->sort(fn($a, $b) => [$b->search_score, $b->total_sold, $b->rating] <=> [$a->search_score, $a->total_sold, $a->rating])
            ->take($limit)
            ->values();
    }

    private function rankedTagsForSearch(string $query, int $limit)
    {
        $search = $this->prepareSearch($query);
        if (!$search['tokens']) return collect();

        return Tag::select('slug', 'name', 'product_count')
            ->orderByDesc('product_count')
            ->limit(500)
            ->get()
            ->map(function ($tag) use ($search) {
                $tag->search_score = $this->scoreSearchText($search, "{$tag->slug} {$tag->name}") + min(12, (int) $tag->product_count / 4);
                return $tag;
            })
            ->filter(fn($tag) => $tag->search_score >= 14)
            ->sort(fn($a, $b) => [$b->search_score, $b->product_count] <=> [$a->search_score, $a->product_count])
            ->take($limit)
            ->values();
    }

    private function prepareSearch(string $query): array
    {
        $normalized = $this->normalizeSearchText($query);
        $tokens = [];
        $baseTokens = [];
        foreach (preg_split('/\s+/', $normalized) ?: [] as $token) {
            $token = trim($token);
            if ($token === '' || in_array($token, $this->searchStopWords(), true)) continue;
            $base = $this->stemSearchToken($token);
            $baseTokens[] = $base;
            $tokens[] = $base;
            foreach ($this->searchSynonyms()[$token] ?? [] as $synonym) {
                $tokens[] = $this->stemSearchToken($synonym);
            }
        }

        return [
            'direct' => $normalized,
            'base_count' => max(1, count(array_unique(array_filter($baseTokens)))),
            'tokens' => array_values(array_unique(array_filter($tokens, fn($t) => strlen($t) >= 2))),
        ];
    }

    private function productSearchText(Product $product): string
    {
        $tags = $product->tagModels?->map(fn($t) => "{$t->slug} {$t->name}")->implode(' ') ?? '';
        $category = $product->category ? "{$product->category->name} {$product->category->slug} {$product->category->tag_slug}" : '';
        $vendor = $product->vendor ? "{$product->vendor->name} {$product->vendor->username} {$product->vendor->city}" : '';
        return "{$product->name} {$product->description} {$tags} {$category} {$vendor}";
    }

    private function scoreSearchText(array $search, string $text): int
    {
        $haystack = $this->normalizeSearchText($text);
        $hayTokens = array_values(array_unique(array_filter(array_map(
            fn($token) => $this->stemSearchToken($token),
            preg_split('/\s+/', $haystack) ?: []
        ), fn($token) => strlen($token) >= 2)));

        if (!$hayTokens || !$search['tokens']) return 0;

        $score = 0;
        if ($search['direct'] && str_contains($haystack, $search['direct'])) {
            $score += 90;
        }

        foreach ($search['tokens'] as $needle) {
            $best = 0;
            foreach ($hayTokens as $token) {
                if ($token === $needle) {
                    $best = max($best, 45);
                } elseif (str_contains($token, $needle) || str_contains($needle, $token)) {
                    $best = max($best, 34);
                } else {
                    $sim = $this->tokenSimilarity($needle, $token);
                    if ($sim >= 0.86) $best = max($best, 30);
                    elseif ($sim >= 0.76) $best = max($best, 22);
                    elseif ($sim >= 0.66) $best = max($best, 14);
                }
            }
            $score += $best;
        }

        return $score;
    }

    private function normalizeSearchText(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]+/u', ' ', $text) ?? $text;
        $text = str_replace('-', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function stemSearchToken(string $token): string
    {
        $token = preg_replace('/^(me|mem|men|meng|meny|ber|ter|di|ke|se|pe|pem|pen|peng|peny)/', '', $token) ?? $token;
        $token = preg_replace('/(nya|lah|kah|ku|mu|an|kan|i)$/', '', $token) ?? $token;
        return $token ?: '';
    }

    private function tokenSimilarity(string $a, string $b): float
    {
        if ($a === $b) return 1.0;
        $max = max(strlen($a), strlen($b));
        if ($max === 0) return 0.0;
        return max(0, 1 - (levenshtein($a, $b) / $max));
    }

    private function searchStopWords(): array
    {
        return [
            'untuk', 'buat', 'yang', 'dan', 'atau', 'di', 'ke', 'dari', 'dengan', 'ini', 'itu',
            'ada', 'cari', 'mencari', 'produk', 'barang', 'beli', 'ingin', 'mau',
            'the', 'for', 'and', 'or', 'with', 'of', 'a', 'an',
        ];
    }

    private function searchSynonyms(): array
    {
        return [
            'jogging' => ['joging', 'lari', 'running', 'run'],
            'joging' => ['jogging', 'lari', 'running', 'run'],
            'running' => ['jogging', 'joging', 'lari'],
            'lari' => ['jogging', 'joging', 'running'],
            'hp' => ['handphone', 'smartphone', 'ponsel'],
            'handphone' => ['hp', 'smartphone', 'ponsel'],
            'smartphone' => ['hp', 'handphone', 'ponsel'],
            'ponsel' => ['hp', 'handphone', 'smartphone'],
            'baju' => ['kaos', 'shirt', 'kemeja', 'pakaian'],
            'kaos' => ['baju', 'shirt', 'pakaian'],
            'sepatu' => ['sneakers', 'shoes'],
            'sneakers' => ['sepatu', 'shoes'],
            'tas' => ['bag'],
            'makeup' => ['kosmetik', 'kecantikan'],
            'kosmetik' => ['makeup', 'kecantikan'],
        ];
    }
}
