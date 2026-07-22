const MONTHS_ID = [
	'Januari',
	'Februari',
	'Maret',
	'April',
	'Mei',
	'Juni',
	'Juli',
	'Agustus',
	'September',
	'Oktober',
	'November',
	'Desember'
];

/**
 * Waktu relatif yang presisi (Bahasa Indonesia):
 *  - < 1 menit  : "baru saja" / "N detik lalu"
 *  - < 1 jam    : "N menit lalu"
 *  - < 1 hari   : "N jam lalu"
 *  - < 1 minggu : "N hari lalu"
 *  - selebihnya : tanggal pasti "21 Maret"
 *  - beda tahun : "21 Maret 2024"
 * Tidak ada lagi istilah samar seperti "kemarin dulu".
 */
/**
 * Umur cerita dalam bentuk SANGAT ringkas — satu satuan saja, untuk header story.
 *
 *   < 1 menit : "30d" (detik)
 *   < 1 jam   : "30m" (menit)
 *   selebihnya: "23j" (jam)
 *
 * Cerita kedaluwarsa dalam 24 jam, jadi nilainya dibatasi maksimal "23j" — tidak pernah
 * ada "24j" atau "1h". Sengaja tidak digabung ("1j 5m 12d") supaya ringkas di ruang sempit.
 */
export function storyAgeId(value: string | Date, now = new Date()): string {
	const date = value instanceof Date ? value : new Date(value);
	if (Number.isNaN(date.getTime())) return '';

	const seconds = Math.max(0, Math.floor((now.getTime() - date.getTime()) / 1000));
	if (seconds < 60) return `${seconds}d`;

	const minutes = Math.floor(seconds / 60);
	if (minutes < 60) return `${minutes}m`;

	return `${Math.min(23, Math.floor(minutes / 60))}j`;
}

export function relativeTimeId(value: string | Date, now = new Date()): string {
	const date = value instanceof Date ? value : new Date(value);
	if (Number.isNaN(date.getTime())) return 'Waktu tidak diketahui';

	const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);
	if (seconds < 5) return 'baru saja';
	if (seconds < 60) return `${seconds} detik lalu`;

	const minutes = Math.floor(seconds / 60);
	if (minutes < 60) return `${minutes} menit lalu`;

	const hours = Math.floor(minutes / 60);
	if (hours < 24) return `${hours} jam lalu`;

	const days = Math.floor(hours / 24);
	if (days < 7) return `${days} hari lalu`;

	const label = `${date.getDate()} ${MONTHS_ID[date.getMonth()]}`;
	return date.getFullYear() === now.getFullYear() ? label : `${label} ${date.getFullYear()}`;
}
