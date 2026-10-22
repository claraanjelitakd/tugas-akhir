<!--
  Komponen Bersama: Tab Hasil Scraping LinkedIn & Staging Audit (Admin View)
  Lokasi: resources/js/Components/AlumniAudit/TabLinkedin.vue
  
  Mendukung peran Super Admin, Fakultas, Prodi, dan Biro 3 (readonly).
-->
<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

/**
 * Definisi properti komponen tab sinkronisasi LinkedIn.
 */
const props = defineProps({
    /** Objek data alumni */
    alumni: {
        type: Object,
        required: true,
    },
    /** Hasil audit sinkronisasi LinkedIn terakhir */
    linkedinSyncResult: {
        type: Object,
        default: null,
    },
    /** Pemetaan data audit hasil tracing LinkedIn */
    linkedinTraceMapping: {
        type: Array,
        default: () => [],
    },
    /** Riwayat log sinkronisasi LinkedIn alumni */
    linkedinHistory: {
        type: Array,
        default: () => [],
    },
    /** Penanda apakah pengguna saat ini adalah Super Administrator */
    isSuperAdmin: {
        type: Boolean,
        default: true,
    },
    /** Mode murni lihat saja (misal untuk Biro 3) */
    readonly: {
        type: Boolean,
        default: false,
    },
});

/** Sub-tab aktif dalam tab LinkedIn ('penyesuaian', 'riwayat', 'mapping') */
const activeLinkedinTab = ref('penyesuaian');

/** Kueri pencarian filter baris pemetaan data tracing */
const searchTraceQuery = ref('');

/** Flag status modal/drawer JSON mentah */
const showRawJson = ref(false);

/** Status loading saat sinkronisasi langsung sedang berlangsung */
const isDirectSyncing = ref(false);

/** Status loading saat proses approve sedang dikirim */
const isApprovingSync = ref(false);

/** Status loading saat proses reject sedang dikirim */
const isRejectingSync = ref(false);

/** Form data Inertia untuk aksi verifikasi / persetujuan audit LinkedIn */
const approveForm = useForm({
    tipe_pekerjaan: 'pekerja',
    posisi_jabatan: '',
    posisi_wiraswasta: '',
    nama_perusahaan: '',
    skills: '',
    sync_foto: true,
});

const initApproveForm = () => {
    if (props.linkedinSyncResult?.preview) {
        const prev = props.linkedinSyncResult.preview;
        approveForm.posisi_jabatan = prev.current_job || props.alumni.posisi_jabatan || '';
        approveForm.posisi_wiraswasta = prev.current_job || props.alumni.posisi_jabatan || '';
        approveForm.nama_perusahaan = prev.current_company || props.alumni.perusahaan?.nama_perusahaan || '';
        approveForm.skills = props.alumni.keahlian || props.alumni.skills || '';
        approveForm.sync_foto = !!prev.profile_picture;
    } else {
        approveForm.posisi_jabatan = props.alumni.posisi_jabatan || '';
        approveForm.nama_perusahaan = props.alumni.perusahaan?.nama_perusahaan || '';
        approveForm.skills = props.alumni.keahlian || props.alumni.skills || '';
    }
};

watch(() => props.linkedinSyncResult, () => {
    initApproveForm();
}, { immediate: true });

const applyCareerToForm = (exp) => {
    if (!exp) return;
    if (exp.position) approveForm.posisi_jabatan = exp.position;
    if (exp.company) approveForm.nama_perusahaan = exp.company;
    activeLinkedinTab.value = 'penyesuaian';
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'info',
        title: 'Posisi & Perusahaan disalin ke usulan data!',
        showConfirmButton: false,
        timer: 2000,
    });
};

const copyRawJson = () => {
    if (!props.linkedinSyncResult?.scraped_data) return;
    navigator.clipboard.writeText(JSON.stringify(props.linkedinSyncResult.scraped_data, null, 2))
        .then(() => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'JSON scraping berhasil disalin!',
                showConfirmButton: false,
                timer: 2000,
            });
        });
};

const handleDirectLinkedInSync = () => {
    if (!props.alumni?.id) return;
    isDirectSyncing.value = true;
    router.post(`/superadmin/linkedin-sync/direct-sync/${props.alumni.id}`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            isDirectSyncing.value = false;
            Swal.fire({
                icon: 'success',
                title: 'Sinkronisasi Berhasil!',
                text: 'Data profil publik LinkedIn terbaru telah berhasil diambil.',
                confirmButtonColor: '#0D542B',
            });
        },
        onError: (err) => {
            isDirectSyncing.value = false;
            Swal.fire({
                icon: 'error',
                title: 'Sinkronisasi Gagal',
                text: Object.values(err)[0] || 'Gagal menyinkronkan data LinkedIn.',
                confirmButtonColor: '#0D542B',
            });
        },
        onFinish: () => {
            isDirectSyncing.value = false;
        },
    });
};

const handleApproveStagingWithForm = () => {
    if (!props.linkedinSyncResult?.id) return;
    isApprovingSync.value = true;
    approveForm.post(`/superadmin/linkedin-sync/staging/${props.linkedinSyncResult.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => {
            isApprovingSync.value = false;
            Swal.fire({
                icon: 'success',
                title: 'Data Disetujui!',
                text: 'Data profil LinkedIn telah berhasil diterapkan ke profil alumni.',
                confirmButtonColor: '#0D542B',
            });
        },
        onError: (err) => {
            isApprovingSync.value = false;
            Swal.fire({
                icon: 'error',
                title: 'Persetujuan Gagal',
                text: Object.values(err)[0] || 'Gagal menyetujui data staging LinkedIn.',
                confirmButtonColor: '#0D542B',
            });
        },
        onFinish: () => {
            isApprovingSync.value = false;
        },
    });
};

const handleRejectStaging = () => {
    if (!props.linkedinSyncResult?.id) return;
    Swal.fire({
        title: 'Tolak Data Scraping LinkedIn?',
        text: 'Data hasil scraping ini akan ditandai ditolak dan tidak akan diterapkan ke profil alumni.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Tolak',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            isRejectingSync.value = true;
            router.post(`/superadmin/linkedin-sync/staging/${props.linkedinSyncResult.id}/reject`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    isRejectingSync.value = false;
                    Swal.fire({
                        icon: 'success',
                        title: 'Data Ditolak',
                        text: 'Hasil scraping LinkedIn ini telah ditolak.',
                        confirmButtonColor: '#0D542B',
                    });
                },
                onFinish: () => {
                    isRejectingSync.value = false;
                },
            });
        }
    });
};

const filteredTraceMapping = computed(() => {
    if (!props.linkedinTraceMapping) return [];
    if (!searchTraceQuery.value.trim()) return props.linkedinTraceMapping;
    const q = searchTraceQuery.value.toLowerCase();
    return props.linkedinTraceMapping.filter(item => {
        return (item.key && item.key.toLowerCase().includes(q)) ||
               (item.label && item.label.toLowerCase().includes(q)) ||
               (item.scraped_value && String(item.scraped_value).toLowerCase().includes(q)) ||
               (item.target_table && item.target_table.toLowerCase().includes(q)) ||
               (item.target_column && item.target_column.toLowerCase().includes(q)) ||
               (item.db_value && String(item.db_value).toLowerCase().includes(q)) ||
               (item.status && item.status.toLowerCase().includes(q));
    });
});
</script>

<template>
    <div class="space-y-5">
        <!-- Header Toolbar Bersih & Aksi Cepat (Ala Master Data) -->
        <div class="bg-white rounded-xl p-4 sm:p-5 border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h3 class="text-sm sm:text-base font-bold text-slate-900">
                        Data Sinkronisasi LinkedIn Alumni
                    </h3>
                    <span 
                        v-if="linkedinSyncResult"
                        class="px-2.5 py-0.5 rounded text-[11px] font-semibold border"
                        :class="linkedinSyncResult.status === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : (linkedinSyncResult.status === 'pending' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-rose-50 text-rose-800 border-rose-200')"
                    >
                        Status: {{ linkedinSyncResult.status === 'approved' ? 'Disetujui (Approved)' : (linkedinSyncResult.status === 'pending' ? 'Pending Review' : 'Ditolak (Rejected)') }}
                    </span>
                    <span v-else class="px-2.5 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                        Belum Ada Data Sinkronisasi
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Kelola pemetaan data publik LinkedIn alumni ke basis data Tracer Study.
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <!-- Tombol Tarik Pembaruan LinkedIn -->
                <button 
                    v-if="!readonly"
                    type="button" 
                    @click="handleDirectLinkedInSync"
                    :disabled="isDirectSyncing"
                    class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition flex items-center gap-1.5 cursor-pointer disabled:opacity-60 border border-slate-200"
                    title="Sinkronkan data LinkedIn alumni ini secara langsung"
                >
                    <svg v-if="!isDirectSyncing" class="w-3.5 h-3.5 fill-current shrink-0" viewBox="0 0 24 24">
                        <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                    </svg>
                    <svg v-else class="animate-spin w-3.5 h-3.5 text-slate-700 shrink-0" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>{{ isDirectSyncing ? 'Menyinkronkan...' : 'Sinkronkan Ulang' }}</span>
                </button>

                <!-- Tombol Toggle Raw JSON -->
                <button 
                    v-if="linkedinSyncResult?.scraped_data"
                    type="button" 
                    @click="showRawJson = !showRawJson" 
                    class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition flex items-center gap-1.5 cursor-pointer border border-slate-200"
                >
                    <span>{{ showRawJson ? 'Tutup JSON' : 'Lihat JSON' }}</span>
                </button>

                <!-- Tombol Buka LinkedIn Sync Menu -->
                <Link 
                    v-if="!readonly && isSuperAdmin"
                    :href="'/superadmin/linkedin-sync?alumni_id=' + alumni.id + '&search=' + encodeURIComponent(alumni.nim || alumni.nama || '')" 
                    class="px-3.5 py-2 bg-[#0D542B] hover:bg-[#0A4322] text-white text-xs font-semibold rounded-lg transition flex items-center gap-1.5 shadow-2xs"
                    title="Buka direktori lengkap LinkedIn Sync"
                >
                    <span>Menu LinkedIn Sync &rarr;</span>
                </Link>
            </div>
        </div>

        <!-- Panel Raw JSON (Expandable) -->
        <div v-if="showRawJson && linkedinSyncResult?.scraped_data" class="p-4 bg-slate-900 text-emerald-400 font-mono text-xs rounded-xl overflow-x-auto max-h-96 border border-slate-700">
            <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-800 text-slate-400">
                <span class="font-bold">Raw Payload JSON: {{ JSON.stringify(linkedinSyncResult.scraped_data).length }} Karakter</span>
                <button 
                    type="button" 
                    @click="copyRawJson"
                    class="text-xs text-slate-300 hover:text-white underline cursor-pointer"
                >
                    Salin ke Clipboard
                </button>
            </div>
            <pre class="leading-relaxed whitespace-pre-wrap">{{ JSON.stringify(linkedinSyncResult.scraped_data, null, 2) }}</pre>
        </div>

        <!-- Konten Utama Jika Ada Sinkronisasi -->
        <div v-if="linkedinSyncResult" class="space-y-4">
            <!-- TABEL 1: INFORMASI METADATA LINKEDIN -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                        Informasi Profil LinkedIn
                    </h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr class="divide-x divide-slate-100">
                                <td class="w-48 py-2.5 px-4 font-semibold bg-slate-50/60 text-slate-600">Target URL Profil</td>
                                <td class="py-2.5 px-4">
                                    <a 
                                        :href="linkedinSyncResult.linkedin_url" 
                                        target="_blank" 
                                        class="text-[#0077B5] hover:underline font-mono font-medium truncate block max-w-lg"
                                    >
                                        {{ linkedinSyncResult.linkedin_url }}
                                    </a>
                                </td>
                                <td class="w-48 py-2.5 px-4 font-semibold bg-slate-50/60 text-slate-600">Username / Handle</td>
                                <td class="py-2.5 px-4 font-mono font-medium text-slate-900">
                                    {{ linkedinSyncResult.linkedin_username || '-' }}
                                </td>
                            </tr>
                            <tr class="divide-x divide-slate-100">
                                <td class="w-48 py-2.5 px-4 font-semibold bg-slate-50/60 text-slate-600">Waktu Pengambilan Data</td>
                                <td class="py-2.5 px-4 font-mono text-slate-800">
                                    {{ linkedinSyncResult.scraped_at ? new Date(linkedinSyncResult.scraped_at).toLocaleString('id-ID') : '-' }}
                                </td>
                                <td class="w-48 py-2.5 px-4 font-semibold bg-slate-50/60 text-slate-600">Petugas Peninjau</td>
                                <td class="py-2.5 px-4 text-slate-800">
                                    {{ linkedinSyncResult.reviewer?.name || (linkedinSyncResult.status === 'approved' ? 'Super Admin' : 'Menunggu Review') }}
                                    <span v-if="linkedinSyncResult.reviewed_at" class="text-slate-400 font-mono text-[11px] ml-1">
                                        ({{ new Date(linkedinSyncResult.reviewed_at).toLocaleDateString('id-ID') }})
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SUB-NAVIGASI TAB BERSATU -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                    <div class="inline-flex rounded-lg bg-slate-200/60 p-1 text-xs">
                        <button
                            type="button"
                            @click="activeLinkedinTab = 'penyesuaian'"
                            :class="activeLinkedinTab === 'penyesuaian' ? 'bg-[#0D542B] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3 py-1.5 rounded-md transition cursor-pointer"
                        >
                            Perbandingan & Usulan Data
                        </button>
                        <button
                            type="button"
                            @click="activeLinkedinTab = 'riwayat_karier'"
                            :class="activeLinkedinTab === 'riwayat_karier' ? 'bg-[#0D542B] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3 py-1.5 rounded-md transition cursor-pointer flex items-center gap-1.5"
                        >
                            <span>Riwayat Karier & Pendidikan</span>
                            <span 
                                v-if="(linkedinSyncResult.preview?.experience || []).length" 
                                class="px-1.5 py-0.2 rounded-full text-[10px]"
                                :class="activeLinkedinTab === 'riwayat_karier' ? 'bg-white/20 text-white' : 'bg-slate-300 text-slate-700'"
                            >
                                {{ (linkedinSyncResult.preview?.experience || []).length }}
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="activeLinkedinTab = 'riwayat_sync'"
                            :class="activeLinkedinTab === 'riwayat_sync' ? 'bg-[#0D542B] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3 py-1.5 rounded-md transition cursor-pointer flex items-center gap-1.5"
                        >
                            <span>Riwayat Sinkronisasi (Log)</span>
                            <span 
                                v-if="linkedinHistory.length" 
                                class="px-1.5 py-0.2 rounded-full text-[10px]"
                                :class="activeLinkedinTab === 'riwayat_sync' ? 'bg-white/20 text-white' : 'bg-slate-300 text-slate-700'"
                            >
                                {{ linkedinHistory.length }}
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="activeLinkedinTab = 'audit_trace'"
                            :class="activeLinkedinTab === 'audit_trace' ? 'bg-[#0D542B] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3 py-1.5 rounded-md transition cursor-pointer flex items-center gap-1.5"
                        >
                            <span>Tabel Pemetaan Kolom Database</span>
                            <span 
                                v-if="filteredTraceMapping.length" 
                                class="px-1.5 py-0.2 rounded-full text-[10px]"
                                :class="activeLinkedinTab === 'audit_trace' ? 'bg-white/20 text-white' : 'bg-slate-300 text-slate-700'"
                            >
                                {{ filteredTraceMapping.length }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- SUB-TAB 1: TABEL PERBANDINGAN & USULAN DATA -->
                <div v-show="activeLinkedinTab === 'penyesuaian'" class="p-4 sm:p-5 space-y-4">
                    <div v-if="linkedinSyncResult.status === 'pending'" class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs text-slate-600">
                        Status saat ini <strong>Pending Review</strong>. Anda dapat menyesuaikan data pekerjaan sebelum menyetujui penerapan data ke basis data kampus.
                    </div>
                    <div v-else-if="linkedinSyncResult.status === 'approved'" class="p-3 bg-emerald-50 text-emerald-900 rounded-lg border border-emerald-200 text-xs">
                        Data hasil sinkronisasi LinkedIn telah <strong>disetujui dan diterapkan</strong> ke profil alumni oleh <b>{{ linkedinSyncResult.reviewer?.name || 'Super Admin' }}</b> pada {{ linkedinSyncResult.reviewed_at ? new Date(linkedinSyncResult.reviewed_at).toLocaleString('id-ID') : '-' }}.
                    </div>

                    <!-- Tabel Komparatif Master Data -->
                    <div class="overflow-x-auto border border-slate-200 rounded-xl">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-700 border-b border-slate-200 text-[11px] uppercase font-bold tracking-wider">
                                    <th class="py-3 px-4 w-40">Atribut Profil</th>
                                    <th class="py-3 px-4 min-w-[180px]">Data di Kampus Saat Ini</th>
                                    <th class="py-3 px-4 min-w-[200px]">Hasil Scraping LinkedIn</th>
                                    <th class="py-3 px-4 min-w-[260px]">Usulan Penyesuaian Data</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <!-- 1. Tipe Pekerjaan -->
                                <tr class="divide-x divide-slate-100">
                                    <td class="py-3 px-4 font-semibold text-slate-900 bg-slate-50/50">Tipe Pekerjaan</td>
                                    <td class="py-3 px-4 font-medium">{{ alumni.kategori_pekerjaan || '- Belum diisi -' }}</td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-sky-50 text-sky-800 border border-sky-200">
                                            Karyawan
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-100 text-[11px]">
                                            <button
                                                type="button"
                                                @click="approveForm.tipe_pekerjaan = 'pekerja'"
                                                :class="approveForm.tipe_pekerjaan === 'pekerja' ? 'bg-[#0D542B] text-white font-bold' : 'text-slate-600'"
                                                class="px-2.5 py-1 rounded-md transition cursor-pointer"
                                            >
                                                Karyawan
                                            </button>
                                            <button
                                                type="button"
                                                @click="approveForm.tipe_pekerjaan = 'wirausaha'"
                                                :class="approveForm.tipe_pekerjaan === 'wirausaha' ? 'bg-[#0D542B] text-white font-bold' : 'text-slate-600'"
                                                class="px-2.5 py-1 rounded-md transition cursor-pointer"
                                            >
                                                Wirausaha
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- 2. Posisi / Jabatan -->
                                <tr class="divide-x divide-slate-100">
                                    <td class="py-3 px-4 font-semibold text-slate-900 bg-slate-50/50">
                                        {{ approveForm.tipe_pekerjaan === 'wirausaha' ? 'Posisi Usaha / Bisnis' : 'Posisi / Jabatan' }}
                                    </td>
                                    <td class="py-3 px-4 font-medium">{{ alumni.posisi_jabatan || '- Belum diisi -' }}</td>
                                    <td class="py-3 px-4 font-medium text-slate-900">
                                        {{ linkedinSyncResult.preview?.current_job || '-' }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <input
                                            v-if="approveForm.tipe_pekerjaan === 'wirausaha'"
                                            type="text"
                                            v-model="approveForm.posisi_wiraswasta"
                                            :placeholder="approveForm.posisi_jabatan || 'contoh: Founder, Direktur'"
                                            class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]"
                                        />
                                        <input
                                            v-else
                                            type="text"
                                            v-model="approveForm.posisi_jabatan"
                                            placeholder="contoh: Software Engineer"
                                            class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]"
                                        />
                                    </td>
                                </tr>

                                <!-- 3. Perusahaan / Instansi -->
                                <tr class="divide-x divide-slate-100">
                                    <td class="py-3 px-4 font-semibold text-slate-900 bg-slate-50/50">
                                        {{ approveForm.tipe_pekerjaan === 'wirausaha' ? 'Nama Unit Usaha' : 'Nama Perusahaan / Instansi' }}
                                    </td>
                                    <td class="py-3 px-4 font-medium">{{ alumni.perusahaan?.nama_perusahaan || '- Belum diisi -' }}</td>
                                    <td class="py-3 px-4 font-medium text-slate-900">
                                        {{ linkedinSyncResult.preview?.current_company || '-' }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <input
                                            type="text"
                                            v-model="approveForm.nama_perusahaan"
                                            placeholder="contoh: PT Bank Mandiri"
                                            class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]"
                                        />
                                    </td>
                                </tr>

                                <!-- 4. Keahlian / Skills -->
                                <tr class="divide-x divide-slate-100">
                                    <td class="py-3 px-4 font-semibold text-slate-900 bg-slate-50/50">Keahlian / Skills</td>
                                    <td class="py-3 px-4 text-slate-600 line-clamp-2" :title="alumni.keahlian || alumni.skills || ''">
                                        {{ alumni.keahlian || alumni.skills || '- Belum diisi -' }}
                                    </td>
                                    <td class="py-3 px-4 text-slate-700">
                                        <span v-if="approveForm.skills" class="line-clamp-2" :title="approveForm.skills">{{ approveForm.skills }}</span>
                                        <span v-else class="text-slate-400 italic">-</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <input
                                            type="text"
                                            v-model="approveForm.skills"
                                            placeholder="contoh: PHP, Laravel, SQL"
                                            class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]"
                                        />
                                    </td>
                                </tr>

                                <!-- 5. Foto Profil LinkedIn -->
                                <tr v-if="linkedinSyncResult.preview?.profile_picture" class="divide-x divide-slate-100">
                                    <td class="py-3 px-4 font-semibold text-slate-900 bg-slate-50/50">Foto Profil</td>
                                    <td class="py-3 px-4">
                                        <div v-if="alumni.foto" class="flex items-center gap-2">
                                            <img :src="alumni.foto" class="w-8 h-8 rounded-lg object-cover border border-slate-200" />
                                            <span class="text-[11px] text-slate-500 font-mono">Tersimpan di profil</span>
                                        </div>
                                        <span v-else class="text-slate-400 italic">Belum ada foto</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <img :src="linkedinSyncResult.preview.profile_picture" class="w-8 h-8 rounded-lg object-cover border border-slate-200 shrink-0" />
                                            <span class="text-[11px] text-[#0077B5] font-mono truncate max-w-[200px]">Foto LinkedIn Terdeteksi</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" v-model="approveForm.sync_foto" class="w-4 h-4 text-[#0D542B] rounded border-slate-300 focus:ring-[#0D542B]" />
                                            <span class="text-xs text-slate-700 font-medium">Terapkan foto ini ke profil alumni</span>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Baris Aksi Persetujuan -->
                    <div v-if="linkedinSyncResult.status === 'pending' && !readonly" class="pt-2 flex flex-col sm:flex-row items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="handleRejectStaging"
                            :disabled="isRejectingSync || isApprovingSync"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition cursor-pointer disabled:opacity-50"
                        >
                            <span v-if="isRejectingSync">Menolak...</span>
                            <span v-else>Tolak (Reject)</span>
                        </button>
                        <button
                            type="button"
                            @click="handleApproveStagingWithForm"
                            :disabled="isApprovingSync || isRejectingSync"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#0A4322] text-white text-xs font-bold rounded-lg transition shadow-2xs cursor-pointer flex items-center gap-1.5 disabled:opacity-50"
                        >
                            <svg v-if="!isApprovingSync" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>{{ isApprovingSync ? 'Menerapkan Data...' : 'Setujui & Terapkan Data' }}</span>
                        </button>
                    </div>
                </div>

                <!-- SUB-TAB 2: RIWAYAT KARIER & PENDIDIKAN -->
                <div v-show="activeLinkedinTab === 'riwayat_karier'" class="p-4 sm:p-5 space-y-5">
                    <div v-if="linkedinSyncResult.preview?.headline || linkedinSyncResult.preview?.about" class="bg-slate-50/70 rounded-xl border border-slate-200 p-4 space-y-2 text-xs">
                        <div v-if="linkedinSyncResult.preview.headline">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block">Headline Profil</span>
                            <p class="font-semibold text-slate-800 mt-0.5">{{ linkedinSyncResult.preview.headline }}</p>
                        </div>
                        <div v-if="linkedinSyncResult.preview.about" class="pt-2 border-t border-slate-200/60">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block">Tentang (About)</span>
                            <p class="text-slate-600 mt-0.5 whitespace-pre-line leading-relaxed">{{ linkedinSyncResult.preview.about }}</p>
                        </div>
                    </div>

                    <!-- Tabel Riwayat Pengalaman Kerja -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                                Riwayat Pengalaman Kerja
                            </h4>
                            <span class="text-xs text-slate-500 font-mono">
                                Total: {{ (linkedinSyncResult.preview?.experience || []).length }} Pengalaman
                            </span>
                        </div>
                        <div class="overflow-x-auto border border-slate-200 rounded-xl">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 border-b border-slate-200 text-[11px] uppercase font-bold tracking-wider">
                                        <th class="py-2.5 px-3 text-center w-10">No</th>
                                        <th class="py-2.5 px-3">Posisi / Jabatan</th>
                                        <th class="py-2.5 px-3">Perusahaan / Instansi</th>
                                        <th class="py-2.5 px-3">Periode</th>
                                        <th class="py-2.5 px-3">Lokasi</th>
                                        <th class="py-2.5 px-3 text-center w-28">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <tr 
                                        v-for="(exp, idx) in linkedinSyncResult.preview?.experience || []" 
                                        :key="idx"
                                        class="hover:bg-slate-50/50"
                                    >
                                        <td class="py-3 px-3 text-center font-mono text-slate-400">{{ idx + 1 }}</td>
                                        <td class="py-3 px-3">
                                            <div class="font-bold text-slate-900">{{ exp.position || '-' }}</div>
                                            <span v-if="exp.is_current" class="inline-block mt-0.5 px-2 py-0.2 bg-emerald-100 text-emerald-800 text-[10px] font-bold rounded">
                                                Pekerjaan Saat Ini
                                            </span>
                                            <p v-if="exp.description" class="text-[11px] text-slate-500 mt-1 line-clamp-2" :title="exp.description">
                                                {{ exp.description }}
                                            </p>
                                        </td>
                                        <td class="py-3 px-3 font-semibold text-slate-800">{{ exp.company || '-' }}</td>
                                        <td class="py-3 px-3 font-mono text-[11px] text-slate-600">
                                            {{ exp.date || '-' }}
                                            <span v-if="exp.time_period" class="block text-slate-400 text-[10px]">{{ exp.time_period }}</span>
                                        </td>
                                        <td class="py-3 px-3 text-slate-600">{{ exp.location || '-' }}</td>
                                        <td class="py-3 px-3 text-center">
                                            <button
                                                v-if="linkedinSyncResult.status === 'pending'"
                                                type="button"
                                                @click="applyCareerToForm(exp)"
                                                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-semibold rounded transition cursor-pointer"
                                                title="Terapkan jabatan dan perusahaan ini ke usulan data"
                                            >
                                                Gunakan
                                            </button>
                                            <span v-else class="text-slate-400 text-[11px]">-</span>
                                        </td>
                                    </tr>
                                    <tr v-if="(linkedinSyncResult.preview?.experience || []).length === 0">
                                        <td colspan="6" class="py-6 text-center text-slate-400 text-xs italic">
                                            Tidak ada data riwayat pengalaman kerja tersendiri.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tabel Riwayat Pendidikan -->
                    <div class="space-y-2 pt-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                                Riwayat Pendidikan
                            </h4>
                            <span class="text-xs text-slate-500 font-mono">
                                Total: {{ (linkedinSyncResult.preview?.education || []).length }} Institusi
                            </span>
                        </div>
                        <div class="overflow-x-auto border border-slate-200 rounded-xl">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 border-b border-slate-200 text-[11px] uppercase font-bold tracking-wider">
                                        <th class="py-2.5 px-3 text-center w-10">No</th>
                                        <th class="py-2.5 px-3">Nama Institusi / Universitas</th>
                                        <th class="py-2.5 px-3">Gelar & Bidang Studi</th>
                                        <th class="py-2.5 px-3">Periode</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <tr 
                                        v-for="(edu, idx) in linkedinSyncResult.preview?.education || []" 
                                        :key="idx"
                                        class="hover:bg-slate-50/50"
                                    >
                                        <td class="py-3 px-3 text-center font-mono text-slate-400">{{ idx + 1 }}</td>
                                        <td class="py-3 px-3 font-bold text-slate-900">{{ edu.school || '-' }}</td>
                                        <td class="py-3 px-3 text-slate-700">
                                            {{ [edu.degree, edu.field_of_study].filter(Boolean).join(' - ') || '-' }}
                                        </td>
                                        <td class="py-3 px-3 font-mono text-[11px] text-slate-600">{{ edu.date || '-' }}</td>
                                    </tr>
                                    <tr v-if="(linkedinSyncResult.preview?.education || []).length === 0">
                                        <td colspan="4" class="py-6 text-center text-slate-400 text-xs italic">
                                            Tidak ada data riwayat pendidikan tersendiri.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- SUB-TAB 3: RIWAYAT SINKRONISASI (LOG) -->
                <div v-show="activeLinkedinTab === 'riwayat_sync'" class="p-4 sm:p-5 space-y-3">
                    <div class="flex items-center justify-between pb-1">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                            Log Seluruh Sinkronisasi LinkedIn Alumni Ini
                        </h4>
                        <span class="text-xs font-mono font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
                            {{ linkedinHistory.length }} Riwayat
                        </span>
                    </div>

                    <div class="overflow-x-auto border border-slate-200 rounded-xl">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-700 border-b border-slate-200 text-[11px] uppercase font-bold tracking-wider">
                                    <th class="py-2.5 px-3">Waktu Scrape</th>
                                    <th class="py-2.5 px-3">Target URL</th>
                                    <th class="py-2.5 px-3">Jabatan & Perusahaan Terdeteksi</th>
                                    <th class="py-2.5 px-3">Status</th>
                                    <th class="py-2.5 px-3">Petugas Peninjau</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="hist in linkedinHistory" :key="hist.id" class="hover:bg-slate-50/50">
                                    <td class="py-3 px-3 font-mono text-[11px] text-slate-800 whitespace-nowrap">
                                        {{ hist.scraped_at ? new Date(hist.scraped_at).toLocaleString('id-ID') : '-' }}
                                    </td>
                                    <td class="py-3 px-3 max-w-[200px] truncate">
                                        <a :href="hist.linkedin_url" target="_blank" class="text-[#0077B5] hover:underline font-mono text-[11px]">
                                            {{ hist.linkedin_url }}
                                        </a>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="font-semibold text-slate-900 block">{{ hist.preview?.current_job || '-' }}</span>
                                        <span class="text-slate-500 text-[11px]">{{ hist.preview?.current_company || '-' }}</span>
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <span 
                                            class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                            :class="hist.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : (hist.status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800')"
                                        >
                                            {{ hist.status }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-[11px] text-slate-600 whitespace-nowrap">
                                        {{ hist.reviewer?.name || (hist.status === 'approved' ? 'Super Admin' : '-') }}
                                        <span v-if="hist.reviewed_at" class="text-slate-400 block font-mono text-[10px]">
                                            {{ new Date(hist.reviewed_at).toLocaleDateString('id-ID') }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="linkedinHistory.length === 0">
                                    <td colspan="5" class="py-6 text-center text-slate-400 text-xs italic">
                                        Belum ada riwayat sinkronisasi tersimpan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SUB-TAB 4: PEMETAAN BASIS DATA (AUDIT TRACE) -->
                <div v-show="activeLinkedinTab === 'audit_trace'" class="p-4 sm:p-5 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-2 border-b border-slate-200">
                        <div>
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                                Pemetaan Kolom Basis Data
                            </h4>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Daftar atribut scraping LinkedIn yang dipetakan ke tabel basis data kampus
                            </p>
                        </div>

                        <div class="w-full sm:w-72">
                            <input 
                                type="text" 
                                v-model="searchTraceQuery" 
                                placeholder="Cari atribut atau kolom target..."
                                class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]"
                            />
                        </div>
                    </div>

                    <div class="overflow-x-auto border border-slate-200 rounded-xl">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-700 border-b border-slate-200 text-[10px] uppercase font-bold tracking-wider">
                                    <th class="py-2.5 px-3 text-center w-10">#</th>
                                    <th class="py-2.5 px-3 min-w-[140px]">Atribut Scraping</th>
                                    <th class="py-2.5 px-3 min-w-[160px]">Deskripsi Field</th>
                                    <th class="py-2.5 px-3 min-w-[200px]">Nilai Hasil LinkedIn</th>
                                    <th class="py-2.5 px-3 min-w-[180px] bg-slate-100/70 text-slate-800">Target Kolom Database</th>
                                    <th class="py-2.5 px-3 min-w-[180px]">Nilai di Database Kampus</th>
                                    <th class="py-2.5 px-3 text-center min-w-[110px]">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr 
                                    v-for="(row, idx) in filteredTraceMapping" 
                                    :key="idx" 
                                    class="hover:bg-slate-50/60 transition-colors"
                                    :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/20'"
                                >
                                    <td class="py-2.5 px-3 text-center font-mono text-slate-400 font-semibold text-[11px]">
                                        {{ idx + 1 }}
                                    </td>
                                    <td class="py-2.5 px-3 font-mono font-bold text-slate-900">
                                        <span class="bg-slate-100 px-2 py-0.5 rounded text-[11px] text-slate-800 border border-slate-200">
                                            {{ row.key }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 font-semibold text-slate-800">
                                        {{ row.label }}
                                        <p v-if="row.note" class="text-[10px] text-slate-400 font-normal mt-0.5">{{ row.note }}</p>
                                    </td>
                                    <td class="py-2.5 px-3 break-words max-w-xs">
                                        <div v-if="row.key === 'li_profile_image_url' && row.scraped_value && row.scraped_value.startsWith('http')" class="flex items-center gap-2">
                                            <img :src="row.scraped_value" class="w-8 h-8 rounded-lg object-cover border border-slate-200 shrink-0" />
                                            <span class="truncate text-[11px] text-blue-600 font-mono">{{ row.scraped_value }}</span>
                                        </div>
                                        <span v-else class="text-xs text-slate-900 font-medium">
                                            {{ row.scraped_value }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 bg-slate-50/50">
                                        <div class="flex items-center gap-1 flex-wrap font-mono text-[10px]">
                                            <span class="px-1.5 py-0.5 bg-[#0D542B] text-white font-bold rounded">
                                                {{ row.target_table }}
                                            </span>
                                            <span class="text-slate-400 font-bold">&rarr;</span>
                                            <span class="px-1.5 py-0.5 bg-slate-200 text-slate-800 font-bold rounded">
                                                {{ row.target_column }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3 font-semibold break-words max-w-xs">
                                        <div v-if="row.target_column === 'foto' && row.db_value && row.db_value !== '(Belum tersimpan di biodata)'" class="flex items-center gap-2">
                                            <img :src="row.db_value" class="w-8 h-8 rounded-lg object-cover border border-slate-200 shrink-0" />
                                            <code class="text-[10px] font-mono text-[#0D542B] truncate">{{ row.db_value }}</code>
                                        </div>
                                        <span v-else :class="row.db_value && !row.db_value.startsWith('(') ? 'text-slate-900 font-bold' : 'text-slate-400 italic font-normal'">
                                            {{ row.db_value }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span 
                                            class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider inline-block whitespace-nowrap"
                                            :class="{
                                                'bg-emerald-100 text-emerald-800': row.badge === 'emerald',
                                                'bg-sky-100 text-sky-800': row.badge === 'sky',
                                                'bg-amber-100 text-amber-800': row.badge === 'amber',
                                                'bg-rose-100 text-rose-800': row.badge === 'rose',
                                                'bg-slate-100 text-slate-700': row.badge === 'slate'
                                            }"
                                        >
                                            {{ row.status }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="filteredTraceMapping.length === 0">
                                    <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                                        Tidak ada atribut yang cocok dengan kata kunci pencarian.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- State Bersih jika Belum Pernah Ada Data LinkedIn -->
        <div v-else class="bg-white rounded-xl p-6 border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h4 class="font-bold text-slate-900 text-sm">Belum Ada Riwayat Scraping LinkedIn untuk Alumni Ini</h4>
                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600">Belum Disinkron</span>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed max-w-2xl">
                    Data profil LinkedIn belum pernah diambil dari engine Apify. 
                    <span v-if="alumni.linkedin_url">Tautan LinkedIn terdaftar: <a :href="alumni.linkedin_url" target="_blank" class="text-[#0077B5] font-semibold hover:underline">{{ alumni.linkedin_url }}</a></span>
                    <span v-else>Alumni belum memiliki URL profil LinkedIn terdaftar di biodata kampus.</span>
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button
                    v-if="alumni.linkedin_url"
                    type="button"
                    @click="handleDirectLinkedInSync"
                    :disabled="isDirectSyncing"
                    class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg transition flex items-center gap-2 shadow-xs cursor-pointer disabled:opacity-60"
                >
                    <svg v-if="!isDirectSyncing" class="w-3.5 h-3.5 fill-current shrink-0" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                    <span>{{ isDirectSyncing ? 'Mengambil Data...' : 'Ambil Data LinkedIn Sekarang' }}</span>
                </button>
                <Link
                    :href="'/superadmin/linkedin-sync?alumni_id=' + alumni.id"
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition cursor-pointer border border-slate-200"
                >
                    Buka Menu LinkedIn Sync
                </Link>
            </div>
        </div>
    </div>
</template>
