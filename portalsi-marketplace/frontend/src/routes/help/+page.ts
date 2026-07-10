import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';

const FALLBACK = [
  { title: 'Pesanan', items: [
    { q: 'Bagaimana cara melacak pesanan saya?', a: 'Buka halaman Pesanan Saya, pilih order, klik Detail. Status real-time + nomor resi tersedia.' },
    { q: 'Berapa lama pesanan diproses?', a: 'Setelah pembayaran berhasil, seller wajib memproses dalam 1×24 jam.' },
    { q: 'Bisa membatalkan pesanan?', a: 'Ya, selama status masih "Menunggu Pembayaran" — tidak melakukan pembayaran dalam 24 jam akan otomatis dibatalkan.' }
  ]},
  { title: 'Pembayaran', items: [
    { q: 'Metode pembayaran apa saja?', a: 'VA semua bank besar, OVO, DANA, ShopeePay, LinkAja, QRIS, Alfamart, Indomaret, & Kartu Kredit.' },
    { q: 'Sudah bayar tapi status pending?', a: 'Status update otomatis dalam 1-5 menit. Jika lebih lama, hubungi CS dengan bukti transfer + Order ID.' }
  ]},
  { title: 'Pengiriman', items: [
    { q: 'Kurir apa saja?', a: 'JNE, J&T Express, SiCepat, AnterAja, GoSend Sameday, Pos Indonesia.' },
    { q: 'Estimasi sampai?', a: 'Same city: 1-2 hari · Pulau Jawa: 2-4 hari · Luar Jawa: 3-7 hari.' }
  ]},
  { title: 'Akun & Toko', items: [
    { q: 'Bagaimana jadi seller?', a: 'Klik "Buka Toko" di profil → isi form → tunggu verifikasi admin → langsung dapat akses Seller Center.' }
  ]}
];

export const load: PageLoad = async ({ fetch }) => {
  try {
    const sections: any = await apiEndpoints.faqs(fetch);
    return { sections: Array.isArray(sections) && sections.length ? sections : FALLBACK };
  } catch {
    return { sections: FALLBACK };
  }
};
