/**
 * Panggil `handler` saat ada interaksi DI LUAR elemen ini.
 *
 * Memakai `pointerdown` (bukan `click`) agar menutup terasa langsung dan tetap bekerja
 * ketika sentuhan berakhir di elemen lain — misalnya saat pengguna menggeser layar.
 * Listener dipasang di fase capture supaya tetap terpanggil meski elemen di dalam
 * halaman menghentikan perambatan event.
 */
export function clickOutside(node: HTMLElement, handler: () => void) {
	let onOutside = handler;

	function onPointerDown(event: PointerEvent) {
		const target = event.target as Node | null;
		if (!target || node.contains(target)) return;
		onOutside();
	}
	function onKeydown(event: KeyboardEvent) {
		if (event.key === 'Escape') onOutside();
	}

	document.addEventListener('pointerdown', onPointerDown, true);
	document.addEventListener('keydown', onKeydown);

	return {
		update(next: () => void) {
			onOutside = next;
		},
		destroy() {
			document.removeEventListener('pointerdown', onPointerDown, true);
			document.removeEventListener('keydown', onKeydown);
		}
	};
}
