<!--
  Halaman Publik: Formulir Evaluasi Pengguna Lulusan (Atasan)
  File: resources/js/Pages/Public/EvaluasiAtasanForm.vue

  Deskripsi:
  Menampilkan sapaan formal kepada atasan, ringkasan informasi alumni yang dikelompokkan
  secara ringkas (tanpa puluhan card terpisah), avatar profil alumni bergaya dropdown di header,
  serta 3 langkah pengisian evaluasi atasan.
-->
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

// Props dari EvaluasiAtasanController@show
const props = defineProps({
    token: String,
    evaluasi: Object,
    pertanyaanList: {
        type: Array,
        default: () => [],
    },
    existingResponses: {
        type: Object,
        default: () => ({}),
    },
    existingCatatan: {
        type: String,
        default: '',
    },
    alumniInfo: Object,
    initialCompany: Object,
    isSubmitted: Boolean,
});

// State dropdown profil alumni di pojok kanan header (identik dengan navbar alumni)
const isAlumniMenuOpen = ref(false);
const alumniMenuRef = ref(null);

const closeAlumniMenuOnClickOutside = (event) => {
    if (alumniMenuRef.value && !alumniMenuRef.value.contains(event.target)) {
        isAlumniMenuOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeAlumniMenuOnClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', closeAlumniMenuOnClickOutside);
});

// Inisial nama alumni jika belum memiliki foto
const alumniInitials = computed(() => {
    const rawName = String(props.alumniInfo?.nama || 'A').trim();
    const parts = rawName.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return rawName.slice(0, 2).toUpperCase();
});

// Definisi 3 langkah stepper evaluasi atasan
const activeStep = ref('instansi');

const steps = [
    { id: 'instansi', name: 'Profil Perusahaan', subtitle: 'Identitas & Pimpinan', step: '1' },
    { id: 'karakteristik', name: 'Karakteristik', subtitle: 'Skala & Sebaran Alumni', step: '2' },
    { id: 'penilaian', name: 'Penilaian Kinerja', subtitle: '13 Aspek Kompetensi', step: '3' },
];

// Pisahkan butir pertanyaan: Kesiapan Kerja & 12 Aspek Kinerja Likert
const likertPertanyaanList = computed(() => props.pertanyaanList.filter((q) => q.kode !== 'tingkat_kesiapan_kerja'));
const kesiapanPertanyaan = computed(() => props.pertanyaanList.find((q) => q.kode === 'tingkat_kesiapan_kerja'));

// Urutan opsi kesiapan kerja dari paling kiri (Tidak siap) ke paling kanan (Sangat siap)
const kesiapanOptions = ['Tidak siap', 'Kurang siap', 'Cukup Siap', 'Sangat siap'];

// Urutan opsi skala 12 aspek kompetensi dari paling kiri (Sangat Kurang) ke paling kanan (Sangat Tinggi)
const skalaNilaiOptions = ['Sangat Kurang', 'Kurang', 'Cukup', 'Tinggi', 'Sangat Tinggi'];

// Siapkan state default jawaban untuk setiap butir pertanyaan
const initialJawaban = {};
props.pertanyaanList.forEach((q) => {
    initialJawaban[q.kode] = props.existingResponses[q.kode] || '';
});

// Normalisasi nilai skala perusahaan dari profil alumni agar cocok dengan pilihan Langkah 2
const normalizeSkala = (val) => {
    if (!val) return '';
    if (val === 'Regional/Lokal') return 'Lokal';
    return val;
};

// State form utama Inertia
const form = useForm({
    // Langkah 1: Informasi Perusahaan & Nama Atasan
    nama_perusahaan: props.initialCompany?.nama || '',
    alamat_lengkap: props.initialCompany?.alamat || '',
    no_telp_fax: props.initialCompany?.no_telp_fax || '',
    homepage: props.initialCompany?.homepage || '',
    nama_atasan: props.initialCompany?.nama_atasan || '',

    // Langkah 2: Karakteristik Perusahaan & Sebaran Alumni UKDW
    bentuk_perusahaan: props.initialCompany?.bentuk || '',
    skala_perusahaan: normalizeSkala(props.initialCompany?.skala || ''),
    jumlah_pegawai: props.initialCompany?.jumlah_pegawai || '',
    jumlah_alumni_ukdw: props.evaluasi?.jumlah_alumni_ukdw || '',
    standar_gaji_pertama: props.evaluasi?.standar_gaji_pertama || '',

    // Langkah 3: Jawaban Kuesioner Evaluasi & Saran
    jawaban: initialJawaban,
    catatan_lainnya: props.existingCatatan || '',
});

// Helper cek nilai teks tidak kosong
const cleanText = (val) => {
    const str = String(val ?? '').trim();
    return str !== '' && str !== '-' ? str : '';
};

// Nama atasan untuk sapaan pembuka
const greetingSupervisorName = computed(() => {
    return cleanText(form.nama_atasan) || cleanText(props.initialCompany?.nama_atasan) || 'Pimpinan / Atasan Penilai';
});

// Ringkasan terstruktur menjadi maksimal 3 grup bersih (Jabatan, Instansi, Studi Lanjut) agar tidak bertumpuk banyak card
const careerSummary = computed(() => {
    const info = props.alumniInfo || {};
    const jabatan = cleanText(info.posisi_jabatan);
    const divisi = cleanText(info.bidang_pekerjaan);
    const kategori = cleanText(info.kategori_pekerjaan);

    const primaryLine = [jabatan, divisi].filter(Boolean).join(' — ') || kategori;
    const secondaryLine = jabatan || divisi ? (kategori ? `Status Aktivitas: ${kategori}` : '') : '';

    if (!primaryLine && !secondaryLine) return null;
    return { primaryLine, secondaryLine };
});

const companySummary = computed(() => {
    const info = props.alumniInfo || {};
    const nama = cleanText(form.nama_perusahaan) || cleanText(info.perusahaan);
    const jenis = cleanText(info.jenis_perusahaan);
    const bentuk = cleanText(form.bentuk_perusahaan) || cleanText(info.bentuk_perusahaan);
    const skala = cleanText(form.skala_perusahaan) || cleanText(info.skala_perusahaan);
    const alamat = cleanText(form.alamat_lengkap) || cleanText(info.alamat_perusahaan);
    const lokasi = cleanText(info.lokasi_perusahaan);
    const kodePos = cleanText(info.kode_pos_perusahaan);
    const cakupan = cleanText(info.jenis_lokasi);

    const metaParts = [jenis, bentuk, skala ? `Skala ${skala}` : '', cakupan].filter(Boolean);
    const locationParts = [alamat, lokasi !== alamat ? lokasi : '', kodePos].filter(Boolean);

    if (!nama && metaParts.length === 0 && locationParts.length === 0) return null;
    return {
        nama: nama || 'Instansi Tempat Bekerja',
        metaLine: metaParts.join(' • '),
        locationLine: locationParts.join(', '),
    };
});

const studySummary = computed(() => {
    const info = props.alumniInfo || {};
    const jenjang = cleanText(info.pendidikan_tingkat);
    const prodi = cleanText(info.pendidikan_prodi);
    const univ = cleanText(info.perguruan_tinggi);

    if (!jenjang && !prodi && !univ) return null;
    const primaryLine = [jenjang, prodi].filter(Boolean).join(' — ') || univ;
    const secondaryLine = (jenjang || prodi) && univ ? univ : '';

    return { primaryLine, secondaryLine };
});

// Fungsi toggle / batal pilih (cancel) untuk field Langkah 2
const toggleFieldOption = (fieldName, val) => {
    if (form[fieldName] === val) {
        form[fieldName] = '';
    } else {
        form[fieldName] = val;
    }
};

// Fungsi toggle / batal pilih (cancel) untuk jawaban Langkah 3
const toggleJawaban = (kode, nilai) => {
    if (form.jawaban[kode] === nilai) {
        form.jawaban[kode] = '';
    } else {
        form.jawaban[kode] = nilai;
    }
};

const clearJawaban = (kode) => {
    form.jawaban[kode] = '';
};

const submitSuccess = ref(props.isSubmitted || false);

// Evaluasi kelengkapan per langkah
const isStepCompleted = (stepId) => {
    if (stepId === 'instansi') {
        return !!(
            form.nama_perusahaan &&
            form.alamat_lengkap &&
            form.nama_atasan
        );
    }
    if (stepId === 'karakteristik') {
        return !!(
            form.bentuk_perusahaan &&
            form.skala_perusahaan &&
            form.jumlah_pegawai &&
            form.jumlah_alumni_ukdw &&
            form.standar_gaji_pertama
        );
    }
    if (stepId === 'penilaian') {
        const kesiapanOk = kesiapanPertanyaan.value ? !!form.jawaban[kesiapanPertanyaan.value.kode] : true;
        const likertOk = likertPertanyaanList.value.every((q) => !!form.jawaban[q.kode]);
        return kesiapanOk && likertOk;
    }
    return false;
};

// Navigasi langkah
const goToStep = (stepId) => {
    activeStep.value = stepId;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const nextStep = () => {
    if (activeStep.value === 'instansi') {
        if (!isStepCompleted('instansi')) {
            Swal.fire({
                icon: 'warning',
                title: 'Data Belum Lengkap',
                text: 'Mohon lengkapi Nama Perusahaan, Alamat Perusahaan, dan Nama Atasan sebelum melanjutkan.',
                confirmButtonColor: '#0D542B',
            });
            return;
        }
        goToStep('karakteristik');
    } else if (activeStep.value === 'karakteristik') {
        if (!isStepCompleted('karakteristik')) {
            Swal.fire({
                icon: 'warning',
                title: 'Karakteristik Belum Dipilih',
                text: 'Mohon lengkapi seluruh karakteristik instansi dan keberadaan alumni pada langkah 2.',
                confirmButtonColor: '#0D542B',
            });
            return;
        }
        goToStep('penilaian');
    }
};

const prevStep = () => {
    if (activeStep.value === 'penilaian') {
        goToStep('karakteristik');
    } else if (activeStep.value === 'karakteristik') {
        goToStep('instansi');
    }
};

const submitForm = () => {
    const missingKeys = props.pertanyaanList.filter((q) => !form.jawaban[q.kode]);
    if (missingKeys.length > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Penilaian Belum Lengkap',
            text: `Mohon lengkapi penilaian pada semua aspek (${missingKeys.length} pertanyaan/aspek belum dinilai).`,
            confirmButtonColor: '#0D542B',
        });
        return;
    }

    form.post(`/evaluasi-atasan/${props.token}`, {
        preserveScroll: true,
        onSuccess: () => {
            submitSuccess.value = true;
            Swal.fire({
                icon: 'success',
                title: 'Evaluasi Berhasil Disimpan',
                text: 'Terima kasih atas waktu dan evaluasi obyektif yang telah diberikan untuk peningkatan mutu lulusan Universitas Kristen Duta Wacana.',
                confirmButtonColor: '#0D542B',
            });
        },
    });
};
</script>

<template>
    <Head title="Form Evaluasi Kepuasan & Kinerja Lulusan - UKDW" />

    <!-- Kontainer Halaman Utama -->
    <div class="min-h-screen bg-slate-50 flex flex-col font-sans text-slate-900 antialiased selection:bg-emerald-100 selection:text-emerald-900">

        <!-- Header Resmi UKDW dengan Avatar Dropdown Alumni di Kanan (Seperti Navbar Alumni) -->
        <header class="bg-[#0D542B] text-white shadow-xs w-full">
            <div class="w-full px-4 sm:px-6 lg:px-10 py-3.5 sm:py-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="bg-white p-2 rounded-xl border border-emerald-100 shrink-0 shadow-2xs">
                            <img
                                src="/uploads/logo/logo-ukdw.png"
                                alt="Logo UKDW"
                                class="h-9 sm:h-10 w-auto object-contain"
                                onerror="this.style.display='none'"
                            />
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-emerald-200 block truncate">
                                Pusat Karir &amp; Alumni (PKA) UKDW
                            </span>
                            <h1 class="text-sm sm:text-lg font-extrabold tracking-tight text-white leading-tight mt-0.5 truncate">
                                Formulir Evaluasi Pengguna Lulusan (Atasan)
                            </h1>
                            <p class="text-[11px] text-emerald-100/90 mt-0.5 hidden sm:block">
                                Universitas Kristen Duta Wacana &bull; Yogyakarta
                            </p>
                        </div>
                    </div>

                    <!-- Pill Lingkaran Foto & Dropdown Informasi Alumni (Gaya Identik dengan Halaman Alumni) -->
                    <div ref="alumniMenuRef" class="relative shrink-0">
                        <button
                            type="button"
                            @click.stop="isAlumniMenuOpen = !isAlumniMenuOpen"
                            class="flex items-center gap-2 p-1 pr-2.5 rounded-full bg-white text-slate-800 border border-white/90 hover:bg-emerald-50 transition-all cursor-pointer shadow-xs"
                            title="Lihat Identitas Alumni"
                        >
                            <div class="w-9 h-9 rounded-full overflow-hidden bg-emerald-50 border border-emerald-200 flex items-center justify-center shrink-0">
                                <img
                                    v-if="alumniInfo.foto"
                                    :src="alumniInfo.foto"
                                    :alt="alumniInfo.nama"
                                    class="w-full h-full object-cover object-top"
                                />
                                <span v-else class="text-xs font-extrabold text-[#0D542B] tracking-tight">
                                    {{ alumniInitials }}
                                </span>
                            </div>
                            <svg
                                class="w-4 h-4 text-slate-600 transition-transform duration-200"
                                :class="{ 'rotate-180 text-[#0D542B]': isAlumniMenuOpen }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Dropdown Detail Identitas Alumni -->
                        <div
                            v-if="isAlumniMenuOpen"
                            class="absolute right-0 mt-2 w-64 bg-white rounded-2xl shadow-lg border border-slate-200 py-3 z-50 text-slate-800"
                        >
                            <div class="px-4 pb-2.5 border-b border-slate-100">
                                <p class="text-sm font-bold text-slate-900 truncate">{{ alumniInfo.nama }}</p>
                                <p class="text-xs font-mono text-slate-500 mt-0.5">NIM: {{ alumniInfo.nim }}</p>
                            </div>
                            <div class="px-4 pt-2.5 space-y-1 text-xs">
                                <div class="flex justify-between gap-2">
                                    <span class="text-slate-400">Program Studi</span>
                                    <span class="font-semibold text-slate-800 text-right">{{ alumniInfo.prodi }}</span>
                                </div>
                                <div v-if="alumniInfo.fakultas && alumniInfo.fakultas !== '-'" class="flex justify-between gap-2">
                                    <span class="text-slate-400">Fakultas</span>
                                    <span class="font-medium text-slate-700 text-right">{{ alumniInfo.fakultas }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Konten Utama Formulir -->
        <main class="flex-1 w-full px-4 sm:px-6 lg:px-10 py-6 sm:py-8 space-y-6">

            <!-- BANNER SAPAAN FORMAL & RINGKASAN PROFIL ALUMNI (TERPADU, TANPA BANYAK CARD) -->
            <div class="w-full bg-white rounded-xl border border-slate-200 shadow-2xs p-5 sm:p-6 space-y-4">
                <!-- Sapaan Ramah & Formal kepada Atasan + Identitas Ringkas Alumni -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                    <div class="space-y-1">
                        <p class="text-sm sm:text-base font-bold text-slate-900">
                            Yth. <span class="text-[#0D542B]">{{ greetingSupervisorName }}</span>,
                        </p>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-4xl">
                            Terima kasih atas kesediaan Anda meluangkan waktu untuk memberikan evaluasi terhadap lulusan kami,
                            <strong class="text-slate-900 font-semibold">{{ alumniInfo.nama }}</strong>
                            (Program Studi <strong class="text-slate-800 font-semibold">{{ alumniInfo.prodi }}</strong><span v-if="alumniInfo.fakultas && alumniInfo.fakultas !== '-'">, {{ alumniInfo.fakultas }}</span> &bull; NIM <span class="font-mono font-semibold text-slate-800">{{ alumniInfo.nim }}</span>).
                        </p>
                    </div>
                </div>

                <!-- Ringkasan Terpadu (Maksimal 3 Kolom Bersih: Peran Kerja, Instansi, & Studi Lanjut jika ada) -->
                <div
                    v-if="careerSummary || companySummary || studySummary"
                    class="grid grid-cols-1 gap-4"
                    :class="[
                        careerSummary && companySummary && studySummary ? 'md:grid-cols-3' :
                        (careerSummary && companySummary) || (careerSummary && studySummary) || (companySummary && studySummary) ? 'md:grid-cols-2' :
                        'md:grid-cols-1'
                    ]"
                >
                    <!-- 1. Ringkasan Jabatan & Divisi -->
                    <div v-if="careerSummary" class="bg-slate-50/70 rounded-lg px-4 py-3 border border-slate-200/80">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
                            Jabatan &amp; Divisi Alumni
                        </span>
                        <p class="text-xs sm:text-sm font-bold text-slate-900 mt-0.5">
                            {{ careerSummary.primaryLine }}
                        </p>
                        <p v-if="careerSummary.secondaryLine" class="text-[11px] text-slate-500 mt-0.5">
                            {{ careerSummary.secondaryLine }}
                        </p>
                    </div>

                    <!-- 2. Ringkasan Instansi & Lokasi Kerja -->
                    <div v-if="companySummary" class="bg-slate-50/70 rounded-lg px-4 py-3 border border-slate-200/80">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
                            Instansi &amp; Lokasi Penempatan
                        </span>
                        <p class="text-xs sm:text-sm font-bold text-slate-900 mt-0.5">
                            {{ companySummary.nama }}
                        </p>
                        <p v-if="companySummary.metaLine" class="text-[11px] text-slate-600 font-medium mt-0.5">
                            {{ companySummary.metaLine }}
                        </p>
                        <p v-if="companySummary.locationLine" class="text-[11px] text-slate-500 mt-0.5">
                            {{ companySummary.locationLine }}
                        </p>
                    </div>

                    <!-- 3. Ringkasan Studi Lanjut (Hanya muncul apabila alumni mengisi studi lanjut) -->
                    <div v-if="studySummary" class="bg-slate-50/70 rounded-lg px-4 py-3 border border-slate-200/80">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
                            Pendidikan / Studi Lanjut Saat Ini
                        </span>
                        <p class="text-xs sm:text-sm font-bold text-slate-900 mt-0.5">
                            {{ studySummary.primaryLine }}
                        </p>
                        <p v-if="studySummary.secondaryLine" class="text-[11px] text-slate-500 mt-0.5">
                            {{ studySummary.secondaryLine }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- TAMPILAN JIKA FORMULIR SUDAH SELESAI / SUBMITTED -->
            <div v-if="submitSuccess && isSubmitted" class="w-full bg-white rounded-xl border border-slate-200 p-8 sm:p-12 text-center space-y-4 shadow-2xs">
                <div class="w-12 h-12 bg-emerald-50 text-[#0D542B] rounded-full flex items-center justify-center mx-auto text-xl font-bold border border-emerald-200">
                    &check;
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-slate-900">Evaluasi Berhasil Diterima</h3>
                <p class="text-slate-600 text-xs sm:text-sm max-w-2xl mx-auto leading-relaxed">
                    Terima kasih yang sebesar-besarnya kepada <strong>{{ greetingSupervisorName }}</strong> di <strong>{{ form.nama_perusahaan }}</strong> atas waktu, evaluasi obyektif, dan masukan konstruktif yang telah diberikan terhadap alumni kami, <strong>{{ alumniInfo.nama }}</strong>. Data respon Anda telah tersimpan secara resmi di sistem Tracer Study UKDW.
                </p>
                <div class="pt-2">
                    <a href="https://www.ukdw.ac.id" target="_blank" class="inline-flex items-center px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-lg transition-colors border border-slate-300">
                        Kunjungi Situs Resmi UKDW &rarr;
                    </a>
                </div>
            </div>

            <!-- FORMULIR INPUT DENGAN 3 STEPPER -->
            <form v-else @submit.prevent="submitForm" class="space-y-6">

                <!-- STEPPER NAVIGASI 3 LANGKAH -->
                <div class="w-full bg-white rounded-xl border border-slate-200 overflow-hidden shadow-2xs">
                    <div class="overflow-x-auto py-3.5 sm:py-4 px-4 sm:px-8 border-b border-slate-200 bg-slate-50/50">
                        <div class="flex items-center justify-between w-full min-w-[500px] px-2 sm:px-6 md:px-12">
                            <template v-for="(tab, index) in steps" :key="tab.id">
                                <!-- Lingkaran & Label Langkah -->
                                <div
                                    class="flex flex-col items-center justify-center cursor-pointer group px-2 py-1 transition-all"
                                    @click="goToStep(tab.id)"
                                    :title="'Langkah ' + tab.step + ': ' + tab.name"
                                >
                                    <div
                                        class="flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-full font-bold text-xs sm:text-sm transition-all duration-200 z-10 shadow-xs relative"
                                        :class="[
                                            activeStep === tab.id
                                                ? 'bg-[#0D542B] text-white ring-4 ring-[#0D542B]/20 shadow-md scale-105' :
                                            (isStepCompleted(tab.id)
                                                ? 'bg-[#0D542B] text-white shadow-xs group-hover:bg-[#08381c]' :
                                                'bg-white text-slate-500 border border-slate-300 group-hover:bg-slate-100 group-hover:text-slate-700')
                                        ]"
                                    >
                                        <svg
                                            v-if="isStepCompleted(tab.id) && activeStep !== tab.id"
                                            class="w-4 h-4 text-white"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span v-else>{{ tab.step }}</span>
                                    </div>
                                    <span
                                        class="text-[11px] sm:text-xs mt-1.5 text-center leading-tight transition-colors"
                                        :class="activeStep === tab.id ? 'text-[#0D542B] font-extrabold' : (isStepCompleted(tab.id) ? 'text-slate-800 font-semibold' : 'text-slate-400 font-medium')"
                                    >
                                        {{ tab.name }}
                                    </span>
                                    <span
                                        v-if="isStepCompleted(tab.id)"
                                        class="text-[9px] sm:text-[10px] text-emerald-800 font-bold mt-0.5"
                                    >
                                        Sudah Lengkap
                                    </span>
                                    <span
                                        v-else
                                        class="text-[9px] sm:text-[10px] text-slate-400 font-normal mt-0.5"
                                    >
                                        {{ tab.subtitle }}
                                    </span>
                                </div>

                                <!-- Garis Penghubung -->
                                <div
                                    v-if="index < steps.length - 1"
                                    class="flex-1 h-1 sm:h-1.5 rounded-full transition-colors duration-300 mx-3 sm:mx-6 min-w-[30px]"
                                    :class="isStepCompleted(tab.id) ? 'bg-[#0D542B]' : 'bg-slate-200'"
                                ></div>
                            </template>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- LANGKAH 1: PROFIL PERUSAHAAN & NAMA ATASAN                -->
                    <!-- ========================================================= -->
                    <div v-show="activeStep === 'instansi'" class="p-5 sm:p-7 space-y-5">
                        <div class="border-b border-slate-200 pb-3">
                            <h3 class="text-sm sm:text-base font-bold text-slate-900">
                                Langkah 1: Informasi Perusahaan &amp; Atasan Langsung
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Data di bawah ini telah diisi oleh alumni. Anda cukup memeriksa atau memperbaruinya jika terdapat perubahan.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                            <!-- Nama Perusahaan -->
                            <div class="sm:col-span-2 lg:col-span-1">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                    Nama Perusahaan / Lembaga / Institusi <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.nama_perusahaan"
                                    required
                                    class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-xs sm:text-sm font-medium focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] transition-colors bg-white outline-none"
                                    placeholder="Nama resmi instansi tempat alumni bekerja"
                                />
                            </div>

                            <!-- Nama Atasan Langsung -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                    Nama Atasan Langsung <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.nama_atasan"
                                    required
                                    class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-xs sm:text-sm font-medium focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] transition-colors bg-white outline-none"
                                    placeholder="Nama lengkap atasan langsung alumni"
                                />
                            </div>

                            <!-- No Telp / Fax -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">No. Telp / Fax Perusahaan</label>
                                <input
                                    type="text"
                                    v-model="form.no_telp_fax"
                                    class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-xs sm:text-sm font-medium focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] transition-colors bg-white outline-none"
                                    placeholder="Contoh: 0274-563929"
                                />
                            </div>

                            <!-- Alamat Lengkap -->
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                    Alamat Lengkap Perusahaan <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.alamat_lengkap"
                                    required
                                    class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-xs sm:text-sm font-medium focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] transition-colors bg-white outline-none"
                                    placeholder="Jalan, Gedung, Kota/Kabupaten, Kode Pos..."
                                />
                            </div>

                            <!-- Homepage -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Website / Homepage Perusahaan</label>
                                <input
                                    type="text"
                                    v-model="form.homepage"
                                    class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-xs sm:text-sm font-medium focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] transition-colors bg-white outline-none"
                                    placeholder="Contoh: https://perusahaan.com"
                                />
                            </div>
                        </div>

                        <!-- Tombol Navigasi Langkah 1 -->
                        <div class="pt-4 border-t border-slate-200 flex items-center justify-end">
                            <button
                                type="button"
                                @click="nextStep"
                                class="px-5 py-2.5 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs sm:text-sm font-bold rounded-lg transition-colors cursor-pointer inline-flex items-center gap-2 shadow-2xs"
                            >
                                <span>Lanjut ke Karakteristik Instansi</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- LANGKAH 2: KARAKTERISTIK INSTANSI & LULUSAN               -->
                    <!-- ========================================================= -->
                    <div v-show="activeStep === 'karakteristik'" class="p-5 sm:p-7 space-y-6">
                        <div class="border-b border-slate-200 pb-3">
                            <h3 class="text-sm sm:text-base font-bold text-slate-900">
                                Langkah 2: Karakteristik Perusahaan &amp; Sebaran Alumni UKDW
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Gambaran umum skala organisasi dan jumlah alumni UKDW yang berkarier di perusahaan ini (klik ulang atau klik 2x untuk membatalkan pilihan).
                            </p>
                        </div>

                        <!-- 1. Bentuk Perusahaan -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-800">
                                1. Bentuk Perusahaan / Institusi <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2.5">
                                <div
                                    v-for="opt in ['BUMN', 'Perusahaan Terbatas', 'Koperasi', 'CV', 'Firma']"
                                    :key="opt"
                                    @click="toggleFieldOption('bentuk_perusahaan', opt)"
                                    @dblclick.stop="form.bentuk_perusahaan = ''"
                                    class="flex items-center gap-2.5 p-3 border rounded-lg cursor-pointer text-xs font-semibold transition-colors select-none"
                                    :class="form.bentuk_perusahaan === opt ? 'border-[#0D542B] bg-emerald-50 text-[#0D542B] ring-1 ring-[#0D542B] font-bold' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                                >
                                    <input
                                        type="radio"
                                        :checked="form.bentuk_perusahaan === opt"
                                        :value="opt"
                                        readonly
                                        class="accent-[#0D542B] w-4 h-4 cursor-pointer shrink-0 pointer-events-none"
                                    />
                                    <span class="truncate">{{ opt }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Skala Perusahaan -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-800">
                                2. Skala Perusahaan / Organisasi <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                <div
                                    v-for="opt in ['Lokal', 'Nasional', 'Internasional']"
                                    :key="opt"
                                    @click="toggleFieldOption('skala_perusahaan', opt)"
                                    @dblclick.stop="form.skala_perusahaan = ''"
                                    class="flex items-center gap-2.5 p-3 border rounded-lg cursor-pointer text-xs font-semibold transition-colors select-none"
                                    :class="form.skala_perusahaan === opt ? 'border-[#0D542B] bg-emerald-50 text-[#0D542B] ring-1 ring-[#0D542B] font-bold' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                                >
                                    <input
                                        type="radio"
                                        :checked="form.skala_perusahaan === opt"
                                        :value="opt"
                                        readonly
                                        class="accent-[#0D542B] w-4 h-4 cursor-pointer shrink-0 pointer-events-none"
                                    />
                                    <span>{{ opt }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Jumlah Pegawai Keseluruhan -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-800">
                                3. Jumlah Pegawai Keseluruhan di Perusahaan ini <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
                                <div
                                    v-for="opt in ['< 50 Orang', '51 - 100 Orang', '101 – 150 Orang', '151 – 300 Orang', '301 – 500 Orang', '> 500 Orang']"
                                    :key="opt"
                                    @click="toggleFieldOption('jumlah_pegawai', opt)"
                                    @dblclick.stop="form.jumlah_pegawai = ''"
                                    class="flex items-center gap-2.5 p-3 border rounded-lg cursor-pointer text-xs font-semibold transition-colors select-none"
                                    :class="form.jumlah_pegawai === opt ? 'border-[#0D542B] bg-emerald-50 text-[#0D542B] ring-1 ring-[#0D542B] font-bold' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                                >
                                    <input
                                        type="radio"
                                        :checked="form.jumlah_pegawai === opt"
                                        :value="opt"
                                        readonly
                                        class="accent-[#0D542B] w-4 h-4 cursor-pointer shrink-0 pointer-events-none"
                                    />
                                    <span class="truncate">{{ opt }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Jumlah Alumni UKDW yang Bekerja -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-800">
                                4. Jumlah Alumni UKDW yang Bekerja di Perusahaan ini <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <div
                                    v-for="opt in ['< 5 Orang', '6 - 10 Orang', '11 – 20 Orang', '> 21 Orang']"
                                    :key="opt"
                                    @click="toggleFieldOption('jumlah_alumni_ukdw', opt)"
                                    @dblclick.stop="form.jumlah_alumni_ukdw = ''"
                                    class="flex items-center gap-2.5 p-3 border rounded-lg cursor-pointer text-xs font-semibold transition-colors select-none"
                                    :class="form.jumlah_alumni_ukdw === opt ? 'border-[#0D542B] bg-emerald-50 text-[#0D542B] ring-1 ring-[#0D542B] font-bold' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                                >
                                    <input
                                        type="radio"
                                        :checked="form.jumlah_alumni_ukdw === opt"
                                        :value="opt"
                                        readonly
                                        class="accent-[#0D542B] w-4 h-4 cursor-pointer shrink-0 pointer-events-none"
                                    />
                                    <span>{{ opt }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 5. Standar Gaji Pertama -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-800">
                                5. Standar Gaji Pertama (per bulan) yang Diberikan ke Alumni UKDW <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
                                <div
                                    v-for="opt in ['< 1.000.000', '1.000.000 – 1.500.000', '1.500.000 – 2.000.000', '2.000.000 – 3.000.000', '3.000.000 – 4.000.000', '> 5.000.000']"
                                    :key="opt"
                                    @click="toggleFieldOption('standar_gaji_pertama', opt)"
                                    @dblclick.stop="form.standar_gaji_pertama = ''"
                                    class="flex items-center gap-2.5 p-3 border rounded-lg cursor-pointer text-xs font-semibold transition-colors select-none"
                                    :class="form.standar_gaji_pertama === opt ? 'border-[#0D542B] bg-emerald-50 text-[#0D542B] ring-1 ring-[#0D542B] font-bold' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                                >
                                    <input
                                        type="radio"
                                        :checked="form.standar_gaji_pertama === opt"
                                        :value="opt"
                                        readonly
                                        class="accent-[#0D542B] w-4 h-4 cursor-pointer shrink-0 pointer-events-none"
                                    />
                                    <span class="truncate">{{ opt }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Navigasi Langkah 2 -->
                        <div class="pt-4 border-t border-slate-200 flex items-center justify-between">
                            <button
                                type="button"
                                @click="prevStep"
                                class="px-4 py-2.5 border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs sm:text-sm font-semibold rounded-lg transition-colors cursor-pointer inline-flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                <span>Kembali ke Profil</span>
                            </button>
                            <button
                                type="button"
                                @click="nextStep"
                                class="px-5 py-2.5 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs sm:text-sm font-bold rounded-lg transition-colors cursor-pointer inline-flex items-center gap-2 shadow-2xs"
                            >
                                <span>Lanjut ke Penilaian Kinerja</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- LANGKAH 3: PENILAIAN KINERJA LULUSAN                      -->
                    <!-- ========================================================= -->
                    <div v-show="activeStep === 'penilaian'" class="p-5 sm:p-7 space-y-6">
                        <div class="border-b border-slate-200 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm sm:text-base font-bold text-slate-900">
                                    Langkah 3: Penilaian Kesiapan &amp; Kinerja Lulusan
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Penilaian obyektif terhadap performa kerja alumni atas nama <strong class="text-slate-900">{{ alumniInfo.nama }}</strong> (klik ulang atau klik 2x pada pilihan yang aktif untuk membatalkan jawaban).
                                </p>
                            </div>
                            <span class="text-xs text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded border border-emerald-200 font-semibold self-start sm:self-auto">
                                13 Butir Penilaian
                            </span>
                        </div>

                        <!-- 1. Kesiapan Kerja (Urutan paling kiri Tidak siap, paling kanan Sangat siap) -->
                        <div v-if="kesiapanPertanyaan" class="p-4 sm:p-5 rounded-lg bg-slate-50 border border-slate-200 space-y-3">
                            <label class="block text-xs font-bold text-slate-900 leading-snug">
                                1. Tingkat kesiapan alumni UKDW dalam bekerja di Perusahaan ini <span class="text-rose-500">*</span>
                                <span class="block text-[11px] font-normal text-slate-500 mt-0.5">
                                    {{ kesiapanPertanyaan.deskripsi || 'Tingkat kesiapan alumni UKDW dalam bekerja dan beradaptasi di Perusahaan / Institusi ini.' }}
                                </span>
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <div
                                    v-for="opt in kesiapanOptions"
                                    :key="opt"
                                    @click="toggleJawaban(kesiapanPertanyaan.kode, opt)"
                                    @dblclick.stop="clearJawaban(kesiapanPertanyaan.kode)"
                                    class="flex items-center gap-2.5 p-3.5 border rounded-lg cursor-pointer text-xs font-semibold transition-colors select-none bg-white"
                                    :class="form.jawaban[kesiapanPertanyaan.kode] === opt ? 'border-[#0D542B] bg-emerald-50 text-[#0D542B] ring-1 ring-[#0D542B] font-bold' : 'border-slate-200 text-slate-700 hover:bg-slate-100'"
                                >
                                    <input
                                        type="radio"
                                        :name="`jawaban_${kesiapanPertanyaan.kode}`"
                                        :checked="form.jawaban[kesiapanPertanyaan.kode] === opt"
                                        :value="opt"
                                        readonly
                                        class="accent-[#0D542B] w-4 h-4 cursor-pointer shrink-0 pointer-events-none"
                                    />
                                    <span>{{ opt }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Matriks 12 Aspek Kinerja Likert (Urutan paling kiri Sangat Kurang, paling kanan Sangat Tinggi) -->
                        <div class="space-y-3">
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                    2. Aspek Kinerja Lulusan (Alumni) UKDW <span class="text-rose-500">*</span>
                                </h4>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Pilih satu tingkat penilaian pada setiap aspek kompetensi di bawah ini (klik kembali atau klik 2x untuk membatalkan pilihan):
                                </p>
                            </div>

                            <!-- Tampilan Mobile (< 768px): Card Sentuh -->
                            <div class="block md:hidden space-y-3">
                                <div
                                    v-for="(pertanyaan, idx) in likertPertanyaanList"
                                    :key="`m_${pertanyaan.id}`"
                                    class="p-4 rounded-xl border transition-colors bg-white shadow-2xs space-y-3"
                                    :class="form.jawaban[pertanyaan.kode] ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-200'"
                                >
                                    <div class="flex items-start gap-2.5">
                                        <span class="w-6 h-6 rounded-md bg-[#0D542B] text-white text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">
                                            {{ idx + 1 }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <h5 class="text-xs font-bold text-slate-900 leading-snug">
                                                {{ pertanyaan.aspek }}
                                            </h5>
                                            <p v-if="pertanyaan.deskripsi" class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                                {{ pertanyaan.deskripsi }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                                        <button
                                            v-for="skala in skalaNilaiOptions"
                                            :key="skala"
                                            type="button"
                                            @click="toggleJawaban(pertanyaan.kode, skala)"
                                            @dblclick.stop="clearJawaban(pertanyaan.kode)"
                                            class="w-full py-2 px-3 text-xs font-semibold rounded-lg border transition-colors flex items-center justify-between cursor-pointer"
                                            :class="form.jawaban[pertanyaan.kode] === skala
                                                ? 'bg-[#0D542B] text-white border-[#0D542B] font-bold'
                                                : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                                        >
                                            <span>{{ skala }}</span>
                                            <span v-if="form.jawaban[pertanyaan.kode] === skala" class="text-white text-xs font-bold">&check;</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tampilan Tablet & Desktop (>= 768px): Tabel Matriks Rapi -->
                            <div class="hidden md:block overflow-x-auto border border-slate-200 rounded-lg shadow-2xs bg-white">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-[#0D542B] text-white font-bold text-center text-xs">
                                            <th class="p-3 w-12 border-r border-emerald-800 text-center text-white">No</th>
                                            <th class="p-3 border-r border-emerald-800 text-left min-w-[260px] text-white">
                                                Aspek Kinerja &amp; Kemampuan
                                            </th>
                                            <th v-for="skala in skalaNilaiOptions" :key="skala" class="p-3 w-28 text-center border-r border-emerald-800 last:border-r-0 text-white">
                                                {{ skala }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        <tr
                                            v-for="(pertanyaan, idx) in likertPertanyaanList"
                                            :key="`d_${pertanyaan.id}`"
                                            class="hover:bg-slate-50 transition-colors"
                                            :class="idx % 2 === 1 ? 'bg-slate-50/40' : 'bg-white'"
                                        >
                                            <td class="p-3 text-center font-bold text-slate-400 border-r border-slate-100">
                                                {{ idx + 1 }}
                                            </td>
                                            <td class="p-3 border-r border-slate-100">
                                                <div class="font-bold text-slate-900 text-xs leading-snug">{{ pertanyaan.aspek }}</div>
                                                <div v-if="pertanyaan.deskripsi" class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">{{ pertanyaan.deskripsi }}</div>
                                            </td>
                                            <td
                                                v-for="skala in skalaNilaiOptions"
                                                :key="skala"
                                                class="p-3 text-center border-r border-slate-100 last:border-r-0 cursor-pointer select-none transition-colors"
                                                :class="form.jawaban[pertanyaan.kode] === skala ? 'bg-emerald-50 text-[#0D542B] font-bold' : 'hover:bg-slate-100/60'"
                                                @click="toggleJawaban(pertanyaan.kode, skala)"
                                                @dblclick.stop="clearJawaban(pertanyaan.kode)"
                                            >
                                                <div class="flex items-center justify-center">
                                                    <input
                                                        type="radio"
                                                        :name="`d_jawaban_${pertanyaan.kode}`"
                                                        :checked="form.jawaban[pertanyaan.kode] === skala"
                                                        :value="skala"
                                                        readonly
                                                        class="w-4 h-4 accent-[#0D542B] cursor-pointer pointer-events-none"
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Catatan Tambahan / Saran Khusus -->
                        <div class="space-y-1.5 pt-2">
                            <label class="block text-xs font-bold text-slate-800">
                                Catatan Tambahan / Saran Khusus (Opsional):
                            </label>
                            <textarea
                                v-model="form.catatan_lainnya"
                                rows="3"
                                class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-xs sm:text-sm font-medium focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] transition-colors bg-white leading-relaxed outline-none"
                                placeholder="Saran, kritik, atau catatan khusus mengenai kinerja dan pengembangan kompetensi lulusan UKDW..."
                            ></textarea>
                        </div>

                        <!-- Tombol Submit Akhir & Kembali -->
                        <div class="pt-5 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <button
                                type="button"
                                @click="prevStep"
                                class="w-full sm:w-auto px-4 py-2.5 border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs sm:text-sm font-semibold rounded-lg transition-colors cursor-pointer inline-flex items-center justify-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                <span>Kembali ke Karakteristik</span>
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full sm:w-auto px-7 py-3 bg-[#0D542B] hover:bg-[#08381c] text-white font-bold text-xs sm:text-sm rounded-lg transition-colors shadow-xs disabled:opacity-50 cursor-pointer inline-flex items-center justify-center gap-2"
                            >
                                <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ form.processing ? 'Menyimpan Jawaban...' : 'Kirim Respon Evaluasi Kinerja' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </main>

        <!-- Footer Resmi -->
        <footer class="bg-white border-t border-slate-200 mt-10 py-5 text-xs text-slate-500 w-full">
            <div class="w-full px-4 sm:px-6 lg:px-10 flex flex-col sm:flex-row items-center justify-between gap-2 text-center sm:text-left">
                <div>
                    <span class="font-bold text-slate-800 block text-xs">Pusat Karir &amp; Alumni Universitas Kristen Duta Wacana</span>
                    <span class="text-[11px] text-slate-400">Jl. Dr. Wahidin Sudirohusodo No. 5-19, Yogyakarta 55224 | Telp: 0274-563929</span>
                </div>
                <div class="text-[11px] text-slate-400">
                    &copy; {{ new Date().getFullYear() }} Sistem Tracer Study UKDW
                </div>
            </div>
        </footer>
    </div>
</template>
