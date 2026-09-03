import { derived, writable } from 'svelte/store';

export type Lang = 'id' | 'en';

// Cookie BERSAMA lintas subdomain *.portalsi.com — sinkron dgn Landing/App/Meet/Mail.
export const LANG_COOKIE = 'portalsi_lang';
const COOKIE_DOMAIN = '.portalsi.com';

export const lang = writable<Lang>('id');

function writeCookie(l: Lang) {
	if (typeof document === 'undefined') return;
	try {
		localStorage.setItem(LANG_COOKIE, l);
	} catch {
		/* ignore */
	}
	try {
		document.cookie = `${LANG_COOKIE}=${l};path=/;max-age=31536000;samesite=lax;domain=${COOKIE_DOMAIN}`;
	} catch {
		/* ignore */
	}
	try {
		document.cookie = `${LANG_COOKIE}=${l};path=/;max-age=31536000;samesite=lax`;
	} catch {
		/* ignore */
	}
	document.documentElement.lang = l;
}

export function setLang(l: Lang) {
	lang.set(l);
	writeCookie(l);
}

export function initLang(initial?: Lang) {
	let l: Lang = initial === 'en' || initial === 'id' ? initial : 'id';
	if (typeof document !== 'undefined') {
		const m = document.cookie.match(/(?:^|;\s*)portalsi_lang=(id|en)/);
		if (m) l = m[1] as Lang;
		else {
			try {
				const s = localStorage.getItem(LANG_COOKIE);
				if (s === 'id' || s === 'en') l = s;
			} catch {
				/* ignore */
			}
		}
		document.documentElement.lang = l;
	}
	lang.set(l);
}

const DICT: Record<Lang, Record<string, string>> = {
	id: {
		// header / nav
		'nav.home': 'Beranda',
		'nav.products': 'Produk',
		'nav.categories': 'Kategori',
		'nav.orders': 'Pesanan',
		'nav.wishlist': 'Wishlist',
		'nav.cart': 'Keranjang',
		'nav.chats': 'Chat',
		'nav.openStore': 'Buka Toko',
		'nav.myStore': 'Toko Saya',
		'nav.profile': 'Profil',
		'nav.settings': 'Pengaturan',
		'nav.login': 'Masuk',
		'nav.register': 'Daftar',
		'nav.logout': 'Keluar',
		'nav.searchPlaceholder': 'Cari produk, toko...',
		'footer.securedPayment': 'Pembayaran diamankan dengan enkripsi end-to-end',
		'nav.refunds': 'Riwayat Refund',
		'nav.findStore': 'Cari toko',
		'nav.paymentOptions': 'Opsi pembayaran',
		'nav.faq': 'FAQ',
		'nav.notifications': 'Notifikasi',
		'nav.adminCenter': 'Admin Center',
		'nav.sellerCenter': 'Seller Center',
		'nav.storeStatus': 'Status Toko',
		'nav.account': 'Akun',
		'nav.menu': 'Menu',
		'header.logoutTitle': 'Keluar dari akun?',
		'header.logoutMsg': 'Anda perlu login ulang untuk mengakses pesanan, chat, dan seller center.',
		'header.searchProduct': 'Cari produk',
		'header.searchBtn': 'Cari',
		'header.searching': 'Mencari...',
		'header.searchKeyword': 'Cari kata kunci',
		'header.seeFull': 'Lihat hasil lengkap',
		'header.products': 'Produk',
		'header.stores': 'Toko',
		'header.tag': 'Tag',
		// settings
		'set.language': 'Bahasa',
		'set.languageNote': 'Preferensi bahasa berlaku di seluruh layanan Portal SI.',
		'lang.id': 'Indonesia',
		'lang.en': 'English',
		'home.searchPlaceholder': 'Cari produk, toko, brand, atau tag',
		'home.flashEyebrow': 'Penawaran Terbatas',
		'home.flashTitle': 'Penawaran terbaik hari ini',
		'home.seeAll': 'Lihat semua',
		'home.officialEyebrow': 'Toko Resmi',
		'home.officialTitle': 'Brand pilihan, terverifikasi',
		'home.recoEyebrow': 'Rekomendasi',
		'home.recoTitle': 'Produk pilihan untuk Anda',
		'home.exploreEyebrow': 'Jelajahi',
		'home.tagsTitle': 'Tag populer',
		'home.allProducts': 'Semua produk',
		'home.f1t': 'Pembayaran terjamin',
		'home.f1d': 'Dana ditahan hingga barang Anda terima.',
		'home.f2t': 'Pengiriman cepat',
		'home.f2d': 'kurir resmi, tracking real-time.',
		'home.f3t': 'Dukungan 24/7',
		'home.f3d': 'Tim siap membantu kapan saja.',
		'auth.signin': 'Masuk',
		'auth.loginSub': 'Gunakan akun Portal SI Anda untuk masuk ke marketplace.',
		'auth.loginId': 'Email atau Username Portal SI',
		'auth.password': 'Password',
		'auth.forgotPw': 'Lupa password?',
		'auth.processing': 'Memproses…',
		'auth.noAccount': 'Belum punya akun?',
		'auth.register': 'Daftar',
		'auth.welcome': 'Selamat datang',
		'auth.registerSub': 'Buat akun Portal SI untuk marketplace dan layanan Portal SI lainnya.',
		'auth.username': 'Username Portal SI',
		'auth.fullName': 'Nama Lengkap',
		'auth.email': 'Email',
		'auth.phone': 'No. HP',
		'auth.registerOk': 'Berhasil daftar. Cek email untuk verifikasi, termasuk folder Spam/Promosi.',
		'auth.haveAccount': 'Sudah punya akun?',
		'prod.sortPopular': 'Terpopuler',
		'prod.sortNewest': 'Terbaru',
		'prod.sortCheapest': 'Termurah',
		'prod.sortExpensive': 'Termahal',
		'prod.sortRating': 'Rating',
		'prod.catalogDesc': 'Jelajahi katalog produk pilihan dari toko terpercaya dengan pembayaran aman.',
		'prod.searchInCatalog': 'Cari produk di katalog',
		'prod.catalog': 'Katalog',
		'prod.allProducts': 'Semua Produk',
		'prod.removeTag': 'Hapus tag',
		'prod.loading': 'Memuat produk…',
		'prod.products': 'produk',
		'prod.page': 'halaman',
		'prod.of': 'dari',
		'cart.removeSelectedTitle': 'Hapus item terpilih?',
		'cart.removeSelectedMsg': 'Produk yang dicentang akan dihapus dari keranjang.',
		'cart.remove': 'Hapus',
		'cart.removeTitle': 'Hapus produk?',
		'cart.removeMsg': 'Produk ini akan dihapus dari keranjang.',
		'cart.title': 'Keranjang',
		'cart.loginToView': 'Login untuk melihat keranjang',
		'cart.adminNoCart': 'Admin tidak punya keranjang',
		'cart.empty': 'Keranjang masih kosong',
		'cart.startShopping': 'Mulai Belanja',
		'cart.outOfStockWarn': 'Ada produk stok habis. Hapus atau batal pilih produk tersebut sebelum checkout.',
		'cart.loginDesc': 'Masuk ke akun Anda untuk melihat dan checkout produk di keranjang.',
		'cart.adminDesc': 'Akun admin tidak bisa berbelanja. Gunakan akun pembeli.',
		'cart.emptyDesc': 'Mulai belanja produk pilihan Anda.',
		'cart.summary': 'Ringkasan',
		'cart.subtotal': 'Subtotal',
		'cart.estShipping': 'Estimasi ongkir',
		'cart.total': 'Total',
		'cart.continue': 'Lanjut Belanja',
		'co.today': 'Hari Ini',
		'co.loadAddrFail': 'Gagal memuat alamat pengiriman',
		'co.manualShipping': 'Menampilkan opsi pengiriman manual dari admin.',
		'co.enterVoucher': 'Masukkan kode voucher',
		'co.voucherInvalid': 'Voucher tidak valid',
		'co.pickAddr': 'Pilih alamat pengiriman dari profil',
		'co.pickPayment': 'Pilih metode pembayaran',
		'co.pickCourier': 'Pilih ekspedisi pengiriman',
		'co.createOrderQ': 'Buat pesanan?',
		'co.createOrder': 'Buat pesanan',
		'co.orderCreated': 'Pesanan dibuat, silakan lanjutkan pembayaran',
		'co.checkoutFail': 'Checkout gagal diproses',
		'co.title': 'Checkout',
		'co.primary': 'Utama',
		'co.noAddr': 'Belum ada alamat pengiriman',
		'co.shipping': 'Pengiriman',
		'co.paymentMethod': 'Metode Pembayaran',
		'co.voucherPlaceholder': 'Kode voucher seller',
		'co.apply': 'Apply',
		'co.paymentSummary': 'Ringkasan Pembayaran',
		'co.promoVoucher': 'Promo voucher',
		'co.subtotalAfter': 'Subtotal setelah promo',
		'co.shippingCost': 'Ongkir',
		'co.insurance': 'Asuransi',
		'co.pickAddrShort': 'Pilih alamat pengiriman',
		'co.autoRateFail': 'Gagal memuat tarif ekspedisi otomatis. Menampilkan opsi pengiriman manual.',
		'co.createOrderMsg': 'Pesanan akan dibuat dengan status menunggu pembayaran. Stok baru dikurangi setelah pembayaran berhasil.',
		'co.checking': 'Cek...',
		'co.change': 'Ubah',
		'co.processing': 'Memproses...',
		'co.pay': 'Bayar',
		'co.completePin': 'Lengkapi pin alamat'
	},
	en: {
		'nav.home': 'Home',
		'nav.products': 'Products',
		'nav.categories': 'Categories',
		'nav.orders': 'Orders',
		'nav.wishlist': 'Wishlist',
		'nav.cart': 'Cart',
		'nav.chats': 'Chats',
		'nav.openStore': 'Open a Store',
		'nav.myStore': 'My Store',
		'nav.profile': 'Profile',
		'nav.settings': 'Settings',
		'nav.login': 'Sign In',
		'nav.register': 'Sign Up',
		'nav.logout': 'Sign Out',
		'nav.searchPlaceholder': 'Search products, stores...',
		'footer.securedPayment': 'Payments secured with end-to-end encryption',
		'nav.refunds': 'Refund History',
		'nav.findStore': 'Find a store',
		'nav.paymentOptions': 'Payment options',
		'nav.faq': 'FAQ',
		'nav.notifications': 'Notifications',
		'nav.adminCenter': 'Admin Center',
		'nav.sellerCenter': 'Seller Center',
		'nav.storeStatus': 'Store Status',
		'nav.account': 'Account',
		'nav.menu': 'Menu',
		'header.logoutTitle': 'Sign out of your account?',
		'header.logoutMsg': 'You will need to sign in again to access orders, chats, and the seller center.',
		'header.searchProduct': 'Search products',
		'header.searchBtn': 'Search',
		'header.searching': 'Searching...',
		'header.searchKeyword': 'Search for',
		'header.seeFull': 'See full results',
		'header.products': 'Products',
		'header.stores': 'Stores',
		'header.tag': 'Tag',
		'set.language': 'Language',
		'set.languageNote': 'Your language preference applies across all Portal SI services.',
		'lang.id': 'Indonesia',
		'lang.en': 'English',
		'home.searchPlaceholder': 'Search products, stores, brands, or tags',
		'home.flashEyebrow': 'Limited Offer',
		'home.flashTitle': "Today's best deals",
		'home.seeAll': 'See all',
		'home.officialEyebrow': 'Official Stores',
		'home.officialTitle': 'Curated, verified brands',
		'home.recoEyebrow': 'Recommended',
		'home.recoTitle': 'Picks for you',
		'home.exploreEyebrow': 'Explore',
		'home.tagsTitle': 'Popular tags',
		'home.allProducts': 'All products',
		'home.f1t': 'Guaranteed payments',
		'home.f1d': 'Funds are held until you receive your item.',
		'home.f2t': 'Fast shipping',
		'home.f2d': 'official couriers, real-time tracking.',
		'home.f3t': '24/7 support',
		'home.f3d': 'Our team is ready to help anytime.',
		'auth.signin': 'Sign In',
		'auth.loginSub': 'Use your Portal SI account to sign in to the marketplace.',
		'auth.loginId': 'Portal SI email or username',
		'auth.password': 'Password',
		'auth.forgotPw': 'Forgot password?',
		'auth.processing': 'Processing…',
		'auth.noAccount': "Don't have an account?",
		'auth.register': 'Sign Up',
		'auth.welcome': 'Welcome',
		'auth.registerSub': 'Create a Portal SI account for the marketplace and other Portal SI services.',
		'auth.username': 'Portal SI username',
		'auth.fullName': 'Full name',
		'auth.email': 'Email',
		'auth.phone': 'Phone number',
		'auth.registerOk': 'Registration successful. Check your email to verify, including the Spam/Promotions folder.',
		'auth.haveAccount': 'Already have an account?',
		'prod.sortPopular': 'Most popular',
		'prod.sortNewest': 'Newest',
		'prod.sortCheapest': 'Lowest price',
		'prod.sortExpensive': 'Highest price',
		'prod.sortRating': 'Rating',
		'prod.catalogDesc': 'Browse a curated product catalog from trusted stores with secure payments.',
		'prod.searchInCatalog': 'Search products in the catalog',
		'prod.catalog': 'Catalog',
		'prod.allProducts': 'All Products',
		'prod.removeTag': 'Remove tag',
		'prod.loading': 'Loading products…',
		'prod.products': 'products',
		'prod.page': 'page',
		'prod.of': 'of',
		'cart.removeSelectedTitle': 'Remove selected items?',
		'cart.removeSelectedMsg': 'The checked products will be removed from your cart.',
		'cart.remove': 'Remove',
		'cart.removeTitle': 'Remove product?',
		'cart.removeMsg': 'This product will be removed from your cart.',
		'cart.title': 'Cart',
		'cart.loginToView': 'Sign in to view your cart',
		'cart.adminNoCart': 'Admins do not have a cart',
		'cart.empty': 'Your cart is empty',
		'cart.startShopping': 'Start Shopping',
		'cart.outOfStockWarn': 'Some products are out of stock. Remove or deselect them before checkout.',
		'cart.loginDesc': 'Sign in to your account to view and checkout items in your cart.',
		'cart.adminDesc': 'Admin accounts cannot shop. Use a buyer account.',
		'cart.emptyDesc': 'Start shopping for your favorite products.',
		'cart.summary': 'Summary',
		'cart.subtotal': 'Subtotal',
		'cart.estShipping': 'Estimated shipping',
		'cart.total': 'Total',
		'cart.continue': 'Continue Shopping',
		'co.today': 'Today',
		'co.loadAddrFail': 'Failed to load shipping addresses',
		'co.manualShipping': 'Showing manual shipping options from the admin.',
		'co.enterVoucher': 'Enter a voucher code',
		'co.voucherInvalid': 'Invalid voucher',
		'co.pickAddr': 'Choose a shipping address from your profile',
		'co.pickPayment': 'Choose a payment method',
		'co.pickCourier': 'Choose a shipping courier',
		'co.createOrderQ': 'Create order?',
		'co.createOrder': 'Create order',
		'co.orderCreated': 'Order created, please proceed to payment',
		'co.checkoutFail': 'Checkout failed to process',
		'co.title': 'Checkout',
		'co.primary': 'Primary',
		'co.noAddr': 'No shipping address yet',
		'co.shipping': 'Shipping',
		'co.paymentMethod': 'Payment Method',
		'co.voucherPlaceholder': 'Seller voucher code',
		'co.apply': 'Apply',
		'co.paymentSummary': 'Payment Summary',
		'co.promoVoucher': 'Voucher promo',
		'co.subtotalAfter': 'Subtotal after promo',
		'co.shippingCost': 'Shipping',
		'co.insurance': 'Insurance',
		'co.pickAddrShort': 'Choose a shipping address',
		'co.autoRateFail': 'Failed to load automatic courier rates. Showing manual options.',
		'co.createOrderMsg': 'The order will be created with a pending-payment status. Stock is only reduced after successful payment.',
		'co.checking': 'Checking...',
		'co.change': 'Change',
		'co.processing': 'Processing...',
		'co.pay': 'Pay',
		'co.completePin': 'Complete the address pin'
	}
};

export const t = derived(
	lang,
	($l) =>
		(key: string, fallback?: string): string =>
			DICT[$l]?.[key] ?? DICT.id[key] ?? fallback ?? key
);
