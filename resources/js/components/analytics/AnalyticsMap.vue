<script setup>
/**
 * Peta analitik MapLibre menampilkan choropleth provinsi dan marker cluster
 * dari lokasi perusahaan tanpa memakai gambar peta statis.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Map as MapLibreMap, NavigationControl, Popup, setWorkerUrl } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';

// Worker dibundel Vite agar MapLibre tidak bergantung pada CDN JavaScript.
setWorkerUrl(workerUrl);

// Props menerima agregat provinsi aktual dari view lokasi kerja.
const props = defineProps({
    regions: { type: Array, default: () => [] },
    metricLabel: { type: String, default: 'Alumni' },
});

// Event klik wilayah mengirim kode provinsi untuk memfilter data individu.
const emit = defineEmits(['province-selected']);

// State UI peta membedakan proses loading, error, dan visibilitas layer.
const mapElement = ref(null);
const isLoading = ref(true);
const errorMessage = ref('');
const showChoropleth = ref(true);
const showClusters = ref(true);
let map = null;
let provinceGeoJson = null;
let hoverPopup = null;

// Nama provinsi dinormalisasi agar master wilayah cocok dengan GeoJSON lokal.
const normalizeProvinceName = (name) => {
    const normalized = String(name || '').toLowerCase().trim();
    if (normalized.includes('yogyakarta')) return 'daerah istimewa yogyakarta';
    if (normalized.includes('jakarta')) return 'dki jakarta';
    if (normalized.includes('bangka')) return 'kepulauan bangka belitung';
    if (normalized.includes('nusa tenggara barat') || normalized === 'ntb') return 'nusa tenggara barat';
    if (normalized.includes('nusa tenggara timur') || normalized === 'ntt') return 'nusa tenggara timur';

    return normalized;
};

// Lookup agregat provinsi mencegah pencarian berulang pada setiap feature peta.
const regionLookup = () => new Map(props.regions.map((region) => [
    normalizeProvinceName(region.province_name || region.label),
    region,
]));

// Mengambil nama provinsi dari variasi properti file GeoJSON Indonesia.
const featureProvinceName = (feature) => feature?.properties?.PROVINSI
    || feature?.properties?.WADMPR
    || feature?.properties?.NAME_1
    || feature?.properties?.name
    || 'Wilayah';

// Centroid sederhana dipakai sebagai titik cluster provinsi tanpa mengarang lokasi perusahaan.
const geometryCentroid = (geometry) => {
    const points = [];
    const collect = (coordinates) => {
        if (!Array.isArray(coordinates)) return;
        if (typeof coordinates[0] === 'number' && typeof coordinates[1] === 'number') {
            points.push(coordinates);
            return;
        }
        coordinates.forEach(collect);
    };
    collect(geometry?.coordinates);
    if (!points.length) return [118.02, -2.55];

    return [
        points.reduce((sum, point) => sum + point[0], 0) / points.length,
        points.reduce((sum, point) => sum + point[1], 0) / points.length,
    ];
};

// GeoJSON diperkaya jumlah alumni dan kode provinsi dari master database.
const enrichedGeoJson = () => {
    const lookup = regionLookup();
    const features = (provinceGeoJson?.features || []).map((feature) => {
        const name = featureProvinceName(feature);
        const region = lookup.get(normalizeProvinceName(name));

        return {
            ...feature,
            properties: {
                ...feature.properties,
                analytics_name: name,
                analytics_count: Number(region?.count || 0),
                analytics_code: region?.province_code || '',
            },
        };
    });

    return { type: 'FeatureCollection', features };
};

// Titik cluster berasal dari centroid feature provinsi yang mempunyai data kerja.
const clusterGeoJson = () => {
    const lookup = regionLookup();
    const features = (provinceGeoJson?.features || []).flatMap((feature) => {
        const name = featureProvinceName(feature);
        const region = lookup.get(normalizeProvinceName(name));
        if (!region || Number(region.count || 0) <= 0) return [];

        return [{
            type: 'Feature',
            properties: {
                province_name: name,
                province_code: region.province_code || '',
                count: Number(region.count || 0),
            },
            geometry: { type: 'Point', coordinates: geometryCentroid(feature.geometry) },
        }];
    });

    return { type: 'FeatureCollection', features };
};

// Memperbarui source saat tahun, prodi, atau UMP berubah tanpa membuat ulang peta.
const updateSources = () => {
    if (!map?.isStyleLoaded() || !provinceGeoJson) return;
    map.getSource('province-source')?.setData(enrichedGeoJson());
    map.getSource('work-clusters')?.setData(clusterGeoJson());
};

// Event klik feature meneruskan wilayah ke cross-filter halaman.
const selectFeature = (properties) => {
    if (!properties?.analytics_code && !properties?.province_code) return;
    emit('province-selected', {
        province_code: properties.analytics_code || properties.province_code,
        province_name: properties.analytics_name || properties.province_name,
        count: Number(properties.analytics_count ?? properties.count ?? 0),
    });
};

// Layer choropleth dan cluster ditambahkan setelah style dasar siap.
const addAnalyticsLayers = () => {
    map.addSource('province-source', { type: 'geojson', data: enrichedGeoJson() });
    map.addLayer({
        id: 'province-fill',
        type: 'fill',
        source: 'province-source',
        paint: {
            'fill-color': ['interpolate', ['linear'], ['get', 'analytics_count'], 0, '#E2E8F0', 1, '#B7E4C7', 25, '#52B788', 75, '#0D542B'],
            'fill-opacity': 0.78,
        },
    });
    map.addLayer({ id: 'province-border', type: 'line', source: 'province-source', paint: { 'line-color': '#FFFFFF', 'line-width': 1.2 } });

    map.addSource('work-clusters', {
        type: 'geojson',
        data: clusterGeoJson(),
        cluster: true,
        clusterRadius: 54,
        clusterMaxZoom: 7,
        clusterProperties: { total: ['+', ['get', 'count']] },
    });
    map.addLayer({
        id: 'work-cluster-circles',
        type: 'circle',
        source: 'work-clusters',
        filter: ['has', 'point_count'],
        paint: {
            'circle-color': '#FDC700',
            'circle-radius': ['step', ['get', 'total'], 18, 50, 24, 150, 31],
            'circle-stroke-color': '#0D542B',
            'circle-stroke-width': 2,
        },
    });
    map.addLayer({
        id: 'work-cluster-label',
        type: 'symbol',
        source: 'work-clusters',
        filter: ['has', 'point_count'],
        layout: { 'text-field': ['to-string', ['get', 'total']], 'text-size': 12 },
        paint: { 'text-color': '#0D542B' },
    });
    map.addLayer({
        id: 'work-points',
        type: 'circle',
        source: 'work-clusters',
        filter: ['!', ['has', 'point_count']],
        paint: { 'circle-color': '#FDC700', 'circle-radius': 9, 'circle-stroke-color': '#0D542B', 'circle-stroke-width': 2 },
    });
    map.addLayer({
        id: 'work-point-label',
        type: 'symbol',
        source: 'work-clusters',
        filter: ['!', ['has', 'point_count']],
        layout: { 'text-field': ['to-string', ['get', 'count']], 'text-size': 10 },
        paint: { 'text-color': '#0D542B' },
    });

    map.on('click', 'province-fill', (event) => selectFeature(event.features?.[0]?.properties));
    map.on('click', 'work-points', (event) => selectFeature(event.features?.[0]?.properties));
    map.on('click', 'work-cluster-circles', async (event) => {
        const feature = event.features?.[0];
        if (!feature) return;
        const zoom = await map.getSource('work-clusters').getClusterExpansionZoom(feature.properties.cluster_id);
        map.easeTo({ center: feature.geometry.coordinates, zoom });
    });

    ['province-fill', 'work-points', 'work-cluster-circles'].forEach((layerId) => {
        map.on('mouseenter', layerId, () => { map.getCanvas().style.cursor = 'pointer'; });
        map.on('mouseleave', layerId, () => { map.getCanvas().style.cursor = ''; });
    });
    map.on('mousemove', 'province-fill', (event) => {
        const properties = event.features?.[0]?.properties;
        if (!properties) return;
        hoverPopup?.remove();
        hoverPopup = new Popup({ closeButton: false, closeOnClick: false, offset: 8 })
            .setLngLat(event.lngLat)
            .setText(`${properties.analytics_name}: ${Number(properties.analytics_count || 0).toLocaleString('id-ID')} ${props.metricLabel.toLowerCase()}`)
            .addTo(map);
    });
    map.on('mouseleave', 'province-fill', () => hoverPopup?.remove());
};

// Peta dasar memakai raster OpenStreetMap tanpa token eksternal.
const initializeMap = async () => {
    if (!mapElement.value) return;
    try {
        const response = await fetch('/geojson/indonesia-provinsi.json');
        if (!response.ok) throw new Error('GeoJSON provinsi tidak tersedia.');
        provinceGeoJson = await response.json();

        map = new MapLibreMap({
            container: mapElement.value,
            style: {
                version: 8,
                sources: { osm: { type: 'raster', tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256, attribution: 'OpenStreetMap contributors' } },
                layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
            },
            center: [118.02, -2.55],
            zoom: 3.4,
            minZoom: 2.8,
            maxZoom: 12,
            attributionControl: true,
        });
        map.addControl(new NavigationControl({ showCompass: false }), 'top-left');
        map.on('load', () => {
            addAnalyticsLayers();
            isLoading.value = false;
        });
        map.on('error', (event) => {
            if (!event?.error?.message?.includes('tile')) errorMessage.value = 'Peta tidak dapat dimuat sepenuhnya.';
        });
    } catch (error) {
        errorMessage.value = error.message || 'Peta tidak dapat dimuat.';
        isLoading.value = false;
    }
};

// Toggle layer mempertahankan posisi zoom dan pan pengguna.
const syncLayerVisibility = () => {
    if (!map?.isStyleLoaded()) return;
    ['province-fill', 'province-border'].forEach((id) => map.setLayoutProperty(id, 'visibility', showChoropleth.value ? 'visible' : 'none'));
    ['work-cluster-circles', 'work-cluster-label', 'work-points', 'work-point-label'].forEach((id) => map.setLayoutProperty(id, 'visibility', showClusters.value ? 'visible' : 'none'));
};

// Lifecycle memperbarui source dan membuang WebGL map saat komponen hilang.
onMounted(initializeMap);
watch(() => props.regions, updateSources, { deep: true });
watch([showChoropleth, showClusters], syncLayerVisibility);
onBeforeUnmount(() => {
    hoverPopup?.remove();
    map?.remove();
    map = null;
});
</script>

<template>
    <div class="relative overflow-hidden bg-slate-100">
        <!-- Kontrol layer memberi pilihan antara choropleth dan cluster lokasi kerja. -->
        <div class="absolute right-3 top-3 z-10 flex rounded bg-white p-1 shadow-md">
            <button type="button" class="rounded px-3 py-2 text-xs font-bold" :class="showChoropleth ? 'bg-[#0D542B] text-white' : 'text-slate-600'" @click="showChoropleth = !showChoropleth">Wilayah</button>
            <button type="button" class="rounded px-3 py-2 text-xs font-bold" :class="showClusters ? 'bg-[#FDC700] text-[#0D542B]' : 'text-slate-600'" @click="showClusters = !showClusters">Cluster</button>
        </div>
        <div ref="mapElement" class="h-[440px] w-full sm:h-[520px]"></div>
        <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center bg-white/85 text-sm font-semibold text-slate-600">Memuat peta wilayah</div>
        <div v-if="errorMessage" class="absolute inset-x-4 bottom-4 bg-white p-3 text-sm font-semibold text-[#B42318] shadow">{{ errorMessage }}</div>
    </div>
</template>
