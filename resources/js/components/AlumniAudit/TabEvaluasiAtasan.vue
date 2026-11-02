<!--
  Komponen Bersama: Tab Evaluasi Atasan / Pengguna Lulusan (Admin View)
  Lokasi: resources/js/components/AlumniAudit/TabEvaluasiAtasan.vue

  Mendukung peran Super Admin, Fakultas, Prodi, dan Biro 3.
  Menampilkan audit lengkap hasil evaluasi pengguna lulusan:
  1. Identitas Pimpinan / Atasan Langsung
  2. Profil & Kontak Instansi / Perusahaan
  3. Karakteristik Instansi & Sebaran Alumni UKDW
  4. Penempatan Kerja & Riwayat Studi Lanjut Alumni
  5. Hasil Penilaian Tingkat Kesiapan Kerja
  6. Tabel Penilaian 12 Aspek Kompetensi & Kinerja (Skor 1 - 5, 1 Paling Rendah)
  7. Catatan & Masukan Tunggal Penilai di bagian paling bawah
-->
<script setup>
import { computed } from 'vue';

/**
 * Definisi properti komponen tab evaluasi atasan.
 */
const props = defineProps({
    /** Objek data alumni */
    alumni: {
        type: Object,
        required: true,
    },
    /** Data survei evaluasi atasan terkait */
    evaluasiAtasan: {
        type: Object,
        default: null,
    },
    /** Daftar 13 butir pertanyaan evaluasi pengguna lulusan */
    pertanyaanEvaluasiAtasan: {
        type: Array,
        default: () => [],
    },
});

/**
 * Data entitas perusahaan terpadu dari evaluasi atasan dan profil alumni.
 */
const company = computed(() => {
    return props.evaluasiAtasan?.perusahaan || props.alumni?.perusahaan || {};
});

/**
 * Data entitas atasan penilai terpadu dari evaluasi atasan dan profil alumni.
 */
const atasan = computed(() => {
    return props.evaluasiAtasan?.atasan || props.alumni?.atasan || {};
});

/**
 * Format label jenis perusahaan / instansi (F11).
 */
const jenisPerusahaanDisplay = computed(() => {
    const c = company.value;
    if (!c.jenis_perusahaan) return '-';
    if (c.jenis_perusahaan === 'Lainnya' && c.jenis_perusahaan_lainnya) {
        return `Lainnya (${c.jenis_perusahaan_lainnya})`;
    }
    return c.jenis_perusahaan;
});

/**
 * Format baris lokasi geografis instansi.
 */
const wilayahPerusahaanDisplay = computed(() => {
    const c = company.value;
    const parts = [
        c.kabupaten?.nama_kabupaten,
        c.propinsi?.nama_provinsi,
        c.negara && c.negara !== 'Indonesia' ? c.negara : null,
        c.kode_pos ? `Kode Pos ${c.kode_pos}` : null,
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(', ') : '-';
});

/**
 * Data karakteristik instansi dan keberadaan alumni UKDW (Langkah 2 Formulir Atasan).
 */
const karakteristik = computed(() => {
    const c = company.value;
    return {
        bentuk: c.bentuk_perusahaan || props.alumni?.perusahaan?.bentuk_perusahaan || '-',
        skala: c.skala || props.alumni?.perusahaan?.skala || '-',
        jumlah_pegawai: c.jumlah_pegawai || props.alumni?.perusahaan?.jumlah_pegawai || '-',
        jumlah_alumni_ukdw: props.evaluasiAtasan?.jumlah_alumni_ukdw || '-',
        standar_gaji_pertama: props.evaluasiAtasan?.standar_gaji_pertama || '-',
    };
});

/**
 * Data penempatan karier dan riwayat studi lanjut alumni bersangkutan.
 */
const alumniPlacement = computed(() => {
    const al = props.alumni || {};
    const isStudi = !!(al.pendidikan_tingkat || al.perguruan_tinggi || al.pendidikan_prodi);

    return {
        posisi_jabatan: al.posisi_jabatan || '-',
        bidang_pekerjaan: al.bidang_pekerjaan || '-',
        kategori_pekerjaan: al.kategori_pekerjaan || '-',
        is_studi: isStudi,
        pendidikan_tingkat: al.pendidikan_tingkat || '-',
        perguruan_tinggi: al.perguruan_tinggi || '-',
        pendidikan_prodi: al.pendidikan_prodi || '-',
    };
});

/**
 * Mengonversi nilai respon evaluasi menjadi format skor 1 - 5 dinamis.
 * Format skala: 1 = Paling Rendah (Sangat Kurang / Tidak Siap) s.d. 5 = Paling Tinggi (Sangat Tinggi / Sangat Siap).
 */
const parseSkorLikert = (val) => {
    if (val === null || val === undefined || val === '') {
        return null;
    }

    // Jika nilai sudah berupa angka 1 - 5
    const num = Number(val);
    if (!isNaN(num) && num >= 1 && num <= 5) {
        const labels = {
            1: 'Sangat Kurang',
            2: 'Kurang',
            3: 'Cukup',
            4: 'Tinggi',
            5: 'Sangat Tinggi',
        };
        return {
            skor: num,
            label: labels[num] || String(num),
            display: `${num} - ${labels[num] || ''}`,
        };
    }

    const str = String(val).trim().toLowerCase();

    // Pemetaan 12 Aspek Kompetensi & Kinerja
    if (str === 'sangat tinggi' || str === 'sangat baik') {
        return { skor: 5, label: 'Sangat Tinggi', display: '5 - Sangat Tinggi' };
    }
    if (str === 'tinggi' || str === 'baik') {
        return { skor: 4, label: 'Tinggi', display: '4 - Tinggi' };
    }
    if (str === 'cukup' || str === 'sedang') {
        return { skor: 3, label: 'Cukup', display: '3 - Cukup' };
    }
    if (str === 'kurang' || str === 'rendah') {
        return { skor: 2, label: 'Kurang', display: '2 - Kurang' };
    }
    if (str === 'sangat kurang' || str === 'sangat rendah') {
        return { skor: 1, label: 'Sangat Kurang', display: '1 - Sangat Kurang' };
    }

    // Pemetaan Tingkat Kesiapan Kerja
    if (str === 'sangat siap') {
        return { skor: 5, label: 'Sangat Siap', display: '5 - Sangat Siap' };
    }
    if (str === 'siap') {
        return { skor: 4, label: 'Siap', display: '4 - Siap' };
    }
    if (str === 'cukup siap') {
        return { skor: 3, label: 'Cukup Siap', display: '3 - Cukup Siap' };
    }
    if (str === 'kurang siap') {
        return { skor: 2, label: 'Kurang Siap', display: '2 - Kurang Siap' };
    }
    if (str === 'tidak siap') {
        return { skor: 1, label: 'Tidak Siap', display: '1 - Tidak Siap' };
    }

    return { skor: null, label: val, display: String(val) };
};

/**
 * Mengelompokkan seluruh pertanyaan dan mengaitkannya dengan respon penilai.
 */
const allQuestionsWithAnswers = computed(() => {
    if (!props.pertanyaanEvaluasiAtasan || props.pertanyaanEvaluasiAtasan.length === 0) return [];

    const responsMap = {};
    if (props.evaluasiAtasan?.respons) {
        props.evaluasiAtasan.respons.forEach((r) => {
            responsMap[r.pertanyaan_id] = r;
        });
    }

    return props.pertanyaanEvaluasiAtasan.map((q) => {
        const resp = responsMap[q.id];
        const rawNilai = resp ? resp.nilai : null;
        return {
            ...q,
            kode_pertanyaan: q.kode || q.kode_pertanyaan || '-',
            aspek_penilaian: q.aspek || q.aspek_penilaian || '-',
            keterangan: q.deskripsi || q.keterangan || '',
            nilai: rawNilai,
            skorObj: parseSkorLikert(rawNilai),
            catatan: resp ? resp.catatan : null,
            has_response: !!resp,
        };
    });
});

/**
 * Butir evaluasi kesiapan kerja alumni.
 */
const kesiapanKerjaItem = computed(() => {
    return allQuestionsWithAnswers.value.find((q) => q.kode_pertanyaan === 'tingkat_kesiapan_kerja');
});

/**
 * 12 Butir evaluasi aspek kompetensi & kinerja lulusan.
 */
const aspekKinerjaItems = computed(() => {
    return allQuestionsWithAnswers.value.filter((q) => q.kode_pertanyaan !== 'tingkat_kesiapan_kerja');
});

/**
 * Catatan kualitatif tunggal dari penilai (satu catatan di bagian bawah).
 */
const catatanPenilaiTunggal = computed(() => {
    if (props.evaluasiAtasan?.catatan && String(props.evaluasiAtasan.catatan).trim() !== '') {
        return props.evaluasiAtasan.catatan;
    }
    if (props.evaluasiAtasan?.saran && String(props.evaluasiAtasan.saran).trim() !== '') {
        return props.evaluasiAtasan.saran;
    }
    const fromResponses = props.evaluasiAtasan?.respons?.find((r) => r.catatan && String(r.catatan).trim() !== '');
    return fromResponses?.catatan || null;
});

/**
 * Rata-rata skor numerik aspek kinerja (skala 1 - 5).
 */
const rataRataSkorKinerja = computed(() => {
    const validScores = aspekKinerjaItems.value
        .map((item) => item.skorObj?.skor)
        .filter((s) => typeof s === 'number' && !isNaN(s));

    if (validScores.length === 0) return null;
    const sum = validScores.reduce((acc, curr) => acc + curr, 0);
    return (sum / validScores.length).toFixed(2);
});
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Header Banner & Status Verifikasi -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h3 class="text-sm sm:text-base font-bold text-slate-900">
                            Evaluasi Pengguna Lulusan (Atasan Langsung)
                        </h3>
                        <span
                            v-if="evaluasiAtasan"
                            class="px-2.5 py-0.5 rounded text-[11px] font-semibold border"
                            :class="evaluasiAtasan.is_submitted ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200'"
                        >
                            {{ evaluasiAtasan.is_submitted ? 'Sudah Diisi (Lengkap)' : 'Menunggu Respon Atasan' }}
                        </span>
                        <span v-else class="px-2.5 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                            Belum Ada Data Evaluasi
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">
                        Instrumen penilaian kepuasan pengguna lulusan terkait performa, etika, dan kompetensi alumni di tempat kerja.
                    </p>
                </div>

                <div v-if="evaluasiAtasan" class="text-right text-xs text-slate-500">
                    <span class="block text-[11px] uppercase tracking-wider text-slate-400 font-semibold">Waktu Pengisian</span>
                    <span class="font-medium text-slate-800">
                        {{ evaluasiAtasan.submitted_at_formatted || (evaluasiAtasan.is_submitted ? 'Telah Terkirim' : 'Menunggu Atasan') }}
                    </span>
                </div>
            </div>

            <!-- State Kosong jika Belum Ada Data Evaluasi Atasan -->
            <div v-if="!evaluasiAtasan" class="py-8 px-4 text-center bg-slate-50 rounded-lg border border-slate-200 mt-4">
                <svg class="w-8 h-8 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <h4 class="text-xs font-bold text-slate-800">Belum Ada Informasi Atasan Terdaftar</h4>
                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                    Alumni ini belum mencantumkan informasi kontak atasan langsung tempat bekerja atau belum ada penugasan survei evaluasi pengguna lulusan.
                </p>
            </div>
        </div>

        <!-- 2. Informasi Lengkap: Atasan, Profil Instansi, Karakteristik & Penempatan Alumni -->
        <div v-if="evaluasiAtasan" class="space-y-4">
            <!-- Grid 2 Kolom: Identitas Atasan & Penempatan Alumni -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Kartu Identitas Atasan Penilai -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wide">Pimpinan / Atasan Penilai</span>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold border border-slate-200">
                            Atasan Langsung
                        </span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Nama Atasan</span>
                            <span class="font-bold text-slate-900">{{ atasan.nama || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Hubungan Penilai</span>
                            <span class="font-medium text-slate-700">Atasan Langsung Alumni</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Email Atasan</span>
                            <span class="font-mono text-slate-700 truncate block">{{ atasan.email || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">No. Telepon / WhatsApp</span>
                            <span class="font-medium text-slate-700">{{ atasan.telepon || '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Kartu Penempatan Karier & Studi Lanjut Alumni -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wide">Penempatan Kerja & Studi Lanjut</span>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 font-semibold border border-emerald-200">
                            {{ alumniPlacement.kategori_pekerjaan }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Posisi / Jabatan</span>
                            <span class="font-bold text-slate-900">{{ alumniPlacement.posisi_jabatan }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Bidang Pekerjaan / Divisi</span>
                            <span class="font-medium text-slate-700">{{ alumniPlacement.bidang_pekerjaan }}</span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Status Studi Lanjut</span>
                            <span v-if="alumniPlacement.is_studi" class="font-medium text-slate-800">
                                {{ alumniPlacement.pendidikan_tingkat }} — {{ alumniPlacement.pendidikan_prodi }} ({{ alumniPlacement.perguruan_tinggi }})
                            </span>
                            <span v-else class="text-slate-500 italic">Tidak sedang melanjutkan studi</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kartu Profil Instansi & Karakteristik Perusahaan (Langkah 1 & Langkah 2 Lengkap) -->
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs space-y-4">
                <div class="border-b border-slate-100 pb-2.5">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wide">
                        Profil & Karakteristik Instansi / Perusahaan
                    </h4>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        Identitas lembaga, kontak resmi, skala operasional, serta keterhubungan dengan lulusan UKDW.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                    <!-- Baris 1: Identitas & Klasifikasi -->
                    <div class="lg:col-span-2">
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Nama Perusahaan / Instansi</span>
                        <span class="font-bold text-slate-900 text-sm">{{ company.nama_perusahaan || '-' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Jenis Instansi (F11)</span>
                        <span class="font-medium text-slate-800">{{ jenisPerusahaanDisplay }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Bentuk Lembaga</span>
                        <span class="font-medium text-slate-800">{{ karakteristik.bentuk }}</span>
                    </div>

                    <!-- Baris 2: Alamat, Kontak & Web -->
                    <div class="lg:col-span-2">
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Alamat Lengkap</span>
                        <span class="text-slate-700 leading-relaxed">{{ company.alamat || '-' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">No. Telp / Fax</span>
                        <span class="font-medium text-slate-800">{{ company.no_telp_fax || '-' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Website / Homepage</span>
                        <a
                            v-if="company.homepage"
                            :href="company.homepage.startsWith('http') ? company.homepage : `https://${company.homepage}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-[#0D542B] hover:underline font-medium truncate block"
                        >
                            {{ company.homepage }}
                        </a>
                        <span v-else class="text-slate-400">-</span>
                    </div>

                    <!-- Baris 3: Sebaran Wilayah Spesifik (Negara, Provinsi, Kabupaten/Kota, Kode Pos) -->
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Negara</span>
                        <span class="font-medium text-slate-800">
                            {{ company.negara || (company.jenis_lokasi === 'Luar Negeri' ? 'Luar Negeri' : 'Indonesia') }}
                            <span v-if="company.jenis_lokasi === 'Luar Negeri'" class="text-[10px] text-slate-500 block font-normal">(Luar Negeri)</span>
                        </span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Provinsi</span>
                        <span class="font-medium text-slate-800">
                            {{ company.propinsi?.nama_provinsi || (company.jenis_lokasi === 'Luar Negeri' ? '-' : '-') }}
                        </span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Kabupaten / Kota</span>
                        <span class="font-medium text-slate-800">
                            {{ company.kabupaten?.nama_kabupaten || '-' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Kode Pos</span>
                        <span class="font-medium text-slate-800">{{ company.kode_pos || '-' }}</span>
                    </div>

                    <!-- Baris 4: Skala Operasional, Pegawai, Alumni & Gaji -->
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Skala Operasional</span>
                        <span class="font-medium text-slate-800">{{ karakteristik.skala }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Jumlah Pegawai</span>
                        <span class="font-medium text-slate-800">{{ karakteristik.jumlah_pegawai }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Jumlah Alumni UKDW</span>
                        <span class="font-semibold text-emerald-900">{{ karakteristik.jumlah_alumni_ukdw }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-slate-400 block">Standar Gaji Pertama</span>
                        <span class="font-bold text-slate-900">{{ karakteristik.standar_gaji_pertama }}</span>
                    </div>
                </div>
            </div>

            <!-- 3. Hasil Evaluasi: Kesiapan Kerja Lulusan -->
            <div v-if="kesiapanKerjaItem" class="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-2xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="bg-slate-100 px-2 py-0.5 rounded text-[11px] font-mono font-bold text-slate-800 border border-slate-200">
                                {{ kesiapanKerjaItem.kode_pertanyaan }}
                            </span>
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wide">
                                {{ kesiapanKerjaItem.aspek_penilaian }}
                            </h4>
                        </div>
                        <p class="text-xs text-slate-600">
                            {{ kesiapanKerjaItem.keterangan || 'Penilaian tingkat kesiapan awal lulusan saat pertama kali masuk ke lingkungan kerja profesional.' }}
                        </p>
                    </div>

                    <div class="shrink-0 flex items-center gap-2">
                        <span class="text-xs text-slate-500 font-medium">Hasil Penilaian:</span>
                        <span v-if="kesiapanKerjaItem.skorObj" class="text-xs font-semibold text-slate-800">
                            {{ kesiapanKerjaItem.skorObj.display }}
                        </span>
                        <span v-else class="text-xs text-slate-400 italic">
                            Menunggu Respon
                        </span>
                    </div>
                </div>
            </div>

            <!-- 4. Tabel Penilaian 12 Aspek Kompetensi & Kinerja (Skor 1 - 5, 1 Paling Rendah) -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-2xs">
                <div class="p-4 sm:p-5 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                            Penilaian 12 Aspek Kompetensi & Kinerja Lulusan
                        </h4>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Skala penilaian berkisar antara 1 (Paling Rendah / Sangat Kurang) hingga 5 (Paling Tinggi / Sangat Tinggi).
                        </p>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <span v-if="rataRataSkorKinerja" class="px-2.5 py-1 bg-slate-100 text-slate-800 text-[11px] font-semibold rounded">
                            Rata-Rata Skor: {{ rataRataSkorKinerja }} / 5.0
                        </span>
                        <span class="px-2.5 py-1 bg-slate-100 text-slate-700 text-[11px] font-medium rounded">
                            {{ aspekKinerjaItems.length }} Aspek
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-100 text-slate-700 border-b border-slate-200 text-[11px] uppercase font-bold tracking-wider">
                                <th class="py-2.5 px-3 text-center w-12 border-r border-slate-200">#</th>
                                <th class="py-2.5 px-4 border-r border-slate-200 w-36">Kode Butir</th>
                                <th class="py-2.5 px-4 border-r border-slate-200">Aspek Penilaian & Deskripsi</th>
                                <th class="py-2.5 px-4 text-center w-52">
                                    <div>Skor / Nilai</div>
                                    <div class="text-[10px] font-normal text-slate-500 normal-case">Skala 1 - 5 (1 Paling Rendah)</div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr
                                v-for="(item, idx) in aspekKinerjaItems"
                                :key="item.id || idx"
                                class="hover:bg-slate-50/60 transition-colors"
                                :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/20'"
                            >
                                <td class="py-3 px-3 text-center font-mono font-bold text-slate-400 border-r border-slate-100">
                                    {{ idx + 1 }}
                                </td>
                                <td class="py-3 px-4 font-mono font-bold text-slate-800 border-r border-slate-100">
                                    <span class="bg-slate-100 px-2 py-0.5 rounded text-[11px] text-slate-800 border border-slate-200">
                                        {{ item.kode_pertanyaan }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 border-r border-slate-100">
                                    <div class="font-bold text-slate-900">{{ item.aspek_penilaian }}</div>
                                    <p v-if="item.keterangan" class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                        {{ item.keterangan }}
                                    </p>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span v-if="item.skorObj" class="text-xs font-semibold text-slate-800">
                                        {{ item.skorObj.display }}
                                    </span>
                                    <span v-else class="text-xs text-slate-400 italic">
                                        Menunggu Respon
                                    </span>
                                </td>
                            </tr>

                            <tr v-if="aspekKinerjaItems.length === 0">
                                <td colspan="4" class="py-8 text-center text-slate-400 text-xs italic">
                                    Belum ada butir pertanyaan kuesioner evaluasi atasan yang dikonfigurasi.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 5. Catatan & Masukan Tunggal Penilai di Paling Bawah -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 space-y-2 shadow-2xs">
                <div class="border-b border-slate-100 pb-2.5">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wide">
                        Catatan & Masukan Penilai
                    </h4>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        Umpan balik kualitatif, evaluasi performa, serta saran kurikulum terbuka dari pimpinan/atasan langsung untuk peningkatan mutu lulusan.
                    </p>
                </div>

                <div v-if="catatanPenilaiTunggal" class="p-4 bg-slate-50 rounded-lg border border-slate-200 text-xs text-slate-800 leading-relaxed italic whitespace-pre-line">
                    &ldquo;{{ catatanPenilaiTunggal }}&rdquo;
                </div>
                <div v-else class="p-4 bg-slate-50 rounded-lg border border-dashed border-slate-200 text-xs text-slate-500 italic text-center">
                    Tidak ada catatan tambahan yang diberikan oleh penilai.
                </div>
            </div>
        </div>
    </div>
</template>
