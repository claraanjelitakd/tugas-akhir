<script setup>
// Halaman dashboard menyajikan ringkasan operasional dan tautan tindak lanjut superadmin.
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminSidebar from '@/components/AdminSidebar.vue';

// Kontrak data dashboard dari controller superadmin.
const props = defineProps({
    user: Object,
    availableYears: {
        type: Array,
        default: () => [],
    },
    selectedYear: {
        type: [String, Number],
        default: () => new Date().getFullYear(),
    },
    dashboardSummary: {
        type: Object,
        default: () => ({
            total_alumni: 0,
            total_perusahaan: 0,
            total_perusahaan_terverifikasi: 0,
            total_atasan: 0,
            total_prodi: 0,
            pending_perusahaan: 0,
        }),
    },
    // Metrik tracer study utama untuk konteks operasional Superadmin.
    stats: {
        type: Object,
        default: () => ({
            total_alumni: 0,
            total_responden: 0,
            response_rate: 0,
            response_rate_status: { label: 'Belum ada data' },
        }),
    },
    // Ringkasan kelengkapan profil dan kuesioner berdasarkan tahun terpilih.
    completionMetrics: {
        type: Object,
        default: () => ({
            population: 0,
            profile: { label: 'Profil Alumni', completed_count: 0, percentage: 0 },
            university_questionnaire: { label: 'Kuesioner Universitas', completed_count: 0, percentage: 0 },
            profile_and_university: { label: 'Profil dan Kuesioner', completed_count: 0, percentage: 0 },
        }),
    },
    // Ringkasan response rate per program studi untuk pemantauan cepat.
    prodiSummaries: {
        type: Array,
        default: () => [],
    },
    companyRequests: {
        type: Array,
        default: () => [],
    },
    companyDirectory: {
        type: Array,
        default: () => [],
    },
    supervisorDirectory: {
        type: Array,
        default: () => [],
    },
    companyDistribution: {
        type: Array,
        default: () => [],
    },
    recentAlumni: {
        type: Array,
        default: () => [],
    },
    recentLogs: {
        type: Array,
        default: () => [],
    },
});

// Menjaga pilihan tahun tetap sinkron dengan props setelah navigasi Inertia.
const selectedYear = ref(String(props.selectedYear));

// Menyegarkan dashboard berdasarkan kohort lulusan yang dipilih.
watch(() => props.selectedYear, (year) => {
    selectedYear.value = String(year);
});

// Mengirim filter tahun tanpa memindahkan posisi scroll pengguna.
const applyYearFilter = () => {
    router.get('/superadmin/dashboard', {
        tahun: selectedYear.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Menentukan nilai maksimum untuk proporsi panjang batang sebaran perusahaan.
const maxDistributionCount = computed(() => {
    return Math.max(...props.companyDistribution.map((item) => Number(item.count || 0)), 1);
});

// Menentukan warna badge status verifikasi perusahaan.
const getCompanyStatusClass = (status) => {
    if (status === 'Terverifikasi') {
        return 'bg-emerald-50 text-[#0D542B] border-emerald-200';
    }

    if (status === 'Ditolak') {
        return 'bg-red-50 text-red-700 border-red-200';
    }

    return 'bg-amber-50 text-amber-700 border-amber-200';
};

// Menentukan teks peran pengaju agar informasi permintaan tidak ambigu.
const getApplicantLabel = (request) => {
    if (request.applicant_role === 'alumni') {
        return 'Alumni';
    }

    if (request.applicant_role === 'admin_prodi') {
        return 'Admin Prodi';
    }

    return request.applicant_role || 'Sistem';
};

// Menyusun lima kartu ringkasan utama dengan palet netral dan aksen status yang terukur.
const summaryCards = computed(() => [
    {
        label: 'Pengajuan perusahaan baru',
        value: props.dashboardSummary.pending_perusahaan || 0,
        detail: 'Menunggu verifikasi',
        href: '/superadmin/perusahaan',
        tone: 'border-l-slate-400 border-slate-200 bg-white',
        valueClass: 'text-slate-900',
    },
    {
        label: 'Master perusahaan',
        value: props.dashboardSummary.total_perusahaan || 0,
        detail: `${props.dashboardSummary.total_perusahaan_terverifikasi || 0} terverifikasi`,
        href: '/superadmin/perusahaan',
        tone: 'border-l-[#0D542B] border-slate-200 bg-white',
        valueClass: 'text-[#0D542B]',
    },
    {
        label: 'Data atasan',
        value: props.dashboardSummary.total_atasan || 0,
        detail: 'Terhubung ke profil alumni',
        href: '/superadmin/atasan',
        tone: 'border-slate-200 bg-white',
        valueClass: 'text-slate-900',
    },
    {
        label: 'Data alumni',
        value: props.dashboardSummary.total_alumni || 0,
        detail: `Lulusan ${props.selectedYear}`,
        href: '/superadmin/alumni',
        tone: 'border-slate-200 bg-white',
        valueClass: 'text-slate-900',
    },
    {
        label: 'Program studi',
        value: props.dashboardSummary.total_prodi || 0,
        detail: 'Dalam cakupan universitas',
        href: '/superadmin/analitik-prodi',
        tone: 'border-slate-200 bg-white',
        valueClass: 'text-slate-900',
    },
]);

// Menyediakan metrik kelengkapan dalam urutan yang mudah dipindai.
const completionRows = computed(() => [
    props.completionMetrics.profile,
    props.completionMetrics.university_questionnaire,
    props.completionMetrics.profile_and_university,
].filter(Boolean));

// Mengambil lima program studi dengan response rate terendah untuk prioritas tindak lanjut.
const lowestResponseProdis = computed(() => [...props.prodiSummaries]
    .sort((first, second) => Number(first.response_rate || 0) - Number(second.response_rate || 0))
    .slice(0, 5));

// Menentukan label status response rate tanpa menambah warna dekoratif pada Dashboard.
const getResponseRateStatusClass = (status) => {
    if (status === 'green') {
        return 'border-emerald-200 bg-emerald-50 text-[#0D542B]';
    }

    if (status === 'yellow') {
        return 'border-slate-300 bg-slate-50 text-slate-700';
    }

    return 'border-slate-300 bg-slate-100 text-slate-700';
};
</script>

<template>
    <Head title="Dashboard Super Admin - Tracer Study UKDW" />

    <div class="min-h-screen min-w-0 bg-[#f8fafc] font-sans text-slate-800">
        <AdminSidebar role="superadmin" :user="user" active-menu="dashboard" />

        <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
            <header class="border-b border-[#0A4322] bg-[#0D542B] px-4 py-6 text-white sm:px-6 lg:px-8">
                <div class="mx-auto w-full max-w-[1440px]">
                    <nav class="mb-2.5 flex items-center gap-2 text-xs font-medium text-emerald-100">
                        <span>Tracer Study</span>
                        <span class="text-emerald-300/60">/</span>
                        <span class="rounded-md bg-white/10 px-2 py-0.5 font-bold text-white">Pusat Kendali</span>
                    </nav>
                    <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div>
                            <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">
                                Dashboard Super Administrator
                            </h1>
                            <p class="mt-1.5 max-w-2xl text-xs font-normal leading-relaxed text-emerald-100/90 sm:text-sm">
                                Pusat informasi operasional untuk memantau data perusahaan, atasan, alumni, dan pengajuan baru.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <label for="dashboard-year" class="text-xs font-bold text-white">Tahun lulusan</label>
                            <select
                                id="dashboard-year"
                                v-model="selectedYear"
                                @change="applyYearFilter"
                                aria-label="Pilih tahun lulusan dashboard"
                                class="cursor-pointer appearance-none rounded-lg bg-[#FDC700] px-3 py-2 text-xs font-extrabold text-[#0D542B] outline-none focus-visible:ring-2 focus-visible:ring-white"
                            >
                                <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </header>

            <main class="admin-page-main w-full px-4 pb-12 pt-6 sm:px-6 lg:px-8">
                <div class="mx-auto w-full max-w-[1440px] space-y-6">
                <!-- Ringkasan operasional utama yang menjadi pintu masuk ke data terkait. -->
                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Link
                        v-for="card in summaryCards"
                        :key="card.label"
                        :href="card.href"
                        :class="['group rounded-xl border border-l-4 p-5 shadow-sm transition-shadow hover:-translate-y-0.5 hover:shadow-md', card.tone]"
                    >
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                        <p :class="['mt-3 text-3xl font-extrabold tracking-tight', card.valueClass]">{{ card.value }}</p>
                        <div class="mt-2 flex items-center justify-between gap-2">
                            <p class="text-xs text-slate-500">{{ card.detail }}</p>
                            <span class="text-[11px] font-bold text-[#0D542B] opacity-0 transition-opacity group-hover:opacity-100">Buka</span>
                        </div>
                    </Link>
                </section>

                <!-- Kilas indikator menghubungkan statistik tracer dengan prioritas kerja Superadmin. -->
                <section class="grid grid-cols-1 gap-5 xl:grid-cols-[1.15fr_0.85fr]">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col justify-between gap-3 border-b border-slate-100 pb-5 sm:flex-row sm:items-start">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#0D542B]">Kilas indikator</p>
                                <h2 class="mt-1 text-lg font-extrabold tracking-tight text-slate-900">Respons dan kelengkapan data</h2>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Ringkasan kohort lulusan {{ selectedYear }} untuk menentukan tindak lanjut.</p>
                            </div>
                            <span :class="['inline-flex w-fit rounded-full border px-2.5 py-1 text-[11px] font-bold', getResponseRateStatusClass(props.stats.response_rate_status?.code)]">
                                {{ props.stats.response_rate_status?.label || 'Belum ada status' }}
                            </span>
                        </div>

                        <div class="grid gap-5 pt-5 sm:grid-cols-[0.8fr_1.2fr] sm:items-center">
                            <div>
                                <p class="text-xs font-semibold text-slate-500">Response rate</p>
                                <p class="mt-1 text-4xl font-extrabold tracking-tight text-[#0D542B]">{{ props.stats.response_rate || 0 }}%</p>
                                <p class="mt-1 text-xs text-slate-500">{{ props.stats.total_responden || 0 }} dari {{ props.stats.total_alumni || 0 }} alumni merespons</p>
                            </div>
                            <div class="space-y-4">
                                <div v-for="metric in completionRows" :key="metric.label">
                                    <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                                        <span class="font-semibold text-slate-700">{{ metric.label }}</span>
                                        <span class="font-bold text-slate-900">{{ metric.percentage || 0 }}%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-[#0D542B] transition-all" :style="{ width: `${Math.min(Number(metric.percentage || 0), 100)}%` }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#0D542B]">Prioritas tindak lanjut</p>
                                <h2 class="mt-1 text-lg font-extrabold tracking-tight text-slate-900">Response rate per prodi</h2>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Program studi dengan capaian terendah pada tahun terpilih.</p>
                            </div>
                            <Link href="/superadmin/analitik-prodi" class="shrink-0 text-xs font-bold text-[#0D542B] hover:underline">Lihat analitik</Link>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="prodi in lowestResponseProdis" :key="prodi.id" class="flex items-center justify-between gap-4 py-3 first:pt-4 last:pb-0">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-900">{{ prodi.nama_prodi }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">{{ prodi.total_responden || 0 }} dari {{ prodi.total_alumni || 0 }} responden</p>
                                </div>
                                <span class="shrink-0 text-sm font-extrabold text-slate-700">{{ prodi.response_rate || 0 }}%</span>
                            </div>
                            <div v-if="lowestResponseProdis.length === 0" class="py-8 text-center text-xs text-slate-400">
                                Belum ada ringkasan response rate per prodi.
                            </div>
                        </div>
                    </article>
                </section>

                <section class="grid grid-cols-1 gap-5 xl:grid-cols-[1.25fr_0.75fr]">
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:px-6">
                            <div>
                                <h2 class="text-base font-extrabold text-slate-900">Pengajuan perusahaan terbaru</h2>
                                <p class="mt-1 text-xs text-slate-500">Perlu ditinjau sebelum masuk ke master perusahaan.</p>
                            </div>
                            <Link href="/superadmin/perusahaan" class="text-xs font-bold text-[#0D542B] hover:underline">Lihat semua</Link>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="request in companyRequests" :key="request.id" class="px-5 py-4 sm:px-6">
                                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="truncate text-sm font-bold text-slate-900">{{ request.name }}</p>
                                            <span :class="['rounded-full border px-2 py-0.5 text-[10px] font-bold', getCompanyStatusClass(request.status)]">{{ request.status }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-600">
                                            Diajukan oleh <strong>{{ request.applicant }}</strong> · {{ getApplicantLabel(request) }}
                                            <span v-if="request.applicant_prodi"> · {{ request.applicant_prodi }}</span>
                                        </p>
                                        <p class="mt-1 text-[11px] text-slate-400">{{ request.location }} · {{ request.created_at }}</p>
                                    </div>
                                    <Link href="/superadmin/perusahaan" class="shrink-0 text-xs font-bold text-[#0D542B] hover:underline">Tinjau</Link>
                                </div>
                            </div>
                            <div v-if="companyRequests.length === 0" class="px-5 py-10 text-center text-xs text-slate-400 sm:px-6">
                                Tidak ada pengajuan perusahaan baru yang menunggu verifikasi.
                            </div>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-extrabold text-slate-900">Sebaran perusahaan</h2>
                                <p class="mt-1 text-xs text-slate-500">Jumlah master perusahaan berdasarkan provinsi kantor.</p>
                            </div>
                            <Link href="/superadmin/perusahaan" class="text-xs font-bold text-[#0D542B] hover:underline">Detail</Link>
                        </div>
                        <div class="mt-5 space-y-4">
                            <div v-for="item in companyDistribution" :key="item.label">
                                <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                                    <span class="truncate font-semibold text-slate-700">{{ item.label }}</span>
                                    <span class="shrink-0 font-bold text-slate-900">{{ item.count }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-[#0D542B]" :style="{ width: `${(item.count / maxDistributionCount) * 100}%` }"></div>
                                </div>
                            </div>
                            <div v-if="companyDistribution.length === 0" class="py-8 text-center text-xs text-slate-400">
                                Belum ada data lokasi perusahaan.
                            </div>
                        </div>
                    </article>
                </section>

                <section class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
                            <div>
                                <h2 class="text-base font-extrabold text-slate-900">Daftar perusahaan</h2>
                                <p class="mt-1 text-xs text-slate-500">Master terbaru dan jumlah alumni yang terhubung.</p>
                            </div>
                            <Link href="/superadmin/perusahaan" class="text-xs font-bold text-[#0D542B] hover:underline">Buka master</Link>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[620px] text-left text-xs">
                                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-5 py-3">Perusahaan</th>
                                        <th class="px-5 py-3">Lokasi</th>
                                        <th class="px-5 py-3 text-center">Alumni</th>
                                        <th class="px-5 py-3 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="company in companyDirectory" :key="company.id" class="hover:bg-slate-50">
                                        <td class="px-5 py-3">
                                            <p class="font-bold text-slate-900">{{ company.name }}</p>
                                            <p class="mt-0.5 text-[11px] text-slate-400">{{ company.sector }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600">{{ company.location }}</td>
                                        <td class="px-5 py-3 text-center font-bold text-[#0D542B]">{{ company.alumni_count }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <span :class="['inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold', getCompanyStatusClass(company.status)]">{{ company.status }}</span>
                                        </td>
                                    </tr>
                                    <tr v-if="companyDirectory.length === 0">
                                        <td colspan="4" class="px-5 py-8 text-center text-slate-400">Belum ada master perusahaan.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
                            <div>
                                <h2 class="text-base font-extrabold text-slate-900">Relasi alumni dan atasan</h2>
                                <p class="mt-1 text-xs text-slate-500">Satu baris mewakili satu alumni dan data atasan dari profilnya.</p>
                            </div>
                            <Link href="/superadmin/atasan" class="text-xs font-bold text-[#0D542B] hover:underline">Buka data</Link>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="supervisor in supervisorDirectory" :key="supervisor.id" class="flex items-center justify-between gap-4 px-5 py-4 sm:px-6">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-900">{{ supervisor.name }}</p>
                                    <p class="mt-1 truncate text-xs text-slate-600">{{ supervisor.company }}</p>
                                    <p class="mt-0.5 truncate text-[11px] text-slate-400">{{ supervisor.contact }}</p>
                                    <p class="mt-1 truncate text-[11px] font-semibold text-[#0D542B]">Alumni: {{ supervisor.alumni_name }} · {{ supervisor.alumni_nim }}</p>
                                </div>
                                <Link :href="`/superadmin/alumni/${supervisor.id}`" class="shrink-0 text-xs font-bold text-[#0D542B] hover:underline">Detail</Link>
                            </div>
                            <div v-if="supervisorDirectory.length === 0" class="px-5 py-10 text-center text-xs text-slate-400 sm:px-6">
                                Belum ada data atasan yang tercatat.
                            </div>
                        </div>
                    </article>
                </section>

                <section class="grid grid-cols-1 gap-5 xl:grid-cols-[1.1fr_0.9fr]">
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
                            <div>
                                <h2 class="text-base font-extrabold text-slate-900">Data alumni terbaru</h2>
                                <p class="mt-1 text-xs text-slate-500">Pratinjau alumni pada tahun lulusan {{ selectedYear }}.</p>
                            </div>
                            <Link href="/superadmin/alumni" class="text-xs font-bold text-[#0D542B] hover:underline">Buka data</Link>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[540px] text-left text-xs">
                                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-5 py-3">Alumni</th>
                                        <th class="px-5 py-3">Program Studi</th>
                                        <th class="px-5 py-3 text-center">Profil</th>
                                        <th class="px-5 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="alumni in recentAlumni" :key="alumni.id" class="hover:bg-slate-50">
                                        <td class="px-5 py-3">
                                            <p class="font-bold text-slate-900">{{ alumni.nama }}</p>
                                            <p class="mt-0.5 font-mono text-[11px] text-slate-400">{{ alumni.nim }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600">{{ alumni.prodi }}</td>
                                        <td class="px-5 py-3 text-center">
                                            <span :class="['inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold', alumni.is_profile_complete ? 'border-emerald-200 bg-emerald-50 text-[#0D542B]' : 'border-slate-200 bg-slate-50 text-slate-500']">
                                                {{ alumni.is_profile_complete ? 'Lengkap' : 'Belum lengkap' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <Link :href="`/superadmin/alumni/${alumni.id}`" class="text-xs font-bold text-[#0D542B] hover:underline">Detail</Link>
                                        </td>
                                    </tr>
                                    <tr v-if="recentAlumni.length === 0">
                                        <td colspan="4" class="px-5 py-8 text-center text-slate-400">Belum ada data alumni pada tahun ini.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
                            <div>
                                <h2 class="text-base font-extrabold text-slate-900">Aktivitas terbaru</h2>
                                <p class="mt-1 text-xs text-slate-500">Jejak perubahan data dan aktivitas pengguna.</p>
                            </div>
                            <Link href="/superadmin/logs" class="text-xs font-bold text-[#0D542B] hover:underline">Buka log</Link>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="log in recentLogs" :key="log.id" class="px-5 py-4 sm:px-6">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-bold text-slate-900">{{ log.user_name }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-600">{{ log.description || log.action }}</p>
                                    </div>
                                    <span class="shrink-0 text-[10px] text-slate-400">{{ log.created_at }}</span>
                                </div>
                            </div>
                            <div v-if="recentLogs.length === 0" class="px-5 py-10 text-center text-xs text-slate-400 sm:px-6">
                                Belum ada aktivitas yang tercatat.
                            </div>
                        </div>
                    </article>
                </section>
                </div>
            </main>

            <footer class="py-5 text-center text-xs text-slate-400">
                &copy; {{ new Date().getFullYear() }} Universitas Kristen Duta Wacana. Hak Cipta Dilindungi.
            </footer>
        </div>
    </div>
</template>
