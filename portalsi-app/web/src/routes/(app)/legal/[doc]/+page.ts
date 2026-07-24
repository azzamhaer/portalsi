import { error } from '@sveltejs/kit';
import type { PageLoad } from './$types';

type Section = { h: string; p: string };
type Doc = { title: string; intro: string; updated: string; sections: Section[] };

// Konten dasar — silakan sesuaikan dengan kebijakan resmi Portal SI.
const DOCS: Record<string, Doc> = {
	privasi: {
		title: 'Kebijakan Privasi',
		updated: 'Juli 2026',
		intro:
			'Kebijakan ini menjelaskan data apa yang Portal SI kumpulkan, bagaimana kami menggunakannya, dan hak Anda atas data tersebut.',
		sections: [
			{
				h: 'Data yang kami kumpulkan',
				p: 'Kami mengumpulkan informasi akun (nama, username, email), konten yang Anda unggah (postingan, cerita, komentar, pesan), serta data teknis seperti alamat IP, jenis perangkat, dan aktivitas login untuk keamanan.'
			},
			{
				h: 'Cara kami menggunakan data',
				p: 'Data digunakan untuk menyediakan dan meningkatkan layanan, menampilkan konten yang relevan, menjaga keamanan akun, serta mengirim notifikasi yang Anda izinkan. Kami tidak menjual data pribadi Anda.'
			},
			{
				h: 'Penyimpanan & keamanan',
				p: 'Media disimpan pada penyimpanan objek (Cloudflare R2) dan diakses melalui koneksi terenkripsi. Kami menerapkan langkah teknis dan organisasi yang wajar untuk melindungi data Anda.'
			},
			{
				h: 'Hak Anda',
				p: 'Anda dapat mengubah data profil, mengatur preferensi notifikasi, membuat akun privat, atau meminta penghapusan akun. Hubungi kami di noreply@portalsi.com untuk permintaan terkait data.'
			}
		]
	},
	kebijakan: {
		title: 'Kebijakan Komunitas',
		updated: 'Juli 2026',
		intro:
			'Aturan ini menjaga Portal SI tetap aman, sopan, dan bermanfaat bagi seluruh anggota komunitas.',
		sections: [
			{
				h: 'Konten yang dilarang',
				p: 'Dilarang mengunggah konten kekerasan, pornografi, ujaran kebencian, perundungan, penipuan, spam, atau materi yang melanggar hukum dan hak kekayaan intelektual pihak lain.'
			},
			{
				h: 'Standar kualitas konten',
				p: 'Portal SI mengutamakan konten yang bernilai dan orisinal. Konten asal-asalan — misalnya unggahan tanpa maksud jelas, kualitas sangat rendah, atau tidak memberi manfaat bagi komunitas — dapat dimoderasi. Jaga agar setiap unggahan memiliki nilai, konteks, atau tujuan yang jelas.'
			},
			{
				h: 'Keaslian & hak cipta',
				p: 'Unggah karya sendiri. Konten yang mengandung watermark atau tanda hak cipta dari platform lain (TikTok, CapCut, Instagram, dan sebagainya), hasil repost tanpa izin, atau bukan karya orisinal Anda dapat diturunkan. Menghormati karya orang lain adalah bagian dari etika komunitas.'
			},
			{
				h: 'Perilaku menghormati',
				p: 'Perlakukan anggota lain dengan hormat. Pelecehan, ancaman, atau doxxing tidak ditoleransi dan dapat berujung pada pembatasan atau penonaktifan akun.'
			},
			{
				h: 'Keaslian akun',
				p: 'Gunakan identitas yang jujur. Peniruan (impersonasi) dan akun palsu dapat dihapus. Satu orang tidak dianjurkan menyalahgunakan banyak akun untuk manipulasi.'
			},
			{
				h: 'Moderasi oleh sistem AI',
				p: 'Portal SI menggunakan sistem AI dan peninjauan moderator untuk mendeteksi konten yang melanggar kebijakan. Postingan yang dimoderasi akan disembunyikan dari publik dan hanya terlihat oleh pemiliknya. Pemilik akan menerima pemberitahuan berisi alasan, dan diberi kesempatan untuk memperbaiki atau menghapusnya.'
			},
			{
				h: 'Masa retensi & penghapusan',
				p: 'Konten yang dimoderasi disimpan sementara selama masa retensi (sekitar 30 hari) agar pemilik dapat meninjaunya, lalu dihapus permanen apabila tidak diperbaiki. Pelanggaran berulang dapat berujung pada pembatasan atau pemblokiran akun.'
			},
			{
				h: 'Pelaporan & penegakan',
				p: 'Konten yang melanggar dapat dilaporkan dan akan ditinjau. Kami dapat menghapus konten, memberi peringatan, atau menonaktifkan akun sesuai tingkat pelanggaran.'
			}
		]
	},
	syarat: {
		title: 'Syarat & Ketentuan',
		updated: 'Juli 2026',
		intro:
			'Dengan menggunakan Portal SI, Anda menyetujui syarat dan ketentuan berikut.',
		sections: [
			{
				h: 'Penggunaan layanan',
				p: 'Anda bertanggung jawab atas aktivitas pada akun Anda dan menjaga kerahasiaan kata sandi. Layanan disediakan untuk penggunaan yang sah sesuai hukum yang berlaku.'
			},
			{
				h: 'Konten pengguna',
				p: 'Anda tetap memiliki konten yang Anda unggah, namun memberi Portal SI lisensi untuk menampilkan dan mendistribusikannya di dalam platform demi menjalankan layanan.'
			},
			{
				h: 'Moderasi konten & konsekuensi',
				p: 'Anda memahami dan menyetujui bahwa Portal SI dapat memoderasi konten secara otomatis melalui sistem AI maupun peninjauan moderator. Konten yang melanggar dapat disembunyikan atau dihapus, dan pelanggaran dapat berujung pada peringatan, pembatasan fitur, penangguhan, atau pemblokiran akun sesuai tingkat pelanggaran.'
			},
			{
				h: 'Pembatasan',
				p: 'Kami dapat menangguhkan atau menghentikan akses jika terjadi pelanggaran ketentuan ini atau Kebijakan Komunitas, dengan atau tanpa pemberitahuan sebelumnya bila diperlukan untuk keamanan.'
			},
			{
				h: 'Perubahan',
				p: 'Ketentuan ini dapat diperbarui sewaktu-waktu. Perubahan penting akan diinformasikan melalui aplikasi. Penggunaan berkelanjutan berarti Anda menyetujui versi terbaru.'
			}
		]
	}
};

export const load: PageLoad = ({ params }) => {
	const doc = DOCS[params.doc];
	if (!doc) error(404, 'Dokumen hukum tidak ditemukan.');
	return { doc };
};
