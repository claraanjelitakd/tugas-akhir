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
import { ref, computed, watch, onMounted, nextTick } from 'vue';
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

/** Kunci baseline default form setelah render agar tidak memunculkan indikator dirty palsu */
onMounted(() => {
    nextTick(() => {
        form.defaults();
    });
});

/** Watcher pembaruan props.formData dari server (reset dirty state agar tidak memunculkan notifikasi palsu) */
watch(() => props.formData, (newData) => {
    if (newData) {
        Object.assign(form, JSON.parse(JSON.stringify(newData)));
        nextTick(() => {
            form.defaults();
        });
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

const currentTabIndex = computed(() => profileTabs.findIndex(t => t.id === activeProfileTab.value));
const prevTab = computed(() => currentTabIndex.value > 0 ? profileTabs[currentTabIndex.value - 1] : null);
const nextTab = computed(() => currentTabIndex.value < profileTabs.length - 1 ? profileTabs[currentTabIndex.value + 1] : null);

const goToTab = (tabId) => {
    activeProfileTab.value = tabId;
    window.scrollTo({ top: 120, behavior: 'smooth' });
};

/**
 * Kirim formulir profil alumni ke endpoint server.
 */
const submitForm = () => {
    if (props.readonly || !props.updateRoute) return;
    form.post(props.updateRoute, {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults();
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
        <!-- Sub-Navigasi Tab Profil Formal Ala /alumni (Tanpa Duplikasi Tombol Simpan di Header) -->
        <div class="bg-white p-3 sm:p-4 rounded-xl border border-gray-200 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
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
            <span class="text-xs text-gray-500 font-medium hidden sm:inline">
                {{ profileTabs.find(t => t.id === activeProfileTab)?.subtitle || '' }}
            </span>
        </div>

        <!-- Formulir Profil yang Mendukung Save Enter Secara Silent -->
        <form @submit.prevent="submitForm" @keydown="handleKeyDown" class="space-y-4">
            <!-- 1. Identitas & Alamat Resmi -->
            <!-- Fieldset mengunci seluruh input profil ketika detail dibuka lintas fakultas. -->
            <fieldset
                v-show="activeProfileTab === 'pribadi'"
                :disabled="readonly"
                :class="readonly ? 'pointer-events-none opacity-65 grayscale' : ''"
            >
                <FormPribadi 
                    :form="form" 
                    :provinces="provinces" 
                    :kabupatens="kabupatens" 
                    :negaras="negaras" 
                />
            </fieldset>

            <!-- 2. Riwayat Karier & Pekerjaan -->
            <fieldset
                v-show="activeProfileTab === 'karier'"
                :disabled="readonly"
                :class="readonly ? 'pointer-events-none opacity-65 grayscale' : ''"
            >
                <FormKarier 
                    :form="form" 
                    :provinces="provinces" 
                    :kabupatens="kabupatens" 
                    :negaras="negaras" 
                    :companies="companies" 
                    :alumniData="alumni" 
                    :refOptions="refOptions" 
                    :evaluasiAtasan="evaluasiAtasan" 
                    :readonly="readonly"
                />
            </fieldset>

            <!-- 3. Data Akademik & Yudisium -->
            <fieldset
                v-show="activeProfileTab === 'akademik'"
                :disabled="readonly"
                :class="readonly ? 'pointer-events-none opacity-65 grayscale' : ''"
            >
                <FormAkademik :form="form" />
            </fieldset>

            <!-- Navigasi Bawah Formulir Profil Ala /alumni -->
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-2xs flex items-center justify-between gap-3">
                <button
                    v-if="prevTab"
                    type="button"
                    @click="goToTab(prevTab.id)"
                    class="px-4 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg transition-colors cursor-pointer flex items-center gap-1.5"
                >
                    &larr; Sebelumnya: {{ prevTab.name }}
                </button>
                <div v-else class="text-xs text-gray-400 font-medium">Langkah 1 dari {{ profileTabs.length }}</div>

                <div class="flex items-center gap-2">
                    <!-- Tombol Lanjut ke Langkah Berikutnya (Jika Belum di Tab Terakhir) -->
                    <button
                        v-if="nextTab"
                        type="button"
                        @click="goToTab(nextTab.id)"
                        class="px-5 py-2.5 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-semibold rounded-lg transition-colors cursor-pointer flex items-center gap-1.5 shadow-2xs"
                    >
                        <span>Lanjut: {{ nextTab.name }}</span>
                        <span>&rarr;</span>
                    </button>

                    <!-- Tombol Utama di Langkah Terakhir: Simpan Profil Alumni -->
                    <button
                        v-else-if="!readonly && updateRoute"
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2.5 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg transition-all shadow-2xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
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
            </div>
        </form>

        <!-- Floating Action Bar Indikator Perubahan Belum Disimpan Ala /alumni -->
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="transform translate-y-6 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform translate-y-6 opacity-0"
        >
            <div
                v-if="form.isDirty && !readonly"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 w-[calc(100%-5rem)] max-w-xl pointer-events-none"
            >
                <div class="pointer-events-auto flex items-center justify-between gap-4 rounded-2xl border border-[#0D542B]/30 bg-white px-5 py-3.5 text-gray-900 shadow-lg ring-1 ring-[#0D542B]/10">
                    <div class="min-w-0 flex-1 flex items-center gap-3">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#0D542B] shrink-0"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-bold text-gray-900 truncate">
                                Perubahan Profil Belum Disimpan
                            </p>
                            <p class="text-[11px] text-gray-500 truncate">
                                Ada pengeditan pada form profil alumni ini.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="submitForm"
                        :disabled="form.processing"
                        class="shrink-0 rounded-xl bg-[#0D542B] hover:bg-[#093d1f] px-4 py-2 text-xs font-bold text-white shadow-xs transition-all cursor-pointer disabled:opacity-50"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Sekarang' }}
                    </button>
                </div>
            </div>
        </transition>

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
