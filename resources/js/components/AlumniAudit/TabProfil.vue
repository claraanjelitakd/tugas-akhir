<!--
  TabProfil.vue
  Komponen Bersama: Tab Profil & Biodata Alumni (Admin View)
  Lokasi: resources/js/Components/AlumniAudit/TabProfil.vue
  
  Digunakan oleh seluruh peran:
  - Super Administrator
  - Admin Fakultas
  - Admin Program Studi
  - Biro 3 Kemahasiswaan & Alumni (readonly)
  
  Mengintegrasikan 3 form profil dari @/Components/AlumniProfil/ serta tombol floating scroll cepat.
-->
<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

// Import komponen form profil dari folder netral bersama
import FormPribadi from '@/Components/AlumniProfil/FormPribadi.vue';
import FormAkademik from '@/Components/AlumniProfil/FormAkademik.vue';
import FormKarier from '@/Components/AlumniProfil/FormKarier.vue';

/**
 * Definisi properti komponen tab profil alumni.
 */
const props = defineProps({
    /** Data objek biodata alumni dari server */
    alumni: {
        type: Object,
        required: true,
    },
    /** Objek awal form data profil */
    formData: {
        type: Object,
        default: () => ({}),
    },
    /** Daftar opsi provinsi untuk wilayah */
    provinces: {
        type: Array,
        default: () => [],
    },
    /** Daftar opsi kabupaten untuk wilayah */
    kabupatens: {
        type: Array,
        default: () => [],
    },
    /** Daftar opsi negara */
    negaras: {
        type: Array,
        default: () => [],
    },
    /** Daftar database instansi/perusahaan */
    companies: {
        type: Array,
        default: () => [],
    },
    /** Opsi referensi kuesioner & karier */
    refOptions: {
        type: Object,
        default: () => ({}),
    },
    /** Data evaluasi atasan terkait */
    evaluasiAtasan: {
        type: Object,
        default: null,
    },
    /** Endpoint rute POST penyimpanan data profil */
    updateRoute: {
        type: String,
        default: '',
    },
    /** Mode murni lihat saja (misal untuk Biro 3) */
    readonly: {
        type: Boolean,
        default: false,
    },
});

/** Form state reactive Inertia */
const form = useForm({ ...(props.formData || {}) }).transform((data) => {
    if (typeof File !== 'undefined' && data.foto instanceof File) {
        return data;
    }

    const { foto, ...dataWithoutExistingPhoto } = data;

    return dataWithoutExistingPhoto;
});

/** Watcher pembaruan props.formData dari server */
watch(() => props.formData, (newData) => {
    if (newData) {
        Object.assign(form, JSON.parse(JSON.stringify(newData)));
    }
}, { deep: true });

/** Tab sub-profil yang sedang aktif */
const activeProfileTab = ref('pribadi');

/** Konfigurasi daftar sub-tab profil */
const profileTabs = [
    { id: 'pribadi', name: '1. Identitas & Alamat', subtitle: 'Biodata & Kontak' },
    { id: 'karier', name: '2. Karier & Jejaring', subtitle: 'Pekerjaan, Medsos & Atasan' },
    { id: 'akademik', name: '3. Akademik', subtitle: 'Riwayat Studi & IPK' },
];

/**
 * Kirim formulir profil alumni ke endpoint server.
 */
const submitForm = () => {
    if (props.readonly || !props.updateRoute) return;
    form.post(props.updateRoute, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Data Tersimpan!',
                text: 'Profil alumni berhasil diperbarui.',
                confirmButtonColor: '#0D542B',
                timer: 2000,
            });
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0] || 'Terjadi kesalahan saat menyimpan data.';
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan',
                text: firstError,
                confirmButtonColor: '#0D542B',
            });
        },
    });
};

/**
 * Handle tombol enter keyboard untuk menyimpan perubahan secara silent.
 */
const handleKeyDown = (e) => {
    if (!props.readonly && props.updateRoute && e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
        submitForm();
    }
};

/**
 * Fungsi navigasi floating scroll ke posisi teratas halaman.
 */
const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

/**
 * Fungsi navigasi floating scroll ke posisi terbawah halaman.
 */
const scrollToBottom = () => {
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
};
</script>

<template>
    <div class="space-y-4">
        <!-- Sub-Navigasi Tab Profil Formal Ala Master Data -->
        <div class="bg-white p-3 sm:p-4 rounded-xl border border-gray-300 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex flex-wrap gap-1 p-1 bg-gray-100 border border-gray-200 rounded-lg text-xs">
                <button 
                    v-for="t in profileTabs"
                    :key="t.id"
                    type="button"
                    @click="activeProfileTab = t.id"
                    class="px-3.5 py-1.5 rounded-md font-semibold text-xs transition-colors cursor-pointer"
                    :class="activeProfileTab === t.id ? 'bg-[#0D542B] text-white shadow-2xs' : 'text-gray-600 hover:text-gray-900 hover:bg-white'"
                >
                    {{ t.name }}
                </button>
            </div>

            <button 
                v-if="!readonly && updateRoute"
                type="button" 
                @click="submitForm"
                :disabled="form.processing"
                class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg transition-all shadow-2xs flex items-center gap-1.5 self-start sm:self-auto cursor-pointer disabled:opacity-50"
            >
                <svg v-if="!form.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <svg v-else class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span>{{ form.processing ? 'Menyimpan...' : 'Simpan Profil Alumni' }}</span>
            </button>
        </div>

        <!-- Notifikasi Perubahan Belum Disimpan -->
        <div v-if="form.isDirty" class="p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-center justify-between text-xs text-amber-900">
            <span>Ada perubahan data yang belum disimpan.</span>
            <span class="text-[11px] font-semibold text-amber-800 ml-2 shrink-0">
                Klik tombol Simpan Profil Alumni di bagian atas.
            </span>
        </div>

        <!-- Formulir Profil yang Mendukung Save Enter Secara Silent -->
        <form @submit.prevent="submitForm" @keydown="handleKeyDown">
            <!-- 1. Identitas & Alamat Resmi -->
            <div v-show="activeProfileTab === 'pribadi'">
                <FormPribadi 
                    :form="form" 
                    :provinces="provinces" 
                    :kabupatens="kabupatens" 
                    :negaras="negaras" 
                />
            </div>

            <!-- 2. Riwayat Karier & Pekerjaan -->
            <div v-show="activeProfileTab === 'karier'">
                <FormKarier 
                    :form="form" 
                    :provinces="provinces" 
                    :kabupatens="kabupatens" 
                    :negaras="negaras" 
                    :companies="companies" 
                    :alumniData="alumni" 
                    :refOptions="refOptions" 
                    :evaluasiAtasan="evaluasiAtasan" 
                />
            </div>

            <!-- 3. Data Akademik & Yudisium -->
            <div v-show="activeProfileTab === 'akademik'">
                <FormAkademik :form="form" />
            </div>

        </form>

        <!-- Floating Quick Scroll Navigation Buttons (Scroll to Top & Scroll to Bottom) -->
        <div class="fixed bottom-6 right-6 z-40 flex flex-col gap-2">
            <button 
                type="button" 
                @click="scrollToTop"
                class="w-10 h-10 rounded-full bg-white text-[#0D542B] shadow-md border border-emerald-100 flex items-center justify-center hover:bg-emerald-50 hover:shadow-lg transition-all cursor-pointer"
                title="Scroll ke atas"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                </svg>
            </button>
            <button 
                type="button" 
                @click="scrollToBottom"
                class="w-10 h-10 rounded-full bg-white text-[#0D542B] shadow-md border border-emerald-100 flex items-center justify-center hover:bg-emerald-50 hover:shadow-lg transition-all cursor-pointer"
                title="Scroll ke bawah"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
            </button>
        </div>
    </div>
</template>
