<!--
  Komponen Bersama: Tab Evaluasi Atasan / Pengguna Lulusan (Admin View)
  Lokasi: resources/js/Components/AlumniAudit/TabEvaluasiAtasan.vue
  
  Mendukung peran Super Admin, Fakultas, Prodi, dan Biro 3.
-->
<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

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
 * Menyalin tautan survei publik evaluasi atasan ke clipboard.
 */
const copySurveyUrl = () => {
    if (props.evaluasiAtasan?.survey_url) {
        navigator.clipboard.writeText(props.evaluasiAtasan.survey_url)
            .then(() => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Tautan kuesioner atasan berhasil disalin!',
                    showConfirmButton: false,
                    timer: 2000,
                });
            });
    }
};

const sendingEmail = ref(false);
const emailAtasanTarget = computed(() => {
    return props.evaluasiAtasan?.atasan?.email || props.alumni?.email_atasan || props.alumni?.atasan?.email || '';
});

const handleKirimEmailAtasan = () => {
    const email = emailAtasanTarget.value;
    if (!email || !email.includes('@')) {
        Swal.fire({
            title: 'Email Atasan Tidak Ditemukan',
            text: 'Data email atasan belum tercantum atau format tidak valid pada profil alumni ini.',
            icon: 'warning',
            confirmButtonColor: '#0D542B',
        });
        return;
    }

    Swal.fire({
        title: 'Kirim Email ke Atasan?',
        html: `Kirimkan email undangan pengisian kuesioner evaluasi atasan langsung ke: <br><strong class="text-slate-900">${email}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Kirim Email',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            sendingEmail.value = true;
            router.post('/alumni/kirim-email-atasan', {
                biodata_id: props.alumni?.id || props.evaluasiAtasan?.biodata_id,
                email_atasan: email,
            }, {
                preserveScroll: true,
                onFinish: () => {
                    sendingEmail.value = false;
                },
                onSuccess: () => {
                    Swal.fire({
                        title: 'Email Terkirim',
                        text: `Undangan evaluasi atasan berhasil dikirim ke ${email}.`,
                        icon: 'success',
                        confirmButtonColor: '#0D542B',
                    });
                },
                onError: (err) => {
                    Swal.fire({
                        title: 'Gagal Mengirim Email',
                        text: err?.message || 'Terjadi kendala saat mengirim email ke atasan.',
                        icon: 'error',
                        confirmButtonColor: '#0D542B',
                    });
                },
            });
        }
    });
};

const getSkorLabel = (skor) => {
    const num = Number(skor);
    if (num === 4) return 'Sangat Baik';
    if (num === 3) return 'Baik';
    if (num === 2) return 'Cukup';
    if (num === 1) return 'Kurang';
    return String(skor);
};

const getSkorBadgeClass = (skor) => {
    const num = Number(skor);
    if (num >= 4) return 'bg-emerald-50 text-emerald-800 border-emerald-300 font-bold';
    if (num === 3) return 'bg-emerald-50/50 text-slate-800 border-slate-300 font-semibold';
    if (num === 2) return 'bg-slate-50 text-slate-700 border-slate-300 font-medium';
    if (num === 1) return 'bg-slate-100 text-slate-800 border-slate-300 font-medium';
    return 'bg-slate-50 text-slate-600 border-slate-200';
};

const evaluasiAtasanQuestionsWithAnswers = computed(() => {
    if (!props.pertanyaanEvaluasiAtasan || props.pertanyaanEvaluasiAtasan.length === 0) return [];
    
    const responsMap = {};
    if (props.evaluasiAtasan?.respons) {
        props.evaluasiAtasan.respons.forEach(r => {
            responsMap[r.pertanyaan_id] = r;
        });
    }

    return props.pertanyaanEvaluasiAtasan.map(q => {
        const resp = responsMap[q.id];
        return {
            ...q,
            kode_pertanyaan: q.kode || q.kode_pertanyaan || '-',
            aspek_penilaian: q.aspek || q.aspek_penilaian || '-',
            keterangan: q.deskripsi || q.keterangan || '',
            skor: resp ? resp.skor : null,
            catatan: resp ? resp.catatan : null,
            has_response: !!resp,
        };
    });
});
</script>

<template>
    <div class="space-y-5">
        <!-- Heading & Kartu Info Atasan -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-200">
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h3 class="text-sm sm:text-base font-bold text-slate-900">
                            Evaluasi Pengguna Lulusan (Atasan)
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

                <div v-if="evaluasiAtasan?.survey_url" class="flex items-center gap-2 shrink-0 flex-wrap">
                    <button 
                        type="button" 
                        @click="copySurveyUrl" 
                        class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5 border border-slate-300 shadow-2xs cursor-pointer"
                        title="Salin tautan formulir survei atasan"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                        <span>Salin Link Kuesioner</span>
                    </button>
                    <button 
                        type="button" 
                        @click="handleKirimEmailAtasan" 
                        :disabled="sendingEmail || !emailAtasanTarget"
                        class="px-3.5 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5 shadow-2xs disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                        title="Kirim email undangan evaluasi langsung ke atasan"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <span>{{ sendingEmail ? 'Mengirim...' : 'Kirim Email ke Atasan' }}</span>
                    </button>
                </div>
            </div>

            <!-- Detail Atasan & Perusahaan jika ada -->
            <div v-if="evaluasiAtasan" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <!-- Data Atasan Langsung -->
                <div class="p-3.5 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
                    <span class="text-xs font-bold text-slate-800 block uppercase tracking-wide">Pimpinan / Atasan Langsung</span>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Nama Atasan</span>
                            <span class="font-bold text-slate-800">{{ evaluasiAtasan.atasan?.nama || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Jabatan</span>
                            <span class="font-medium text-slate-700">{{ evaluasiAtasan.atasan?.jabatan || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Email</span>
                            <span class="font-mono text-slate-700 truncate block">{{ evaluasiAtasan.atasan?.email || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">No. Telepon / WA</span>
                            <span class="font-medium text-slate-700">{{ evaluasiAtasan.atasan?.no_hp || '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Data Instansi & Token -->
                <div class="p-3.5 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
                    <span class="text-xs font-bold text-slate-800 block uppercase tracking-wide">Instansi & Token Akses</span>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Perusahaan / Instansi</span>
                            <span class="font-bold text-slate-800">{{ evaluasiAtasan.perusahaan?.nama || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Kota</span>
                            <span class="font-medium text-slate-700">{{ evaluasiAtasan.perusahaan?.kota || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Token Akses</span>
                            <code class="font-mono bg-white px-2 py-0.5 rounded border border-slate-200 text-[#0D542B] font-bold text-[11px] inline-block">{{ evaluasiAtasan.token || '-' }}</code>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Status Survei</span>
                            <span class="font-semibold text-emerald-800 capitalize">{{ evaluasiAtasan.status || 'Aktif' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- State Bersih jika Belum Ada Atasan -->
            <div v-else class="py-8 px-4 text-center bg-slate-50 rounded-lg border border-slate-200 mt-4">
                <svg class="w-8 h-8 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <h4 class="text-xs font-bold text-slate-800">Belum Ada Informasi Atasan Terdaftar</h4>
                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                    Alumni ini belum mencantumkan informasi kontak atasan langsung tempat bekerja atau belum ada penugasan survei evaluasi pengguna lulusan.
                </p>
            </div>
        </div>

        <!-- Tabel Hasil Penilaian Aspek Kinerja (Hanya muncul jika ada evaluasiAtasan) -->
        <div v-if="evaluasiAtasan" class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-2xs">
            <div class="p-4 sm:p-5 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                        Hasil Penilaian Aspek Kinerja & Kemampuan Alumni
                    </h4>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        {{ evaluasiAtasan.is_submitted ? 'Respon penilaian telah terverifikasi dan masuk ke dalam basis data' : 'Survei telah terkirim, menunggu pengisian instrumen oleh atasan' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 bg-[#0D542B] text-white text-[11px] font-bold rounded-lg">
                        {{ evaluasiAtasanQuestionsWithAnswers.length }} Butir Penilaian
                    </span>
                    <span 
                        class="px-2.5 py-0.5 rounded text-[11px] font-semibold border"
                        :class="evaluasiAtasan.is_submitted ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200'"
                    >
                        {{ evaluasiAtasan.is_submitted ? 'Respon Terverifikasi' : 'Menunggu Respon Atasan' }}
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-100 text-slate-700 border-b border-slate-200 text-[11px] uppercase font-bold tracking-wider">
                            <th class="py-2.5 px-3 text-center w-12 border-r border-slate-200">#</th>
                            <th class="py-2.5 px-4 border-r border-slate-200 w-28">Kode Butir</th>
                            <th class="py-2.5 px-4 border-r border-slate-200 min-w-[280px]">Aspek Penilaian & Deskripsi</th>
                            <th class="py-2.5 px-4 text-center border-r border-slate-200 w-44">Skor / Nilai</th>
                            <th class="py-2.5 px-4 min-w-[200px]">Catatan Penilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <tr 
                            v-for="(item, idx) in evaluasiAtasanQuestionsWithAnswers" 
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
                                <p v-if="item.keterangan" class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">{{ item.keterangan }}</p>
                            </td>
                            <td class="py-3 px-4 text-center border-r border-slate-100">
                                <div v-if="item.skor !== null && item.skor !== undefined" class="flex flex-col items-center gap-1">
                                    <span 
                                        class="px-2.5 py-1 rounded text-[11px] border"
                                        :class="getSkorBadgeClass(item.skor)"
                                    >
                                        Skor: {{ item.skor }} - {{ getSkorLabel(item.skor) }}
                                    </span>
                                </div>
                                <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                    Menunggu Respon
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-700">
                                <span v-if="item.catatan" class="font-medium text-xs">{{ item.catatan }}</span>
                                <span v-else class="text-slate-400 italic text-[11px]">-</span>
                            </td>
                        </tr>

                        <tr v-if="evaluasiAtasanQuestionsWithAnswers.length === 0">
                            <td colspan="5" class="py-8 text-center text-slate-400 text-xs italic">
                                Belum ada butir pertanyaan kuesioner evaluasi atasan yang dikonfigurasi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Saran Terbuka dari Atasan -->
        <div v-if="evaluasiAtasan?.saran" class="bg-white rounded-xl p-5 border border-slate-200 space-y-2 shadow-2xs">
            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                Saran & Rekomendasi Kurikulum dari Pengguna Lulusan
            </h4>
            <div class="p-3.5 bg-slate-50 rounded-lg border border-slate-200 text-xs text-slate-700 leading-relaxed italic whitespace-pre-line">
                &ldquo;{{ evaluasiAtasan.saran }}&rdquo;
            </div>
        </div>
    </div>
</template>
