<!--
  Halaman Detail Mahasiswa & Audit Kuesioner Tracer Study (Admin Biro 3)
  File: resources/js/Pages/AdminBiroTiga/AlumniShow.vue
  
  Format Modular Seragam dengan SuperAdmin, Fakultas, dan Prodi:
  1. Detail Profil (Urutan 1, Komponen TabProfil - Read-Only)
  2. Kuesioner Universitas (Urutan 2, Komponen TabKuesionerUniv)
  3. Kuesioner Program Studi (Urutan 3, Komponen TabKuesionerProdi)
  4. Evaluasi Atasan (Urutan 4, Komponen TabEvaluasiAtasan)
  5. Hasil LinkedIn (Urutan 5, Komponen TabLinkedin - Read-Only)
  
  Navigasi menggunakan Stepper lingkaran resmi UKDW.
  Biro 3 bersifat Read-Only untuk data & LinkedIn, dilengkapi fitur komunikasi WA & Email pengingat.
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import Sidebar from './Components/Sidebar.vue';

// Sub-komponen modular dan Stepper dari direktori netral bersama @/Components/AlumniAudit/
import AlumniStepper from '@/Components/AlumniAudit/AlumniStepper.vue';
import TabProfil from '@/Components/AlumniAudit/TabProfil.vue';
import TabKuesionerUniv from '@/Components/AlumniAudit/TabKuesionerUniv.vue';
import TabKuesionerProdi from '@/Components/AlumniAudit/TabKuesionerProdi.vue';
import TabEvaluasiAtasan from '@/Components/AlumniAudit/TabEvaluasiAtasan.vue';
import TabLinkedin from '@/Components/AlumniAudit/TabLinkedin.vue';

const props = defineProps({
    user: Object,
    alumni: {
        type: Object,
        required: true,
    },
    evaluasi: {
        type: Object,
        required: true,
    },
    sections: {
        type: Array,
        default: () => [],
    },
    prodiSections: {
        type: Array,
        default: () => [],
    },
    prodiEvaluasi: {
        type: Object,
        default: () => ({
            is_complete: false,
            percentage: 0,
            answered_count: 0,
            total_questions: 0,
        }),
    },
    formData: {
        type: Object,
        default: () => ({}),
    },
    provinces: {
        type: Array,
        default: () => [],
    },
    kabupatens: {
        type: Array,
        default: () => [],
    },
    companies: {
        type: Array,
        default: () => [],
    },
    negaras: {
        type: Array,
        default: () => [],
    },
    refOptions: {
        type: Object,
        default: () => ({}),
    },
    evaluasiAtasan: {
        type: Object,
        default: null,
    },
    pertanyaanEvaluasiAtasan: {
        type: Array,
        default: () => [],
    },
    linkedinSyncResult: {
        type: Object,
        default: null,
    },
    linkedinTraceMapping: {
        type: Array,
        default: () => [],
    },
    linkedinHistory: {
        type: Array,
        default: () => [],
    },
});

// Urutan Tab Utama: 'profil', 'kuesioner', 'kuesioner_prodi', 'evaluasi_atasan', 'linkedin'
const initialTab = (typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tab')) || 'profil';
const activeMainTab = ref(['profil', 'kuesioner', 'kuesioner_prodi', 'evaluasi_atasan', 'linkedin'].includes(initialTab) ? initialTab : 'profil');

// Sinkronisasi query param URL saat tab berganti tanpa reload halaman
watch(activeMainTab, (newTab) => {
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', newTab);
        window.history.replaceState({}, '', url.toString());
    }
});

// Konfigurasi Navigasi Stepper
const stepperTabs = computed(() => {
    const list = [
        {
            id: 'profil',
            title: 'Detail Profil',
            subtitle: 'Data Pribadi & Akademik',
            isComplete: !!props.evaluasi?.profile?.is_complete,
            statusText: props.evaluasi?.profile?.is_complete 
                ? 'Lengkap (100%)' 
                : `${props.evaluasi?.profile?.percentage || 0}% Terisi`,
        },
        {
            id: 'kuesioner',
            title: 'Kuesioner Universitas',
            subtitle: 'Wajib Dikti',
            isComplete: !!props.evaluasi?.questionnaire?.is_complete,
            statusText: props.evaluasi?.questionnaire?.is_complete 
                ? 'Lengkap (100%)' 
                : `${props.evaluasi?.questionnaire?.mandatory_percentage || 0}% Wajib`,
        },
    ];

    if (props.alumni?.prodi_id) {
        list.push({
            id: 'kuesioner_prodi',
            title: 'Kuesioner Prodi',
            subtitle: props.alumni.prodi?.nama_prodi || 'Program Studi',
            isComplete: !!props.prodiEvaluasi?.is_complete,
            statusText: props.prodiEvaluasi?.is_complete 
                ? 'Lengkap (100%)' 
                : `${props.prodiEvaluasi?.percentage || 0}% Terisi`,
        });
    }

    list.push({
        id: 'evaluasi_atasan',
        title: 'Evaluasi Atasan',
        subtitle: 'Pengguna Lulusan',
        isComplete: !!props.evaluasiAtasan?.is_submitted,
        statusText: props.evaluasiAtasan?.is_submitted 
            ? 'Sudah Diisi' 
            : (props.evaluasiAtasan ? 'Pending' : 'Belum Ada'),
    });

    list.push({
        id: 'linkedin',
        title: 'Hasil LinkedIn',
        subtitle: 'Audit Rekam Karier',
        isComplete: !!props.linkedinSyncResult,
        statusText: props.linkedinSyncResult 
            ? `${props.linkedinTraceMapping?.length || 0} Data Terlacak` 
            : 'Belum Ada Data',
    });

    return list;
});



// Helper Semester & Yudisium
const semesterKelulusan = computed(() => {
    return props.alumni?.data_akademik?.tahun_akademik_lulus || props.alumni?.tahun_lulus || '-';
});

// =========================================================================
// FITUR KOMUNIKASI RESMI KHUSUS BIRO 3 (WHATSAPP MODAL & EMAIL FLASH)
// =========================================================================
const showWhatsAppModal = ref(false);
const customWaMessage = ref('');
const portalSurveyUrl = ref('https://seru.ukdw.ac.id/login');

const buildDefaultWaMessage = () => {
    const nama = props.alumni.nama || props.alumni.user?.name || 'Alumni UKDW';
    const nim = props.alumni.nim || '-';
    const link = portalSurveyUrl.value;

    return `Shalom, Salam Sejahtera Kak ${nama} (${nim}).\n\nKami dari Biro Kemahasiswaan, Alumni dan Pengembangan Karir (Biro 3) Universitas Kristen Duta Wacana (UKDW) mengundang Kakak untuk berpartisipasi dalam Pengisian Kuesioner Tracer Study UKDW.\n\nPartisipasi Kakak sangat berharga untuk peningkatan mutu akademik dan akreditasi almamater kita tercinta.\n\nSilakan akses portal tracer study pada tautan berikut:\n${link}\n\nPanduan Login:\n- Username: NIM (${nim})\n- Password: Tanggal Lahir (Format: DDMMYYYY) atau password yang telah diatur\n\nTerima kasih banyak atas perhatian dan kerjasamanya. Sukses selalu untuk karir Kakak!\nTuhan Memberkati.`;
};

const openWhatsAppModal = () => {
    customWaMessage.value = buildDefaultWaMessage();
    showWhatsAppModal.value = true;
};

const closeWhatsAppModal = () => {
    showWhatsAppModal.value = false;
};

const kirimPesanWhatsApp = () => {
    const rawPhone = props.alumni.nomor_telepon;
    if (!rawPhone || rawPhone === '-') {
        Swal.fire({
            icon: 'warning',
            title: 'Nomor WhatsApp Tidak Ditemukan',
            text: 'Data nomor telepon alumni ini belum terisi pada sistem.',
            confirmButtonColor: '#0D542B',
        });
        return;
    }

    let clean = rawPhone.replace(/\D/g, '');
    if (clean.startsWith('0')) {
        clean = '62' + clean.slice(1);
    } else if (clean.startsWith('8')) {
        clean = '62' + clean;
    }

    const encoded = encodeURIComponent(customWaMessage.value);
    const waUrl = `https://wa.me/${clean}?text=${encoded}`;
    window.open(waUrl, '_blank');
    closeWhatsAppModal();
};

const kirimEmailFlash = () => {
    const targetEmail = props.alumni.email_pribadi || props.alumni.user?.email;
    if (!targetEmail || targetEmail === '-') {
        Swal.fire({
            icon: 'warning',
            title: 'Email Tidak Ditemukan',
            text: 'Data email pribadi alumni ini belum terisi pada profil.',
            confirmButtonColor: '#0D542B',
        });
        return;
    }

    Swal.fire({
        title: 'Kirim Email Pengingat?',
        text: `Sistem akan mengirimkan email pengingat tracer study resmi ke ${targetEmail}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Kirim Sekarang',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            Swal.fire({
                title: 'Mengirim Email...',
                text: 'Sistem sedang memproses pengiriman email.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            router.post(`/biro3/alumni/${props.alumni.id}/send-email`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Email Berhasil Dikirim',
                        text: `Email pengingat tracer study telah dikirimkan ke ${targetEmail}.`,
                        confirmButtonColor: '#0D542B',
                    });
                },
                onError: () => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Mengirim Email',
                        text: 'Terjadi kendala pada pengiriman email server.',
                        confirmButtonColor: '#0D542B',
                    });
                },
            });
        }
    });
};
</script>

<template>
    <Head :title="`Detail Alumni: ${alumni.nama || alumni.user?.name} - Biro 3 UKDW`" />

    <div class="min-h-screen bg-[#F4F6F8] text-gray-800 font-sans flex">
        <!-- Sidebar Resmi Admin Biro 3 -->
        <Sidebar :user="user" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            
            <!-- Header Solid Hijau Resmi UKDW #0D542B -->
            <header class="admin-page-header bg-[#0D542B] text-white pt-8 pb-14 px-4 sm:px-6 lg:px-8 shadow-xs border-b border-[#0A4322]">
                <div class="max-w-[1400px] w-full mx-auto">
                    <!-- Breadcrumbs Navigasi Atas -->
                    <div class="flex items-center gap-2 text-xs text-white/80 font-medium mb-3">
                        <Link href="/biro3/dashboard" class="hover:underline">Dashboard</Link>
                        <span>/</span>
                        <Link href="/biro3/alumni" class="hover:underline">Direktori Alumni</Link>
                        <span>/</span>
                        <span class="text-white font-bold bg-white/10 px-2.5 py-0.5 rounded-md">Detail & Audit</span>
                    </div>

                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <!-- Profil Singkat Header Alumni -->
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-white/10 border-2 border-white/20 flex items-center justify-center font-bold text-xl sm:text-2xl text-white shadow-inner shrink-0 overflow-hidden">
                                <img 
                                    v-if="alumni.foto" 
                                    :src="alumni.foto" 
                                    :alt="alumni.nama" 
                                    class="w-full h-full object-cover"
                                />
                                <span v-else>{{ (alumni.nama || alumni.user?.name || 'A').charAt(0).toUpperCase() }}</span>
                            </div>
                            <div>
                                <h1 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-white tracking-tight leading-tight">
                                    {{ alumni.nama || alumni.user?.name }}
                                </h1>
                                <p class="text-xs sm:text-sm text-emerald-100/90 font-medium mt-1">
                                    NIM: <span class="font-mono font-bold text-white">{{ alumni.nim }}</span> &bull; 
                                    Program Studi: <span class="font-semibold text-white">{{ alumni.prodi?.nama_prodi || '-' }}</span> &bull; 
                                    Tahun Lulus: <span class="font-semibold text-white">{{ semesterKelulusan }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Tombol Aksi Cepat Header Biro 3 (WA, Email Flash, Export Excel) -->
                        <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
                            <!-- Tombol WhatsApp Cepat -->
                            <button 
                                @click="openWhatsAppModal()"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm cursor-pointer active:scale-95"
                                title="Kirim pesan WhatsApp resmi berisi tautan portal & panduan login"
                            >
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                <span>Hubungi WA</span>
                            </button>

                            <!-- Tombol Email Flash Cepat -->
                            <button 
                                @click="kirimEmailFlash()"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm cursor-pointer active:scale-95"
                                title="Kirim email pengingat tracer study resmi ke email alumni"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                <span>Kirim Email</span>
                            </button>

                            <!-- Tombol Download Excel -->
                            <a 
                                :href="`/biro3/alumni/${alumni.id}/export-excel`"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#FDC700] hover:bg-[#e5b400] text-black text-xs font-extrabold rounded-xl transition-all shadow-sm cursor-pointer"
                                title="Download berkas Excel (.xls) hasil kuesioner alumni ini"
                            >
                                <svg class="w-4 h-4 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                <span>Export Excel</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="admin-page-main max-w-[1400px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6 pb-16">

                <!-- Stepper Tahapan Audit Mahasiswa UKDW -->
                <AlumniStepper 
                    :tabs="stepperTabs" 
                    v-model="activeMainTab" 
                />

                <!-- ============================================================= -->
                <!-- 1. TAB DETAIL PROFIL ALUMNI (READ-ONLY KHUSUS BIRO 3)         -->
                <!-- ============================================================= -->
                <TabProfil 
                    v-show="activeMainTab === 'profil'"
                    :alumni="alumni"
                    :form-data="formData"
                    :provinces="provinces"
                    :kabupatens="kabupatens"
                    :negaras="negaras"
                    :companies="companies"
                    :ref-options="refOptions"
                    :evaluasi-atasan="evaluasiAtasan"
                    :readonly="true"
                />

                <!-- ============================================================= -->
                <!-- 2. TAB KUESIONER UNIVERSITAS                                  -->
                <!-- ============================================================= -->
                <TabKuesionerUniv 
                    v-show="activeMainTab === 'kuesioner'"
                    :sections="sections"
                    :evaluasi="evaluasi"
                />

                <!-- ============================================================= -->
                <!-- 3. TAB KUESIONER PROGRAM STUDI                                -->
                <!-- ============================================================= -->
                <TabKuesionerProdi 
                    v-show="activeMainTab === 'kuesioner_prodi'"
                    :alumni="alumni"
                    :prodi-sections="prodiSections"
                    :prodi-evaluasi="prodiEvaluasi"
                />

                <!-- ============================================================= -->
                <!-- 4. TAB EVALUASI ATASAN (PENGGUNA LULUSAN)                     -->
                <!-- ============================================================= -->
                <TabEvaluasiAtasan 
                    v-show="activeMainTab === 'evaluasi_atasan'"
                    :alumni="alumni"
                    :evaluasi-atasan="evaluasiAtasan"
                    :pertanyaan-evaluasi-atasan="pertanyaanEvaluasiAtasan"
                />

                <!-- ============================================================= -->
                <!-- 5. TAB HASIL SINKRONISASI & AUDIT LINKEDIN (READ-ONLY BIRO 3) -->
                <!-- ============================================================= -->
                <TabLinkedin 
                    v-show="activeMainTab === 'linkedin'"
                    :alumni="alumni"
                    :linkedin-sync-result="linkedinSyncResult"
                    :linkedin-trace-mapping="linkedinTraceMapping"
                    :linkedin-history="linkedinHistory"
                    :is-super-admin="false"
                    :readonly="true"
                />

            </main>
        </div>
    </div>

    <!-- MODAL CUSTOM PESAN WHATSAPP RESMI BIRO 3 -->
    <div 
        v-if="showWhatsAppModal" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-opacity"
    >
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-gray-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Kirim Undangan WhatsApp</h3>
                        <p class="text-[11px] text-gray-500">Tinjau atau sesuaikan draf pesan sebelum diarahkan ke aplikasi WhatsApp</p>
                    </div>
                </div>
                <button 
                    @click="closeWhatsAppModal()" 
                    class="text-gray-400 hover:text-gray-600 rounded-lg p-1 text-sm font-bold"
                >
                    ✕
                </button>
            </div>

            <!-- Preview Nomor Tujuan -->
            <div class="bg-gray-50 p-3 rounded-xl border border-gray-200 text-xs flex items-center justify-between">
                <span class="text-gray-600 font-medium">Tujuan Penerima:</span>
                <span class="font-mono font-bold text-gray-900">
                    {{ alumni.nomor_telepon || '- (Belum Ada Nomor)' }}
                </span>
            </div>

            <!-- Editor Teks Pesan -->
            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-gray-700">Isi Pesan WhatsApp:</label>
                <textarea 
                    v-model="customWaMessage"
                    rows="8"
                    class="w-full text-xs font-sans p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#0D542B] focus:outline-none text-gray-800 leading-relaxed"
                    placeholder="Tuliskan pesan resmi..."
                ></textarea>
                <span class="text-[10px] text-gray-400 italic">Pesan sudah diformat dengan sapaan, identitas NIM, tautan portal, dan panduan login resmi.</span>
            </div>

            <!-- Tombol Aksi Modal -->
            <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-gray-100">
                <button 
                    type="button" 
                    @click="closeWhatsAppModal()" 
                    class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl transition-colors cursor-pointer"
                >
                    Batal
                </button>
                <button 
                    type="button" 
                    @click="kirimPesanWhatsApp()" 
                    class="px-5 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-xs transition-colors flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    <span>Lanjutkan ke WhatsApp</span>
                </button>
            </div>
        </div>
    </div>
</template>
