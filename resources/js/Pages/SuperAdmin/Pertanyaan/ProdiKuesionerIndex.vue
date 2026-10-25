<!--
  Halaman: Pengaturan Kuesioner Program Studi (Super Admin)
  File: resources/js/Pages/SuperAdmin/Pertanyaan/ProdiKuesionerIndex.vue

  Desain Standar Enterprise UKDW:
  - Dipilih Program Studi terlebih dahulu via Selector Bar.
  - Bebas Emoticon / Emojis (Menggunakan SVG Icons Resmi & Bahasa Formal).
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import SuperAdminSidebar from '../Components/Sidebar.vue';
import AdminFakultasSidebar from '../../AdminFakultas/Components/Sidebar.vue';
import AdminProdiSidebar from '../../AdminProdi/Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    role: {
        type: String,
        default: 'superadmin',
    },
    basePath: {
        type: String,
        default: '/superadmin/prodi-kuesioner',
    },
    dashboardPath: {
        type: String,
        default: '/superadmin/dashboard',
    },
    fakultas: {
        type: Object,
        default: () => ({}),
    },
    prodis: {
        type: Array,
        default: () => [],
    },
    selectedProdi: {
        type: Object,
        default: () => ({}),
    },
    sections: {
        type: Array,
        default: () => [],
    },
    questions: {
        type: Array,
        default: () => [],
    },
    availableJumpTargets: {
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

const isFaculty = computed(() => props.role === 'admin_fakultas');
const isProdi = computed(() => props.role === 'admin_prodi');
const pageTitle = computed(() => isFaculty.value
    ? 'Pengaturan Kuesioner Prodi - Admin Fakultas'
    : isProdi.value
        ? 'Pengaturan Kuesioner Prodi - Admin Prodi'
        : 'Pengaturan Kuesioner Prodi - Super Admin UKDW');

// Tab Aktif: 'sections' atau 'questions'
const activeTab = ref('sections');

// Selected Prodi State
const selectedProdiId = ref(props.selectedProdi?.id || (props.prodis[0]?.id || ''));

const handleProdiChange = () => {
    if (selectedProdiId.value) {
        router.get(props.basePath, {
            prodi_id: selectedProdiId.value,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    }
};

// ========================================================
// SECTION STATE & FILTERS
// ========================================================
const sectionSearchQuery = ref('');
const filteredSections = computed(() => {
    if (!sectionSearchQuery.value.trim()) return props.sections;
    const q = sectionSearchQuery.value.toLowerCase();
    return props.sections.filter(s =>
        s.title?.toLowerCase().includes(q) ||
        s.description?.toLowerCase().includes(q)
    );
});

// ========================================================
// QUESTION STATE & FILTERS
// ========================================================
const selectedSectionFilter = ref('all');
const selectedRequiredFilter = ref('all');
const questionSearchQuery = ref('');

const typeLabels = {
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
    text: 'Jawaban Singkat (Text)',
    textarea: 'Paragraf (Textarea)',
    number: 'Isian Angka (Number)',
    date: 'Tanggal',
    time: 'Waktu',
    file: 'Unggah Berkas (File)',
    header: 'Header / Judul Bagian',
};

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

const canHaveOptions = (type) => OPTION_SUPPORTED_TYPES.includes(type);

const filteredQuestions = computed(() => {
    return props.questions.filter((q) => {
        if (selectedSectionFilter.value !== 'all') {
            const secId = parseInt(selectedSectionFilter.value, 10);
            if (q.prodi_question_section_id !== secId) return false;
        }
        if (selectedRequiredFilter.value !== 'all') {
            const isReq = selectedRequiredFilter.value === 'wajib';
            if (Boolean(q.is_required) !== isReq) return false;
        }
        if (questionSearchQuery.value.trim()) {
            const query = questionSearchQuery.value.toLowerCase();
            const matchCode = q.code?.toLowerCase().includes(query);
            const matchText = q.question_text?.toLowerCase().includes(query);
            const matchType = (typeLabels[q.type] || q.type)?.toLowerCase().includes(query);
            return matchCode || matchText || matchType;
        }
        return true;
    });
});

// ========================================================
// MODAL HANDLERS: SECTION (TAMBAH / EDIT / REORDER / HAPUS)
// ========================================================
// Penjelasan Konsep JavaScript & Vue:
// 1. ref() membungkus data reaktif sehingga saat nilainya dimodifikasi, UI langsung merespons secara otomatis.
// 2. Teleport menempatkan elemen dialog modal ke hierarki DOM tertinggi (body) guna mencegah terpotong layout.
// 3. Form input terikat dua arah (two-way binding) dengan 'v-model' tanpa perlu manipulasi DOM langsung (Swal/getElementById).

// Mengontrol visibilitas modal bagian (section) kuesioner prodi
const isSectionModalOpen = ref(false);

// Menandai apakah sedang mengedit bagian yang sudah ada (true) atau menambah baru (false)
const isEditingSection = ref(false);

// Menyimpan ID bagian yang sedang disunting
const editingSectionId = ref(null);

// Status loading saat request pengiriman form section
const isSubmittingSection = ref(false);

// Objek data form untuk bagian kuesioner prodi
const sectionForm = ref({
    prodi_id: null,
    title: '',
    description: '',
    order: 1,
});

// Menyimpan pesan galat validasi field input section
const sectionFormErrors = ref({});

/**
 * Membuka modal tambah atau sunting bagian kuesioner prodi
 * @param {Object|null} sectionToEdit Data bagian jika mode edit, null jika tambah baru
 */
const openSectionModal = (sectionToEdit = null) => {
    isEditingSection.value = !!sectionToEdit;
    editingSectionId.value = sectionToEdit ? sectionToEdit.id : null;
    sectionFormErrors.value = {};

    if (sectionToEdit) {
        sectionForm.value = {
            prodi_id: props.selectedProdi?.id || null,
            title: sectionToEdit.title || '',
            description: sectionToEdit.description || '',
            order: sectionToEdit.order || 1,
        };
    } else {
        sectionForm.value = {
            prodi_id: props.selectedProdi?.id || null,
            title: '',
            description: '',
            order: props.sections.length + 1,
        };
    }

    isSectionModalOpen.value = true;
};

/**
 * Menutup modal bagian kuesioner prodi
 */
const closeSectionModal = () => {
    isSectionModalOpen.value = false;
    sectionFormErrors.value = {};
};

/**
 * Mengirim form bagian kuesioner prodi ke server (POST atau PUT)
 */
const submitSectionForm = () => {
    sectionFormErrors.value = {};
    const errors = {};

    if (!sectionForm.value.title.trim()) {
        errors.title = 'Judul bagian kuesioner prodi wajib diisi.';
    }

    if (Object.keys(errors).length > 0) {
        sectionFormErrors.value = errors;
        return;
    }

    isSubmittingSection.value = true;

    const payload = {
        prodi_id: props.selectedProdi.id,
        title: sectionForm.value.title.trim(),
        description: sectionForm.value.description.trim(),
        order: sectionForm.value.order ? parseInt(sectionForm.value.order, 10) : null,
    };

    if (isEditingSection.value) {
        router.put(`${props.basePath}/sections/${editingSectionId.value}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isSectionModalOpen.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Bagian kuesioner prodi diperbarui.',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
            onError: (errs) => {
                sectionFormErrors.value = errs;
            },
            onFinish: () => {
                isSubmittingSection.value = false;
            },
        });
    } else {
        router.post(`${props.basePath}/sections`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isSectionModalOpen.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Bagian kuesioner prodi ditambahkan.',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
            onError: (errs) => {
                sectionFormErrors.value = errs;
            },
            onFinish: () => {
                isSubmittingSection.value = false;
            },
        });
    }
};

const handleReorderSection = (sectionId, direction) => {
    router.post(`${props.basePath}/sections/reorder`, {
        id: sectionId,
        direction,
    }, {
        preserveScroll: true,
    });
};

const handleDeleteSection = (section) => {
    Swal.fire({
        title: 'Hapus Bagian Kuesioner Prodi?',
        text: `Apakah Anda yakin ingin menghapus bagian "${section.title}" beserta seluruh butir pertanyaannya?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Bagian',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`${props.basePath}/sections/${section.id}`, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Terhapus', 'Bagian kuesioner prodi berhasil dihapus.', 'success'),
            });
        }
    });
};

// ========================================================
// MODAL HANDLERS: QUESTION (TAMBAH / EDIT / REORDER / HAPUS)
// ========================================================
// Mengontrol visibilitas dialog modal pertanyaan prodi
const isQuestionModalOpen = ref(false);

// Menandai apakah sedang mengedit butir pertanyaan (true) atau menambah baru (false)
const isEditingQuestion = ref(false);

// Menyimpan ID butir pertanyaan yang sedang disunting
const editingQuestionId = ref(null);

// Status loading request pengiriman form pertanyaan
const isSubmittingQuestion = ref(false);

// Objek data formulir pertanyaan prodi
const questionForm = ref({
    prodi_id: null,
    code: '',
    question_text: '',
    type: 'single_choice',
    prodi_question_section_id: '',
    order: 1,
    is_required: true,
});

// Menyimpan pesan galat validasi form pertanyaan
const questionFormErrors = ref({});

/**
 * Membuka modal tambah atau sunting pertanyaan prodi
 * @param {Object|null} questionToEdit Data pertanyaan jika mode edit, null jika tambah baru
 */
const openQuestionModal = (questionToEdit = null) => {
    isEditingQuestion.value = !!questionToEdit;
    editingQuestionId.value = questionToEdit ? questionToEdit.id : null;
    questionFormErrors.value = {};

    if (questionToEdit) {
        questionForm.value = {
            prodi_id: props.selectedProdi?.id || null,
            code: questionToEdit.code || '',
            question_text: questionToEdit.question_text || '',
            type: questionToEdit.type || 'single_choice',
            prodi_question_section_id: questionToEdit.prodi_question_section_id || '',
            order: questionToEdit.order || 1,
            is_required: questionToEdit.is_required !== undefined ? Boolean(questionToEdit.is_required) : true,
        };
    } else {
        const defaultSecId = props.sections.length > 0 ? props.sections[0].id : '';
        questionForm.value = {
            prodi_id: props.selectedProdi?.id || null,
            code: `P${props.questions.length + 1}`,
            question_text: '',
            type: 'single_choice',
            prodi_question_section_id: defaultSecId,
            order: props.questions.length + 1,
            is_required: true,
        };
    }

    isQuestionModalOpen.value = true;
};

/**
 * Menutup modal pertanyaan prodi
 */
const closeQuestionModal = () => {
    isQuestionModalOpen.value = false;
    questionFormErrors.value = {};
};

/**
 * Mengirim formulir pertanyaan prodi ke server (POST atau PUT)
 */
const submitQuestionForm = () => {
    questionFormErrors.value = {};
    const errors = {};

    if (!questionForm.value.code.trim()) {
        errors.code = 'Kode pertanyaan wajib diisi.';
    }
    if (!questionForm.value.question_text.trim()) {
        errors.question_text = 'Teks pertanyaan wajib diisi.';
    }

    if (Object.keys(errors).length > 0) {
        questionFormErrors.value = errors;
        return;
    }

    isSubmittingQuestion.value = true;

    const payload = {
        prodi_id: props.selectedProdi.id,
        code: questionForm.value.code.trim(),
        question_text: questionForm.value.question_text.trim(),
        type: questionForm.value.type,
        prodi_question_section_id: questionForm.value.prodi_question_section_id
            ? parseInt(questionForm.value.prodi_question_section_id, 10)
            : null,
        order: questionForm.value.order ? parseInt(questionForm.value.order, 10) : null,
        is_required: questionForm.value.is_required,
    };

    if (isEditingQuestion.value) {
        router.put(`${props.basePath}/pertanyaan/${editingQuestionId.value}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isQuestionModalOpen.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Pertanyaan kuesioner prodi diperbarui.',
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
        router.post(`${props.basePath}/pertanyaan`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isQuestionModalOpen.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Pertanyaan kuesioner prodi ditambahkan.',
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

const handleReorderQuestion = (questionId, direction) => {
    router.post(`${props.basePath}/pertanyaan/reorder`, {
        id: questionId,
        direction,
    }, {
        preserveScroll: true,
    });
};

const handleDeleteQuestion = (question) => {
    Swal.fire({
        title: 'Hapus Pertanyaan Kuesioner?',
        text: `Apakah Anda yakin ingin menghapus pertanyaan [${question.code}] "${question.question_text}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Pertanyaan',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`${props.basePath}/pertanyaan/${question.id}`, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Terhapus', 'Pertanyaan kuesioner prodi berhasil dihapus.', 'success'),
            });
        }
    });
};

// ========================================================
// MODAL HANDLERS: OPSI JAWABAN (TAMBAH / EDIT / HAPUS)
// ========================================================
// Mengontrol visibilitas modal opsi jawaban
const isOptionModalOpen = ref(false);

// Menandai apakah sedang mengedit opsi (true) atau menambah opsi baru (false)
const isEditingOption = ref(false);

// Menyimpan ID opsi yang sedang diedit
const editingOptionId = ref(null);

// Menyimpan objek pertanyaan induk tempat opsi bernaung
const currentQuestionForOption = ref(null);

// Status loading request pengiriman form opsi
const isSubmittingOption = ref(false);

// Data formulir opsi jawaban
const optionForm = ref({
    prodi_question_id: null,
    code: '',
    option_text: '',
    jump_to: '',
    order: 1,
});

// Menyimpan galat validasi input opsi jawaban
const optionFormErrors = ref({});

/**
 * Membuka modal tambah atau sunting opsi pilihan jawaban
 * @param {Object} question Pertanyaan induk
 * @param {Object|null} optionToEdit Objek opsi jika edit, null jika tambah baru
 */
const openOptionModal = (question, optionToEdit = null) => {
    currentQuestionForOption.value = question;
    isEditingOption.value = !!optionToEdit;
    editingOptionId.value = optionToEdit ? optionToEdit.id : null;
    optionFormErrors.value = {};

    if (optionToEdit) {
        optionForm.value = {
            prodi_question_id: question.id,
            code: optionToEdit.code || '',
            option_text: optionToEdit.option_text || '',
            jump_to: optionToEdit.jump_to || '',
            order: optionToEdit.order || 1,
        };
    } else {
        const nextOrder = (question.options?.length || 0) + 1;
        optionForm.value = {
            prodi_question_id: question.id,
            code: `${question.code}-${String(nextOrder).padStart(2, '0')}`,
            option_text: '',
            jump_to: '',
            order: nextOrder,
        };
    }

    isOptionModalOpen.value = true;
};

/**
 * Menutup modal opsi pilihan jawaban
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

    if (!optionForm.value.code.trim()) {
        errors.code = 'Kode opsi wajib diisi.';
    }
    if (!optionForm.value.option_text.trim()) {
        errors.option_text = 'Teks opsi jawaban wajib diisi.';
    }

    if (Object.keys(errors).length > 0) {
        optionFormErrors.value = errors;
        return;
    }

    isSubmittingOption.value = true;

    const payload = {
        prodi_question_id: optionForm.value.prodi_question_id,
        code: optionForm.value.code.trim(),
        option_text: optionForm.value.option_text.trim(),
        jump_to: optionForm.value.jump_to || null,
        order: optionForm.value.order ? parseInt(optionForm.value.order, 10) : null,
    };

    if (isEditingOption.value) {
        router.put(`${props.basePath}/opsi/${editingOptionId.value}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isOptionModalOpen.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Opsi jawaban diperbarui.',
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
        router.post(`${props.basePath}/opsi`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isOptionModalOpen.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Opsi jawaban ditambahkan.',
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

const handleDeleteOption = (opt) => {
    Swal.fire({
        title: 'Hapus Opsi Jawaban?',
        text: `Apakah Anda yakin ingin menghapus opsi "${opt.option_text}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Opsi',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`${props.basePath}/opsi/${opt.id}`, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Terhapus', 'Opsi jawaban berhasil dihapus.', 'success'),
            });
        }
    });
};
</script>

<template>
    <Head :title="pageTitle" />

    <div class="min-h-screen bg-[#F8FAFC] text-slate-800 font-sans flex">
        <AdminFakultasSidebar v-if="isFaculty" :user="user" :fakultas="fakultas" />
        <AdminProdiSidebar v-else-if="isProdi" :user="user" :prodi="selectedProdi" />
        <SuperAdminSidebar v-else :user="user" />

        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Solid Hijau Resmi UKDW #0D542B -->
            <header class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full max-w-[1440px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <!-- Breadcrumbs -->
                        <nav class="flex items-center gap-2 text-xs text-emerald-100 font-medium mb-2.5">
                            <Link :href="dashboardPath" class="hover:text-white transition-colors flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                <span>Dashboard</span>
                            </Link>
                            <span class="text-emerald-300/60">/</span>
                            <span class="text-white font-bold bg-white/10 px-2 py-0.5 rounded-md">Kuesioner Per Prodi</span>
                        </nav>

                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white tracking-tight">
                            Pengaturan Kuesioner Program Studi
                        </h1>
                        <p class="text-emerald-100/90 text-xs sm:text-sm font-normal mt-1.5 max-w-2xl leading-relaxed">
                            Pilih program studi untuk mengelola struktur bagian (section) dan butir pertanyaan kuesioner spesifik prodi.
                        </p>
                    </div>
                </div>
            </header>

            <main class="admin-page-main w-full max-w-[1440px] mx-auto -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
                <!-- Bar Pemilihan Program Studi (2 Tone) -->
                <div class="bg-white rounded-xl p-5 border border-gray-300 shadow-2xs">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Program Studi Target
                            </label>
                            <select
                                v-model="selectedProdiId"
                                @change="handleProdiChange"
                                class="w-full md:w-96 px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm font-bold text-gray-900 focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] focus:outline-none"
                            >
                                <option v-for="p in prodis" :key="p.id" :value="p.id">
                                    {{ p.kode_prodi ? `[${p.kode_prodi}] ` : '' }}{{ p.nama_prodi }} {{ p.fakultas ? `(${p.fakultas.nama_fakultas})` : '' }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-center gap-3 bg-gray-50/80 p-3 rounded-lg border border-gray-200">
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-slate-900">{{ stats.total_sections }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Bagian / Section</span>
                            </div>
                            <div class="h-8 w-px bg-gray-200"></div>
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-[#0D542B]">{{ stats.total_questions }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Butir Kuesioner</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigasi Tab -->
                <div class="flex border-b border-slate-200 space-x-4">
                    <button
                        @click="activeTab = 'sections'"
                        class="pb-3 px-3 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2"
                        :class="[
                            activeTab === 'sections'
                                ? 'border-[#0D542B] text-[#0D542B]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <span>Daftar Bagian (Section)</span>
                    </button>
                    <button
                        @click="activeTab = 'questions'"
                        class="pb-3 px-3 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2"
                        :class="[
                            activeTab === 'questions'
                                ? 'border-[#0D542B] text-[#0D542B]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Daftar Pertanyaan (Kuesioner)</span>
                    </button>
                </div>

                <!-- TAB 1: SECTION PRODI MANAGEMENT -->
                <div v-if="activeTab === 'sections'" class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="w-full sm:w-72">
                            <input
                                type="text"
                                v-model="sectionSearchQuery"
                                placeholder="Cari judul bagian section..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] focus:outline-none bg-white"
                            />
                        </div>
                        <button
                            type="button"
                            @click="openSectionModal(null)"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <span>+ Tambah Section Baru</span>
                        </button>
                    </div>

                    <!-- Tabel Section (2 Tone) -->
                    <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                <tr>
                                    <th class="px-4 py-3 text-center w-20">Urutan</th>
                                    <th class="px-4 py-3">Judul Bagian Section</th>
                                    <th class="px-4 py-3">Deskripsi & Petunjuk Pengisian</th>
                                    <th class="px-4 py-3 text-center">Jumlah Pertanyaan</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredSections.length === 0">
                                    <td colspan="5" class="p-8 text-center text-gray-400 italic">
                                        Belum ada bagian (section) kuesioner yang dibuat untuk {{ selectedProdi.nama_prodi }}.
                                    </td>
                                </tr>
                                <tr v-for="(sec, idx) in filteredSections" :key="sec.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-1 font-bold text-gray-700">
                                            <span>#{{ sec.order }}</span>
                                            <div class="flex flex-col gap-0.5 ml-1">
                                                <button
                                                    type="button"
                                                    @click="handleReorderSection(sec.id, 'up')"
                                                    :disabled="idx === 0"
                                                    class="p-0.5 rounded text-gray-400 hover:text-emerald-700 disabled:opacity-30 cursor-pointer"
                                                    title="Naikkan Urutan"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="handleReorderSection(sec.id, 'down')"
                                                    :disabled="idx === filteredSections.length - 1"
                                                    class="p-0.5 rounded text-gray-400 hover:text-emerald-700 disabled:opacity-30 cursor-pointer"
                                                    title="Turunkan Urutan"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-bold text-sm text-gray-900">
                                        {{ sec.title }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 max-w-sm">
                                        {{ sec.description || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-700 font-medium text-xs">
                                        {{ sec.questions_count ?? 0 }} Pertanyaan
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                @click="openSectionModal(sec)"
                                                class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Edit Section"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="handleDeleteSection(sec)"
                                                class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                title="Hapus Section"
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
                </div>

                <!-- TAB 2: PERTANYAAN PRODI MANAGEMENT -->
                <div v-if="activeTab === 'questions'" class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-gray-300 shadow-2xs">
                        <div class="flex flex-wrap items-center gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Filter Section</label>
                                <select v-model="selectedSectionFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Section</option>
                                    <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.title }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Sifat Pengisian</label>
                                <select v-model="selectedRequiredFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Sifat</option>
                                    <option value="wajib">Wajib (Required)</option>
                                    <option value="opsional">Opsional</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Pencarian Teks</label>
                                <input type="text" v-model="questionSearchQuery" placeholder="Cari kode atau pertanyaan..." class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs w-48 bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]" />
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="openQuestionModal(null)"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer shrink-0"
                        >
                            <span>+ Tambah Pertanyaan Prodi</span>
                        </button>
                    </div>

                    <!-- Tabel Pertanyaan (2 Tone) -->
                    <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                <tr>
                                    <th class="px-3 py-3 text-center w-20">Urutan</th>
                                    <th class="px-3 py-3 w-20">Kode</th>
                                    <th class="px-4 py-3">Butir Pertanyaan Kuesioner</th>
                                    <th class="px-3 py-3">Tipe & Bagian</th>
                                    <th class="px-4 py-3">Opsi Jawaban & Percabangan</th>
                                    <th class="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredQuestions.length === 0">
                                    <td colspan="6" class="p-8 text-center text-gray-400 italic">
                                        Belum ada butir pertanyaan kuesioner untuk {{ selectedProdi.nama_prodi }}.
                                    </td>
                                </tr>
                                <tr v-for="(q, idx) in filteredQuestions" :key="q.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-3 py-3.5 text-center">
                                        <div class="flex items-center justify-center gap-1 font-bold text-gray-700">
                                            <span>#{{ q.order }}</span>
                                            <div class="flex flex-col gap-0.5 ml-1">
                                                <button
                                                    type="button"
                                                    @click="handleReorderQuestion(q.id, 'up')"
                                                    :disabled="idx === 0"
                                                    class="p-0.5 text-gray-400 hover:text-emerald-700 disabled:opacity-30 cursor-pointer"
                                                    title="Naikkan Urutan"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="handleReorderQuestion(q.id, 'down')"
                                                    :disabled="idx === filteredQuestions.length - 1"
                                                    class="p-0.5 text-gray-400 hover:text-emerald-700 disabled:opacity-30 cursor-pointer"
                                                    title="Turunkan Urutan"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 font-bold font-mono text-gray-900">
                                        {{ q.code }}
                                    </td>
                                    <td class="px-4 py-3.5 max-w-sm">
                                        <div class="font-bold text-gray-900 text-xs leading-snug">
                                            {{ q.question_text }}
                                        </div>
                                        <div class="mt-1 text-xs">
                                            <span :class="q.is_required ? 'font-bold text-gray-900' : 'text-gray-400'">
                                                {{ q.is_required ? 'Wajib' : 'Opsional' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 max-w-xs">
                                        <div class="font-semibold text-gray-800">
                                            {{ typeLabels[q.type] || q.type }}
                                        </div>
                                        <div class="text-[10px] text-gray-500 mt-0.5">
                                            {{ q.section?.title || 'Biasa' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 max-w-md">
                                        <div v-if="canHaveOptions(q.type)">
                                            <div v-if="q.options && q.options.length > 0" class="space-y-1 mb-2">
                                                <div v-for="opt in q.options" :key="opt.id" class="p-1.5 bg-gray-50 border border-gray-200 rounded flex items-center justify-between gap-2 text-[11px]">
                                                    <div class="min-w-0">
                                                        <span class="font-mono font-bold text-gray-500 me-1">[{{ opt.code }}]</span>
                                                        <span class="text-gray-800 font-medium">{{ opt.option_text }}</span>
                                                        <span v-if="opt.jump_to" class="ms-1.5 text-gray-500 font-medium text-[10px]">
                                                            &rarr; Lanjut ke: {{ opt.jump_to }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-2 shrink-0">
                                                        <button type="button" @click="openOptionModal(q, opt)" class="text-gray-500 hover:text-gray-900 font-medium cursor-pointer">Edit</button>
                                                        <span class="text-gray-300">|</span>
                                                        <button type="button" @click="handleDeleteOption(opt)" class="text-gray-400 hover:text-rose-600 font-medium cursor-pointer">Hapus</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                @click="openOptionModal(q, null)"
                                                class="px-2.5 py-1 text-[11px] font-semibold text-gray-700 bg-white hover:bg-gray-50 rounded border border-gray-300 flex items-center gap-1 cursor-pointer transition-colors mt-1"
                                            >
                                                <span>+ Tambah Opsi Jawaban</span>
                                            </button>
                                        </div>
                                        <div v-else class="text-gray-400 italic text-[10px]">
                                            Tipe isian teks/bebas (tanpa pilihan opsi).
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                @click="openQuestionModal(q)"
                                                class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Edit Pertanyaan"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="handleDeleteQuestion(q)"
                                                class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                title="Hapus Pertanyaan"
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
                </div>
            </main>

            <footer class="text-gray-400 text-xs text-center mt-12 py-6 border-t border-gray-100">
                &copy; {{ new Date().getFullYear() }} Universitas Kristen Duta Wacana. Hak Cipta Dilindungi.
            </footer>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL VUE: TAMBAH / SUNTING BAGIAN KUESIONER PRODI       -->
    <!-- ======================================================== -->
    <Teleport to="body">
        <div
            v-if="isSectionModalOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden border border-gray-100 animate-in fade-in zoom-in-95 duration-150"
            >
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-white">
                    <div>
                        <h3 class="font-bold text-gray-900 text-sm">
                            {{ isEditingSection ? 'Sunting Bagian Kuesioner Prodi' : 'Tambah Bagian Kuesioner Prodi Baru' }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Bagian bab pengelompokan pertanyaan instrumen program studi.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeSectionModal"
                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitSectionForm" class="p-5 space-y-4">
                    <div>
                        <label class="block font-semibold text-gray-700 text-xs mb-1">
                            Program Studi Target
                        </label>
                        <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg font-bold text-xs text-[#0D542B]">
                            {{ selectedProdi?.nama_prodi || 'Program Studi Target' }}
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 text-xs mb-1">
                            Judul Bagian (Section Title) <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="sectionForm.title"
                            type="text"
                            placeholder="Contoh: Evaluasi Capaian Pembelajaran Prodi"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                        />
                        <p v-if="sectionFormErrors.title" class="text-red-600 text-xs mt-1">
                            {{ sectionFormErrors.title }}
                        </p>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 text-xs mb-1">
                            Deskripsi / Petunjuk Pengisian (Opsional)
                        </label>
                        <textarea
                            v-model="sectionForm.description"
                            rows="3"
                            placeholder="Ketikkan petunjuk pengisian bagi responden alumni..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B] leading-relaxed"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 text-xs mb-1">
                            Nomor Urut Posisi
                        </label>
                        <input
                            v-model.number="sectionForm.order"
                            type="number"
                            min="1"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button
                            type="button"
                            @click="closeSectionModal"
                            class="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmittingSection"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5 disabled:opacity-50"
                        >
                            <span>{{ isEditingSection ? 'Simpan Perubahan' : 'Tambah Bagian' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- ======================================================== -->
    <!-- MODAL VUE: TAMBAH / SUNTING PERTANYAAN KUESIONER PRODI   -->
    <!-- ======================================================== -->
    <Teleport to="body">
        <div
            v-if="isQuestionModalOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-xl shadow-xl max-w-lg w-full overflow-hidden border border-gray-100 animate-in fade-in zoom-in-95 duration-150"
            >
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-white">
                    <div>
                        <h3 class="font-bold text-gray-900 text-sm">
                            {{ isEditingQuestion ? 'Sunting Pertanyaan Kuesioner Prodi' : 'Tambah Pertanyaan Kuesioner Prodi Baru' }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Konfigurasi instrumen butir pertanyaan khusus Program Studi {{ selectedProdi?.nama_prodi }}.
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
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-gray-700 text-xs mb-1">
                                Kode Pertanyaan <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="questionForm.code"
                                type="text"
                                placeholder="Contoh: P01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono uppercase font-bold focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            />
                            <p v-if="questionFormErrors.code" class="text-red-600 text-xs mt-1">
                                {{ questionFormErrors.code }}
                            </p>
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 text-xs mb-1">
                                Bagian / Section <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="questionForm.prodi_question_section_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            >
                                <option v-if="props.sections.length === 0" value="">
                                    -- Buat Section Baru Dulu --
                                </option>
                                <option
                                    v-for="s in props.sections"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.title }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 text-xs mb-1">
                            Pertanyaan / Teks Kuesioner <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="questionForm.question_text"
                            rows="3"
                            placeholder="Ketik kalimat pertanyaan..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B] leading-relaxed"
                        ></textarea>
                        <p v-if="questionFormErrors.question_text" class="text-red-600 text-xs mt-1">
                            {{ questionFormErrors.question_text }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-gray-700 text-xs mb-1">
                                Tipe Pertanyaan <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="questionForm.type"
                                class="w-full px-2 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            >
                                <option
                                    v-for="(label, val) in prodiSelectableTypes"
                                    :key="val"
                                    :value="val"
                                >
                                    {{ label }}
                                </option>
                            </select>
                            <p class="text-[10px] text-emerald-700 font-semibold mt-1">Opsi default otomatis dibuat & dapat disesuaikan.</p>
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 text-xs mb-1">
                                Nomor Urut
                            </label>
                            <input
                                v-model.number="questionForm.order"
                                type="number"
                                min="1"
                                class="w-full px-2 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input
                            id="vue-q-req"
                            v-model="questionForm.is_required"
                            type="checkbox"
                            class="rounded text-[#0D542B] focus:ring-[#0D542B]"
                        />
                        <label for="vue-q-req" class="font-semibold text-xs text-gray-700 cursor-pointer">
                            Wajib diisi oleh alumni responden
                        </label>
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
                            <span>{{ isEditingQuestion ? 'Simpan Perubahan' : 'Tambah Pertanyaan' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- ======================================================== -->
    <!-- MODAL VUE: TAMBAH / SUNTING OPSI JAWABAN KUESIONER PRODI -->
    <!-- ======================================================== -->
    <Teleport to="body">
        <div
            v-if="isOptionModalOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden border border-gray-100 animate-in fade-in zoom-in-95 duration-150"
            >
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-white">
                    <div>
                        <h3 class="font-bold text-gray-900 text-sm">
                            {{ isEditingOption ? `Edit Opsi [${currentQuestionForOption?.code || ''}]` : `Tambah Opsi Jawaban [${currentQuestionForOption?.code || ''}]` }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Konfigurasi opsi pilihan dan percabangan alur kuesioner.
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
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-gray-700 text-xs mb-1">
                                Kode Opsi <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="optionForm.code"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono uppercase focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            />
                            <p v-if="optionFormErrors.code" class="text-red-600 text-xs mt-1">
                                {{ optionFormErrors.code }}
                            </p>
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
                            Teks Opsi Jawaban <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="optionForm.option_text"
                            type="text"
                            placeholder="Contoh: Sangat Relevan"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                        />
                        <p v-if="optionFormErrors.option_text" class="text-red-600 text-xs mt-1">
                            {{ optionFormErrors.option_text }}
                        </p>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 text-xs mb-1">
                            Alur Percabangan (Lompat Ke / Jump To)
                        </label>
                        <select
                            v-model="optionForm.jump_to"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                        >
                            <option value="">-- Tanpa Percabangan (Lanjut Normal) --</option>
                            <option
                                v-for="code in props.availableJumpTargets"
                                :key="code"
                                :value="code"
                            >
                                Lompat ke Pertanyaan: [{{ code }}]
                            </option>
                        </select>
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
                            <span>{{ isEditingOption ? 'Simpan Opsi' : 'Tambah Opsi' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
