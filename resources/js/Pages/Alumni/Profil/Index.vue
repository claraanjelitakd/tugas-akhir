<!--
  Halaman Utama (Parent Component): Kelola Profil Alumni
  File: resources/js/Pages/Alumni/Profil/Index.vue
  
  DIRELOAD OLEH BACKEND DARI:
  - Controller Tampil : App\Http\Controllers\Alumni\Profil\ProfilController.php (method tampilkanHalamanProfil)
  - Route URL (GET)   : /alumni/profile
  
  DISIMPAN KE BACKEND OLEH:
  - Controller Simpan : App\Http\Controllers\Alumni\Profil\SimpanProfilController.php (method simpanProfil)
  - Route URL (POST)  : /alumni/profile
-->
<script setup>
import { Head, router, useForm, usePage, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Swal from 'sweetalert2';

import FormPribadi from '@/components/AlumniProfil/FormPribadi.vue';
import FormAkademik from '@/components/AlumniProfil/FormAkademik.vue';
import FormKarier from '@/components/AlumniProfil/FormKarier.vue';
import Stepper from '@/components/AlumniProfil/Stepper.vue';
import Navbar from '../Components/Navbar.vue';

/**
 * ====================================================================
 * MENERIMA DATA (PROPS) DARI CONTROLLER (ProfilController.php)
 * ====================================================================
 * - alumniData : Konteks minimum alumni yang dibutuhkan FormKarier
 * - formData   : Objek data awal gabungan (data akademik, pribadi, domisili, pekerjaan)
 * - provinces  : Array master data provinsi dari tabel 'provinces'
 * - kabupatens : Array master data kabupaten dari tabel 'kabupatens'
 * - companies  : Array master data perusahaan dari tabel 'companies'
 */
const props = defineProps({
    alumniData: Object,
    formData: Object,
    provinces: Array,
    kabupatens: Array,
    negaras: Array,
    companies: Array,
    refOptions: Object,
    profileQuestions: Array,
    evaluasiAtasan: Object,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// Storage key untuk mengingat tab aktif saat refresh
const PROFILE_TAB_STORAGE_KEY = 'tracerstudy_alumni_profile_tab';
const PROFILE_TAB_IDS = ['pribadi', 'karier', 'akademik'];

const normalizeProfileTab = (tabName) => {
    return PROFILE_TAB_IDS.includes(tabName) ? tabName : 'pribadi';
};

// Inisialisasi activeTab dari URL param atau localStorage
const getInitialTab = () => {
    if (typeof window !== 'undefined') {
        // Hapus cache draft profil lama jika pernah tersimpan di browser
        localStorage.removeItem('tracerstudy_alumni_profile_draft');

        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam && PROFILE_TAB_IDS.includes(tabParam)) {
            return tabParam;
        }
        const cached = localStorage.getItem(PROFILE_TAB_STORAGE_KEY);
        if (cached && PROFILE_TAB_IDS.includes(cached)) {
            return cached;
        }
    }
    return 'pribadi';
};

/**
 * ====================================================================
 * MEMBUAT OBJEK FORM DENGAN useForm DARI INERTIA
 * ====================================================================
 * Di sinilah 'form' lahir!
 * Nilai awalnya diambil dari props.formData yang dikirim oleh ProfilController.php.
 * 
 * Objek 'form' inilah yang nanti dioper ke 3 komponen anak:
 * <FormPribadi  :form="form" />
 * <FormAkademik :form="form" />
 * <FormKarier   :form="form" />
 */
const form = useForm(JSON.parse(JSON.stringify(props.formData || {}))).transform((data) => {
    if (typeof File !== 'undefined' && data.foto instanceof File) {
        return data;
    }

    const { foto, ...dataWithoutExistingPhoto } = data;

    return dataWithoutExistingPhoto;
});

const isOtherProfileOption = (value) => {
    const normalized = String(value || '').trim().toLowerCase();

    return normalized.includes('lainnya') || normalized.includes('tuliskan') || normalized.includes('...');
};

const hasDynamicProfileAnswer = (question) => {
    const selected = form.profile_answers?.[question.id];
    if (!selected || !String(selected).trim()) {
        return false;
    }

    if (!isOtherProfileOption(selected)) {
        return true;
    }

    return Boolean(form.profile_answer_custom?.[question.id]?.trim());
};

// Sinkronkan data form jika props berubah dari backend (misal setelah simpan)
watch(() => props.formData, (newData) => {
    if (newData) {
        Object.assign(form, JSON.parse(JSON.stringify(newData)));
    }
}, { deep: true });

// State untuk tab yang sedang aktif ('pribadi', 'akademik', 'karier')
const activeTab = ref(normalizeProfileTab(getInitialTab()));

const tabs = [
    { id: 'pribadi', name: 'Identitas & Alamat', subtitle: 'Biodata & Kontak', step: '1' },
    { id: 'karier', name: 'Karier & Jejaring', subtitle: 'Pekerjaan & Medsos', step: '2' },
    { id: 'akademik', name: 'Akademik', subtitle: 'Riwayat Studi', step: '3' },
];

// Helper validasi email
const isValidEmail = (val) => {
    if (!val) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(val).trim());
};

// Helper validasi telepon
const isValidPhone = (val) => {
    if (!val) return false;
    const clean = String(val).replace(/[^0-9+]/g, '');
    return clean.length >= 10 && clean.length <= 15;
};

// Helper validasi URL
const isValidUrl = (val) => {
    if (!val) return false;
    return /^https?:\/\/.+/i.test(String(val).trim());
};

// Evaluasi kelengkapan data tiap tab secara realtime
const tabCompleteness = computed(() => {
    // 1. Tab Pribadi
    const missingPribadi = [];
    if (!form.nama || !String(form.nama).trim()) missingPribadi.push('Nama Lengkap');
    if (!form.tempat_lahir || !String(form.tempat_lahir).trim()) missingPribadi.push('Tempat Lahir');
    if (!form.tanggal_lahir) missingPribadi.push('Tanggal Lahir');
    if (!form.jenis_kelamin) missingPribadi.push('Jenis Kelamin');
    if (!form.agama) missingPribadi.push('Agama');
    if (!form.golongan_darah) missingPribadi.push('Golongan Darah');
    if (!form.warga_negara || !String(form.warga_negara).trim()) missingPribadi.push('Kewarganegaraan');
    if (!form.nomor_telepon || !isValidPhone(form.nomor_telepon)) missingPribadi.push('Nomor Telepon / WhatsApp');
    if (!form.email_pribadi || !isValidEmail(form.email_pribadi)) missingPribadi.push('Email Pribadi');
    if (!form.alamat_saat_ini || !String(form.alamat_saat_ini).trim()) missingPribadi.push('Alamat Domisili');
    if (!form.provinsi_id && !form.propinsi_id) missingPribadi.push('Provinsi Domisili');
    if (!form.kabupaten_id) missingPribadi.push('Kabupaten/Kota Domisili');
    if (!form.kecamatan || !String(form.kecamatan).trim()) missingPribadi.push('Kecamatan Domisili');
    if (!form.kelurahan || !String(form.kelurahan).trim()) missingPribadi.push('Kelurahan/Desa Domisili');
    if (!form.kode_pos || !String(form.kode_pos).trim()) missingPribadi.push('Kode Pos Domisili');

    // 2. Tab Karier
    const missingKarier = [];
    if (!form.linkedin_url || !isValidUrl(form.linkedin_url)) missingKarier.push('LinkedIn URL');
    if (!form.linkedin_username || !String(form.linkedin_username).trim()) missingKarier.push('LinkedIn Username');
    if (!form.instagram_url || !isValidUrl(form.instagram_url)) missingKarier.push('Instagram URL');
    if (!form.facebook_url || !isValidUrl(form.facebook_url)) missingKarier.push('Facebook URL');
    const hasSkills = form.skills && String(form.skills).trim();
    if (!hasSkills) missingKarier.push('Keahlian (Skills)');

    const hasExperience = form.experience && String(form.experience).trim();
    if (!hasExperience) missingKarier.push('Pengalaman Kerja (Experience)');

    const kategori = (form.kategori_pekerjaan || '').trim();
    if (!kategori) {
        missingKarier.push('Status Pekerjaan / Aktivitas Utama');
    } else if (kategori === 'Pekerja') {
        if (!form.nama_perusahaan || !String(form.nama_perusahaan).trim()) missingKarier.push('Nama Perusahaan / Instansi');
        if (!form.perusahaan_bentuk && !form.company_bentuk) missingKarier.push('Bentuk Perusahaan / Institusi');
        if ((!form.perusahaan_alamat || !String(form.perusahaan_alamat).trim()) && (!form.company_alamat || !String(form.company_alamat).trim())) missingKarier.push('Alamat Perusahaan');
        if (!form.perusahaan_skala && !form.company_skala) missingKarier.push('Skala Perusahaan');
        if (!form.perusahaan_jenis_perusahaan && !form.company_jenis_perusahaan) missingKarier.push('Jenis Perusahaan');
        const hasKodePosKantor = (form.company_kode_pos && String(form.company_kode_pos).trim()) || (form.perusahaan_kode_pos && String(form.perusahaan_kode_pos).trim()) || (form.zipcode && String(form.zipcode).trim());
        if (!hasKodePosKantor) missingKarier.push('Kode Pos Perusahaan');
        if (!form.gaji || Number(form.gaji) < 1000) missingKarier.push('Rata-rata Pendapatan / Gaji (Min Rp 1.000)');
        if (!form.posisi_jabatan || !String(form.posisi_jabatan).trim()) missingKarier.push('Posisi Jabatan');
        if (!form.nama_atasan || !String(form.nama_atasan).trim()) missingKarier.push('Nama Atasan Langsung');
        if (!form.email_atasan || !isValidEmail(form.email_atasan)) missingKarier.push('Email Atasan');
        if (!form.telepon_atasan || !isValidPhone(form.telepon_atasan)) missingKarier.push('Nomor Telepon Atasan');
    } else if (kategori === 'Wiraswasta') {
        if (!form.nama_perusahaan || !String(form.nama_perusahaan).trim()) missingKarier.push('Nama Usaha / Bisnis');
        if (!form.posisi_wiraswasta) missingKarier.push('Posisi / Jabatan dalam Usaha');
        if ((!form.perusahaan_alamat || !String(form.perusahaan_alamat).trim()) && (!form.company_alamat || !String(form.company_alamat).trim())) missingKarier.push('Alamat Usaha');
        if (!form.perusahaan_skala && !form.company_skala) missingKarier.push('Skala Usaha');
        if (!form.perusahaan_jenis_perusahaan && !form.company_jenis_perusahaan) missingKarier.push('Jenis Usaha');
        const hasKodePosUsaha = (form.company_kode_pos && String(form.company_kode_pos).trim()) || (form.perusahaan_kode_pos && String(form.perusahaan_kode_pos).trim()) || (form.zipcode && String(form.zipcode).trim());
        if (!hasKodePosUsaha) missingKarier.push('Kode Pos Lokasi Usaha');
        if (!form.gaji || Number(form.gaji) < 1000) missingKarier.push('Rata-rata Pendapatan / Gaji (Min Rp 1.000)');
        if (!form.nama_atasan || !String(form.nama_atasan).trim()) missingKarier.push('Nama Atasan / Pemilik Usaha');
        if (!form.email_atasan || !isValidEmail(form.email_atasan)) missingKarier.push('Email Atasan / Pemilik Usaha');
        if (!form.telepon_atasan || !isValidPhone(form.telepon_atasan)) missingKarier.push('Nomor Telepon Atasan / Pemilik Usaha');
    }

    for (const question of props.profileQuestions || []) {
        if (question.wajib && !hasDynamicProfileAnswer(question)) {
            missingKarier.push(question.subpertanyaan);
        }
    }

    // Validasi studi lanjut jika alumni mengaktifkan studi lanjut
    if (form.pendidikan_tingkat || form.perguruan_tinggi || form.pendidikan_prodi) {
        if (!form.pendidikan_tingkat || !String(form.pendidikan_tingkat).trim()) missingKarier.push('Jenjang Pendidikan');
        if (!form.perguruan_tinggi || !String(form.perguruan_tinggi).trim()) missingKarier.push('Perguruan Tinggi Studi Lanjut');
        if (!form.pendidikan_prodi || !String(form.pendidikan_prodi).trim()) missingKarier.push('Program Studi Lanjut');
    }

    // 3. Tab Akademik
    const missingAkademik = [];
    if (!form.nim) missingAkademik.push('NIM');

    return {
        pribadi: {
            isComplete: missingPribadi.length === 0,
            missing: missingPribadi,
        },
        karier: {
            isComplete: missingKarier.length === 0,
            missing: missingKarier,
        },
        akademik: {
            isComplete: missingAkademik.length === 0,
            missing: missingAkademik,
        },
    };
});

const isTabCompleted = (tabId) => {
    return Boolean(tabCompleteness.value[tabId]?.isComplete);
};

const currentTabIndex = computed(() => tabs.findIndex(t => t.id === activeTab.value));
const prevTab = computed(() => currentTabIndex.value > 0 ? tabs[currentTabIndex.value - 1] : null);
const nextTab = computed(() => currentTabIndex.value < tabs.length - 1 ? tabs[currentTabIndex.value + 1] : null);

const switchTab = (tabName) => {
    if (!PROFILE_TAB_IDS.includes(tabName)) {
        return;
    }

    activeTab.value = tabName;
    if (typeof window !== 'undefined') {
        const container = document.getElementById('profile-form-container');
        if (container) {
            const topOffset = -90;
            const targetY = container.getBoundingClientRect().top + window.pageYOffset + topOffset;
            window.scrollTo({ top: targetY, behavior: 'smooth' });
        }
    }
};

// Sinkronkan activeTab ke URL dan localStorage agar tidak reset saat refresh
watch(activeTab, (newTab) => {
    const normalizedTab = normalizeProfileTab(newTab);

    if (normalizedTab !== newTab) {
        activeTab.value = normalizedTab;
        return;
    }

    if (typeof window !== 'undefined') {
        localStorage.setItem(PROFILE_TAB_STORAGE_KEY, newTab);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', newTab);
        window.history.replaceState({}, '', url.toString());
    }
});

const scrollToTop = () => {
    if (typeof window !== 'undefined') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const scrollToBottom = () => {
    if (typeof window !== 'undefined') {
        window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
    }
};

/**
 * ====================================================================
 * FUNGSI SUBMIT (SIMPAN SELURUH PROFIL KE BACKEND)
 * Jika nextTabId diberikan, setelah simpan berhasil langsung pindah tab.
 * ====================================================================
 */
const submit = (nextTabId = null, redirectTo = null) => {
    // Validasi nominal gaji sebelum submit
    if (form.gaji && Number(form.gaji) > 0 && Number(form.gaji) < 1000) {
        activeTab.value = 'karier';
        Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            icon: 'warning',
            title: 'Nominal pendapatan minimal Rp 1.000 (ribuan)',
        });
        return;
    }

    form.post('/alumni/profile', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                icon: 'success',
                title: 'Data profil berhasil disimpan',
            });
            form.clearErrors();

            if (redirectTo) {
                router.visit(redirectTo);

                return;
            }

            const targetTab = nextTabId && PROFILE_TAB_IDS.includes(nextTabId)
                ? nextTabId
                : normalizeProfileTab(activeTab.value);

            switchTab(targetTab);
        },
        onError: (errors) => {
            const firstErr = Object.values(errors)[0] || 'Ada data yang belum lengkap atau salah';
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                icon: 'error',
                title: firstErr,
            });
        }
    });
};
</script>

<template>
    <Head title="Profil Alumni - Tracer Study" />

    <div class="min-h-screen bg-slate-50 pb-24 font-sans">
        <!-- Navigasi Utama Terpadu Alumni -->
        <Navbar :user="user" />

        <!-- Header Halaman Profil Alumni Bersih & Formal (Selaras Navbar max-w-7xl) -->
        <header class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 sm:pt-10 pb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    Profil & Riwayat Karier
                </h1>
                <p class="text-sm sm:text-base text-slate-500 mt-1.5">
                    Pemutakhiran data domisili, kontak, serta riwayat pekerjaan alumni.
                </p>
            </div>
        </header>

        <main id="profile-form-container" class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-20">
            <form @submit.prevent="submit(null, currentTabIndex === tabs.length - 1 ? '/alumni/kuesioner' : null)" autocomplete="off" class="bg-white rounded-2xl shadow-xs border border-gray-200 relative transition-all duration-300">
                
                <!-- Sticky Stepper Navigation (Bersih, Formal & Konsisten) -->
                <div class="sticky top-[72px] lg:top-20 z-40">
                    <Stepper 
                        :tabs="tabs" 
                        :active-tab="activeTab" 
                        :is-tab-completed="isTabCompleted" 
                        @select-tab="switchTab" 
                    />
                </div>

                <!-- ============================================================= -->
                <!-- KONTEN TAB FORM PROFIL -->
                <!-- ============================================================= -->
                <div class="p-6 sm:p-8 md:p-12 min-h-[500px]">
                    <!-- Tab 1: Identitas & Alamat Pribadi -->
                    <div v-show="activeTab === 'pribadi'">
                        <FormPribadi :form="form" :provinces="provinces" :kabupatens="kabupatens" :negaras="negaras" />
                    </div>

                    <!-- Tab 2: Karier, Pekerjaan & Data Atasan -->
                    <div v-show="activeTab === 'karier'">
                        <FormKarier 
                            :form="form" 
                            :provinces="provinces" 
                            :kabupatens="kabupatens" 
                            :negaras="negaras" 
                            :companies="companies" 
                            :alumniData="alumniData" 
                            :refOptions="refOptions" 
                            :profileQuestions="profileQuestions"
                            :evaluasiAtasan="evaluasiAtasan" 
                        />
                    </div>

                    <!-- Tab 3: Data Akademik & Yudisium -->
                    <div v-show="activeTab === 'akademik'">
                        <FormAkademik :form="form" />
                    </div>

                </div>

                <!-- Bottom Sticky / Action Bar: Navigasi Antar Tab & Tombol Simpan di Langkah Terakhir -->
                <div class="px-6 sm:px-8 md:px-12 py-5 bg-gray-50/90 backdrop-blur-md rounded-b-[2rem] flex flex-col md:flex-row justify-between items-center gap-4 border-t border-gray-100">
                    <!-- Left: Navigasi Sebelumnya -->
                    <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-start">
                        <button 
                            v-if="prevTab" 
                            type="button" 
                            @click="switchTab(prevTab.id)"
                            class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold transition-all flex items-center gap-1.5 shadow-2xs cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                            <span>Sebelumnya: {{ prevTab.name }}</span>
                        </button>
                        <span v-else class="text-xs text-gray-400 font-medium">Langkah 1 dari {{ tabs.length }}</span>
                    </div>
                    
                    <!-- Right: Alur Lanjut Antar Langkah (Tanpa Duplikasi Tombol Simpan) -->
                    <div class="flex items-center gap-3 w-full md:w-auto justify-end">
                        <!-- Tombol Lanjut ke Langkah Berikutnya -->
                        <button 
                            v-if="nextTab" 
                            type="button" 
                            @click="switchTab(nextTab.id)"
                            class="px-6 py-3 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs sm:text-sm font-extrabold rounded-xl shadow-xs transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span>Lanjut: {{ nextTab.name }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>

                        <!-- Tombol Utama di Langkah Terakhir: Simpan Seluruh Profil -->
                        <button 
                            v-else
                            type="submit" 
                            :disabled="form.processing" 
                            class="px-8 py-3 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs sm:text-sm font-extrabold rounded-xl shadow-xs transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>{{ form.processing ? 'Menyimpan...' : 'Simpan Seluruh Profil' }}</span>
                        </button>
                    </div>
                </div>
            </form>
        </main>

        <!-- Popup Melayang Indikator Perubahan Belum Disimpan (Non-Intrusive Floating Bar) -->
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="transform translate-y-6 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform translate-y-6 opacity-0"
        >
            <div
                v-if="form.isDirty"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 w-[calc(100%-5rem)] max-w-xl pointer-events-none"
            >
                <div class="pointer-events-auto flex items-center justify-between gap-4 rounded-2xl border border-[#0D542B]/30 bg-white px-5 py-3.5 text-gray-900 shadow-lg ring-1 ring-[#0D542B]/10">
                    <div class="min-w-0 flex-1 flex items-center gap-3">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#0D542B] shrink-0"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-bold text-gray-900 truncate">
                                Perubahan Belum Disimpan
                            </p>
                            <p class="text-[11px] text-gray-500 truncate">
                                Silakan lanjutkan pengisian hingga selesai, lalu simpan perubahan.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="submit()"
                        :disabled="form.processing"
                        class="shrink-0 rounded-xl bg-[#0D542B] hover:bg-[#093d1f] px-4 py-2 text-xs font-bold text-white shadow-xs transition-all cursor-pointer disabled:opacity-50"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Sekarang' }}
                    </button>
                </div>
            </div>
        </transition>

        <!-- Tombol Gulir Cepat (Caping Ke Atas / Ke Bawah) -->
        <div class="fixed bottom-6 right-6 z-50 flex flex-col gap-2">
            <button 
                type="button" 
                @click="scrollToTop"
                class="w-10 h-10 rounded-full bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 shadow-md flex items-center justify-center transition-all hover:scale-105 active:scale-95 cursor-pointer"
                title="Gulir ke paling atas"
                aria-label="Gulir ke paling atas"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                </svg>
            </button>
            <button 
                type="button" 
                @click="scrollToBottom"
                class="w-10 h-10 rounded-full bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 shadow-md flex items-center justify-center transition-all hover:scale-105 active:scale-95 cursor-pointer"
                title="Gulir ke paling bawah"
                aria-label="Gulir ke paling bawah"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </div>
    </div>
</template>
