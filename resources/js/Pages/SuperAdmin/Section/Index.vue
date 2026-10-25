<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

import Sidebar from '@/Pages/SuperAdmin/Components/Sidebar.vue';

const props = defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
    kuesioners: {
        type: Array,
        default: () => [],
    },
    questionnaires: {
        type: Array,
        default: () => [],
    },
});

const searchQuery = ref('');
const isReordering = ref(false);

const activeKuesioners = computed(() => {
    return (props.kuesioners && props.kuesioners.length > 0) ? props.kuesioners : props.questionnaires;
});

const activeKuesioner = computed(() => {
    return activeKuesioners.value.find((k) => k.is_active) || activeKuesioners.value[0];
});

const filteredSections = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.sections;
    }
    const query = searchQuery.value.toLowerCase();
    return props.sections.filter((sec) => {
        const matchTitle = sec.title?.toLowerCase().includes(query);
        const matchOrder = sec.order?.toString().includes(query);
        const matchKuesioner = (sec.kuesioner?.title || sec.questionnaire?.title)?.toLowerCase().includes(query);
        return matchTitle || matchOrder || matchKuesioner;
    });
});

const totalQuestionsCount = computed(() => {
    return props.sections.reduce((sum, sec) => sum + (sec.subpertanyaans_count ?? sec.questions_count ?? (sec.subpertanyaans?.length ?? 0)), 0);
});

// ========================================================
// STATE REAKTIF UNTUK MODAL (VUE 3)
// ========================================================
// Catatan Belajar: 'ref' digunakan untuk membuat variabel reaktif.
// Di Vue, saat variabel ref nilainya berubah, tampilan UI akan otomatis ter-update!
// Di dalam script (JavaScript), kita mengakses nilainya dengan '.value' (contoh: isSectionModalOpen.value = true).

// 1. State Modal Bagian (Section)
// Menyimpan status apakah modal tambah/edit bagian sedang terbuka atau tertutup
const isSectionModalOpen = ref(false);
// Menandai apakah modal sedang dalam mode "Edit" (true) atau "Tambah Baru" (false)
const isEditingSection = ref(false);
// Menyimpan ID bagian yang sedang disunting (berisi null jika sedang tambah baru)
const editingSectionId = ref(null);
// Objek penampung nilai input form bagian kuesioner
const sectionFormData = ref({
    kuesioner_id: '',
    title: '',
    description: '',
    order: 1,
});
// Indikator proses simpan sedang berjalan (agar tombol bisa menampilkan status loading / disable)
const isSubmittingSection = ref(false);
// Penampung pesan error validasi khusus form bagian
const sectionFormErrors = ref({});

// 2. State Modal Detail Bagian
// Menentukan apakah modal pembacaan detail bagian ditampilkan
const isDetailModalOpen = ref(false);
// Menyimpan objek data bagian yang dipilih untuk dilihat detailnya
const selectedSectionDetail = ref(null);

// 3. State Modal Pengelola Kuesioner Induk
// Menentukan apakah modal daftar kuesioner induk terbuka
const isKuesionerManagerOpen = ref(false);

// 4. State Modal Form Kuesioner Induk (Tambah & Edit)
// Menentukan apakah modal tambah/edit kuesioner induk terbuka
const isKuesionerFormOpen = ref(false);
// Menandai apakah sedang mengedit kuesioner yang sudah ada
const isEditingKuesioner = ref(false);
// Menyimpan ID kuesioner induk yang sedang disunting
const editingKuesionerId = ref(null);
// Objek data input form kuesioner induk
const kuesionerFormData = ref({
    title: '',
    year: new Date().getFullYear(),
    description: '',
    is_active: true,
});
// Indikator loading penyimpanan kuesioner induk
const isSubmittingKuesioner = ref(false);
// Penampung pesan error validasi form kuesioner induk
const kuesionerFormErrors = ref({});

// ========================================================
// FUNGSI KONTROL: MODAL BAGIAN (SECTION)
// ========================================================

/**
 * Membuka modal form bagian kuesioner.
 * Jika parameter sectionToEdit diberikan, modal akan beralih ke mode Edit dengan data terisi.
 * Jika kosong, modal beralih ke mode Tambah Baru dengan nilai default.
 */
const openSectionModal = (sectionToEdit = null) => {
    // Reset pesan error sebelumnya
    sectionFormErrors.value = {};

    if (sectionToEdit) {
        // Mode Edit: Isi form dengan nilai yang sudah ada di database
        isEditingSection.value = true;
        editingSectionId.value = sectionToEdit.id;
        sectionFormData.value = {
            kuesioner_id: sectionToEdit.kuesioner_id || (activeKuesioners.value[0]?.id || ''),
            title: sectionToEdit.title || '',
            description: sectionToEdit.description || '',
            order: sectionToEdit.order ?? (props.sections.length + 1),
        };
    } else {
        // Mode Tambah Baru: Berikan nilai awal yang bersih
        isEditingSection.value = false;
        editingSectionId.value = null;
        sectionFormData.value = {
            kuesioner_id: activeKuesioner.value?.id || (activeKuesioners.value[0]?.id || ''),
            title: '',
            description: '',
            order: props.sections.length + 1,
        };
    }

    // Tampilkan modal ke layar
    isSectionModalOpen.value = true;
};

/**
 * Menutup modal bagian kuesioner dan mereset status loading
 */
const closeSectionModal = () => {
    isSectionModalOpen.value = false;
    isSubmittingSection.value = false;
    sectionFormErrors.value = {};
};

/**
 * Mengirimkan data form bagian kuesioner ke backend (Laravel Controller).
 * Validasi awal dijalankan di sisi klien (frontend) sebelum request dikirim via Inertia router.
 */
const submitSectionForm = () => {
    sectionFormErrors.value = {};

    // Validasi input wajib
    if (!sectionFormData.value.kuesioner_id) {
        sectionFormErrors.value.kuesioner_id = 'Kuesioner induk wajib dipilih.';
    }
    if (!sectionFormData.value.title || !sectionFormData.value.title.trim()) {
        sectionFormErrors.value.title = 'Judul bagian wajib diisi.';
    }

    // Jika terdapat error validasi, hentikan proses pengiriman
    if (Object.keys(sectionFormErrors.value).length > 0) {
        return;
    }

    isSubmittingSection.value = true;

    // Menentukan metode HTTP: PUT untuk update data lama, POST untuk membuat data baru
    if (isEditingSection.value) {
        router.put(`/superadmin/sections/${editingSectionId.value}`, sectionFormData.value, {
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
        router.post('/superadmin/sections', sectionFormData.value, {
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
 * Membuka modal detail untuk melihat ringkasan bagian kuesioner
 */
const openDetailModal = (sec) => {
    selectedSectionDetail.value = sec;
    isDetailModalOpen.value = true;
};

/**
 * Menutup modal detail bagian kuesioner
 */
const closeDetailModal = () => {
    isDetailModalOpen.value = false;
    selectedSectionDetail.value = null;
};

// ========================================================
// FUNGSI KONTROL: HAPUS & PINDAH URUTAN BAGIAN (SECTION)
// ========================================================

/**
 * Menampilkan konfirmasi SweetAlert2 sebelum menghapus data bagian kuesioner.
 * Untuk dialog konfirmasi sederhana (Ya / Batal), SweetAlert2 adalah pilihan yang tepat & bersih.
 */
const handleDeleteSection = (sec) => {
    const questionCount = sec.subpertanyaans_count ?? sec.questions_count ?? (sec.subpertanyaans?.length ?? 0);
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
            router.delete(`/superadmin/sections/${sec.id}`, {
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
                    Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus data.', 'error');
                },
            });
        }
    });
};

/**
 * Mengubah urutan seksi kuesioner ke atas ('up') atau ke bawah ('down')
 */
const handleMoveSection = (sec, direction) => {
    isReordering.value = true;
    router.post(
        '/superadmin/sections/reorder',
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

// ========================================================
// FUNGSI KONTROL: KELOLA KUESIONER INDUK
// ========================================================

/**
 * Membuka modal daftar instrumen kuesioner induk
 */
const openKuesionerManagerModal = () => {
    isKuesionerManagerOpen.value = true;
};

/**
 * Menutup modal daftar instrumen kuesioner induk
 */
const closeKuesionerManagerModal = () => {
    isKuesionerManagerOpen.value = false;
};

/**
 * Membuka form tambah atau sunting kuesioner induk.
 * Jika kuesionerToEdit ada nilainya, maka form terisi data kuesioner tersebut.
 */
const openKuesionerFormModal = (kuesionerToEdit = null) => {
    kuesionerFormErrors.value = {};

    if (kuesionerToEdit) {
        // Mode edit kuesioner induk yang sudah ada
        isEditingKuesioner.value = true;
        editingKuesionerId.value = kuesionerToEdit.id;
        kuesionerFormData.value = {
            title: kuesionerToEdit.title || '',
            year: kuesionerToEdit.year || new Date().getFullYear(),
            description: kuesionerToEdit.description || '',
            is_active: !!kuesionerToEdit.is_active,
        };
    } else {
        // Mode buat kuesioner induk baru
        isEditingKuesioner.value = false;
        editingKuesionerId.value = null;
        kuesionerFormData.value = {
            title: '',
            year: new Date().getFullYear(),
            description: '',
            is_active: true,
        };
    }

    isKuesionerFormOpen.value = true;
};

/**
 * Menutup modal form tambah/sunting kuesioner induk
 */
const closeKuesionerFormModal = () => {
    isKuesionerFormOpen.value = false;
    isSubmittingKuesioner.value = false;
    kuesionerFormErrors.value = {};
};

/**
 * Mengirimkan data form kuesioner induk ke backend Laravel
 */
const submitKuesionerForm = () => {
    kuesionerFormErrors.value = {};

    // Validasi input
    if (!kuesionerFormData.value.title || !kuesionerFormData.value.title.trim()) {
        kuesionerFormErrors.value.title = 'Judul kuesioner wajib diisi.';
    }
    const currentYear = parseInt(kuesionerFormData.value.year, 10);
    if (!currentYear || currentYear < 2000 || currentYear > 2100) {
        kuesionerFormErrors.value.year = 'Tahun kuesioner harus berupa angka antara 2000 - 2100.';
    }

    if (Object.keys(kuesionerFormErrors.value).length > 0) {
        return;
    }

    isSubmittingKuesioner.value = true;

    if (isEditingKuesioner.value) {
        // Update kuesioner induk yang sudah ada
        router.put(`/superadmin/kuesioner/${editingKuesionerId.value}`, kuesionerFormData.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeKuesionerFormModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Data kuesioner berhasil diperbarui.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: (errors) => {
                kuesionerFormErrors.value = errors;
            },
            onFinish: () => {
                isSubmittingKuesioner.value = false;
            },
        });
    } else {
        // Tambah kuesioner induk baru
        router.post('/superadmin/kuesioner', kuesionerFormData.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeKuesionerFormModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Kuesioner baru berhasil ditambahkan.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: (errors) => {
                kuesionerFormErrors.value = errors;
            },
            onFinish: () => {
                isSubmittingKuesioner.value = false;
            },
        });
    }
};

/**
 * Mengaktifkan atau menonaktifkan instrumen kuesioner induk
 */
const handleToggleKuesionerActive = (k) => {
    const actionText = k.is_active ? 'menonaktifkan' : 'mengaktifkan';
    Swal.fire({
        title: 'Konfirmasi Status',
        text: `Apakah Anda yakin ingin ${actionText} kuesioner "${k.title}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Lanjutkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
    }).then((res) => {
        if (res.isConfirmed) {
            router.patch(`/superadmin/kuesioner/${k.id}/toggle-active`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: `Status kuesioner "${k.title}" berhasil diperbarui.`,
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
                onError: () => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat memperbarui status kuesioner.', 'error');
                },
            });
        }
    });
};

/**
 * Menghapus kuesioner induk beserta seluruh seksi dan pertanyaan di dalamnya
 */
const handleDeleteKuesioner = (k) => {
    Swal.fire({
        title: 'Hapus Kuesioner Induk?',
        html: `Apakah Anda yakin ingin menghapus <strong>"${k.title}"</strong>?<br><br><span class="text-xs text-red-600">Perhatian: Seluruh seksi dan butir pertanyaan di dalam kuesioner ini akan ikut terhapus!</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6B7280',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/superadmin/kuesioner/${k.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: 'Kuesioner berhasil dihapus.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
                onError: () => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus kuesioner.', 'error');
                },
            });
        }
    });
};
</script>

<template>
    <Head title="Kelola Bagian Kuesioner - Super Admin" />

    <div class="min-h-screen bg-slate-50 flex font-sans">
        <!-- Sidebar Terpadu Super Admin -->
        <Sidebar />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Halaman Bersih & Flat Terpadu -->
            <div class="admin-page-header bg-white border-b border-gray-200 px-6 py-5">
                <div class="w-full flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-gray-500 font-medium mb-1">
                            <Link href="/superadmin/dashboard" class="hover:text-emerald-700">Dashboard</Link>
                            <span>/</span>
                            <span>Manajemen Kuesioner</span>
                            <span>/</span>
                            <span class="text-gray-900 font-semibold">Kuesioner Universitas</span>
                        </div>
                        <h1 class="text-xl font-bold text-gray-900">
                            Kuesioner Universitas (Tracer Study Dikti)
                        </h1>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Instrumen kuesioner tingkat universitas, struktur bagian/seksi, opsi jawaban, dan kuesioner induk.
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            @click="openKuesionerManagerModal()"
                            class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
                            title="Kelola Kuesioner Induk Tracer Study"
                        >
                            <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Kelola Kuesioner Induk</span>
                            <span v-if="activeKuesioner" class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                                {{ activeKuesioner.year }}
                            </span>
                        </button>

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

            <!-- Konten Utama -->
            <main class="admin-page-main w-full p-6 space-y-4">
                <!-- TABS NAVIGASI TERPADU KUESIONER UNIVERSITAS -->
                <div class="flex items-center gap-2 flex-wrap">
                    <Link
                        href="/superadmin/pertanyaan"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 bg-white text-gray-600 hover:bg-gray-100 border border-gray-200"
                    >
                        <span>Butir Pertanyaan</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-gray-100 text-gray-700">
                            {{ totalQuestionsCount }}
                        </span>
                    </Link>

                    <Link
                        href="/superadmin/sections"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 bg-[#0D542B] text-white shadow-xs"
                    >
                        <span>Bagian / Seksi (Section)</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-white/20 text-white">
                            {{ sections.length }}
                        </span>
                    </Link>
                </div>
                <!-- Bilah Pencarian & Ringkasan -->
                <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3">
                        <span class="text-gray-600 font-medium">Total: <strong>{{ sections.length }}</strong> Bagian ({{ totalQuestionsCount }} Pertanyaan)</span>
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
                <div class="bg-white border border-gray-300 overflow-x-auto shadow-2xs">
                    <table class="w-full min-w-full text-left border-collapse border border-gray-300 text-xs bg-white">
                        <thead>
                            <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px] whitespace-nowrap">
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-16 whitespace-nowrap">Urut</th>
                                <th class="border border-gray-300 px-3 py-2 whitespace-nowrap">Judul & Deskripsi Bagian</th>
                                <th class="border border-gray-300 px-3 py-2 w-52 whitespace-nowrap">Kuesioner Induk</th>
                                <th class="border border-gray-300 px-3 py-2 w-32 text-center whitespace-nowrap">Jumlah Soal</th>
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-28 whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredSections.length === 0">
                                <td colspan="5" class="border border-gray-300 p-8 text-center text-gray-400 italic">
                                    Tidak ada bagian kuesioner yang sesuai kriteria pencarian.
                                </td>
                            </tr>
                            <tr
                                v-for="(sec, index) in filteredSections"
                                :key="sec.id"
                                class="hover:bg-gray-50/80 transition-colors"
                            >
                                <!-- Urutan -->
                                <td class="border border-gray-300 px-2.5 py-2 text-center">
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
                                <td class="border border-gray-300 px-3 py-2">
                                    <span class="font-bold text-gray-900 text-xs block">
                                        {{ sec.title }}
                                    </span>
                                    <p v-if="sec.description" class="text-gray-500 text-[11px] mt-0.5 leading-normal">
                                        {{ sec.description }}
                                    </p>
                                    <span v-else class="text-gray-400 text-[11px] italic">
                                        Tidak ada deskripsi tambahan
                                    </span>
                                </td>

                                <!-- Kuesioner Induk -->
                                <td class="border border-gray-300 px-3 py-2">
                                    <span class="text-gray-700 font-medium">
                                        {{ sec.kuesioner?.title || sec.questionnaire?.title || 'Kuesioner Umum' }}
                                        <span v-if="sec.kuesioner?.year || sec.questionnaire?.year" class="text-gray-500 text-[11px]">
                                            ({{ sec.kuesioner?.year || sec.questionnaire?.year }})
                                        </span>
                                    </span>
                                </td>

                                <!-- Jumlah Soal -->
                                <td class="border border-gray-300 px-3 py-2 text-center">
                                    <Link
                                        :href="`/superadmin/pertanyaan?sec_id=${sec.id}`"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-emerald-50 text-emerald-800 hover:bg-emerald-100 font-semibold text-[11px] border border-emerald-200 transition-colors"
                                        title="Buka daftar soal untuk bagian ini"
                                    >
                                        <span>{{ sec.subpertanyaans_count ?? sec.questions_count ?? (sec.subpertanyaans?.length ?? 0) }} Soal</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </td>

                                <!-- Aksi -->
                                <td class="border border-gray-300 px-2.5 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Detail (Mata) -->
                                        <button
                                            type="button"
                                            @click="openDetailModal(sec)"
                                            title="Detail Bagian"
                                            class="p-1 text-blue-600 hover:bg-blue-50 rounded cursor-pointer transition-colors"
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
                                            class="p-1 text-amber-600 hover:bg-amber-50 rounded cursor-pointer transition-colors"
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
                                            class="p-1 text-rose-600 hover:bg-rose-50 rounded cursor-pointer transition-colors"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL TELEPORT VUE 3: FORM TAMBAH / SUNTING BAGIAN (SECTION) -->
        <!-- ======================================================== -->
        <!-- Catatan Belajar: <Teleport to="body"> memindahkan HTML modal ini langsung ke tag <body> -->
        <!-- Tujuannya agar posisi popup fixed tidak terhalang oleh overflow atau styling parent container -->
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
                            {{ isEditingSection ? 'Sunting Bagian Kuesioner' : 'Tambah Bagian Kuesioner' }}
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

                    <!-- Isi Form Modal -->
                    <form @submit.prevent="submitSectionForm" class="p-6 space-y-4 text-xs">
                        <!-- Kuesioner Induk -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Kuesioner Induk <span class="text-rose-600">*</span>
                            </label>
                            <!-- Catatan Belajar: 'v-model' menghubungkan nilai select ini langsung dengan sectionFormData.kuesioner_id -->
                            <select
                                v-model="sectionFormData.kuesioner_id"
                                class="w-full px-3 py-2 border rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                :class="sectionFormErrors.kuesioner_id ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            >
                                <option value="" disabled>Pilih Kuesioner Induk...</option>
                                <option
                                    v-for="k in activeKuesioners"
                                    :key="k.id"
                                    :value="k.id"
                                >
                                    {{ k.title }} ({{ k.year || '-' }})
                                </option>
                            </select>
                            <p v-if="sectionFormErrors.kuesioner_id" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ sectionFormErrors.kuesioner_id }}
                            </p>
                        </div>

                        <!-- Judul Bagian -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Judul Bagian (Section Title) <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="sectionFormData.title"
                                placeholder="Contoh: Identitas & Biodata Alumni"
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
                                placeholder="Ketikkan petunjuk pengisian bagi responden..."
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
        <!-- MODAL TELEPORT VUE 3: DETAIL BAGIAN KUESIONER -->
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
                                <span class="text-gray-500 block">Kuesioner Induk:</span>
                                <span class="font-bold text-gray-800 text-xs">
                                    {{ selectedSectionDetail.kuesioner?.title || selectedSectionDetail.questionnaire?.title || 'Kuesioner Umum' }}
                                </span>
                            </div>
                        </div>

                        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-between">
                            <span class="font-bold text-emerald-900">Total Butir Pertanyaan Terdaftar:</span>
                            <span class="font-bold text-emerald-900 text-xs">
                                {{ selectedSectionDetail.subpertanyaans_count ?? selectedSectionDetail.questions_count ?? (selectedSectionDetail.subpertanyaans?.length ?? 0) }} Butir Soal
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

        <!-- ======================================================== -->
        <!-- MODAL TELEPORT VUE 3: KELOLA KUESIONER INDUK -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="isKuesionerManagerOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
                @click.self="closeKuesionerManagerModal"
                @keydown.esc="closeKuesionerManagerModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-xl overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-6 py-4 bg-white border-b border-gray-200 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">
                                Kelola Kuesioner Induk
                            </h3>
                            <p class="text-[11px] text-gray-500 mt-0.5">
                                Kuesioner yang aktif akan ditampilkan pada halaman pengisian alumni.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="openKuesionerFormModal()"
                                class="px-3 py-1.5 bg-[#0D542B] hover:bg-[#08381c] text-white text-[11px] font-bold rounded-lg transition-colors cursor-pointer flex items-center gap-1 shadow-xs"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Tambah Kuesioner</span>
                            </button>
                            <button
                                type="button"
                                @click="closeKuesionerManagerModal"
                                class="text-gray-400 hover:text-gray-600 p-1 rounded-md hover:bg-gray-100 transition-colors cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Daftar Kuesioner Induk -->
                    <div class="p-6 max-h-96 overflow-y-auto space-y-3 text-xs">
                        <div
                            v-if="activeKuesioners.length === 0"
                            class="text-center text-gray-400 py-8 italic"
                        >
                            Belum ada data kuesioner induk terdaftar.
                        </div>

                        <div
                            v-for="k in activeKuesioners"
                            :key="k.id"
                            class="p-3 bg-white border rounded-xl space-y-2 shadow-2xs transition-colors"
                            :class="k.is_active ? 'border-emerald-400 ring-1 ring-emerald-200' : 'border-gray-200'"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-gray-900 text-xs">{{ k.title }}</span>
                                    <span class="px-1.5 py-0.5 text-[10px] font-bold bg-gray-100 border border-gray-200 rounded text-gray-700">
                                        Tahun {{ k.year }}
                                    </span>
                                    <span
                                        v-if="k.is_active"
                                        class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300"
                                    >
                                        Aktif (Alumni)
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-gray-100 text-gray-500"
                                    >
                                        Nonaktif
                                    </span>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <!-- Toggle Aktif/Nonaktif -->
                                    <button
                                        type="button"
                                        @click="handleToggleKuesionerActive(k)"
                                        class="px-2.5 py-1 text-[10px] font-bold rounded border cursor-pointer transition-colors"
                                        :class="k.is_active ? 'border-amber-300 text-amber-800 bg-amber-50 hover:bg-amber-100' : 'border-emerald-300 text-emerald-800 bg-emerald-50 hover:bg-emerald-100'"
                                    >
                                        {{ k.is_active ? 'Nonaktifkan' : 'Jadikan Aktif' }}
                                    </button>
                                    <!-- Edit -->
                                    <button
                                        type="button"
                                        @click="openKuesionerFormModal(k)"
                                        class="px-2 py-1 text-[10px] font-semibold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50 rounded border border-gray-200 cursor-pointer transition-colors"
                                        title="Sunting"
                                    >
                                        Sunting
                                    </button>
                                    <!-- Hapus -->
                                    <button
                                        type="button"
                                        @click="handleDeleteKuesioner(k)"
                                        class="px-2 py-1 text-[10px] font-semibold text-rose-600 hover:bg-rose-50 rounded border border-gray-200 cursor-pointer transition-colors"
                                        title="Hapus"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </div>
                            <p class="text-[11px] text-gray-500 leading-relaxed">
                                {{ k.description || 'Tidak ada deskripsi tambahan.' }}
                            </p>
                            <div class="text-[10px] text-gray-400">
                                Total Bagian: <strong class="text-gray-700">{{ k.kelompok_pertanyaan_count ?? k.sections_count ?? '-' }} Seksi</strong>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 flex justify-end">
                        <button
                            type="button"
                            @click="closeKuesionerManagerModal"
                            class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition-colors cursor-pointer"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ======================================================== -->
        <!-- MODAL TELEPORT VUE 3: FORM TAMBAH / SUNTING KUESIONER INDUK -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="isKuesionerFormOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
                @click.self="closeKuesionerFormModal"
                @keydown.esc="closeKuesionerFormModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-6 py-4 bg-white border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ isEditingKuesioner ? 'Sunting Kuesioner Induk' : 'Tambah Kuesioner Induk' }}
                        </h3>
                        <button
                            type="button"
                            @click="closeKuesionerFormModal"
                            class="text-gray-400 hover:text-gray-600 p-1 rounded-md hover:bg-gray-100 transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitKuesionerForm" class="p-6 space-y-4 text-xs">
                        <!-- Judul Kuesioner -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Judul Kuesioner <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="kuesionerFormData.title"
                                placeholder="Contoh: Tracer Study UKDW 2021"
                                class="w-full px-3 py-2 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                :class="kuesionerFormErrors.title ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            />
                            <p v-if="kuesionerFormErrors.title" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ kuesionerFormErrors.title }}
                            </p>
                        </div>

                        <!-- Tahun Kuesioner -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Tahun Kuesioner <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="number"
                                v-model.number="kuesionerFormData.year"
                                min="2000"
                                max="2100"
                                class="w-full px-3 py-2 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                :class="kuesionerFormErrors.year ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            />
                            <p v-if="kuesionerFormErrors.year" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ kuesionerFormErrors.year }}
                            </p>
                        </div>

                        <!-- Deskripsi / Keterangan -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Deskripsi / Keterangan (Opsional)
                            </label>
                            <textarea
                                v-model="kuesionerFormData.description"
                                rows="3"
                                placeholder="Contoh: Kuesioner Pelacakan Jejak Alumni Universitas Kristen Duta Wacana"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700 leading-relaxed"
                            ></textarea>
                        </div>

                        <!-- Checkbox Jadikan Aktif -->
                        <div class="pt-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    v-model="kuesionerFormData.is_active"
                                    class="w-4 h-4 rounded text-emerald-700 focus:ring-emerald-600 border-gray-300 cursor-pointer"
                                />
                                <span class="font-bold text-gray-700 text-xs">Jadikan Kuesioner Aktif (Ditampilkan kepada Alumni)</span>
                            </label>
                            <p class="text-[11px] text-gray-500 mt-0.5 ml-6">
                                Jika diaktifkan, kuesioner lain secara otomatis akan dinonaktifkan oleh sistem.
                            </p>
                        </div>

                        <!-- Tombol Aksi Bawah -->
                        <div class="pt-3 border-t border-gray-100 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="closeKuesionerFormModal"
                                :disabled="isSubmittingKuesioner"
                                class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors cursor-pointer disabled:opacity-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSubmittingKuesioner"
                                class="px-4 py-2 text-xs font-semibold text-white bg-[#0D542B] hover:bg-[#08381c] rounded-lg transition-colors cursor-pointer flex items-center gap-1.5 disabled:opacity-50 shadow-xs"
                            >
                                <svg v-if="isSubmittingKuesioner" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>{{ isSubmittingKuesioner ? 'Menyimpan...' : (isEditingKuesioner ? 'Simpan Perubahan' : 'Tambah Kuesioner') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
