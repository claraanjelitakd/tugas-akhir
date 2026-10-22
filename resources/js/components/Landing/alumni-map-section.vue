<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import Swal from 'sweetalert2';

const props = defineProps({
    alumniWilayah: {
        type: Object,
        default: () => ({
            domisili: [],
            karier: [],
            perusahaan: {},
            summary: {}
        })
    },
    alumniList: {
        type: Array,
        default: () => []
    },
    // Semua provinsi dari tabel referensi (38 provinsi)
    allProvinsi: {
        type: Array,
        default: () => []
    },
    // Semua kabupaten dari tabel referensi (515 kabupaten)
    allKabupaten: {
        type: Array,
        default: () => []
    },
    // Background class yang dapat diatur dari Beranda.vue
    bgClass: {
        type: String,
        default: 'bg-[#FFC700]'
    }
});

// Deteksi apakah background bertipe terang (kuning, putih, dll.) agar kontras teks & kartu menyesuaikan otomatis
const isLightBg = computed(() => {
    const bg = (props.bgClass || '').toLowerCase();
    return bg.includes('white') || bg.includes('ffc700') || bg.includes('yellow') || bg.includes('amber') || bg.includes('slate-50') || bg.includes('slate-100') || bg.includes('gray-50') || bg.includes('gray-100');
});

// State Filter & Pencarian
const searchQuery = ref('');
const selectedAktivitasFilter = ref('ALL');
const selectedLokasiFilter = ref('ALL');
const selectedProvFilter = ref('ALL');
const selectedKabFilter = ref('ALL');
const selectedProvince = ref(null);
const isZoomedIn = ref(false);
const isLoadingGeo = ref(true);

// Pagination: 4 Kartu per Halaman (Layout 2x2)
const currentPage = ref(1);
const perPage = ref(4);

let map = null;
let geojsonLayer = null;
let kabupatenGeoLayer = null;
let provinsiData = null;
let kabupatenData = null;

// Normalisasi nama provinsi agar sinkron antara DB dan GeoJSON
const normalizeProvinceName = (name) => {
    if (!name) return '';
    let n = name.toLowerCase().trim();
    if (n.includes('yogyakarta')) return 'daerah istimewa yogyakarta';
    if (n.includes('aceh')) return 'aceh';
    if (n.includes('jakarta')) return 'dki jakarta';
    if (n.includes('bangka')) return 'kepulauan bangka belitung';
    if (n.includes('kepulauan riau')) return 'kepulauan riau';
    if (n.includes('ntb') || n.includes('nusa tenggara barat')) return 'nusa tenggara barat';
    if (n.includes('ntt') || n.includes('nusa tenggara timur')) return 'nusa tenggara timur';
    return n;
};

// Daftar provinsi dari tabel referensi lengkap (semua 38 provinsi)
const availableProvinsi = computed(() => {
    if (props.allProvinsi && props.allProvinsi.length > 0) {
        return props.allProvinsi.map(p => p.nama_provinsi).sort();
    }
    // Fallback: dari data alumni jika props belum tersedia
    const provs = new Set();
    props.alumniList.forEach(a => {
        if (a.perusahaan_provinsi) provs.add(a.perusahaan_provinsi);
    });
    return Array.from(provs).sort();
});

// Daftar kabupaten dari tabel referensi lengkap (515 kabupaten), difilter per provinsi
const availableKabupaten = computed(() => {
    if (props.allKabupaten && props.allKabupaten.length > 0 && props.allProvinsi && props.allProvinsi.length > 0) {
        if (selectedProvFilter.value === 'ALL') {
            return props.allKabupaten.map(k => k.nama_kabupaten).sort();
        }
        // Cari id provinsi yang dipilih
        const provObj = props.allProvinsi.find(p =>
            normalizeProvinceName(p.nama_provinsi) === normalizeProvinceName(selectedProvFilter.value)
        );
        if (!provObj) return props.allKabupaten.map(k => k.nama_kabupaten).sort();
        return props.allKabupaten
            .filter(k => k.propinsi_id === provObj.id)
            .map(k => k.nama_kabupaten)
            .sort();
    }
    // Fallback: dari data alumni
    const kabs = new Set();
    props.alumniList.forEach(a => {
        if (selectedProvFilter.value === 'ALL') {
            if (a.perusahaan_kabupaten) kabs.add(a.perusahaan_kabupaten);
        } else {
            const targetNorm = normalizeProvinceName(selectedProvFilter.value);
            if (normalizeProvinceName(a.perusahaan_provinsi) === targetNorm && a.perusahaan_kabupaten) {
                kabs.add(a.perusahaan_kabupaten);
            }
        }
    });
    return Array.from(kabs).sort();
});

// Filter Alumni Real-Time Berdasarkan Pencarian & Wilayah Perusahaan (Karier)
const filteredAlumni = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    
    return props.alumniList.filter(alumni => {
        // Filter Kategori Aktivitas Karier (Perusahaan, Wirausaha, Lanjut Pendidikan)
        if (selectedAktivitasFilter.value !== 'ALL') {
            if (alumni.kategori_aktivitas !== selectedAktivitasFilter.value) {
                return false;
            }
        }

        // Filter Jenis Lokasi (Dalam Negeri, Luar Negeri)
        if (selectedLokasiFilter.value !== 'ALL') {
            if (alumni.jenis_lokasi !== selectedLokasiFilter.value) {
                return false;
            }
        }

        // Filter Provinsi
        if (selectedProvFilter.value !== 'ALL') {
            const targetProv = normalizeProvinceName(selectedProvFilter.value);
            if (normalizeProvinceName(alumni.perusahaan_provinsi) !== targetProv) {
                return false;
            }
        }

        // Filter Kabupaten / Kota
        if (selectedKabFilter.value !== 'ALL') {
            const targetKab = selectedKabFilter.value.toLowerCase();
            const kabMatch = alumni.perusahaan_kabupaten && alumni.perusahaan_kabupaten.toLowerCase().includes(targetKab);
            if (!kabMatch) return false;
        }

        // Filter Teks Pencarian Bebas
        if (query) {
            const nama = (alumni.nama || '').toLowerCase();
            const jabatan = (alumni.posisi_jabatan || '').toLowerCase();
            const prodi = (alumni.prodi || '').toLowerCase();
            const perush = (alumni.perusahaan_nama || '').toLowerCase();
            const provKarier = (alumni.perusahaan_provinsi || '').toLowerCase();
            const kabKarier = (alumni.perusahaan_kabupaten || '').toLowerCase();
            const zip = (alumni.zipcode || '').toLowerCase();
            const judulTa = (alumni.judul_ta || '').toLowerCase();
            const aktivitas = (alumni.kategori_aktivitas || '').toLowerCase();
            const lokasi = (alumni.jenis_lokasi || '').toLowerCase();

            const match = nama.includes(query) ||
                          jabatan.includes(query) ||
                          prodi.includes(query) ||
                          perush.includes(query) ||
                          provKarier.includes(query) ||
                          kabKarier.includes(query) ||
                          zip.includes(query) ||
                          judulTa.includes(query) ||
                          aktivitas.includes(query) ||
                          lokasi.includes(query);

            if (!match) return false;
        }

        return true;
    });
});

// Paginated Alumni Cards (Maksimal 4 Kartu = 2 Baris x 2 Kolom)
const paginatedAlumni = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return filteredAlumni.value.slice(start, start + perPage.value);
});

const totalPages = computed(() => {
    return Math.ceil(filteredAlumni.value.length / perPage.value) || 1;
});

// Reset page saat filter berubah
watch([searchQuery, selectedProvFilter, selectedKabFilter, selectedAktivitasFilter, selectedLokasiFilter], () => {
    currentPage.value = 1;
});

// Ambil Ringkasan Wilayah (Total, Daftar PT, dan Daftar Alumni) untuk Hover Tooltip
const getProvinceSummary = (provName) => {
    const targetNorm = normalizeProvinceName(provName);
    
    // Cari dari summary jika ada
    if (props.alumniWilayah?.summary) {
        for (const [key, data] of Object.entries(props.alumniWilayah.summary)) {
            if (normalizeProvinceName(key) === targetNorm) {
                return data;
            }
        }
    }

    // Fallback: hitung langsung dari props.alumniList
    const inProv = props.alumniList.filter(a => normalizeProvinceName(a.perusahaan_provinsi) === targetNorm);
    const uniquePT = Array.from(new Set(inProv.map(a => a.perusahaan_nama).filter(Boolean)));
    const alumniNames = inProv.map(a => a.nama).filter(Boolean);

    return {
        total: inProv.length,
        perusahaan: uniquePT,
        alumni: alumniNames
    };
};

// Skema Warna Polygon Peta Hijau & Kuning Solid Elegan
const getColorByCount = (count) => {
    if (count > 50) return '#0D542B'; // Hijau Tua Resmi UKDW
    if (count > 20) return '#166534'; // Hijau Hutan
    if (count > 5)  return '#22C55E'; // Hijau Daun
    if (count > 0)  return '#EAB308'; // Kuning Emas
    return '#E2E8F0';                // Netral Abu-abu
};

// Styling Polygon GeoJSON Provinsi
const styleProvinceFeature = (feature) => {
    const provName = feature.properties.PROVINSI;
    const summary = getProvinceSummary(provName);

    return {
        fillColor: getColorByCount(summary.total),
        weight: 1.5,
        opacity: 1,
        color: '#FFFFFF',
        dashArray: '',
        fillOpacity: summary.total > 0 ? 0.85 : 0.4
    };
};

const highlightFeature = (e) => {
    const layer = e.target;
    layer.setStyle({
        weight: 3,
        color: '#FACC15', // Kuning Cerah saat Hover
        dashArray: '',
        fillOpacity: 0.95
    });
    if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
        layer.bringToFront();
    }
};

const resetHighlight = (e) => {
    if (geojsonLayer) {
        geojsonLayer.resetStyle(e.target);
    }
};

// Interaksi saat Polygon Provinsi Diklik: Filter Langsung Kolom Kanan
const handleProvinceClick = (e, feature) => {
    const provName = feature.properties.PROVINSI;
    
    selectedProvFilter.value = provName;
    selectedKabFilter.value = 'ALL';
    selectedProvince.value = provName;
    isZoomedIn.value = true;

    // Zoom peta ke batas wilayah provinsi
    map.fitBounds(e.target.getBounds(), { padding: [25, 25] });

    // Render polygon kabupaten untuk provinsi ini
    renderKabupatenForProvince(provName);
};

// Render batas kabupaten/kota yang sesuai dengan provinsi terpilih
const renderKabupatenForProvince = (provName) => {
    if (!map || !kabupatenData) return;

    if (kabupatenGeoLayer) {
        map.removeLayer(kabupatenGeoLayer);
    }

    const targetNorm = normalizeProvinceName(provName);
    const filteredFeatures = kabupatenData.features.filter(f => {
        const p = normalizeProvinceName(f.properties.WADMPR);
        return p === targetNorm;
    });

    if (filteredFeatures.length === 0) return;

    kabupatenGeoLayer = L.geoJSON({
        type: 'FeatureCollection',
        features: filteredFeatures
    }, {
        style: () => ({
            fillColor: '#FACC15',
            weight: 1.5,
            color: '#0D542B',
            dashArray: '2',
            fillOpacity: 0.35
        }),
        onEachFeature: (feat, layer) => {
            const kabName = feat.properties.WADMKK || feat.properties.NAMOBJ;
            layer.bindTooltip(`
                <div class="px-2 py-1 font-sans text-xs">
                    <strong class="text-slate-900 block">${kabName}</strong>
                    <span class="text-[#0D542B] font-bold">Klik untuk saring kabupaten</span>
                </div>
            `, { sticky: true, className: 'leaflet-custom-tooltip' });

            layer.on({
                click: () => {
                    selectedKabFilter.value = kabName;
                    // Auto-zoom ke kabupaten yang diklik
                    map.fitBounds(layer.getBounds(), { padding: [20, 20] });
                }
            });
        }
    }).addTo(map);
};

// Reset Zoom ke Tampilan Nasional Indonesia ([-2.55, 118.02] Zoom 5)
const resetMapToIndonesia = () => {
    if (map) {
        if (kabupatenGeoLayer) {
            map.removeLayer(kabupatenGeoLayer);
            kabupatenGeoLayer = null;
        }
        map.setView([-2.55, 118.02], 5);
    }
    selectedProvince.value = null;
    selectedProvFilter.value = 'ALL';
    selectedKabFilter.value = 'ALL';
    selectedAktivitasFilter.value = 'ALL';
    selectedLokasiFilter.value = 'ALL';
    searchQuery.value = '';
    isZoomedIn.value = false;
    currentPage.value = 1;
};

// Buka Detail Menggunakan SweetAlert2 — sekaligus zoom peta
const openDetailSweetAlert = (alumni) => {
    // Langsung zoom peta saat kartu diklik
    focusAlumniOnMap(alumni);
    // Bangun HTML Media Sosial
    let medsosHtml = '';
    if (alumni.linkedin_url || alumni.instagram_url || alumni.facebook_url) {
        medsosHtml = '<div style="margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap;">';
        if (alumni.linkedin_url) {
            medsosHtml += `<a href="${alumni.linkedin_url}" target="_blank" style="padding: 4px 10px; border-radius: 8px; background: #e0f2fe; color: #0369a1; font-size: 11px; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">LinkedIn ↗</a>`;
        }
        if (alumni.instagram_url) {
            medsosHtml += `<a href="${alumni.instagram_url}" target="_blank" style="padding: 4px 10px; border-radius: 8px; background: #fce7f3; color: #be185d; font-size: 11px; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">Instagram ↗</a>`;
        }
        if (alumni.facebook_url) {
            medsosHtml += `<a href="${alumni.facebook_url}" target="_blank" style="padding: 4px 10px; border-radius: 8px; background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">Facebook ↗</a>`;
        }
        medsosHtml += '</div>';
    } else {
        medsosHtml = '<span style="color: #94a3b8; font-size: 11px; font-style: italic;">Belum ada tautan medsos publik.</span>';
    }

    // Bangun HTML Tugas Akhir (TA) & Link Publikasi
    let taHtml = '';
    if (alumni.judul_ta) {
        taHtml = `
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; margin-top: 10px; text-align: left;">
                <span style="font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">Tugas Akhir / Skripsi</span>
                <p style="font-size: 12px; font-weight: 700; color: #0f172a; margin: 4px 0 6px 0; line-height: 1.4;">${alumni.judul_ta}</p>
                ${alumni.url_publikasi ? `<a href="${alumni.url_publikasi}" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 800; color: #0D542B; text-decoration: underline;">Buka Repositori Tugas Akhir</a>` : ''}
            </div>
        `;
    }

    // Bangun HTML Pendidikan Lanjut jika ada
    let lanjutStudiHtml = '';
    if (alumni.perguruan_tinggi) {
        lanjutStudiHtml = `
            <div style="background: #fefce8; border: 1px solid #fef08a; border-radius: 12px; padding: 10px; margin-top: 10px; text-align: left;">
                <span style="font-size: 10px; font-weight: 800; color: #854d0e; text-transform: uppercase; display: block;">Studi Lanjut</span>
                <p style="font-size: 11px; font-weight: 700; color: #1e293b; margin: 2px 0 0 0;">${alumni.pendidikan_tingkat || 'S2'} — ${alumni.pendidikan_prodi || 'Pascasarjana'}</p>
                <p style="font-size: 11px; color: #475569; margin: 0;">${alumni.perguruan_tinggi}</p>
            </div>
        `;
    }

    Swal.fire({
        html: `
            <div style="text-align: left; font-family: inherit; padding: 0;">

                <!-- Header: Foto + Nama + Prodi (Tanpa Bulatan) -->
                <div style="margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 12px;">
                    <img
                        src="${alumni.foto || `https://ui-avatars.com/api/?name=${encodeURIComponent(alumni.nama)}&background=0D542B&color=FACC15&bold=true&size=120`}"
                        alt="${alumni.nama}"
                        style="width: 48px; height: 48px; border-radius: 12px; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;"
                    />
                    <div style="min-width: 0; flex: 1;">
                        <h3 style="font-size: 15px; font-weight: 900; color: #0f172a; margin: 0 0 2px 0; line-height: 1.3;">${alumni.nama}</h3>
                        <p style="font-size: 11px; color: #64748b; margin: 0; font-weight: 600;">${alumni.prodi} — Lulus ${alumni.tahun_lulus}</p>
                    </div>
                </div>

                <!-- Jabatan & Kantor -->
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 10px; margin-bottom: 8px;">
                    <span style="font-size: 9px; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.06em; display: block; margin-bottom: 3px;">Posisi &amp; Instansi</span>
                    <p style="font-size: 13px; font-weight: 800; color: #0D542B; margin: 0 0 2px 0; line-height: 1.2;">${alumni.posisi_jabatan || 'Alumni Berprestasi'}</p>
                    <p style="font-size: 12px; font-weight: 700; color: #0f172a; margin: 0 0 3px 0;">${alumni.perusahaan_nama || 'Instansi Terverifikasi'}</p>
                    <p style="font-size: 11px; color: #64748b; margin: 0; line-height: 1.4;">
                        ${[alumni.perusahaan_alamat, alumni.perusahaan_kabupaten, alumni.perusahaan_provinsi].filter(Boolean).join(', ')}${alumni.zipcode ? ' ' + alumni.zipcode : ''}
                    </p>
                </div>

                <!-- Tugas Akhir -->
                ${alumni.judul_ta ? `
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; margin-bottom: 8px;">
                    <span style="font-size: 9px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; display: block; margin-bottom: 3px;">Tugas Akhir</span>
                    <p style="font-size: 11px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0; line-height: 1.4;">${alumni.judul_ta}</p>
                    ${alumni.url_publikasi ? `<a href="${alumni.url_publikasi}" target="_blank" style="font-size: 11px; font-weight: 700; color: #0D542B; text-decoration: underline;">Buka Repositori</a>` : ''}
                </div>` : ''}

                <!-- Studi Lanjut -->
                ${alumni.perguruan_tinggi ? `
                <div style="background: #fefce8; border: 1px solid #fef08a; border-radius: 10px; padding: 10px; margin-bottom: 8px;">
                    <span style="font-size: 9px; font-weight: 800; color: #854d0e; text-transform: uppercase; letter-spacing: 0.06em; display: block; margin-bottom: 3px;">Studi Lanjut</span>
                    <p style="font-size: 11px; font-weight: 700; color: #0f172a; margin: 0;">${alumni.pendidikan_tingkat || 'S2'} — ${alumni.pendidikan_prodi || 'Pascasarjana'} — ${alumni.perguruan_tinggi}</p>
                </div>` : ''}

                <!-- Jejaring Sosial -->
                ${(alumni.linkedin_url || alumni.instagram_url || alumni.facebook_url) ? `
                <div style="padding-top: 8px; border-top: 1px solid #f1f5f9;">
                    <span style="font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; display: block; margin-bottom: 6px;">Jejaring Profesional</span>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        ${alumni.linkedin_url ? `
                        <a href="${alumni.linkedin_url}" target="_blank" style="display:inline-flex; align-items:center; gap:6px; padding: 5px 12px; border-radius: 7px; background: #0077B5; color: #fff; font-size: 11px; font-weight: 700; text-decoration: none;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                            LinkedIn
                        </a>` : ''}
                        ${alumni.instagram_url ? `
                        <a href="${alumni.instagram_url}" target="_blank" style="display:inline-flex; align-items:center; gap:6px; padding: 5px 12px; border-radius: 7px; background: linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); color: #fff; font-size: 11px; font-weight: 700; text-decoration: none;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                            Instagram
                        </a>` : ''}
                        ${alumni.facebook_url ? `
                        <a href="${alumni.facebook_url}" target="_blank" style="display:inline-flex; align-items:center; gap:6px; padding: 5px 12px; border-radius: 7px; background: #1877F2; color: #fff; font-size: 11px; font-weight: 700; text-decoration: none;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            Facebook
                        </a>` : ''}
                    </div>
                </div>` : ''}

            </div>
        `,
        showCloseButton: true,
        showCancelButton: false,
        confirmButtonText: 'Tutup',
        confirmButtonColor: '#0D542B',
        padding: '1.25rem',
        width: '430px',
        customClass: {
            popup: 'rounded-2xl shadow-2xl border border-slate-100',
            htmlContainer: '!mt-0 !text-left',
            confirmButton: 'rounded-xl font-bold text-xs px-6 py-2.5 !mt-3 shadow-sm'
        }
    });
};

// Fokus peta ke lokasi alumni (dipanggil saat kartu diklik)
const focusAlumniOnMap = (alumni) => {
    const provName = alumni.perusahaan_provinsi;
    if (!provName || !geojsonLayer || !map) return;

    // Set filter provinsi & kabupaten agar daftar ikut filter
    selectedProvFilter.value = provName;
    if (alumni.perusahaan_kabupaten) {
        selectedKabFilter.value = alumni.perusahaan_kabupaten;
    }
    const targetNorm = normalizeProvinceName(provName);

    geojsonLayer.eachLayer(layer => {
        if (layer.feature && normalizeProvinceName(layer.feature.properties.PROVINSI) === targetNorm) {
            map.fitBounds(layer.getBounds(), { padding: [35, 35] });
            layer.setStyle({ weight: 4, color: '#FACC15', fillOpacity: 0.95 });
            renderKabupatenForProvince(provName);
            isZoomedIn.value = true;
            selectedProvince.value = provName;
        }
    });
};

// Inisialisasi Peta Leaflet
const initMap = async () => {
    await nextTick();
    const mapContainer = document.getElementById('alumni-leaflet-map');
    if (!mapContainer || map) return;

    // Koordinat pusat peta Indonesia: [-2.55, 118.02] zoom 5
    map = L.map('alumni-leaflet-map', {
        center: [-2.55, 118.02],
        zoom: 5,
        minZoom: 4,
        maxZoom: 13,
        scrollWheelZoom: false,
    });

    // Tile Layer OpenStreetMap Resmi
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);

    try {
        isLoadingGeo.value = true;

        const [resProv, resKab] = await Promise.all([
            fetch('/geojson/indonesia-provinsi.json'),
            fetch('/geojson/indonesia-kabupaten.json')
        ]);

        if (!resProv.ok || !resKab.ok) {
            throw new Error('Gagal mengunduh berkas GeoJSON wilayah.');
        }

        provinsiData = await resProv.json();
        kabupatenData = await resKab.json();

        renderProvinsiLayer();
    } catch (err) {
        console.error('Gagal memuat dataset GeoJSON:', err);
    } finally {
        isLoadingGeo.value = false;
    }
};

const renderProvinsiLayer = () => {
    if (!map || !provinsiData) return;

    if (geojsonLayer) {
        map.removeLayer(geojsonLayer);
    }

    geojsonLayer = L.geoJSON(provinsiData, {
        style: styleProvinceFeature,
        onEachFeature: (feature, layer) => {
            const provName = feature.properties.PROVINSI;
            const summary = getProvinceSummary(provName);

            // Format daftar perusahaan (PT apa saja)
            let ptListHtml = '';
            if (summary.perusahaan && summary.perusahaan.length > 0) {
                const ptSlice = summary.perusahaan.slice(0, 3);
                ptListHtml = `
                    <div class="mt-1 pt-1 border-t border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Perusahaan / Mitra</span>
                        <span class="text-[11px] font-semibold text-slate-800 block">${ptSlice.join(', ')}${summary.perusahaan.length > 3 ? ` (+${summary.perusahaan.length - 3} lainnya)` : ''}</span>
                    </div>
                `;
            }

            // Format daftar alumni (siapa saja)
            let alumniListHtml = '';
            if (summary.alumni && summary.alumni.length > 0) {
                const alumniSlice = summary.alumni.slice(0, 3);
                alumniListHtml = `
                    <div class="mt-1 pt-1 border-t border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Alumni</span>
                        <span class="text-[11px] text-slate-700 block">${alumniSlice.join(', ')}${summary.alumni.length > 3 ? ` (+${summary.alumni.length - 3} lainnya)` : ''}</span>
                    </div>
                `;
            }

            // Bind Tooltip hover — pastikan teks wrap dan tidak meluber keluar kotak
            layer.bindTooltip(`
                <div style="width: 100%; box-sizing: border-box;">
                    <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 6px;">
                        <strong style="font-size: 13px; font-weight: 800; color: #0f172a; line-height: 1.25;">${provName}</strong>
                        <span style="font-size: 11px; font-weight: 800; color: #0D542B; white-space: nowrap !important; flex-shrink: 0;">${summary.total} Alumni</span>
                    </div>
                    ${summary.perusahaan && summary.perusahaan.length > 0 ? `
                    <div style="margin-top: 6px; padding-top: 6px; border-top: 1px solid #f1f5f9;">
                        <span style="font-size: 9px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 2px;">Perusahaan / Mitra</span>
                        <p style="font-size: 11px; font-weight: 600; color: #1e293b; margin: 0; line-height: 1.4;">${summary.perusahaan.slice(0, 3).join(', ')}${summary.perusahaan.length > 3 ? ` (+${summary.perusahaan.length - 3} lainnya)` : ''}</p>
                    </div>` : ''}
                    ${summary.alumni && summary.alumni.length > 0 ? `
                    <div style="margin-top: 6px; padding-top: 6px; border-top: 1px solid #f1f5f9;">
                        <span style="font-size: 9px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 2px;">Alumni</span>
                        <p style="font-size: 11px; color: #475569; margin: 0; line-height: 1.4;">${summary.alumni.slice(0, 3).join(', ')}${summary.alumni.length > 3 ? ` (+${summary.alumni.length - 3} lainnya)` : ''}</p>
                    </div>` : ''}
                    <div style="margin-top: 8px; padding-top: 5px; border-top: 1px dashed #e2e8f0;">
                        <span style="font-size: 10px; color: #0D542B; font-weight: 700; display: block;">Klik untuk saring daftar</span>
                    </div>
                </div>
            `, { sticky: true, className: 'leaflet-custom-tooltip', direction: 'auto', opacity: 1 });

            layer.on({
                mouseover: highlightFeature,
                mouseout: resetHighlight,
                click: (e) => handleProvinceClick(e, feature)
            });
        }
    }).addTo(map);
};

// Pantau dropdown provinsi → update peta otomatis
watch(selectedProvFilter, (newProv) => {
    if (newProv === 'ALL') {
        resetMapToIndonesia();
    } else if (geojsonLayer && map) {
        const targetNorm = normalizeProvinceName(newProv);
        geojsonLayer.eachLayer(layer => {
            if (layer.feature && normalizeProvinceName(layer.feature.properties.PROVINSI) === targetNorm) {
                map.fitBounds(layer.getBounds(), { padding: [25, 25] });
                renderKabupatenForProvince(newProv);
                isZoomedIn.value = true;
                selectedProvince.value = newProv;
            }
        });
    }
});

// Pantau dropdown kabupaten → auto-zoom ke kabupaten di peta
watch(selectedKabFilter, (newKab) => {
    if (newKab === 'ALL' || !kabupatenGeoLayer || !map) return;
    const targetKab = newKab.toLowerCase();
    kabupatenGeoLayer.eachLayer(layer => {
        const kabName = (layer.feature?.properties?.WADMKK || layer.feature?.properties?.NAMOBJ || '').toLowerCase();
        if (kabName.includes(targetKab)) {
            map.fitBounds(layer.getBounds(), { padding: [20, 20] });
        }
    });
});

onMounted(() => {
    initMap();
});

defineExpose({
    searchQuery
});
</script>

<template>
    <section id="peta-alumni" :class="[bgClass, 'py-20 lg:py-24 relative overflow-hidden transition-colors duration-300', isLightBg ? 'text-slate-950 border-b border-amber-300/70' : 'text-white border-b border-white/10']">
        <!-- Glow Accents -->
        <div v-if="!isLightBg" class="absolute -top-32 -left-32 w-96 h-96 bg-[#004D25]/80 rounded-full blur-3xl pointer-events-none"></div>
        <div v-if="!isLightBg" class="absolute -bottom-32 -right-32 w-96 h-96 bg-[#FFC700]/10 rounded-full blur-3xl pointer-events-none"></div>
        <div v-else class="absolute -top-32 -left-32 w-96 h-96 bg-white/40 rounded-full blur-3xl pointer-events-none"></div>
        <div v-if="isLightBg" class="absolute -bottom-32 -right-32 w-96 h-96 bg-amber-600/15 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full relative z-10">
            
            <!-- 
              ========================================================================
              HEADER SECTION (Glossy Frosted Design Language)
              ========================================================================
            -->
            <div class="mb-7 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <!-- Badge Cakupan Peta Indonesia -->
                    <div :class="isLightBg ? 'bg-[#004D25] text-[#FFC700]' : 'bg-[#FFC700] text-slate-950'" class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full shadow-md font-black text-xs mb-2.5">
                        <svg :class="isLightBg ? 'text-[#FFC700]' : 'text-slate-950'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span>Cakupan Peta: Seluruh Wilayah Indonesia (Nasional)</span>
                    </div>

                    <h2 :class="isLightBg ? 'text-slate-950' : 'text-white'" class="text-2xl sm:text-3xl font-black tracking-tight">
                        PETA SEBARAN &amp; DIREKTORI ALUMNI <span :class="isLightBg ? 'text-[#004D25]' : 'text-[#FFC700]'">UKDW</span>
                    </h2>
                    <p :class="isLightBg ? 'text-slate-900/85 font-medium' : 'text-white/80'" class="text-xs sm:text-sm mt-1 max-w-2xl leading-relaxed">
                        Visualisasi sebaran alumni di seluruh provinsi Indonesia. Gunakan filter aktivitas dan wilayah di bawah untuk menelusuri karier dalam maupun luar negeri.
                    </p>
                </div>

                <!-- Info pill -->
                <div :class="isLightBg ? 'text-slate-900 bg-white/90 border-amber-300/80 shadow-md' : 'text-white bg-white/10 border-white/20 shadow-lg'" class="hidden sm:flex items-center gap-2 text-xs font-semibold backdrop-blur-md px-4 py-2 rounded-full border">
                    <span :class="isLightBg ? 'bg-[#004D25]' : 'bg-[#FFC700]'" class="w-2.5 h-2.5 rounded-full animate-pulse"></span>
                    <span>{{ filteredAlumni.length }} Alumni Tersaring</span>
                </div>
            </div>

            <!-- 
              ========================================================================
              FILTER BAR (Glossy Frosted Glass Card)
              Sesuai /profile: Aktivitas (Perusahaan, Wirausaha, Lanjut Studi),
              Wilayah (Dalam Negeri, Luar Negeri), Provinsi & Kab/Kota
              ========================================================================
            -->
            <div :class="isLightBg ? 'bg-white/90 border-amber-300/80 shadow-xl' : 'bg-white/10 border-white/20 shadow-2xl'" class="backdrop-blur-2xl rounded-3xl p-4 sm:p-5 border mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
                    
                    <!-- 1. Search Input (Col 4) -->
                    <div class="lg:col-span-4 relative">
                        <input 
                            type="text"
                            v-model="searchQuery"
                            placeholder="Cari nama, jabatan, perusahaan, prodi..."
                            :class="isLightBg ? 'border-amber-300/80 bg-white text-slate-900 placeholder-slate-400 focus:border-[#004D25] focus:ring-[#004D25]/20' : 'border-white/20 bg-black/40 text-white placeholder-white/50 focus:border-[#FFC700] focus:ring-[#FFC700]/20'"
                            class="w-full pl-9 pr-4 py-2.5 rounded-2xl border text-xs sm:text-sm font-medium focus:outline-none focus:ring-2 transition-all shadow-inner"
                        />
                        <svg :class="isLightBg ? 'text-slate-400' : 'text-white/50'" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>

                    <!-- 2. Dropdown Kategori Aktivitas Karier (Col 2) -->
                    <div class="lg:col-span-2">
                        <select 
                            v-model="selectedAktivitasFilter"
                            :class="isLightBg ? 'border-amber-300/80 text-slate-900 bg-white focus:border-[#004D25]' : 'border-white/20 text-white bg-slate-900/90 focus:border-[#FFC700]'"
                            class="w-full px-3 py-2.5 rounded-2xl border text-xs sm:text-sm font-medium focus:outline-none transition-all cursor-pointer shadow-inner"
                        >
                            <option value="ALL" :class="isLightBg ? 'bg-white text-slate-900' : 'bg-slate-900 text-white'">Semua Aktivitas</option>
                            <option value="Perusahaan" :class="isLightBg ? 'bg-white text-slate-900' : 'bg-slate-900 text-white'">Perusahaan</option>
                            <option value="Wirausaha" :class="isLightBg ? 'bg-white text-slate-900' : 'bg-slate-900 text-white'">Wirausaha</option>
                            <option value="Lanjut Pendidikan" :class="isLightBg ? 'bg-white text-slate-900' : 'bg-slate-900 text-white'">Lanjut Pendidikan</option>
                        </select>
                    </div>

                    <!-- 3. Dropdown Jenis Wilayah (Col 2) -->
                    <div class="lg:col-span-2">
                        <select 
                            v-model="selectedLokasiFilter"
                            :class="isLightBg ? 'border-amber-300/80 text-slate-900 bg-white focus:border-[#004D25]' : 'border-white/20 text-white bg-slate-900/90 focus:border-[#FFC700]'"
                            class="w-full px-3 py-2.5 rounded-2xl border text-xs sm:text-sm font-medium focus:outline-none transition-all cursor-pointer shadow-inner"
                        >
                            <option value="ALL" :class="isLightBg ? 'bg-white text-slate-900' : 'bg-slate-900 text-white'">Semua Wilayah</option>
                            <option value="Dalam Negeri" :class="isLightBg ? 'bg-white text-slate-900' : 'bg-slate-900 text-white'">Dalam Negeri</option>
                            <option value="Luar Negeri" :class="isLightBg ? 'bg-white text-slate-900' : 'bg-slate-900 text-white'">Luar Negeri</option>
                        </select>
                    </div>

                    <!-- 4. Dropdown Provinsi (Col 2) -->
                    <div class="lg:col-span-2">
                        <select 
                            v-model="selectedProvFilter"
                            :disabled="selectedLokasiFilter === 'Luar Negeri'"
                            :class="isLightBg ? 'border-amber-300/80 text-slate-900 bg-white focus:border-[#004D25]' : 'border-white/20 text-white bg-slate-900/90 focus:border-[#FFC700]'"
                            class="w-full px-3 py-2.5 rounded-2xl border text-xs sm:text-sm font-medium focus:outline-none transition-all cursor-pointer shadow-inner disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <option value="ALL" :class="isLightBg ? 'bg-white text-slate-900' : 'bg-slate-900 text-white'">Semua Provinsi</option>
                            <option v-for="prov in availableProvinsi" :key="prov" :value="prov" :class="isLightBg ? 'bg-white text-slate-900' : 'bg-slate-900 text-white'">
                                {{ prov }}
                            </option>
                        </select>
                    </div>

                    <!-- 5. Tombol Reset / Lihat Semua (Col 2) -->
                    <div class="lg:col-span-2">
                        <button
                            type="button"
                            @click="resetMapToIndonesia"
                            :class="isLightBg ? 'bg-[#004D25] hover:bg-[#03542B] text-[#FFC700]' : 'bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950'"
                            class="w-full py-2.5 px-3 font-black text-xs sm:text-sm rounded-full transition-all text-center cursor-pointer shadow-md hover:shadow-lg active:scale-95"
                        >
                            Reset Filter
                        </button>
                    </div>

                </div>
            </div>

            <!-- 
              ========================================================================
              LAYOUT 2 KOLOM TETAP (Fixed Heights, Tidak Peyot Saat Dikecilkan/Diperbesar)
              - KIRI (7 Kolom) : PETA INTERAKTIF OPENSTREETMAP (Nasional Indonesia)
              - KANAN (5 Kolom): DIREKTORI ALUMNI GLOSSY CARDS (4 Kartu)
              ========================================================================
            -->
            <div id="alumni-map-container" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                
                <!-- KOLOM KIRI (7 Kolom): PETA INTERAKTIF OPENSTREETMAP -->
                <div :class="isLightBg ? 'bg-white/95 border-amber-300/80 shadow-xl' : 'bg-white/10 border-white/20 shadow-2xl'" class="lg:col-span-7 backdrop-blur-2xl rounded-3xl border overflow-hidden flex flex-col min-h-[540px]">
                    <!-- Header Bar Peta Glossy Style -->
                    <div :class="isLightBg ? 'bg-amber-100/70 border-b border-amber-200/80' : 'bg-black/40 border-b border-white/15'" class="px-5 py-3.5 flex items-center justify-between gap-2 shrink-0">
                        <div class="flex items-center gap-2">
                            <span :class="isLightBg ? 'text-slate-950' : 'text-white'" class="text-xs font-black uppercase tracking-wider">
                                PETA NASIONAL INDONESIA
                            </span>
                            <span v-if="selectedProvince" :class="isLightBg ? 'text-[#004D25]' : 'text-[#FFC700]'" class="text-xs font-bold">
                                / {{ selectedProvince }}
                            </span>
                        </div>

                        <button
                            v-if="isZoomedIn || selectedProvince"
                            @click="resetMapToIndonesia"
                            :class="isLightBg ? 'bg-[#004D25] hover:bg-[#03542B] text-[#FFC700]' : 'bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950'"
                            class="px-3.5 py-1 font-bold text-xs rounded-full transition-all inline-flex items-center gap-1 shadow-sm cursor-pointer active:scale-95"
                        >
                            <span>Tampilan Nasional</span>
                        </button>
                    </div>

                    <!-- Leaflet Canvas (Tinggi Pasti & Stabil) -->
                    <div class="relative w-full flex-1 min-h-[480px]">
                        <!-- Loading Indikator -->
                        <div v-if="isLoadingGeo" class="absolute inset-0 z-20 bg-black/60 backdrop-blur-sm flex flex-col items-center justify-center gap-3">
                            <svg class="w-12 h-12 text-[#FFC700] animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-15" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>

                        <div id="alumni-leaflet-map" class="w-full h-full z-10 min-h-[480px]"></div>

                        <!-- Legenda Peta Glossy Rounded Pill -->
                        <div :class="isLightBg ? 'bg-white/90 border-amber-300/80 text-slate-900' : 'bg-black/60 border-white/20 text-white'" class="absolute bottom-3 left-3 z-20 backdrop-blur-md px-3.5 py-1.5 rounded-full border shadow-md">
                            <div class="flex items-center gap-3 text-[10px] font-bold">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-[#004D25] border border-white/30"></span> &gt;50</span>
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-[#03542B] border border-white/30"></span> 21–50</span>
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-[#FFC700]"></span> 1–20</span>
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-slate-500 border border-slate-400"></span> 0</span>
                            </div>
                        </div>

                        <!-- Notifikasi jika filter Luar Negeri aktif -->
                        <div v-if="selectedLokasiFilter === 'Luar Negeri'" class="absolute top-3 left-3 right-3 z-20 bg-slate-900/95 backdrop-blur-md text-white px-4 py-2.5 rounded-2xl shadow-xl flex items-center justify-between text-xs border border-white/20">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-[#FFC700]"></span>
                                <span>Filter Luar Negeri aktif: Alumni luar negeri ditampilkan pada daftar di sebelah kanan.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KOLOM KANAN (5 Kolom): DIREKTORI ALUMNI (Glossy Squircle Cards Sesuai Gambar) -->
                <div class="lg:col-span-5 flex flex-col justify-between min-h-[540px]">
                    
                    <!-- Header Kolom Kanan -->
                    <div class="mb-3 flex items-center justify-between gap-2 px-1">
                        <div>
                            <span :class="isLightBg ? 'text-slate-950' : 'text-white'" class="text-xs font-black uppercase tracking-wider block">
                                <span v-if="selectedProvFilter !== 'ALL'">{{ selectedProvFilter }}</span>
                                <span v-else>Direktori Alumni Terdaftar</span>
                            </span>
                            <span :class="isLightBg ? 'text-slate-800' : 'text-white/70'" class="text-[11px] font-semibold">
                                Menampilkan {{ filteredAlumni.length }} data lulusan
                            </span>
                        </div>

                        <!-- Pagination Glossy Pill -->
                        <div v-if="totalPages > 1" :class="isLightBg ? 'bg-white/90 border-amber-300/80 shadow-md' : 'bg-white/10 border-white/20 shadow-md'" class="flex items-center gap-1 backdrop-blur-md p-1 rounded-full border">
                            <button
                                type="button"
                                @click="currentPage = Math.max(1, currentPage - 1)"
                                :disabled="currentPage === 1"
                                :class="isLightBg ? 'text-slate-900 hover:bg-amber-100' : 'text-white hover:bg-white/20'"
                                class="px-2.5 py-1 rounded-full text-xs font-bold disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer"
                            >
                                ‹
                            </button>
                            <span :class="isLightBg ? 'text-[#004D25]' : 'text-[#FFC700]'" class="text-xs font-black px-1">
                                {{ currentPage }}/{{ totalPages }}
                            </span>
                            <button
                                type="button"
                                @click="currentPage = Math.min(totalPages, currentPage + 1)"
                                :disabled="currentPage === totalPages"
                                :class="isLightBg ? 'text-slate-900 hover:bg-amber-100' : 'text-white hover:bg-white/20'"
                                class="px-2.5 py-1 rounded-full text-xs font-bold disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer"
                            >
                                ›
                            </button>
                        </div>
                    </div>

                    <!-- 
                      Grid 2x2 Kartu Alumni Glossy Style:
                      - Tampilan Squircle (rounded-3xl)
                      - Foto Profil Berukuran Nyata /uploads/profile
                      - Badge Kategori (Perusahaan/Wirausaha/Lanjut Studi)
                      - Verified Checkmark Biru
                      - Tombol Pill Action "Detail Profil"
                    -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 flex-1">
                        <div
                            v-for="alumni in paginatedAlumni"
                            :key="alumni.id"
                            @click="openDetailSweetAlert(alumni)"
                            :class="isLightBg ? 'bg-white/95 border-amber-200/90 hover:border-[#004D25]/50 hover:bg-white text-slate-900 shadow-md hover:shadow-xl' : 'bg-white/10 border-white/20 hover:border-[#FFC700]/70 hover:bg-white/15 text-white shadow-2xl hover:shadow-2xl'"
                            class="backdrop-blur-2xl rounded-3xl border p-3.5 transition-all flex flex-col justify-between group cursor-pointer"
                        >
                            <div>
                                <!-- Top Area: Foto Portrait dengan Rounded Squircle & Floating Badges -->
                                <div class="relative w-full h-32 rounded-2xl overflow-hidden bg-slate-900 border border-black/10 shadow-inner mb-2.5">
                                    <img
                                        :src="alumni.foto || `https://ui-avatars.com/api/?name=${encodeURIComponent(alumni.nama)}&background=004D25&color=FFC700&bold=true&size=160`"
                                        :alt="alumni.nama"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                        loading="lazy"
                                    />
                                    
                                    <!-- Badge Kategori Aktivitas (Glossy Frosted Pill) -->
                                    <span class="absolute top-2 left-2 px-2.5 py-0.5 rounded-full bg-black/60 backdrop-blur-md text-white font-bold text-[9px] uppercase tracking-wider shadow-xs border border-white/20">
                                        {{ alumni.kategori_aktivitas || 'Perusahaan' }}
                                    </span>

                                    <!-- Badge Lokasi (Dalam Negeri / Luar Negeri) -->
                                    <span 
                                        :class="alumni.jenis_lokasi === 'Luar Negeri' ? 'bg-[#FFC700] text-slate-950 font-black' : 'bg-white/80 text-slate-900 font-bold'"
                                        class="absolute top-2 right-2 px-2 py-0.5 rounded-full backdrop-blur-md text-[9px] shadow-xs"
                                    >
                                        {{ alumni.jenis_lokasi || 'Dalam Negeri' }}
                                    </span>
                                </div>

                                <!-- Nama Alumni + Verified Icon -->
                                <div class="flex items-center gap-1 min-w-0">
                                    <h4 :class="isLightBg ? 'text-slate-950 group-hover:text-[#004D25]' : 'text-white group-hover:text-[#FFC700]'" class="text-sm font-extrabold transition-colors leading-snug truncate">
                                        {{ alumni.nama }}
                                    </h4>
                                    <!-- Verified Checkmark Badge UKDW Amber Gold -->
                                    <svg :class="isLightBg ? 'text-[#004D25]' : 'text-[#FFC700]'" class="w-3.5 h-3.5 fill-current shrink-0" viewBox="0 0 24 24">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                </div>

                                <!-- Jabatan & Tempat Kerja / Perguruan Tinggi -->
                                <p :class="isLightBg ? 'text-slate-700' : 'text-white/80'" class="text-[11px] font-semibold truncate mt-0.5">
                                    {{ alumni.posisi_jabatan || 'Alumni' }} • {{ alumni.perusahaan_nama || alumni.perguruan_tinggi || 'UKDW' }}
                                </p>

                                <!-- Mini Stats/Chips Row (Glossy Style) -->
                                <div :class="isLightBg ? 'border-slate-200 text-slate-600' : 'border-white/15 text-white/70'" class="flex items-center gap-2 mt-2 pt-2 border-t text-[10px] font-medium">
                                    <span :class="isLightBg ? 'bg-[#004D25] text-[#FFC700]' : 'bg-[#FFC700] text-slate-950'" class="font-black px-1.5 py-0.5 rounded-md">
                                        '{{ (alumni.tahun_lulus || '').slice(-2) }}
                                    </span>
                                    <span class="truncate flex-1">
                                        {{ alumni.perusahaan_kabupaten || alumni.perusahaan_provinsi || alumni.jenis_lokasi || 'Indonesia' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Bottom Row: Pill Action Button & Social Icon -->
                            <div class="mt-3 flex items-center gap-2">
                                <button
                                    type="button"
                                    @click.stop="openDetailSweetAlert(alumni)"
                                    :class="isLightBg ? 'bg-[#004D25] hover:bg-[#03542B] text-[#FFC700]' : 'bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950'"
                                    class="flex-1 py-2 px-3 rounded-full font-black text-xs shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer active:scale-95"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Detail Profil</span>
                                </button>

                                <a
                                    v-if="alumni.linkedin_url"
                                    :href="alumni.linkedin_url"
                                    target="_blank"
                                    @click.stop
                                    :class="isLightBg ? 'bg-slate-100 hover:bg-[#0077b5] text-slate-700 hover:text-white border border-slate-200' : 'bg-white/15 hover:bg-[#0077b5] text-white'"
                                    class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 transition-all shadow-md"
                                    title="LinkedIn Profil"
                                >
                                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 8.76a1.45 1.45 0 0 0 1.45-1.45A1.45 1.45 0 0 0 6.46 5.86 1.45 1.45 0 0 0 5 7.31a1.45 1.45 0 0 0 1.46 1.45m1.39 9.97v-8.37H5.07v8.37z"/></svg>
                                </a>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-if="filteredAlumni.length === 0"
                            :class="isLightBg ? 'bg-white/95 border-amber-300/80 text-slate-900 shadow-md' : 'bg-white/10 border-white/20 text-white shadow-2xl'"
                            class="col-span-1 sm:col-span-2 text-center py-16 backdrop-blur-2xl rounded-3xl border p-6"
                        >
                            <svg :class="isLightBg ? 'text-slate-400' : 'text-white/40'" class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <h4 :class="isLightBg ? 'text-slate-950 font-bold' : 'text-white font-bold'" class="text-sm">Tidak ada alumni yang sesuai kriteria</h4>
                            <p :class="isLightBg ? 'text-slate-600' : 'text-white/70'" class="text-xs mt-1">Coba sesuaikan filter aktivitas, wilayah, atau kata kunci pencarian.</p>
                            <button
                                type="button"
                                @click="resetMapToIndonesia"
                                :class="isLightBg ? 'bg-[#004D25] hover:bg-[#03542B] text-[#FFC700]' : 'bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950'"
                                class="mt-4 px-5 py-2 font-black text-xs rounded-full shadow-md transition-all active:scale-95 cursor-pointer"
                            >
                                Reset Filter
                            </button>
                        </div>
                    </div>

                    <!-- Bottom Status Text -->
                    <div :class="isLightBg ? 'text-slate-800 font-medium' : 'text-white/70 font-medium'" class="mt-3 flex items-center justify-between text-[11px] px-1">
                        <span>Pilih kartu untuk melihat detail profil atau zoom lokasi</span>
                        <span v-if="totalPages > 1" class="text-[#FFC700] font-bold">Halaman {{ currentPage }} dari {{ totalPages }}</span>
                    </div>

                </div>

            </div>

        </div>
    </section>
</template>

<style>
/* Kustomisasi Tooltip Leaflet Apple Glass Style - Pastikan tidak overflow keluar kotak */
.leaflet-tooltip.leaflet-custom-tooltip {
    background: rgba(255, 255, 255, 0.98) !important;
    backdrop-filter: blur(16px) !important;
    border: 1px solid rgba(226, 232, 240, 0.95) !important;
    border-radius: 14px !important;
    box-shadow: 0 12px 30px -4px rgba(0, 0, 0, 0.16) !important;
    padding: 12px 14px !important;
    line-height: 1.45 !important;
    white-space: normal !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
    width: 270px !important;
    min-width: 240px !important;
    max-width: 290px !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
}
.leaflet-tooltip.leaflet-custom-tooltip::before {
    display: none !important;
}
.leaflet-tooltip.leaflet-custom-tooltip * {
    white-space: normal !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
    box-sizing: border-box !important;
}
</style>
