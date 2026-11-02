<!--
  Halaman: Kelola Butir Pertanyaan & Opsi Kuesioner Program Studi
  File: resources/js/Pages/AdminProdi/Pertanyaan/Index.vue

  Desain Standar Kelola Kuesioner UKDW (Identik dengan Super Admin):
  - Sidebar Terpadu Admin Program Studi
  - Header Bersih, Flat & Profesional
  - Bilah Filter Bagian, Sifat Pengisian, dan Kotak Pencarian
  - Tabel Lapang dengan Penampil Opsi Jawaban Langsung
  - Reorder Pertanyaan Cepat (Naik/Turun)
  - Modal SweetAlert2 untuk Pertanyaan, Opsi Jawaban, Detail, dan Hapus
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

import Sidebar from '../Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    prodi: Object,
    questions: {
        type: Array,
        default: () => [],
    },
    sections: {
        type: Array,
        default: () => [],
    },
    availableJumpTargets: {
        type: Array,
        default: () => [],
    },
});

// ========================================================
// 1. FILTER & PENCARIAN
// ========================================================
const getInitialSectionId = () => {
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        const secParam = urlParams.get('sec_id');
        if (secParam) {
            const parsed = parseInt(secParam, 10);
            if (props.sections.some((s) => s.id === parsed)) return parsed.toString();
        }
    }
    return 'all';
};

const selectedSectionFilter = ref(getInitialSectionId());
const selectedRequiredFilter = ref('all');
const searchQuery = ref('');
const isReordering = ref(false);

// Label tipe pertanyaan yang ramah pengguna
const typeLabels = {
    // Tipe Pilihan & Opsi
    single_choice: 'Pilihan Ganda (Radio)',
    radio: 'Pilihan Ganda (Radio)',
    radio_text: 'Pilihan Ganda + Lainnya (Radio + Teks)',
    radio_input: 'Radio + Angka (Bulan)',
    multiple_choice: 'Kotak Centang (Checkbox)',
    checkbox: 'Kotak Centang (Checkbox)',
    dropdown: 'Pilihan Dropdown (Select)',
    searchable_select: 'Pencarian Dropdown',
    rating_5: 'Skala Rating (1-5)',
    multiple_number: 'Rincian Nominal / Gaji',
    matrix: 'Matriks Skala',
    matrix_dual: 'Dual Matrix',
    multiple_textbox: 'Multiple Textbox',

    // Tipe Isian Bebas
    text: 'Jawaban Singkat (Text)',
    textarea: 'Paragraf (Textarea)',
    number: 'Isian Angka (Number)',
    date: 'Tanggal',
    time: 'Waktu',
    file: 'Unggah Berkas (File)',
    header: 'Header / Judul Bagian',
};

// Tipe yang relevan dan dapat dipilih untuk butir pertanyaan kuesioner Prodi
const prodiSelectableTypes = {
    single_choice: 'Pilihan Ganda (Radio)',
    radio_text: 'Pilihan Ganda + Lainnya (Radio + Teks)',
    radio_input: 'Radio + Angka (Bulan)',
    multiple_choice: 'Kotak Centang (Checkbox)',
    dropdown: 'Pilihan Dropdown (Select)',
    searchable_select: 'Pencarian Dropdown',
    rating_5: 'Skala Rating (1-5)',
    text: 'Jawaban Singkat (Text)',
    textarea: 'Paragraf (Textarea)',
    number: 'Isian Angka (Number)',
    date: 'Tanggal',
    time: 'Waktu',
    file: 'Unggah Berkas (File)',
    header: 'Header / Judul Bagian',
};

// Tipe yang mendukung daftar opsi jawaban
const OPTION_SUPPORTED_TYPES = [
    'single_choice',
    'radio',
    'radio_input',
    'radio_text',
    'multiple_choice',
    'checkbox',
    'dropdown',
    'searchable_select',
    'rating_5',
    'multiple_number',
    'matrix',
    'matrix_dual',
    'multiple_textbox',
];

const canHaveOptions = (type) => {
    return OPTION_SUPPORTED_TYPES.includes(type);
};

const filteredQuestions = computed(() => {
    return props.questions.filter((q) => {
        // Filter Bagian
        if (selectedSectionFilter.value !== 'all') {
            const secId = parseInt(selectedSectionFilter.value, 10);
            if (q.prodi_question_section_id !== secId) return false;
        }

        // Filter Wajib / Opsional
        if (selectedRequiredFilter.value !== 'all') {
            const isReq = selectedRequiredFilter.value === 'wajib';
            if (Boolean(q.is_required) !== isReq) return false;
        }

        // Pencarian Teks
        if (searchQuery.value.trim()) {
            const query = searchQuery.value.toLowerCase();
            const matchCode = q.code?.toLowerCase().includes(query);
            const matchText = q.question_text?.toLowerCase().includes(query);
            const matchType = (typeLabels[q.type] || q.type)?.toLowerCase().includes(query);
            if (!matchCode && !matchText && !matchType) return false;
        }

        return true;
    });
});

// ========================================================
// 2. STATE REAKTIF MODAL PERTANYAAN (VUE TELEPORT)
// ========================================================
// Penjelasan Konsep JavaScript & Vue:
// 1. ref() membungkus nilai primitif/objek menjadi variabel reaktif. Perubahan nilai pada '.value'
//    akan memicu Vue untuk memperbarui tampilan (DOM) secara otomatis dan instan tanpa reload.
// 2. Teleport memindahkan rendering dialog HTML ke luar hierarki DOM (langsung di bawah <body>),
//    menghindari masalah visual terpotong akibat 'overflow-hidden' pada layout sidebar atau tabel.
// 3. Form input terikat dua arah (two-way binding) menggunakan 'v-model', sehingga setiap ketikan
//    pengguna di layar langsung tersimpan di variabel JavaScript tanpa perlu getElementById manual.

// Mengontrol visibilitas dialog modal pertanyaan (true: tampil, false: tertutup)
const isQuestionModalOpen = ref(false);

// Penanda apakah modal sedang dalam mode edit (true) atau mode tambah butir baru (false)
const isEditingQuestion = ref(false);

// Menyimpan ID butir pertanyaan yang sedang disunting untuk rute HTTP PUT
const editingQuestionId = ref(null);

// Status pemuatan saat request pengiriman formulir sedang berlangsung
const isSubmittingQuestion = ref(false);

// Objek data form pertanyaan yang terikat dengan input pada modal
const questionForm = ref({
    prodi_question_section_id: '',
    code: '',
    type: 'single_choice',
    is_required: true,
    question_text: '',
    order: 1,
});

// Menyimpan pesan galat validasi per field input
const questionFormErrors = ref({});

/**
 * Membuka modal tambah atau sunting pertanyaan
 * @param {Object|null} questionToEdit Objek butir pertanyaan jika mode edit, null jika tambah baru
 */
const openQuestionModal = (questionToEdit = null) => {
    isEditingQuestion.value = !!questionToEdit;
    editingQuestionId.value = questionToEdit ? questionToEdit.id : null;
    questionFormErrors.value = {};

    if (questionToEdit) {
        // Mode Sunting: Mengisi nilai form dari objek pertanyaan yang dipilih
        const rawType = questionToEdit.type || 'single_choice';
        questionForm.value = {
            prodi_question_section_id: questionToEdit.prodi_question_section_id || '',
            code: questionToEdit.code || '',
            type: rawType === 'radio' ? 'single_choice' : rawType,
            is_required: Boolean(questionToEdit.is_required),
            question_text: questionToEdit.question_text || '',
            order: questionToEdit.order || 1,
        };
    } else {
        // Mode Tambah Baru: Nilai default awal
        const defaultSectionId = selectedSectionFilter.value !== 'all'
            ? parseInt(selectedSectionFilter.value, 10)
            : (props.sections[0]?.id || '');
        questionForm.value = {
            prodi_question_section_id: defaultSectionId,
            code: '',
            type: 'single_choice',
            is_required: true,
            question_text: '',
            order: props.questions.length + 1,
        };
    }
    isQuestionModalOpen.value = true;
};

/**
 * Menutup modal pertanyaan dan membersihkan galat validasi
 */
const closeQuestionModal = () => {
    isQuestionModalOpen.value = false;
    questionFormErrors.value = {};
};

/**
 * Mengirim formulir pertanyaan ke backend (POST atau PUT)
 */
const submitQuestionForm = () => {
    questionFormErrors.value = {};
    const errors = {};

    // Validasi klien sebelum mengirim request ke server
    if (!questionForm.value.code.trim()) {
        errors.code = 'Kode pertanyaan wajib diisi.';
    }
    if (!questionForm.value.question_text.trim()) {
        errors.question_text = 'Bunyi kalimat pertanyaan wajib diisi.';
    }

    // Jika ada error validasi di sisi klien, hentikan pengiriman
    if (Object.keys(errors).length > 0) {
        questionFormErrors.value = errors;
        return;
    }

    isSubmittingQuestion.value = true;

    // Menyiapkan payload yang bersih dan sesuai tipe data backend
    const payload = {
        prodi_question_section_id: questionForm.value.prodi_question_section_id
            ? parseInt(questionForm.value.prodi_question_section_id, 10)
            : null,
        code: questionForm.value.code.trim().toUpperCase(),
        type: questionForm.value.type,
        is_required: questionForm.value.is_required,
        question_text: questionForm.value.question_text.trim(),
        order: questionForm.value.order ? parseInt(questionForm.value.order, 10) : null,
    };

    if (isEditingQuestion.value) {
        router.put(`/prodi/pertanyaan/${editingQuestionId.value}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isQuestionModalOpen.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Pertanyaan berhasil diperbarui.',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
            onError: (errs) => {
                questionFormErrors.value = errs;
            },
            onFinish: () => {
                isSubmittingQuestion.value = false;
            },
        });
    } else {
        router.post('/prodi/pertanyaan', payload, {
            preserveScroll: true,
            onSuccess: () => {
                isQuestionModalOpen.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Pertanyaan baru berhasil ditambahkan.',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
            onError: (errs) => {
                questionFormErrors.value = errs;
            },
            onFinish: () => {
                isSubmittingQuestion.value = false;
            },
        });
    }
};

// ========================================================
// 3. STATE REAKTIF MODAL OPSI JAWABAN (VUE TELEPORT)
// ========================================================
// Mengontrol visibilitas dialog modal opsi jawaban
const isOptionModalOpen = ref(false);

// Penanda apakah sedang mengedit opsi (true) atau menambah opsi baru (false)
const isEditingOption = ref(false);

// Menyimpan ID opsi yang sedang disunting
const editingOptionId = ref(null);

// Menyimpan objek pertanyaan induk tempat opsi ini bernaung
const currentParentQuestion = ref(null);

// Status pemuatan saat menyimpan opsi jawaban
const isSubmittingOption = ref(false);

// Data formulir opsi jawaban
const optionForm = ref({
    question_id: null,
    code: '',
    option_text: '',
    jump_to: '',
    order: 1,
});

// Menyimpan galat validasi opsi jawaban
const optionFormErrors = ref({});

// Daftar target lompatan (jump_to) yang tersedia selain pertanyaan induk itu sendiri
const activeJumpTargets = computed(() => {
    if (!currentParentQuestion.value) return [];
    const parentCode = currentParentQuestion.value.code;
    if (props.availableJumpTargets && props.availableJumpTargets.length > 0) {
        return props.availableJumpTargets.filter((c) => c !== parentCode);
    }
    return props.questions.map((q) => q.code).filter((c) => c && c !== parentCode);
});

/**
 * Membuka modal tambah atau sunting opsi pilihan jawaban
 * @param {Object} question Pertanyaan induk
 * @param {Object|null} optionToEdit Objek opsi jika mode edit, null jika tambah baru
 */
const openAddOptionModal = (question, optionToEdit = null) => {
    currentParentQuestion.value = question;
    isEditingOption.value = !!optionToEdit;
    editingOptionId.value = optionToEdit ? optionToEdit.id : null;
    optionFormErrors.value = {};

    if (optionToEdit) {
        optionForm.value = {
            question_id: question.id,
            code: optionToEdit.code || '',
            option_text: optionToEdit.option_text || '',
            jump_to: optionToEdit.jump_to || '',
            order: optionToEdit.order || 1,
        };
    } else {
        const nextOrder = (question.options?.length || 0) + 1;
        optionForm.value = {
            question_id: question.id,
            code: `${question.code}-${String(nextOrder).padStart(2, '0')}`,
            option_text: '',
            jump_to: '',
            order: nextOrder,
        };
    }

    isOptionModalOpen.value = true;
};

/**
 * Menutup modal opsi jawaban
 */
const closeOptionModal = () => {
    isOptionModalOpen.value = false;
    optionFormErrors.value = {};
};

/**
 * Mengirim formulir opsi jawaban ke backend
 */
const submitOptionForm = () => {
    optionFormErrors.value = {};
    const errors = {};

    if (!optionForm.value.option_text.trim()) {
        errors.option_text = 'Teks pilihan jawaban wajib diisi.';
    }

    if (Object.keys(errors).length > 0) {
        optionFormErrors.value = errors;
        return;
    }

    isSubmittingOption.value = true;

    const payload = {
        question_id: optionForm.value.question_id,
        option_text: optionForm.value.option_text.trim(),
        code: optionForm.value.code.trim().toUpperCase() || null,
        jump_to: optionForm.value.jump_to || null,
        order: optionForm.value.order ? parseInt(optionForm.value.order, 10) : null,
    };

    if (isEditingOption.value) {
        router.put(`/prodi/opsi/${editingOptionId.value}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isOptionModalOpen.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Opsi jawaban berhasil diperbarui.',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
            onError: (errs) => {
                optionFormErrors.value = errs;
            },
            onFinish: () => {
                isSubmittingOption.value = false;
            },
        });
    } else {
        router.post('/prodi/opsi', payload, {
            preserveScroll: true,
            onSuccess: () => {
                isOptionModalOpen.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Opsi jawaban baru berhasil ditambahkan.',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
            onError: (errs) => {
                optionFormErrors.value = errs;
            },
            onFinish: () => {
                isSubmittingOption.value = false;
            },
        });
    }
};

// ========================================================
// 4. STATE REAKTIF MODAL DETAIL PERTANYAAN (VUE TELEPORT)
// ========================================================
// Mengontrol visibilitas dialog modal rincian butir pertanyaan
const isDetailModalOpen = ref(false);

// Menyimpan objek pertanyaan lengkap yang sedang dilihat rinciannya
const activeDetailQuestion = ref(null);

/**
 * Membuka modal rincian pertanyaan (ikon mata)
 * @param {Object} q Objek pertanyaan
 */
const openDetailModal = (q) => {
    activeDetailQuestion.value = q;
    isDetailModalOpen.value = true;
};

/**
 * Menutup modal rincian pertanyaan
 */
const closeDetailModal = () => {
    isDetailModalOpen.value = false;
    activeDetailQuestion.value = null;
};

// ========================================================
// 5. SWEETALERT2: HAPUS OPSI & HAPUS PERTANYAAN
// ========================================================
const handleDeleteOption = (option) => {
    Swal.fire({
        title: 'Hapus Opsi Jawaban?',
        text: `Apakah Anda yakin ingin menghapus opsi "${option.option_text}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6B7280',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/prodi/opsi/${option.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('Berhasil', 'Opsi jawaban telah dihapus.', 'success');
                },
                onError: () => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus opsi.', 'error');
                },
            });
        }
    });
};

const handleDeleteQuestion = (q) => {
    Swal.fire({
        title: 'Hapus Butir Pertanyaan?',
        text: `Hapus butir "${q.code} - ${q.question_text?.substring(0, 45)}..." beserta seluruh opsi jawabannya?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6B7280',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/prodi/pertanyaan/${q.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('Berhasil', `Pertanyaan ${q.code} telah dihapus.`, 'success');
                },
                onError: () => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus pertanyaan.', 'error');
                },
            });
        }
    });
};

const handleMoveQuestion = (q, direction) => {
    isReordering.value = true;
    router.post(
        '/prodi/pertanyaan/reorder',
        {
            id: q.id,
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
    <Head :title="`Kelola Pertanyaan - ${prodi?.nama_prodi || 'Program Studi'}`" />

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
                            <span class="text-gray-800 font-semibold">Kelola Butir Pertanyaan</span>
                        </div>
                        <h1 class="text-xl font-bold text-gray-900">
                            Kelola Butir Pertanyaan Prodi
                        </h1>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Konfigurasi butir instrumen pertanyaan, opsi pilihan jawaban, dan alur kuesioner khusus Program Studi {{ prodi?.nama_prodi }}.
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <Link
                            href="/prodi/sections"
                            class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span>Kelola Bagian (Section)</span>
                        </Link>

                        <button
                            type="button"
                            @click="openQuestionModal()"
                            class="px-3.5 py-1.5 bg-[#0D542B] hover:bg-[#08381c] text-white font-semibold text-xs rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer shadow-xs"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Tambah Pertanyaan</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Konten Tabel Utama -->
            <main class="admin-page-main w-full p-6 space-y-4">
                <!-- Bilah Filter & Pencarian -->
                <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3 flex-wrap">
                        <!-- Dropdown Filter Bagian -->
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-gray-600">Bagian:</span>
                            <select
                                v-model="selectedSectionFilter"
                                class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700"
                            >
                                <option value="all">Semua Bagian (All - {{ questions.length }} Soal)</option>
                                <option v-for="sec in sections" :key="sec.id" :value="sec.id">
                                    Bagian {{ sec.order }}: {{ sec.title }}
                                </option>
                            </select>
                        </div>

                        <!-- Dropdown Filter Sifat Wajib -->
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-gray-600">Sifat:</span>
                            <select
                                v-model="selectedRequiredFilter"
                                class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700"
                            >
                                <option value="all">Semua Sifat</option>
                                <option value="wajib">Wajib Diisi</option>
                                <option value="opsional">Opsional</option>
                            </select>
                        </div>
                    </div>

                    <!-- Kotak Pencarian -->
                    <div class="relative w-full md:w-64">
                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Cari kode atau kalimat soal..."
                            class="w-full pl-8 pr-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                    </div>
                </div>

                <!-- Info Jumlah Data -->
                <div class="flex items-center justify-between text-xs text-gray-500 px-1">
                    <span>
                        Menampilkan <strong>{{ filteredQuestions.length }}</strong> dari total <strong>{{ questions.length }}</strong> butir pertanyaan
                    </span>
                    <span
                        v-if="searchQuery || selectedSectionFilter !== 'all' || selectedRequiredFilter !== 'all'"
                        class="text-emerald-800 font-semibold cursor-pointer hover:underline"
                        @click="selectedSectionFilter = 'all'; selectedRequiredFilter = 'all'; searchQuery = '';"
                    >
                        Reset Filter
                    </span>
                </div>

                <!-- Tabel Data Pertanyaan: Lapang, Bebas Truncate -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-2xs">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-3 w-16 text-center">Urut</th>
                                <th class="py-3 px-3 w-20">Kode</th>
                                <th class="py-3 px-4">Pertanyaan, Tipe & Pilihan Opsi Jawaban</th>
                                <th class="py-3 px-3 w-28 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr
                                v-for="(q, index) in filteredQuestions"
                                :key="q.id"
                                class="transition-colors align-top"
                                :class="q.type === 'header' ? 'bg-[#FEF9C3] hover:bg-[#FEF08A] border-l-4 border-l-[#EAB308]' : 'hover:bg-slate-50/70'"
                            >
                                <!-- Urutan -->
                                <td class="py-4 px-2 text-center">
                                    <div class="flex flex-col items-center justify-center gap-0.5">
                                        <span class="font-bold text-gray-700 text-xs">{{ q.order }}</span>
                                        <div class="flex items-center gap-0.5">
                                            <button
                                                type="button"
                                                title="Naik"
                                                :disabled="index === 0 || isReordering"
                                                @click="handleMoveQuestion(q, 'up')"
                                                class="p-0.5 text-gray-400 hover:text-gray-800 disabled:opacity-20 cursor-pointer disabled:cursor-not-allowed"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                title="Turun"
                                                :disabled="index === filteredQuestions.length - 1 || isReordering"
                                                @click="handleMoveQuestion(q, 'down')"
                                                class="p-0.5 text-gray-400 hover:text-gray-800 disabled:opacity-20 cursor-pointer disabled:cursor-not-allowed"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </td>

                                <!-- Kode -->
                                <td class="py-4 px-3">
                                    <span
                                        class="inline-block px-1.5 py-0.5 rounded font-mono font-bold text-xs border"
                                        :class="q.type === 'header' ? 'bg-[#FDE047] text-amber-950 border-amber-300' : 'bg-gray-100 text-gray-800 border-gray-200'"
                                    >
                                        {{ q.code }}
                                    </span>
                                </td>

                                <!-- Informasi Pertanyaan, Meta Row, dan Opsi Jawaban -->
                                <td class="py-4 px-4 space-y-2.5">
                                    <div class="space-y-1.5">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <!-- Badge Khusus Header Kuning -->
                                                    <span
                                                        v-if="q.type === 'header'"
                                                        class="px-2 py-0.5 rounded text-[11px] font-bold bg-[#FACC15] text-amber-950 border border-amber-400 shrink-0"
                                                    >
                                                        Header Kuesioner
                                                    </span>

                                                    <span
                                                        class="font-bold text-sm leading-snug whitespace-normal"
                                                        :class="q.type === 'header' ? 'text-amber-950 text-base' : 'text-gray-900'"
                                                    >
                                                        {{ q.question_text }}
                                                    </span>

                                                    <span
                                                        v-if="q.is_required"
                                                        class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-red-50 text-red-700 border border-red-200 shrink-0"
                                                    >
                                                        Wajib
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Baris Informasi Tambahan (Bagian & Tipe Input Tampil Utuh Kebawah) -->
                                        <div class="flex items-center gap-4 text-xs text-gray-600 flex-wrap pt-0.5">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-gray-400 font-medium">Bagian:</span>
                                                <span class="font-semibold" :class="q.type === 'header' ? 'text-amber-900' : 'text-gray-800'">
                                                    {{ q.section?.order ? `Bagian ${q.section.order}: ` : '' }}{{ q.section?.title || '-' }}
                                                </span>
                                            </div>
                                            <span class="text-gray-300">•</span>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-gray-400 font-medium">Tipe:</span>
                                                <span class="font-semibold" :class="q.type === 'header' ? 'text-amber-900' : 'text-gray-800'">
                                                    {{ typeLabels[q.type] || q.type }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Bagian Opsi Jawaban: Jika tipe mendukung opsi -->
                                    <div
                                        v-if="canHaveOptions(q.type)"
                                        class="p-3 rounded-lg bg-white border border-gray-200 space-y-2 mt-2"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-800">
                                                Pilihan Opsi Jawaban ({{ q.options?.length || 0 }} Opsi):
                                            </span>
                                            <button
                                                type="button"
                                                @click="openAddOptionModal(q)"
                                                class="text-[11px] font-bold text-emerald-800 hover:text-emerald-950 flex items-center gap-1 hover:underline cursor-pointer"
                                            >
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                                </svg>
                                                <span>Tambah Opsi</span>
                                            </button>
                                        </div>

                                        <!-- Daftar Item Opsi (Vertikal Lebar & Teks Tampil Utuh) -->
                                        <div
                                            v-if="q.options && q.options.length > 0"
                                            class="divide-y divide-gray-200 border border-gray-200 rounded-lg bg-white overflow-hidden text-xs"
                                        >
                                            <div
                                                v-for="(opt, oIdx) in q.options"
                                                :key="opt.id || oIdx"
                                                class="p-2.5 flex items-center justify-between gap-3 hover:bg-slate-50/70"
                                            >
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <span class="w-5 h-5 rounded bg-gray-100 text-gray-700 font-mono font-bold flex items-center justify-center text-xs shrink-0">
                                                        {{ oIdx + 1 }}
                                                    </span>
                                                    <span class="text-gray-900 font-medium whitespace-normal leading-normal">
                                                        {{ opt.option_text }}
                                                    </span>
                                                    <span
                                                        v-if="opt.jump_to"
                                                        class="px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 text-[10px] font-semibold border border-purple-200 shrink-0"
                                                        :title="'Lompat ke: ' + opt.jump_to"
                                                    >
                                                        → Lompat ke: {{ opt.jump_to }}
                                                    </span>
                                                </div>

                                                <!-- Aksi Edit / Hapus Opsi -->
                                                <div class="flex items-center gap-1 shrink-0">
                                                    <button
                                                        type="button"
                                                        @click="openAddOptionModal(q, opt)"
                                                        title="Edit Opsi"
                                                        class="p-1 text-gray-400 hover:text-emerald-700 rounded hover:bg-gray-100 cursor-pointer"
                                                    >
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="handleDeleteOption(opt)"
                                                        title="Hapus Opsi"
                                                        class="p-1 text-gray-400 hover:text-red-600 rounded hover:bg-gray-100 cursor-pointer"
                                                    >
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-else
                                            class="p-3 border border-dashed border-gray-200 rounded-lg text-center text-gray-400 text-xs"
                                        >
                                            Belum ada opsi jawaban. Klik tombol (+) di kolom aksi atau di atas untuk menambahkan pilihan.
                                        </div>
                                    </div>
                                </td>

                                <!-- Aksi -->
                                <td class="py-4 px-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Tambah Opsi (+) Hanya untuk tipe yang mendukung opsi -->
                                        <button
                                            v-if="canHaveOptions(q.type)"
                                            type="button"
                                            @click="openAddOptionModal(q)"
                                            title="Tambah Opsi Jawaban"
                                            class="p-1.5 text-emerald-700 hover:text-emerald-900 hover:bg-emerald-100/60 rounded-lg cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                        </button>

                                        <!-- Detail (Mata) -->
                                        <button
                                            type="button"
                                            @click="openDetailModal(q)"
                                            title="Detail Lengkap"
                                            class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        <!-- Edit (Pensil) -->
                                        <button
                                            type="button"
                                            @click="openQuestionModal(q)"
                                            title="Sunting Soal"
                                            class="p-1.5 text-gray-500 hover:text-emerald-800 hover:bg-emerald-50 rounded-lg cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        <!-- Hapus (Sampah) -->
                                        <button
                                            type="button"
                                            @click="handleDeleteQuestion(q)"
                                            title="Hapus Soal"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="filteredQuestions.length === 0">
                                <td colspan="4" class="py-12 text-center text-gray-500">
                                    <p class="font-bold text-gray-700">Tidak ada butir pertanyaan ditemukan</p>
                                    <p class="text-xs text-gray-400 mt-1">Coba sesuaikan filter bagian atau kata kunci pencarian Anda.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL VUE: TAMBAH / SUNTING BUTIR PERTANYAAN PRODI       -->
    <!-- ======================================================== -->
    <Teleport to="body">
        <div
            v-if="isQuestionModalOpen"
            @click.self="closeQuestionModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-xl shadow-xl max-w-lg w-full overflow-hidden border border-gray-100 animate-in fade-in zoom-in-95 duration-150"
            >
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-white">
                    <div>
                        <h3 class="font-bold text-gray-900 text-sm">
                            {{ isEditingQuestion ? 'Sunting Butir Pertanyaan' : 'Tambah Pertanyaan Baru' }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Konfigurasi instrumen butir pertanyaan khusus Program Studi.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeQuestionModal"
                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitQuestionForm" class="p-5 space-y-4">
                    <div>
                        <label class="block font-semibold text-gray-700 text-xs mb-1">
                            Bagian Kuesioner (Section) <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="questionForm.prodi_question_section_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                        >
                            <option v-if="props.sections.length === 0" value="">
                                -- Belum ada bagian (otomatis dibuat) --
                            </option>
                            <option
                                v-for="sec in props.sections"
                                :key="sec.id"
                                :value="sec.id"
                            >
                                Bagian {{ sec.order }}: {{ sec.title }}
                            </option>
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Pilih bagian bab tempat pertanyaan ini akan ditampilkan.</p>
                        <p v-if="questionFormErrors.prodi_question_section_id" class="text-red-600 text-xs mt-1">
                            {{ questionFormErrors.prodi_question_section_id }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-gray-700 text-xs mb-1">
                                Kode Pertanyaan <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="questionForm.code"
                                type="text"
                                placeholder="Contoh: P01, P02, EVAL_01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono uppercase focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            />
                            <p v-if="questionFormErrors.code" class="text-red-600 text-xs mt-1">
                                {{ questionFormErrors.code }}
                            </p>
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 text-xs mb-1">
                                Sifat Pengisian <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="questionForm.is_required"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            >
                                <option :value="true">Wajib Diisi</option>
                                <option :value="false">Opsional (Boleh Kosong)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-gray-700 text-xs mb-1">
                                Tipe Input Pertanyaan <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="questionForm.type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            >
                                <option
                                    v-for="(label, val) in prodiSelectableTypes"
                                    :key="val"
                                    :value="val"
                                >
                                    {{ label }}
                                </option>
                            </select>
                            <p class="text-[10px] text-emerald-700 font-semibold mt-1">Opsi pilihan default akan otomatis terbuat & dapat disesuaikan.</p>
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 text-xs mb-1">
                                Nomor Urut Tampil
                            </label>
                            <input
                                v-model.number="questionForm.order"
                                type="number"
                                min="1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 text-xs mb-1">
                            Bunyi Kalimat Pertanyaan <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="questionForm.question_text"
                            rows="3"
                            placeholder="Ketikkan rumusan kalimat pertanyaan untuk alumni program studi..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B] leading-relaxed"
                        ></textarea>
                        <p v-if="questionFormErrors.question_text" class="text-red-600 text-xs mt-1">
                            {{ questionFormErrors.question_text }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button
                            type="button"
                            @click="closeQuestionModal"
                            class="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmittingQuestion"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5 disabled:opacity-50"
                        >
                            <span>{{ isSubmittingQuestion ? 'Menyimpan...' : (isEditingQuestion ? 'Simpan Perubahan' : 'Tambah Pertanyaan') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- ======================================================== -->
    <!-- MODAL VUE: TAMBAH / SUNTING OPSI JAWABAN PRODI           -->
    <!-- ======================================================== -->
    <Teleport to="body">
        <div
            v-if="isOptionModalOpen"
            @click.self="closeOptionModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden border border-gray-100 animate-in fade-in zoom-in-95 duration-150"
            >
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-white">
                    <div>
                        <h3 class="font-bold text-gray-900 text-sm">
                            {{ isEditingOption ? 'Sunting Opsi Pilihan Jawaban' : 'Tambah Opsi Pilihan Jawaban' }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Konfigurasi teks pilihan dan alur lompatan pertanyaan.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeOptionModal"
                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitOptionForm" class="p-5 space-y-4">
                    <div v-if="currentParentQuestion" class="p-2.5 rounded-lg bg-gray-50 border border-gray-200 text-xs">
                        <span class="text-gray-500 font-medium block">Pertanyaan Induk:</span>
                        <span class="font-bold text-gray-900 mt-0.5 block leading-snug">
                            <span class="font-mono text-emerald-800">[{{ currentParentQuestion.code }}]</span> {{ currentParentQuestion.question_text }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-gray-700 text-xs mb-1">
                                Kode Opsi (Opsional)
                            </label>
                            <input
                                v-model="optionForm.code"
                                type="text"
                                :placeholder="`Contoh: ${currentParentQuestion ? currentParentQuestion.code + '-01' : 'OPT-01'}`"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono uppercase focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            />
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 text-xs mb-1">
                                Nomor Urut Opsi
                            </label>
                            <input
                                v-model.number="optionForm.order"
                                type="number"
                                min="1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 text-xs mb-1">
                            Teks Pilihan Jawaban <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="optionForm.option_text"
                            type="text"
                            placeholder="Contoh: Sangat Relevan / Sesuai Bidang"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                        />
                        <p v-if="optionFormErrors.option_text" class="text-red-600 text-xs mt-1">
                            {{ optionFormErrors.option_text }}
                        </p>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 text-xs mb-1">
                            Alur Lompatan / Jump To (Opsional)
                        </label>
                        <select
                            v-model="optionForm.jump_to"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                        >
                            <option value="">-- Lanjut ke Pertanyaan Berikutnya (Normal) --</option>
                            <option
                                v-for="target in activeJumpTargets"
                                :key="target"
                                :value="target"
                            >
                                {{ target }}
                            </option>
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Jika opsi ini dipilih responden, kuesioner akan melompat ke kode pertanyaan tersebut.</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button
                            type="button"
                            @click="closeOptionModal"
                            class="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmittingOption"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5 disabled:opacity-50"
                        >
                            <span>{{ isSubmittingOption ? 'Menyimpan...' : (isEditingOption ? 'Simpan Perubahan' : 'Tambah Opsi') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- ======================================================== -->
    <!-- MODAL VUE: DETAIL PERTANYAAN PRODI (IKON MATA)           -->
    <!-- ======================================================== -->
    <Teleport to="body">
        <div
            v-if="isDetailModalOpen && activeDetailQuestion"
            @click.self="closeDetailModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-xl shadow-xl max-w-lg w-full overflow-hidden border border-gray-100 animate-in fade-in zoom-in-95 duration-150"
            >
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-white">
                    <div>
                        <h3 class="font-bold text-gray-900 text-sm">
                            Detail Pertanyaan [{{ activeDetailQuestion.code }}]
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Rincian konfigurasi butir pertanyaan dan opsi pilihan responden.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeDetailModal"
                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-3.5 text-xs">
                    <div class="p-3 rounded-lg bg-gray-50 border border-gray-200 space-y-1.5">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold px-2 py-0.5 rounded bg-white border border-gray-200 text-gray-900 text-xs">
                                {{ activeDetailQuestion.code }}
                            </span>
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-bold"
                                :class="activeDetailQuestion.is_required ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ activeDetailQuestion.is_required ? 'Wajib Diisi' : 'Opsional' }}
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                {{ typeLabels[activeDetailQuestion.type] || activeDetailQuestion.type }}
                            </span>
                        </div>
                        <p class="font-bold text-gray-900 text-sm leading-snug pt-1">
                            {{ activeDetailQuestion.question_text }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200">
                            <span class="text-gray-500 block text-[11px]">Bagian Kuesioner:</span>
                            <span class="font-bold text-gray-800 text-xs">
                                {{ activeDetailQuestion.section ? `Bagian ${activeDetailQuestion.section.order}: ${activeDetailQuestion.section.title}` : '-' }}
                            </span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200">
                            <span class="text-gray-500 block text-[11px]">Nomor Urut Tampil:</span>
                            <span class="font-bold text-gray-800 text-xs">
                                Urutan #{{ activeDetailQuestion.order }}
                            </span>
                        </div>
                    </div>

                    <div class="p-3 rounded-lg bg-slate-50 border border-gray-200 space-y-2">
                        <span class="font-bold text-gray-800 text-xs block">
                            Daftar Opsi Jawaban ({{ activeDetailQuestion.options?.length || 0 }} Opsi):
                        </span>
                        <div
                            v-if="canHaveOptions(activeDetailQuestion.type) && activeDetailQuestion.options && activeDetailQuestion.options.length > 0"
                            class="space-y-1.5 max-h-48 overflow-y-auto pr-1"
                        >
                            <div
                                v-for="(opt, idx) in activeDetailQuestion.options"
                                :key="opt.id || idx"
                                class="p-2 bg-white rounded border border-gray-200 flex items-center justify-between text-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded bg-gray-100 font-mono font-bold flex items-center justify-center text-[10px] text-gray-700">
                                        {{ idx + 1 }}
                                    </span>
                                    <span class="font-medium text-gray-800">{{ opt.option_text }}</span>
                                </div>
                                <span
                                    v-if="opt.jump_to"
                                    class="px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 text-[10px] font-bold border border-purple-200"
                                >
                                    → Lompat ke: {{ opt.jump_to }}
                                </span>
                            </div>
                        </div>
                        <p v-else class="text-xs text-gray-400 italic">
                            Pertanyaan ini tidak memiliki daftar opsi pilihan jawaban terdaftar.
                        </p>
                    </div>

                    <div class="flex items-center justify-end pt-3 border-t border-gray-100">
                        <button
                            type="button"
                            @click="closeDetailModal"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-semibold rounded-lg transition-colors"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
