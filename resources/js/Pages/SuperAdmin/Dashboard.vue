<script setup>
/**
 * Halaman Dashboard Super Administrator
 * File: resources/js/Pages/SuperAdmin/Dashboard.vue
 *
 * Menyajikan ringkasan eksekutif dan operasional Tracer Study UKDW secara runtut
 * satu kolom (vertikal dari atas ke bawah) dengan palet warna formal institusi UKDW (#0D542B)
 * dan slate netral yang bersih dan bebas dari distraksi warna berlebih.
 */
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

// Badge status verifikasi perusahaan: palet netral bersih (tanpa warna menyala/bertabrakan)
const getCompanyStatusClass = (status) => {
    if (status === 'Terverifikasi') {
        return 'bg-slate-100 text-[#0D542B] border-slate-300';
    }

    if (status === 'Ditolak') {
        return 'bg-slate-100 text-slate-700 border-slate-300';
    }

    return 'bg-slate-100 text-slate-700 border-slate-300';
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

// Kartu ringkasan metrik statistik utama: monokromatis dan hijau institusi UKDW
const summaryCards = computed(() => [
    {
        label: 'Pengajuan perusahaan baru',
        value: props.dashboardSummary.pending_perusahaan || 0,
        detail: 'Menunggu verifikasi',
        href: '/superadmin/perusahaan',
        valueClass: 'text-slate-900',
    },
    {
        label: 'Master perusahaan',
        value: props.dashboardSummary.total_perusahaan || 0,
        detail: `${props.dashboardSummary.total_perusahaan_terverifikasi || 0} terverifikasi`,
        href: '/superadmin/perusahaan',
        valueClass: 'text-slate-900',
    },
    {
        label: 'Data atasan',
        value: props.dashboardSummary.total_atasan || 0,
        detail: 'Terhubung ke profil alumni',
        href: '/superadmin/atasan',
        valueClass: 'text-slate-900',
    },
    {
        label: 'Data alumni',
        value: props.dashboardSummary.total_alumni || 0,
        detail: `Lulusan ${props.selectedYear}`,
        href: '/superadmin/alumni',
        valueClass: 'text-slate-900',
    },
    {
        label: 'Program studi',
        value: props.dashboardSummary.total_prodi || 0,
        detail: 'Dalam cakupan universitas',
        href: '/superadmin/analitik-prodi',
        valueClass: 'text-slate-900',
    },
]);

// Menyediakan metrik kelengkapan dalam urutan yang mudah dipindai.
const completionRows = computed(() => [
    props.completionMetrics.profile,
    props.completionMetrics.university_questionnaire,
    props.completionMetrics.profile_and_university,
].filter(Boolean));

// Mengambil program studi dengan response rate terendah untuk prioritas tindak lanjut.
const lowestResponseProdis = computed(() => [...props.prodiSummaries]
    .sort((first, second) => Number(first.response_rate || 0) - Number(second.response_rate || 0))
    .slice(0, 5));

// Menentukan label status response rate tanpa menambah warna dekoratif
const getResponseRateStatusClass = (status) => {
    if (status === 'green') {
        return 'border-slate-300 bg-slate-100 text-[#0D542B]';
    }

    return 'border-slate-300 bg-slate-100 text-slate-700';
};
</script>

<template>
    <Head title="Dashboard Super Admin - Tracer Study UKDW" />

    <div class="min-h-screen min-w-0 bg-[#f8fafc] font-sans text-slate-800">
        <AdminSidebar role="superadmin" :user="user" active-menu="dashboard" />

        <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
            <!-- Header Halaman: Konsisten dengan Palet Institusi UKDW (#0D542B) -->
            <header class="border-b border-[#0A4322] bg-[#0D542B] px-4 py-6 text-white sm:px-6 lg:px-8">
                <div class="mx-auto w-full max-w-6xl">
                    <nav class="mb-2 flex items-center gap-2 text-xs font-medium text-emerald-100">
                        <span>Tracer Study</span>
                        <span class="text-emerald-300/60">/</span>
                        <span class="font-bold text-white">Pusat Kendali</span>
                    </nav>
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div>
                            <h1 class="text-xl font-extrabold tracking-tight text-white sm:text-2xl">
                                Dashboard Super Administrator
                            </h1>
                            <p class="mt-1 text-xs text-emerald-100/90 sm:text-sm">
                                Pusat pemantauan data kuesioner, master perusahaan, kontak atasan, dan pengajuan baru.
                            </p>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <label for="dashboard-year" class="text-xs font-semibold text-white">Tahun Lulusan:</label>
                            <select
                                id="dashboard-year"
                                v-model="selectedYear"
                                @change="applyYearFilter"
                                aria-label="Pilih tahun lulusan dashboard"
                                class="cursor-pointer rounded-lg border border-white/20 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 shadow-2xs outline-none focus:ring-2 focus:ring-white"
                            >
                                <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content: Alur Vertikal Satu Kolom (Dari Atas ke Bawah) -->
            <main class="w-full px-4 pb-12 pt-6 sm:px-6 lg:px-8">
                <div class="mx-auto w-full max-w-6xl space-y-6">

                    <!-- SEKSI 1: Kartu Metrik Ringkasan Utama (5 Kolom Bersih & Flat) -->
                    <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        <Link
                            v-for="card in summaryCards"
                            :key="card.label"
                            :href="card.href"
                            class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-2xs transition-all hover:border-[#0D542B] hover:shadow-xs"
                        >
                            <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ card.label }}</p>
                            <p :class="['mt-2 text-2xl sm:text-3xl font-extrabold tracking-tight', card.valueClass]">{{ card.value }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ card.detail }}</p>
                        </Link>
                    </section>

                    <!-- SEKSI 2: Kilas Indikator Respons & Kelengkapan Tracer Study -->
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xs">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Respons dan Kelengkapan Data Tracer</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Ringkasan capaian pengisian kohort lulusan tahun {{ selectedYear }}.</p>
                            </div>
                            <span :class="['inline-flex w-fit rounded-full border px-2.5 py-1 text-xs font-semibold', getResponseRateStatusClass(props.stats.response_rate_status?.code)]">
                                {{ props.stats.response_rate_status?.label || 'Belum ada status' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-5">
                            <!-- Response Rate Persentase -->
                            <div class="border-b md:border-b-0 md:border-r border-slate-100 pb-4 md:pb-0 md:pr-6 flex flex-col justify-center">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Response Rate Keseluruhan</span>
                                <div class="mt-2 text-4xl sm:text-5xl font-extrabold tracking-tight text-[#0D542B]">
                                    {{ props.stats.response_rate || 0 }}%
                                </div>
                                <p class="mt-1.5 text-xs text-slate-500">
                                    {{ props.stats.total_responden || 0 }} dari {{ props.stats.total_alumni || 0 }} alumni telah mengisi kuesioner.
                                </p>
                            </div>

                            <!-- Progress Bar Kelengkapan Data -->
                            <div class="md:col-span-2 space-y-3.5 flex flex-col justify-center">
                                <div v-for="metric in completionRows" :key="metric.label">
                                    <div class="mb-1 flex items-center justify-between text-xs">
                                        <span class="font-medium text-slate-700">{{ metric.label }}</span>
                                        <span class="font-bold text-slate-900">{{ metric.percentage || 0 }}%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            class="h-full rounded-full bg-[#0D542B] transition-all duration-300"
                                            :style="{ width: `${Math.min(Number(metric.percentage || 0), 100)}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- SEKSI 3: Prioritas Program Studi (Response Rate Terendah) -->
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xs">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Program Studi Perlu Perhatian</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Lima program studi dengan persentase response rate terendah pada tahun {{ selectedYear }}.</p>
                            </div>
                            <Link href="/superadmin/analitik-prodi" class="text-xs font-bold text-[#0D542B] hover:underline">
                                Buka Analitik Prodi &rarr;
                            </Link>
                        </div>
                        <div class="divide-y divide-slate-100 mt-2">
                            <div
                                v-for="prodi in lowestResponseProdis"
                                :key="prodi.id"
                                class="flex items-center justify-between gap-4 py-3"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-xs sm:text-sm font-semibold text-slate-900">{{ prodi.nama_prodi }}</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">{{ prodi.total_responden || 0 }} dari {{ prodi.total_alumni || 0 }} responden</p>
                                </div>
                                <span class="shrink-0 text-xs sm:text-sm font-bold text-slate-800">{{ prodi.response_rate || 0 }}%</span>
                            </div>
                            <div v-if="lowestResponseProdis.length === 0" class="py-6 text-center text-xs text-slate-400">
                                Belum ada ringkasan response rate per prodi.
                            </div>
                        </div>
                    </section>

                    <!-- SEKSI 4: Pengajuan Perusahaan Baru (Menunggu Verifikasi) -->
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xs">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Pengajuan Perusahaan Baru</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Daftar instansi/perusahaan yang baru diajukan dan membutuhkan verifikasi Superadmin.</p>
                            </div>
                            <Link href="/superadmin/perusahaan" class="text-xs font-bold text-[#0D542B] hover:underline">
                                Kelola Perusahaan &rarr;
                            </Link>
                        </div>
                        <div class="divide-y divide-slate-100 mt-2">
                            <div
                                v-for="request in companyRequests"
                                :key="request.id"
                                class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-3.5"
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="truncate text-xs sm:text-sm font-bold text-slate-900">{{ request.name }}</p>
                                        <span :class="['rounded-full border px-2 py-0.5 text-[10px] font-semibold', getCompanyStatusClass(request.status)]">
                                            {{ request.status }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-1">
                                        Diajukan oleh <strong>{{ request.applicant }}</strong> ({{ getApplicantLabel(request) }})
                                        <span v-if="request.applicant_prodi"> &bull; {{ request.applicant_prodi }}</span>
                                    </p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">{{ request.location }} &bull; {{ request.created_at }}</p>
                                </div>
                                <Link href="/superadmin/perusahaan" class="shrink-0 text-xs font-bold text-[#0D542B] hover:underline self-start sm:self-auto">
                                    Tinjau &rarr;
                                </Link>
                            </div>
                            <div v-if="companyRequests.length === 0" class="py-6 text-center text-xs text-slate-400">
                                Tidak ada pengajuan perusahaan baru yang menunggu verifikasi.
                            </div>
                        </div>
                    </section>

                    <!-- SEKSI 5: Sebaran Lokasi Master Perusahaan -->
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xs">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Sebaran Lokasi Perusahaan</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Sebaran geografis kantor pusat perusahaan yang terdata di sistem.</p>
                            </div>
                            <Link href="/superadmin/perusahaan" class="text-xs font-bold text-[#0D542B] hover:underline">
                                Buka Master &rarr;
                            </Link>
                        </div>
                        <div class="mt-4 space-y-3">
                            <div v-for="item in companyDistribution" :key="item.label">
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="truncate font-medium text-slate-700">{{ item.label }}</span>
                                    <span class="shrink-0 font-bold text-slate-900">{{ item.count }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full bg-[#0D542B]"
                                        :style="{ width: `${(item.count / maxDistributionCount) * 100}%` }"
                                    ></div>
                                </div>
                            </div>
                            <div v-if="companyDistribution.length === 0" class="py-6 text-center text-xs text-slate-400">
                                Belum ada data lokasi perusahaan.
                            </div>
                        </div>
                    </section>

                    <!-- SEKSI 6: Daftar Master Perusahaan Terbaru -->
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xs">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Daftar Master Perusahaan</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Perusahaan aktif dan jumlah alumni yang terhubung.</p>
                            </div>
                            <Link href="/superadmin/perusahaan" class="text-xs font-bold text-[#0D542B] hover:underline">
                                Kelola Master &rarr;
                            </Link>
                        </div>
                        <div class="overflow-x-auto mt-2">
                            <table class="w-full min-w-[500px] text-left text-xs">
                                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-4 py-2.5">Perusahaan</th>
                                        <th class="px-4 py-2.5">Lokasi</th>
                                        <th class="px-4 py-2.5 text-center">Alumni Terhubung</th>
                                        <th class="px-4 py-2.5 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="company in companyDirectory" :key="company.id" class="hover:bg-slate-50/70">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-slate-900">{{ company.name }}</p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">{{ company.sector }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ company.location }}</td>
                                        <td class="px-4 py-3 text-center font-bold text-[#0D542B]">{{ company.alumni_count }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <span :class="['inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold', getCompanyStatusClass(company.status)]">
                                                {{ company.status }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="companyDirectory.length === 0">
                                        <td colspan="4" class="px-4 py-6 text-center text-slate-400">Belum ada master perusahaan.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- SEKSI 7: Relasi Alumni dan Atasan Langsung -->
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xs">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Relasi Alumni &amp; Atasan Langsung</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Kontak penilai (atasan) yang dimasukkan oleh alumni di riwayat karier.</p>
                            </div>
                            <Link href="/superadmin/atasan" class="text-xs font-bold text-[#0D542B] hover:underline">
                                Buka Data Atasan &rarr;
                            </Link>
                        </div>
                        <div class="divide-y divide-slate-100 mt-2">
                            <div
                                v-for="supervisor in supervisorDirectory"
                                :key="supervisor.id"
                                class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-3"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-xs sm:text-sm font-semibold text-slate-900">{{ supervisor.name }}</p>
                                    <p class="text-xs text-slate-600 mt-0.5">{{ supervisor.company }} &bull; {{ supervisor.contact }}</p>
                                    <p class="text-[11px] font-medium text-[#0D542B] mt-0.5">
                                        Alumni: {{ supervisor.alumni_name }} ({{ supervisor.alumni_nim }})
                                    </p>
                                </div>
                                <Link :href="`/superadmin/alumni/${supervisor.id}`" class="shrink-0 text-xs font-bold text-[#0D542B] hover:underline self-start sm:self-auto">
                                    Detail Alumni &rarr;
                                </Link>
                            </div>
                            <div v-if="supervisorDirectory.length === 0" class="py-6 text-center text-xs text-slate-400">
                                Belum ada data atasan yang tercatat.
                            </div>
                        </div>
                    </section>

                    <!-- SEKSI 8: Data Alumni Terbaru -->
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xs">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Data Alumni Terbaru</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Pratinjau alumni pada kohort lulusan tahun {{ selectedYear }}.</p>
                            </div>
                            <Link href="/superadmin/alumni" class="text-xs font-bold text-[#0D542B] hover:underline">
                                Kelola Alumni &rarr;
                            </Link>
                        </div>
                        <div class="overflow-x-auto mt-2">
                            <table class="w-full min-w-[500px] text-left text-xs">
                                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-4 py-2.5">Alumni</th>
                                        <th class="px-4 py-2.5">Program Studi</th>
                                        <th class="px-4 py-2.5 text-center">Kelengkapan Profil</th>
                                        <th class="px-4 py-2.5 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="alumni in recentAlumni" :key="alumni.id" class="hover:bg-slate-50/70">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-slate-900">{{ alumni.nama }}</p>
                                            <p class="font-mono text-[11px] text-slate-400 mt-0.5">{{ alumni.nim }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ alumni.prodi }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span :class="['inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold', alumni.is_profile_complete ? 'border-slate-300 bg-slate-100 text-[#0D542B]' : 'border-slate-200 bg-slate-50 text-slate-500']">
                                                {{ alumni.is_profile_complete ? 'Lengkap' : 'Belum lengkap' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <Link :href="`/superadmin/alumni/${alumni.id}`" class="text-xs font-bold text-[#0D542B] hover:underline">
                                                Detail
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="recentAlumni.length === 0">
                                        <td colspan="4" class="px-4 py-6 text-center text-slate-400">Belum ada data alumni pada tahun ini.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- SEKSI 9: Log Aktivitas Audit Terbaru -->
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xs">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Aktivitas Sistem Terbaru</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Jejak audit perubahan data dan operasional pengguna.</p>
                            </div>
                            <Link href="/superadmin/logs" class="text-xs font-bold text-[#0D542B] hover:underline">
                                Buka Log Aktivitas &rarr;
                            </Link>
                        </div>
                        <div class="divide-y divide-slate-100 mt-2">
                            <div v-for="log in recentLogs" :key="log.id" class="py-3 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-900">{{ log.user_name }}</p>
                                    <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">{{ log.description || log.action }}</p>
                                </div>
                                <span class="shrink-0 text-[10px] text-slate-400">{{ log.created_at }}</span>
                            </div>
                            <div v-if="recentLogs.length === 0" class="py-6 text-center text-xs text-slate-400">
                                Belum ada aktivitas yang tercatat.
                            </div>
                        </div>
                    </section>

                </div>
            </main>

            <!-- Footer Resmi -->
            <footer class="py-5 text-center text-xs text-slate-400 border-t border-slate-200 bg-white">
                &copy; {{ new Date().getFullYear() }} Universitas Kristen Duta Wacana. Hak Cipta Dilindungi.
            </footer>
        </div>
    </div>
</template>
