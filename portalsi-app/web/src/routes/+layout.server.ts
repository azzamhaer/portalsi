import type { LayoutServerLoad } from './$types';

// Baca preferensi bahasa dari cookie shared (portalsi_lang) — dipakai semua halaman.
export const load: LayoutServerLoad = ({ cookies }) => {
	const c = cookies.get('portalsi_lang');
	const lang = c === 'en' || c === 'id' ? c : 'id';
	return { lang };
};
