<script lang="ts">
  import { onMount, onDestroy } from 'svelte';

  let { order, height = '320px' } = $props<{ order: any; height?: string }>();

  type Point = [number, number];
  type Segment = { mode: 'road' | 'air'; points: Point[]; distance: number };

  let mapEl: HTMLDivElement | null = $state(null);
  let map: any = null;
  let L: any = null;
  let marker: any = null;
  let segments: Segment[] = [];
  let progress = $state(0);
  let error = $state('');
  let loadingRoute = $state(false);
  let timer: any;

  const vendor = $derived(order.items?.find((it: any) => it.vendor?.latitude != null && it.vendor?.longitude != null)?.vendor);
  const destination = $derived(order.address || order.address_snapshot || order.shipping_payload?.address_snapshot);
  const hasCoords = $derived(vendor?.latitude != null && vendor?.longitude != null && destination?.latitude != null && destination?.longitude != null);
  const etaDays = $derived(parseEtaDays(order.courier_eta));
  const transitHubs = [
    [-6.1256, 106.6559, 'CGK'], [-7.3798, 112.7870, 'SUB'],
    [-5.0616, 119.5540, 'UPG'], [-1.2683, 116.8945, 'BPN'],
    [3.6422, 98.8853, 'KNO'], [-8.7482, 115.1671, 'DPS'],
    [1.5493, 124.9259, 'MDC'], [-2.5769, 140.5164, 'DJJ'],
  ];

  function parseEtaDays(eta: string) {
    const nums = String(eta || '').match(/\d+/g)?.map(Number) ?? [];
    if (!nums.length) return 3;
    return Math.max(1, Math.max(...nums));
  }

  function loadScript(src: string) {
    return new Promise<void>((resolve, reject) => {
      if (document.querySelector(`script[src="${src}"]`)) return resolve();
      const s = document.createElement('script');
      s.src = src;
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('Gagal memuat peta'));
      document.head.appendChild(s);
    });
  }

  function loadCss(href: string) {
    if (document.querySelector(`link[href="${href}"]`)) return;
    const l = document.createElement('link');
    l.rel = 'stylesheet';
    l.href = href;
    document.head.appendChild(l);
  }

  function startPoint(): Point { return [Number(vendor.latitude), Number(vendor.longitude)]; }
  function endPoint(): Point { return [Number(destination.latitude), Number(destination.longitude)]; }

  function distanceKm(a: Point, b: Point) {
    const toRad = (n: number) => n * Math.PI / 180;
    const dLat = toRad(b[0] - a[0]);
    const dLng = toRad(b[1] - a[1]);
    const lat1 = toRad(a[0]);
    const lat2 = toRad(b[0]);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
  }

  function nearestHub(point: Point): Point {
    const hub = transitHubs
      .map((h) => ({ h, d: distanceKm(point, [h[0] as number, h[1] as number]) }))
      .sort((a, b) => a.d - b.d)[0]?.h;
    return hub ? [hub[0] as number, hub[1] as number] : point;
  }

  async function osrmRoute(a: Point, b: Point): Promise<Point[]> {
    const url = `https://router.project-osrm.org/route/v1/driving/${a[1]},${a[0]};${b[1]},${b[0]}?overview=full&geometries=geojson`;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 7000);
    try {
      const res = await fetch(url, { signal: controller.signal });
      if (!res.ok) throw new Error('Route gagal');
      const json = await res.json();
      const coords = json?.routes?.[0]?.geometry?.coordinates;
      if (!Array.isArray(coords) || coords.length < 2) throw new Error('Route kosong');
      return coords.map((c: number[]) => [c[1], c[0]] as Point);
    } finally {
      clearTimeout(timeout);
    }
  }

  function segmentDistance(points: Point[]) {
    return points.slice(1).reduce((sum, p, i) => sum + distanceKm(points[i], p), 0);
  }

  async function buildSegments(): Promise<Segment[]> {
    const start = startPoint();
    const end = endPoint();
    const longTrip = distanceKm(start, end) > 350;
    if (!longTrip) {
      const road = await routeOrFallback(start, end);
      return [{ mode: 'road', points: road, distance: segmentDistance(road) }];
    }

    const originHub = nearestHub(start);
    const destHub = nearestHub(end);
    const firstRoad = await routeOrFallback(start, originHub);
    const lastRoad = await routeOrFallback(destHub, end);
    const air = dedupePoints([originHub, destHub]);
    const built: Segment[] = [
      { mode: 'road', points: firstRoad, distance: segmentDistance(firstRoad) },
      { mode: 'air', points: air, distance: segmentDistance(air) },
      { mode: 'road', points: lastRoad, distance: segmentDistance(lastRoad) },
    ];
    return built.filter((s) => s.points.length > 1 && s.distance > 0.1);
  }

  async function routeOrFallback(a: Point, b: Point) {
    try { return await osrmRoute(a, b); }
    catch { return dedupePoints([a, b]); }
  }

  function dedupePoints(points: Point[]) {
    return points.filter((p, i) => i === 0 || distanceKm(p, points[i - 1]) > 0.02);
  }

  function calcProgress() {
    if (['ARRIVED', 'DONE', 'REFUNDED'].includes(order.status)) return 1;
    const shippedAt = order.shipped_at ? new Date(order.shipped_at).getTime() : Date.now();
    const duration = etaDays * 24 * 60 * 60 * 1000;
    return Math.max(0.02, Math.min(0.98, (Date.now() - shippedAt) / duration));
  }

  function pointOnPolyline(points: Point[], t: number): Point {
    const total = segmentDistance(points);
    if (total <= 0) return points[0];
    let target = total * t;
    for (let i = 1; i < points.length; i++) {
      const dist = distanceKm(points[i - 1], points[i]);
      if (target <= dist) {
        const local = dist ? target / dist : 0;
        return [
          points[i - 1][0] + (points[i][0] - points[i - 1][0]) * local,
          points[i - 1][1] + (points[i][1] - points[i - 1][1]) * local,
        ];
      }
      target -= dist;
    }
    return points[points.length - 1];
  }

  function pointAtProgress(t: number): Point {
    const total = segments.reduce((sum, s) => sum + s.distance, 0);
    let target = total * t;
    for (const segment of segments) {
      if (target <= segment.distance) {
        return pointOnPolyline(segment.points, target / segment.distance);
      }
      target -= segment.distance;
    }
    return endPoint();
  }

  function truckIcon() {
    return L.divIcon({
      className: '',
      iconSize: [36, 36],
      iconAnchor: [18, 18],
      html: '<div style="width:36px;height:36px;border-radius:999px;background:#166534;color:#fff;border:3px solid #fff;box-shadow:0 6px 18px rgba(0,0,0,.28);display:grid;place-items:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17h4V5H2v12h3"/><path d="M14 8h4l4 4v5h-3"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg></div>'
    });
  }

  function render() {
    if (!map || !hasCoords || !segments.length) return;
    progress = calcProgress();
    marker?.setLatLng(pointAtProgress(progress));
  }

  async function init() {
    if (!mapEl || !hasCoords) return;
    loadingRoute = true;
    loadCss('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    await loadScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js');
    L = (window as any).L;
    map = L.map(mapEl, { zoomControl: true, attributionControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);

    segments = await buildSegments();
    for (const segment of segments) {
      L.polyline(segment.points, {
        color: segment.mode === 'air' ? '#4f46e5' : '#2563eb',
        weight: segment.mode === 'air' ? 4 : 5,
        opacity: 0.85,
        dashArray: segment.mode === 'air' ? '10 10' : undefined,
      }).addTo(map);
    }

    L.marker(endPoint()).addTo(map).bindPopup('Alamat penerima');
    marker = L.marker(pointAtProgress(calcProgress()), { icon: truckIcon(), zIndexOffset: 500 }).addTo(map);
    const allPoints = segments.flatMap((s) => s.points);
    map.fitBounds(L.latLngBounds(allPoints), { padding: [28, 28] });
    loadingRoute = false;
    timer = setInterval(render, 60_000);
    render();
  }

  onMount(() => init().catch(() => {
    loadingRoute = false;
    error = 'Peta pengiriman belum bisa dimuat.';
  }));
  onDestroy(() => { clearInterval(timer); map?.remove(); });
</script>

{#if hasCoords}
  <div class="relative z-0 overflow-hidden rounded-2xl border border-ink-200 bg-ink-50">
    <div bind:this={mapEl} style:height class="relative z-0 w-full"></div>
    {#if loadingRoute}
      <div class="absolute inset-0 z-10 grid place-items-center bg-white/70 text-sm text-ink-600">Menyusun rute darat dan udara...</div>
    {/if}
    <div class="flex items-center justify-between gap-3 px-3 py-2 text-xs text-ink-600">
      <span>Progress estimasi: {Math.round(progress * 100)}%</span>
      <span>ETA {order.courier_eta || `${etaDays} hari`}</span>
    </div>
  </div>
{:else}
  <div class="rounded-2xl border border-ink-100 bg-ink-50 p-4 text-sm text-ink-600">
    Peta rute belum tersedia karena koordinat seller atau penerima belum lengkap.
  </div>
{/if}
{#if error}
  <div class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">{error}</div>
{/if}
