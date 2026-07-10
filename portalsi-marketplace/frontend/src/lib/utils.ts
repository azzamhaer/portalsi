export const fmtRp = (n: number) => 'Rp ' + (n || 0).toLocaleString('id-ID');

export const calcDiscount = (price: number, original?: number | null) => {
  if (!original || original <= price) return 0;
  return Math.round(((original - price) / original) * 100);
};

export const ORDER_STATUS_LABEL: Record<string,string> = {
  PENDING_PAYMENT: 'Menunggu Pembayaran',
  PAID: 'Dibayar',
  PROCESSING: 'Diproses Penjual',
  IN_TRANSIT: 'Dalam Perjalanan',
  ARRIVED: 'Telah Sampai',
  SHIPPED: 'Sedang Dikirim',
  DONE: 'Selesai',
  RETURN_REQUESTED: 'Pengembalian Diajukan',
  REFUNDED: 'Direfund',
  CANCELLED: 'Dibatalkan',
  EXPIRED: 'Kadaluarsa'
};

export function statusPill(status: string): string {
  return ({
    PENDING_PAYMENT: 'pill-amber',
    PAID:            'pill-green',
    PROCESSING:      'pill-green',
    IN_TRANSIT:      'pill-blue',
    ARRIVED:         'pill-amber',
    SHIPPED:         'pill-blue',
    DONE:            'pill-ink',
    RETURN_REQUESTED:'pill-amber',
    REFUNDED:        'pill-blue',
    CANCELLED:       'pill-red',
    EXPIRED:         'pill-ink'
  } as any)[status] || 'pill-ink';
}

export function timeAgo(d: string | Date): string {
  const date = new Date(d);
  const sec = Math.floor((Date.now() - date.getTime()) / 1000);
  if (sec < 60) return 'baru saja';
  const min = Math.floor(sec/60);   if (min < 60) return `${min} menit lalu`;
  const hr  = Math.floor(min/60);   if (hr  < 24) return `${hr} jam lalu`;
  const day = Math.floor(hr/24);    if (day < 30) return `${day} hari lalu`;
  return date.toLocaleDateString('id-ID');
}
