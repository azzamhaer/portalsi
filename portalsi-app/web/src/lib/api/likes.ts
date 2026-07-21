import { clientRequest } from '$lib/api/client';

/**
 * Kirim state like yang DIINGINKAN (bukan sekadar "toggle") lalu kembalikan state
 * otoritatif dari server.
 *
 * Kenapa begini: dulu klien hanya memanggil toggle dan menebak hasilnya secara optimistic.
 * Bila dua request beruntun terkirim (mis. double-tap sekaligus klik tombol), server
 * melakukan like → unlike sementara UI berhenti di posisi "disukai" — sehingga like
 * tampak tersimpan tapi hilang setelah refresh. Dengan mengirim `liked` eksplisit,
 * operasi jadi idempoten dan UI selalu disamakan dengan hasil server.
 */
export async function setPostLike(postId: number | string, liked: boolean) {
	const body = new FormData();
	body.set('liked', liked ? 'true' : 'false');
	const result = await clientRequest<{ liked?: boolean; likes_count?: number }>(
		`posts/${postId}/like`,
		{ method: 'POST', body }
	);
	return {
		liked: typeof result?.liked === 'boolean' ? result.liked : liked,
		likesCount: typeof result?.likes_count === 'number' ? result.likes_count : null
	};
}
