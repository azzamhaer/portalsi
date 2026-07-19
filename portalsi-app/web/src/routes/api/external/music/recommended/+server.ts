import { z } from 'zod';
import type { RequestHandler } from './$types';

// Rekomendasi = chart "most-played" Apple Music (Indonesia) + lookup iTunes untuk previewUrl 30 detik.
const feedSchema = z.object({
	feed: z.object({
		results: z.array(
			z
				.object({
					id: z.string(),
					name: z.string(),
					artistName: z.string(),
					artworkUrl100: z.string().url().optional()
				})
				.passthrough()
		)
	})
});

const lookupSchema = z.object({
	results: z.array(
		z
			.object({
				trackId: z.coerce.number(),
				trackName: z.string().optional(),
				artistName: z.string().optional(),
				previewUrl: z.string().url().optional(),
				artworkUrl100: z.string().url().optional()
			})
			.passthrough()
	)
});

let cache: { expires: number; value: unknown } | null = null;

export const GET: RequestHandler = async ({ locals }) => {
	if (!locals.token) return Response.json({ message: 'Sesi tidak tersedia.' }, { status: 401 });
	if (cache && cache.expires > Date.now())
		return Response.json(cache.value, { headers: { 'Cache-Control': 'private, max-age=1800' } });

	try {
		// 1) Chart most-played (25 lagu) — hanya untuk daftar id + judul.
		const feedRes = await fetch(
			'https://rss.applemarketingtools.com/api/v2/id/music/most-played/25/songs.json',
			{ headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(8_000) }
		);
		if (!feedRes.ok) throw new Error('feed failed');
		const feed = feedSchema.parse(await feedRes.json());
		const ids = feed.feed.results.map((r) => r.id).slice(0, 25);
		if (ids.length === 0) return Response.json({ tracks: [] });

		// 2) Lookup untuk mendapatkan previewUrl (chart feed tidak menyertakannya).
		const lookup = new URL('https://itunes.apple.com/lookup');
		lookup.searchParams.set('id', ids.join(','));
		lookup.searchParams.set('entity', 'song');
		const lookupRes = await fetch(lookup, {
			headers: { Accept: 'application/json' },
			signal: AbortSignal.timeout(8_000)
		});
		if (!lookupRes.ok) throw new Error('lookup failed');
		const parsed = lookupSchema.parse(await lookupRes.json());

		// Pertahankan urutan chart.
		const byId = new Map(parsed.results.map((t) => [String(t.trackId), t]));
		const tracks = ids
			.map((id) => byId.get(id))
			.filter((t): t is NonNullable<typeof t> => Boolean(t?.previewUrl && t?.trackName))
			.slice(0, 15)
			.map((t) => ({
				id: String(t.trackId),
				title: t.trackName as string,
				artist: t.artistName ?? 'Artis tidak diketahui',
				durationSeconds: 30,
				previewUrl: t.previewUrl as string,
				artworkUrl: t.artworkUrl100
					? t.artworkUrl100.replace('100x100bb', '300x300bb').replace('100x100', '300x300')
					: null
			}));

		const value = { tracks };
		cache = { expires: Date.now() + 60 * 60_000, value }; // cache 1 jam
		return Response.json(value, { headers: { 'Cache-Control': 'private, max-age=1800' } });
	} catch {
		return Response.json({ tracks: [] });
	}
};
