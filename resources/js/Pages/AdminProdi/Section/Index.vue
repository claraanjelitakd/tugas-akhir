<!--
  Halaman: Kelola Bagian (Section) Kuesioner Program Studi
  File: resources/js/Pages/AdminProdi/Section/Index.vue

  Desain Standar Kelola Kuesioner UKDW (Identik dengan Super Admin):
  - Sidebar Terpadu Admin Program Studi
  - Header Bersih, Flat & Profesional
  - Tabel Lapang dengan Tombol Reorder Cepat (Naik/Turun)
  - Modal SweetAlert2 untuk Tambah, Edit, Detail, dan Hapus
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

import Sidebar from '../Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    prodi: Object,
    sections: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            total_sections: 0,
            total_questions: 0,
        }),
    },
});

const searchQuery = ref('');
const isReordering = ref(false);

const filteredSections = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.sections;
    }
    const query = searchQuery.value.toLowerCase();
    return props.sections.filter((sec) => {
        const matchTitle = sec.title?.toLowerCase().includes(query);
        const matchDesc = sec.description?.toLowerCase().includes(query);
        const matchOrder = sec.order?.toString().includes(query);
        return matchTitle || matchDesc || matchOrder;
    });
});

const totalQuestionsCount = computed(() => {
    return props.stats?.total_questions ?? props.sections.reduce((sum, sec) => sum + (sec.questions_count ?? (sec.questions?.length ?? 0)), 0);
});

// ========================================================
// STATE REAKTIF UNTUK MODAL (VUE 3)
// ========================================================
// Catatan Belajar: 'ref' adalah fungsi dasar Vue 3 Composition API untuk membuat state reaktif.
// Saat nilai properti 'value' diubah di dalam JavaScript, Vue secara otomatis memperbarui tampilan HTML (DOM).

// 1. State Modal Form Bagian (Tambah & Sunting)
// Status apakah modal form terbuka (true) atau tertutup (false)
const isSectionModalOpen = ref(false);
// Menandai apakah sedang dalam mode edit data yang sudah ada
const isEditingSection = ref(false);
// Menyimpan ID bagian kuesioner yang sedang disunting
const editingSectionId = ref(null);
// Objek penampung inputan pengguna untuk form bagian
const sectionFormData = ref({
    title: '',
    description: '',
    order: 1,
});
// Status indikator proses kirim data sedang berlangsung
const isSubmittingSection = ref(false);
// Objek penampung pesan error validasi frontend maupun backend
const sectionFormErrors = ref({});

// 2. State Modal Detail Bagian
// Menentukan apakah modal tampilan detail sedang terbuka
const isDetailModalOpen = ref(false);
// Menyimpan data baris bagian kuesioner yang dipilih pengguna
const selectedSectionDetail = ref(null);

// ========================================================
// FUNGSI KONTROL: MODAL FORM BAGIAN (SECTION)
// ========================================================

/**
 * Membuka modal form bagian kuesioner.
 * Jika parameter sectionToEdit diisi objek, modal akan beralih ke mode Edit.
 * Jika tidak ada parameter, modal terbuka sebagai form Tambah Baru.
 */
const openSectionModal = (sectionToEdit = null) => {
    sectionFormErrors.value = {};

    if (sectionToEdit) {
        // Mode Sunting: Ambil nilai awal dari data yang diklik pengguna
        isEditingSection.value = true;
        editingSectionId.value = sectionToEdit.id;
        sectionFormData.value = {
            title: sectionToEdit.title || '',
            description: sectionToEdit.description || '',
            order: sectionToEdit.order ?? (props.sections.length + 1),
        };
    } else {
        // Mode Tambah: Buat inputan bersih dengan nomor urut selanjutnya
        isEditingSection.value = false;
        editingSectionId.value = null;
        sectionFormData.value = {
            title: '',
            description: '',
            order: props.sections.length + 1,
        };
    }

    isSectionModalOpen.value = true;
};

/**
 * Menutup modal form dan membersihkan error
 */
const closeSectionModal = () => {
    isSectionModalOpen.value = false;
    isSubmittingSection.value = false;
    sectionFormErrors.value = {};
};

/**
 * Mengirimkan data form ke controller backend via Inertia
 */
const submitSectionForm = () => {
    sectionFormErrors.value = {};

    // Validasi judul wajib diisi
    if (!sectionFormData.value.title || !sectionFormData.value.title.trim()) {
        sectionFormErrors.value.title = 'Judul bagian kuesioner wajib diisi.';
        return;
    }

    isSubmittingSection.value = true;

    // Catatan Belajar: 'router.put' dan 'router.post' adalah method bawaan @inertiajs/vue3
    // untuk melakukan request AJAX SPA (Single Page Application) tanpa refresh halaman.
    if (isEditingSection.value) {
        router.put(`/prodi/sections/${editingSectionId.value}`, sectionFormData.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeSectionModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Bagian kuesioner berhasil diperbarui.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: (errors) => {
                sectionFormErrors.value = errors;
            },
            onFinish: () => {
                isSubmittingSection.value = false;
            },
        });
    } else {
        router.post('/prodi/sections', sectionFormData.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeSectionModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Bagian kuesioner baru berhasil ditambahkan.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: (errors) => {
                sectionFormErrors.value = errors;
            },
            onFinish: () => {
                isSubmittingSection.value = false;
            },
        });
    }
};

// ========================================================
// FUNGSI KONTROL: MODAL DETAIL BAGIAN
// ========================================================

/**
 * Membuka modal detail bagian
 */
const openDetailModal = (sec) => {
    selectedSectionDetail.value = sec;
    isDetailModalOpen.value = true;
};

/**
 * Menutup modal detail bagian
 */
const closeDetailModal = () => {
    isDetailModalOpen.value = false;
    selectedSectionDetail.value = null;
};

// ========================================================
// FUNGSI KONTROL: HAPUS & PINDAH URUTAN
// ========================================================

/**
 * Konfirmasi SweetAlert2 sebelum menghapus data bagian kuesioner
 */
const handleDeleteSection = (sec) => {
    const questionCount = sec.questions_count ?? (sec.questions?.length ?? 0);
    Swal.fire({
        title: 'Hapus Bagian Kuesioner?',
        text: `Apakah Anda yakin ingin menghapus "Bagian ${sec.order}: ${sec.title}"? ${questionCount > 0 ? `(Akan menghapus ${questionCount} butir pertanyaan di dalamnya)` : ''}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6B7280',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/prodi/sections/${sec.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: 'Bagian kuesioner telah dihapus.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
                onError: () => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus data bagian.', 'error');
                },
            });
        }
    });
};

/**
 * Memindahkan urutan tampilan posisi bagian ke atas atau ke bawah
 */
const handleMoveSection = (sec, direction) => {
    isReordering.value = true;
    router.post(
        '/prodi/sections/reorder',
        {
            id: sec.id,
            direction: direction,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isReordering.value = false;
            },
        }
    );
};
</script>

<template>
    <Head :title="`Kelola Bagian Kuesioner - ${prodi?.nama_prodi || 'Program Studi'}`" />

    <div class="min-h-screen bg-slate-50 flex font-sans">
        <!-- Sidebar Terpadu Admin Program Studi -->
        <Sidebar :user="user" :prodi="prodi" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Halaman Bersih & Flat -->
            <div class="admin-page-header bg-white border-b border-gray-200 px-6 py-5">
                <div class="w-full flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-gray-500 font-medium mb-1">
                            <Link href="/prodi/dashboard" class="hover:underline hover:text-[#0D542B]">Dashboard</Link>
                            <span>/</span>
                            <span class="text-gray-800 font-semibold">Kelola Bagian Kuesioner</span>
                        </div>
                        <h1 class="text-xl font-bold text-gray-900">
                            Kelola Bagian Kuesioner (Section)
                        </h1>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Pengaturan kelompok tahapan kuesioner dan urutan pengisian alumni Program Studi {{ prodi?.nama_prodi }}.
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <Link
                            href="/prodi/pertanyaan"
                            class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Kelola Butir Pertanyaan</span>
                        </Link>

                        <button
                            type="button"
                            @click="openSectionModal()"
                            class="px-3.5 py-1.5 bg-[#0D542B] hover:bg-[#08381c] text-white font-semibold text-xs rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer shadow-xs"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Tambah Bagian</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Konten Tabel Utama -->
            <main class="admin-page-main w-full p-6 space-y-4">
                <!-- Bilah Pencarian & Ringkasan -->
                <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3">
                        <span class="text-gray-600 font-medium">
                            Total: <strong>{{ sections.length }}</strong> Bagian (<strong>{{ totalQuestionsCount }}</strong> Butir Pertanyaan Terdaftar)
                        </span>
                    </div>

                    <!-- Kotak Pencarian -->
                    <div class="relative w-full sm:w-64">
                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Cari nama bagian..."
                            class="w-full pl-8 pr-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                    </div>
                </div>

                <!-- Tabel Data Bagian Kuesioner -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-2xs">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-3 w-16 text-center">Urut</th>
                                <th class="py-3 px-4">Judul & Deskripsi Bagian</th>
                                <th class="py-3 px-4 w-52">Program Studi</th>
                                <th class="py-3 px-3 w-32 text-center">Jumlah Soal</th>
                                <th class="py-3 px-3 w-28 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr
                                v-for="(sec, index) in filteredSections"
                                :key="sec.id"
                                class="hover:bg-slate-50/70 transition-colors"
                            >
                                <!-- Urutan -->
                                <td class="py-3 px-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <span class="font-bold text-gray-700 text-xs">{{ sec.order }}</span>
                                        <div class="flex flex-col gap-0.5">
                                            <button
                                                type="button"
                                                title="Naik"
                                                :disabled="index === 0 || isReordering"
                                                @click="handleMoveSection(sec, 'up')"
                                                class="p-0.5 text-gray-400 hover:text-gray-800 disabled:opacity-20 cursor-pointer disabled:cursor-not-allowed"
                                            >
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                title="Turun"
                                                :disabled="index === filteredSections.length - 1 || isReordering"
                                                @click="handleMoveSection(sec, 'down')"
                                                class="p-0.5 text-gray-400 hover:text-gray-800 disabled:opacity-20 cursor-pointer disabled:cursor-not-allowed"
                                            >
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </td>

                                <!-- Judul & Deskripsi -->
                                <td class="py-3 px-4">
                                    <span class="font-bold text-gray-900 text-xs block">
                                        {{ sec.title }}
                                    </span>
                                    <p v-if="sec.description" class="text-gray-500 text-[11px] mt-0.5 leading-normal">
                                        {{ sec.description }}
                                    </p>
                                    <span v-else class="text-gray-400 text-[11px] italic">
                                        Tidak ada petunjuk khusus
                                    </span>
                                </td>

                                <!-- Program Studi -->
                                <td class="py-3 px-4">
                                    <span class="inline-block px-2.5 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        {{ prodi?.nama_prodi || 'Program Studi' }}
                                    </span>
                                </td>

                                <!-- Jumlah Soal -->
                                <td class="py-3 px-3 text-center">
                                    <Link
                                        :href="`/prodi/pertanyaan?sec_id=${sec.id}`"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-emerald-50 text-emerald-800 hover:bg-emerald-100 font-semibold text-[11px] border border-emerald-200 transition-colors"
                                        title="Buka butir pertanyaan pada bagian ini"
                                    >
                                        <span>{{ sec.questions_count ?? (sec.questions?.length ?? 0) }} Soal</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </td>

                                <!-- Aksi -->
                                <td class="py-3 px-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Detail (Mata) -->
                                        <button
                                            type="button"
                                            @click="openDetailModal(sec)"
                                            title="Detail Bagian"
                                            class="p-1 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        <!-- Edit (Pensil) -->
                                        <button
                                            type="button"
                                            @click="openSectionModal(sec)"
                                            title="Sunting Bagian"
                                            class="p-1 text-gray-500 hover:text-emerald-800 hover:bg-emerald-50 rounded cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        <!-- Hapus (Sampah) -->
                                        <button
                                            type="button"
                                            @click="handleDeleteSection(sec)"
                                            title="Hapus Bagian"
                                            class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="filteredSections.length === 0">
                                <td colspan="5" class="py-10 text-center text-gray-500">
                                    <p class="font-bold text-gray-700">Tidak ada bagian kuesioner yang cocok</p>
                                    <p class="text-xs text-gray-400 mt-1">Coba gunakan kata kunci pencarian yang lain atau tambah bagian baru.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL TELEPORT VUE 3: FORM TAMBAH / SUNTING BAGIAN PRODI -->
        <!-- ======================================================== -->
        <!-- Catatan Belajar: <Teleport to="body"> merender modal ini langsung di tingkat paling atas DOM (di dalam <body>), -->
        <!-- sehingga modal tidak terpotong oleh tata letak sidebar atau tabel yang memiliki style overflow. -->
        <Teleport to="body">
            <div
                v-if="isSectionModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
                @click.self="closeSectionModal"
                @keydown.esc="closeSectionModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <!-- Header Modal -->
                    <div class="px-6 py-4 bg-white border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ isEditingSection ? 'Sunting Bagian Kuesioner' : 'Tambah Bagian Kuesioner Baru' }}
                        </h3>
                        <button
                            type="button"
                            @click="closeSectionModal"
                            class="text-gray-400 hover:text-gray-600 p-1 rounded-md hover:bg-gray-100 transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Form Isi Modal -->
                    <form @submit.prevent="submitSectionForm" class="p-6 space-y-4 text-xs">
                        <!-- Informasi Program Studi -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Program Studi</label>
                            <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 font-semibold text-xs">
                                {{ prodi?.nama_prodi || 'Program Studi Terhubung' }}
                            </div>
                        </div>

                        <!-- Judul Bagian -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Judul Bagian (Section Title) <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="sectionFormData.title"
                                placeholder="Contoh: Relevansi Kurikulum & Kompetensi Kerja"
                                class="w-full px-3 py-2 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                :class="sectionFormErrors.title ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            />
                            <p v-if="sectionFormErrors.title" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ sectionFormErrors.title }}
                            </p>
                        </div>

                        <!-- Deskripsi / Petunjuk Pengisian -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Deskripsi / Petunjuk Pengisian (Opsional)
                            </label>
                            <textarea
                                v-model="sectionFormData.description"
                                rows="3"
                                placeholder="Ketikkan petunjuk pengisian bagi responden alumni..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700 leading-relaxed"
                            ></textarea>
                        </div>

                        <!-- Nomor Urut Posisi -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Nomor Urut Posisi
                            </label>
                            <input
                                type="number"
                                v-model.number="sectionFormData.order"
                                min="1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700"
                            />
                        </div>

                        <!-- Tombol Aksi Bawah -->
                        <div class="pt-3 border-t border-gray-100 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="closeSectionModal"
                                :disabled="isSubmittingSection"
                                class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors cursor-pointer disabled:opacity-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSubmittingSection"
                                class="px-4 py-2 text-xs font-semibold text-white bg-[#0D542B] hover:bg-[#08381c] rounded-lg transition-colors cursor-pointer flex items-center gap-1.5 disabled:opacity-50 shadow-xs"
                            >
                                <svg v-if="isSubmittingSection" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>{{ isSubmittingSection ? 'Menyimpan...' : (isEditingSection ? 'Simpan Perubahan' : 'Tambah Bagian') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ======================================================== -->
        <!-- MODAL TELEPORT VUE 3: DETAIL BAGIAN KUESIONER PRODI -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="isDetailModalOpen && selectedSectionDetail"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
                @click.self="closeDetailModal"
                @keydown.esc="closeDetailModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-6 py-4 bg-white border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900">
                            Detail Bagian #{{ selectedSectionDetail.order }}
                        </h3>
                        <button
                            type="button"
                            @click="closeDetailModal"
                            class="text-gray-400 hover:text-gray-600 p-1 rounded-md hover:bg-gray-100 transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-3.5 text-xs">
                        <div class="p-3 rounded-lg bg-gray-50 border border-gray-200">
                            <span class="text-gray-500 font-medium block">Judul Bagian:</span>
                            <p class="text-gray-900 font-bold mt-0.5 text-sm leading-snug">
                                {{ selectedSectionDetail.title }}
                            </p>
                            <p v-if="selectedSectionDetail.description" class="text-gray-600 mt-1.5 leading-relaxed">
                                {{ selectedSectionDetail.description }}
                            </p>
                            <p v-else class="text-gray-400 italic mt-1">
                                Tidak ada deskripsi tambahan.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200">
                                <span class="text-gray-500 block">Nomor Urut:</span>
                                <span class="font-bold text-gray-800 text-xs">Bagian {{ selectedSectionDetail.order }}</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200">
                                <span class="text-gray-500 block">Program Studi:</span>
                                <span class="font-bold text-gray-800 text-xs">
                                    {{ prodi?.nama_prodi || 'Program Studi' }}
                                </span>
                            </div>
                        </div>

                        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-between">
                            <span class="font-bold text-emerald-900">Total Butir Pertanyaan Terdaftar:</span>
                            <span class="font-bold text-emerald-900 text-xs">
                                {{ selectedSectionDetail.questions_count ?? (selectedSectionDetail.questions?.length ?? 0) }} Butir Soal
                            </span>
                        </div>

                        <div class="pt-3 border-t border-gray-100 flex justify-end">
                            <button
                                type="button"
                                @click="closeDetailModal"
                                class="px-4 py-2 text-xs font-semibold text-white bg-[#0D542B] hover:bg-[#08381c] rounded-lg transition-colors cursor-pointer"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
