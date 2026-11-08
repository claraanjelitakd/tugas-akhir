<!--
  Halaman: Pengaturan Kuesioner Program Studi lintas peran
  File: resources/js/Pages/Shared/Kuesioner/ProdiKuesionerIndex.vue

  Desain Standar Enterprise UKDW:
  - Dipilih Program Studi terlebih dahulu via Selector Bar.
  - Bebas Emoticon / Emojis (Menggunakan SVG Icons Resmi & Bahasa Formal).
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import SuperAdminSidebar from '@/Pages/SuperAdmin/Components/Sidebar.vue';
import AdminFakultasSidebar from '@/Pages/AdminFakultas/Components/Sidebar.vue';
import AdminProdiSidebar from '@/Pages/AdminProdi/Components/Sidebar.vue';

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
    selectedSectionId: {
        type: Number,
        default: null,
    },
    questions: {
        type: Array,
        default: () => [],
    },
    allSections: {
        type: Array,
        default: () => [],
    },
    bankQuestions: {
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
// Menyimpan filter section dari navigasi halaman Section agar konteks pertanyaan tetap terbawa.
const selectedSectionFilter = ref(props.selectedSectionId ? String(props.selectedSectionId) : 'all');
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

/**
 * Menentukan apakah akun boleh mengubah master pertanyaan yang sedang dipakai.
 */
const canEditQuestion = (question) => Boolean(question?.can_edit);

const filteredQuestions = computed(() => {
    return props.questions.filter((q) => {
        if (selectedSectionFilter.value !== 'all') {
            const secId = parseInt(selectedSectionFilter.value, 10);
            if (q.section_id !== secId) return false;
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
// BANK PERTANYAAN (MODAL SELECTION & ATTACH)
// ========================================================
const isBankModalOpen = ref(false);
const bankSearchQuery = ref('');
const bankOriginFilter = ref('all');
const targetSectionIdForBank = ref(props.sections[0]?.id || '');
const selectedBankQuestionIds = ref([]);
const isSubmittingBank = ref(false);

const availableOrigins = computed(() => {
    const list = new Set();
    props.bankQuestions.forEach(q => {
        if (q.asal) list.add(q.asal);
    });
    return Array.from(list);
});

const filteredBankQuestions = computed(() => {
    return props.bankQuestions.filter(q => {
        if (bankOriginFilter.value !== 'all' && q.asal !== bankOriginFilter.value) {
            return false;
        }
        if (bankSearchQuery.value.trim()) {
            const query = bankSearchQuery.value.toLowerCase();
            const matchCode = q.kode?.toLowerCase().includes(query);
            const matchText = q.teks?.toLowerCase().includes(query);
            const matchOrigin = q.asal?.toLowerCase().includes(query);
            return matchCode || matchText || matchOrigin;
        }
        return true;
    });
});

const openBankModal = () => {
    if (!props.sections.length) {
        Swal.fire({
            icon: 'warning',
            title: 'Section Diperlukan',
            text: 'Silakan buat minimal satu bagian (section) kuesioner terlebih dahulu sebelum mengambil pertanyaan dari Bank Soal.',
            confirmButtonColor: '#0D542B',
        });
        return;
    }
    targetSectionIdForBank.value = selectedSectionFilter.value !== 'all' ? selectedSectionFilter.value : props.sections[0]?.id;
    selectedBankQuestionIds.value = [];
    bankSearchQuery.value = '';
    bankOriginFilter.value = 'all';
    isBankModalOpen.value = true;
};

const closeBankModal = () => {
    isBankModalOpen.value = false;
    selectedBankQuestionIds.value = [];
};

const toggleBankSelection = (id) => {
    const idx = selectedBankQuestionIds.value.indexOf(id);
    if (idx > -1) {
        selectedBankQuestionIds.value.splice(idx, 1);
    } else {
        selectedBankQuestionIds.value.push(id);
    }
};

const submitBankSelection = () => {
    if (!selectedBankQuestionIds.value.length) {
        Swal.fire({
            icon: 'warning',
            title: 'Pilih Pertanyaan',
            text: 'Silakan centang minimal satu butir pertanyaan yang ingin dimasukkan.',
            confirmButtonColor: '#0D542B',
        });
        return;
    }
    if (!targetSectionIdForBank.value) {
        Swal.fire({
            icon: 'warning',
            title: 'Pilih Bagian Kuesioner',
            text: 'Silakan pilih bagian (section) tujuan penempatan pertanyaan.',
            confirmButtonColor: '#0D542B',
        });
        return;
    }

    isSubmittingBank.value = true;
    router.post(`${props.basePath}/bank/attach`, {
        prodi_id: props.selectedProdi?.id,
        section_id: targetSectionIdForBank.value,
        pertanyaan_ids: selectedBankQuestionIds.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isBankModalOpen.value = false;
            selectedBankQuestionIds.value = [];
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'Pertanyaan dari Bank Soal berhasil disematkan ke kuesioner prodi.',
                timer: 1600,
                showConfirmButton: false,
            });
        },
        onError: (errs) => {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: Object.values(errs)[0] || 'Terjadi kesalahan saat menambahkan pertanyaan.',
                confirmButtonColor: '#0D542B',
            });
        },
        onFinish: () => {
            isSubmittingBank.value = false;
        },
    });
};

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
// QUESTION PRODI MANAGEMENT (UNIFIED & INTERACTIVE)
// ========================================================
// Mengontrol visibilitas dialog modal pertanyaan prodi
const isQuestionModalOpen = ref(false);

// Menandai apakah sedang mengedit butir pertanyaan (true) atau menambah baru (false)
const isEditingQuestion = ref(false);

// Menyimpan ID butir pertanyaan yang sedang disunting
const editingQuestionId = ref(null);

// Status loading request pengiriman form pertanyaan
const isSubmittingQuestion = ref(false);

// Status loading saat tombol toggle aktif/nonaktif ditekan
const togglingQuestionId = ref(null);

// Objek data formulir pertanyaan prodi
const questionForm = ref({
    prodi_id: null,
    code: '',
    question_text: '',
    type: 'single_choice',
    section_id: '',
    target_section_ids: [],
    order: 1,
    is_required: true,
    is_reusable: true,
    options: [],
});

// Menyimpan pesan galat validasi form pertanyaan
const questionFormErrors = ref({});

/**
 * Peringatan pencegahan duplikasi butir pertanyaan (Anti-Double Detection).
 * Jika kode pertanyaan sudah terdaftar di Bank Soal, beri tahu admin bahwa pertanyaan
 * akan ditautkan alih-alih membuat baris ganda.
 */
const duplicateMasterWarning = computed(() => {
    if (!questionForm.value.code) return null;
    const inputCode = questionForm.value.code.trim().toUpperCase();
    if (!inputCode) return null;
    const found = props.bankQuestions.find(bq => bq.kode?.toUpperCase() === inputCode);
    if (found && (!isEditingQuestion.value || found.id !== editingQuestionId.value)) {
        return found;
    }
    return null;
});

/**
 * Menambah baris opsi baru pada form pertanyaan
 */
const addOptionToForm = () => {
    questionForm.value.options.push({
        label: '',
        score: null,
    });
};

/**
 * Menghapus baris opsi dari form pertanyaan
 */
const removeOptionFromForm = (idx) => {
    questionForm.value.options.splice(idx, 1);
};

/**
 * Toggle status tampil pertanyaan pada placement section secara interaktif.
 */
const handleToggleActive = (question) => {
    togglingQuestionId.value = question.id;
    router.post(`${props.basePath}/penempatan/${question.placement_id}/toggle-active`, {
        prodi_id: props.selectedProdi?.id,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            // Pembaruan reaktif lokal seketika
            question.is_placement_active = !question.is_placement_active;
        },
        onFinish: () => {
            togglingQuestionId.value = null;
        },
    });
};

/**
 * Membuka modal tambah atau sunting pertanyaan prodi
 * @param {Object|null} questionToEdit Data pertanyaan jika mode edit, null jika tambah baru
 */
const openQuestionModal = (questionToEdit = null) => {
    if (questionToEdit && !canEditQuestion(questionToEdit)) return;

    isEditingQuestion.value = !!questionToEdit;
    editingQuestionId.value = questionToEdit ? questionToEdit.id : null;
    questionFormErrors.value = {};

    if (questionToEdit) {
        questionForm.value = {
            prodi_id: props.selectedProdi?.id || null,
            code: questionToEdit.code || '',
            question_text: questionToEdit.question_text || '',
            type: questionToEdit.type || 'single_choice',
            section_id: questionToEdit.section_id || (props.sections[0]?.id || ''),
            target_section_ids: [],
            order: questionToEdit.order || 1,
            is_required: questionToEdit.is_required !== undefined ? Boolean(questionToEdit.is_required) : true,
            is_reusable: questionToEdit.is_reusable !== undefined ? Boolean(questionToEdit.is_reusable) : true,
            options: (questionToEdit.options || []).map(opt => ({
                label: opt.option_text || '',
                score: opt.score || null,
            })),
        };
    } else {
        const defaultSecId = props.sections.length > 0 ? props.sections[0].id : '';
        questionForm.value = {
            prodi_id: props.selectedProdi?.id || null,
            code: `P${props.questions.length + 1}`,
            question_text: '',
            type: 'single_choice',
            section_id: defaultSecId,
            target_section_ids: [],
            order: props.questions.length + 1,
            is_required: true,
            is_reusable: true,
            options: [
                { label: 'Pilihan 1', score: null },
                { label: 'Pilihan 2', score: null },
            ],
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
    if (!questionForm.value.section_id) {
        errors.section_id = 'Pilih bagian kuesioner utama.';
    }

    if (Object.keys(errors).length > 0) {
        questionFormErrors.value = errors;
        return;
    }

    isSubmittingQuestion.value = true;

    const payload = {
        prodi_id: props.selectedProdi.id,
        code: questionForm.value.code.trim().toUpperCase(),
        question_text: questionForm.value.question_text.trim(),
        type: questionForm.value.type,
        section_id: parseInt(questionForm.value.section_id, 10),
        target_section_ids: questionForm.value.target_section_ids || [],
        order: questionForm.value.order ? parseInt(questionForm.value.order, 10) : null,
        is_required: questionForm.value.is_required,
        is_reusable: questionForm.value.is_reusable,
        options: questionForm.value.options.filter(o => o.label && o.label.trim() !== ''),
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

const handleReorderQuestion = (placementId, direction) => {
    router.post(`${props.basePath}/pertanyaan/reorder`, {
        prodi_id: props.selectedProdi?.id,
        placement_id: placementId,
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
            router.delete(`${props.basePath}/pertanyaan/${question.placement_id}`, {
                data: {
                    prodi_id: props.selectedProdi?.id,
                },
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
    question_id: null,
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
    if (!canEditQuestion(question)) return;

    currentQuestionForOption.value = question;
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
        question_id: optionForm.value.question_id,
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

    <div class="min-h-screen bg-[#F8FAFC] text-slate-800 font-sans flex min-w-[1280px]">
        <AdminFakultasSidebar v-if="isFaculty" :user="user" :fakultas="fakultas" />
        <AdminProdiSidebar v-else-if="isProdi" :user="user" :prodi="selectedProdi" />
        <SuperAdminSidebar v-else :user="user" />

        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Solid Hijau Resmi UKDW #0D542B -->
            <header class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full flex flex-col md:flex-row md:items-center justify-between gap-6">
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

            <main class="admin-page-main w-full -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
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

                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="openBankModal"
                                class="px-3.5 py-2 border border-[#0D542B] text-[#0D542B] hover:bg-emerald-50 text-xs font-bold rounded-lg transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                                title="Pilih pertanyaan yang sudah ada dari Bank Soal universitas atau prodi lain"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <span>Pilih dari Bank Pertanyaan</span>
                            </button>
                            <button
                                type="button"
                                @click="openQuestionModal(null)"
                                class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <span>+ Buat Pertanyaan Baru</span>
                            </button>
                        </div>
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
                                    <th class="px-3 py-3 text-center w-28">Status</th>
                                    <th class="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredQuestions.length === 0">
                                    <td colspan="7" class="p-8 text-center text-gray-400 italic">
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
                                                    @click="handleReorderQuestion(q.placement_id, 'up')"
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
                                                    @click="handleReorderQuestion(q.placement_id, 'down')"
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
                                        <div class="mt-1.5 flex items-center gap-2 flex-wrap text-xs">
                                            <span :class="q.is_required ? 'font-bold text-gray-900' : 'text-gray-400'">
                                                {{ q.is_required ? 'Wajib' : 'Opsional' }}
                                            </span>
                                            <span v-if="q.asal" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                                Asal: {{ q.asal }}
                                            </span>
                                            <span v-if="q.is_reusable" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                Bank Bersama
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
                                                        <span v-if="opt.score !== null && opt.score !== undefined" class="ms-1 text-emerald-700 font-semibold text-[10px]">
                                                            (Skor: {{ opt.score }})
                                                        </span>
                                                        <span v-if="opt.jump_to" class="ms-1.5 text-gray-500 font-medium text-[10px]">
                                                            &rarr; Lanjut ke: {{ opt.jump_to }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-2 shrink-0">
                                                        <button
                                                            type="button"
                                                            :disabled="!canEditQuestion(q)"
                                                            @click="openOptionModal(q, opt)"
                                                            class="font-medium text-gray-500 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40"
                                                            :title="canEditQuestion(q) ? 'Edit opsi' : 'Opsi dikelola oleh pemilik pertanyaan'"
                                                        >
                                                            Edit
                                                        </button>
                                                        <span class="text-gray-300">|</span>
                                                        <button
                                                            type="button"
                                                            :disabled="!canEditQuestion(q)"
                                                            @click="handleDeleteOption(opt)"
                                                            class="font-medium text-gray-400 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-40"
                                                            :title="canEditQuestion(q) ? 'Hapus opsi' : 'Opsi dikelola oleh pemilik pertanyaan'"
                                                        >
                                                            Hapus
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                :disabled="!canEditQuestion(q)"
                                                @click="openOptionModal(q, null)"
                                                class="mt-1 flex items-center gap-1 rounded border border-gray-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                :title="canEditQuestion(q) ? 'Tambah opsi jawaban' : 'Opsi dikelola oleh pemilik pertanyaan'"
                                            >
                                                <span>+ Tambah Opsi Jawaban</span>
                                            </button>
                                        </div>
                                        <div v-else class="text-gray-400 italic text-[10px]">
                                            Tipe isian teks/bebas (tanpa pilihan opsi).
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-center">
                                        <button
                                            type="button"
                                            @click="handleToggleActive(q)"
                                            :disabled="togglingQuestionId === q.id"
                                            class="inline-flex items-center justify-center px-2.5 py-1 rounded-md text-[11px] font-medium transition-colors cursor-pointer disabled:opacity-50"
                                            :class="q.is_placement_active ? 'bg-emerald-50 text-[#0D542B] border border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 border border-gray-200 hover:bg-gray-200'"
                                            :title="q.is_placement_active ? 'Pertanyaan tampil pada section ini (klik untuk menonaktifkan)' : 'Pertanyaan tidak tampil pada section ini (klik untuk mengaktifkan)'"
                                        >
                                            {{ q.is_placement_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="px-3 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                :disabled="!canEditQuestion(q)"
                                                @click="openQuestionModal(q)"
                                                class="cursor-pointer rounded-lg p-1.5 text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40"
                                                :title="canEditQuestion(q) ? 'Edit Pertanyaan' : 'Pertanyaan dikelola oleh pemilik'"
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
                class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl"
            >
                <div class="flex shrink-0 items-start justify-between border-b border-gray-200 bg-white px-6 py-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ isEditingQuestion ? 'Sunting Pertanyaan Kuesioner Prodi' : 'Tambah Pertanyaan Kuesioner Prodi Baru' }}
                        </h3>
                        <p class="mt-0.5 text-[11px] text-gray-500">
                            Konfigurasi instrumen butir pertanyaan khusus Program Studi {{ selectedProdi?.nama_prodi }}.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeQuestionModal"
                        class="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitQuestionForm" class="min-h-0 flex-1 space-y-4 overflow-y-auto p-6 text-xs">
                    <!-- Banner Anti-Double Detection -->
                    <div v-if="duplicateMasterWarning" class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-[#0D542B]">
                        <div class="font-bold flex items-center gap-1.5">
                            <span>Pertanyaan Terdaftar di Bank Soal</span>
                        </div>
                        <p class="mt-1 text-emerald-800 leading-snug">
                            Kode <strong>[{{ duplicateMasterWarning.kode }}]</strong> telah tercatat di Bank Soal: "<em>{{ duplicateMasterWarning.teks }}</em>" (Milik: {{ duplicateMasterWarning.asal }}).
                            Menyimpan form ini akan langsung menautkan butir pertanyaan master ini tanpa membuat data ganda di sistem.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block font-bold text-gray-700">
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
                            <label class="mb-1 block font-bold text-gray-700">
                                Bagian / Section Utama <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="questionForm.section_id"
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
                            <p v-if="questionFormErrors.section_id" class="text-red-600 text-xs mt-1">
                                {{ questionFormErrors.section_id }}
                            </p>
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
                            <label class="mb-1 block font-bold text-gray-700">
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
                        </div>
                        <div>
                            <label class="mb-1 block font-bold text-gray-700">
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

                    <!-- Penempatan Lintas Bagian (Multi-Placement) -->
                    <div v-if="props.allSections && props.allSections.length > 1" class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
                        <label class="mb-1 block font-bold text-gray-800">
                            Penempatan Bagian Tambahan (Multi-Placement)
                        </label>
                        <p class="text-[11px] text-gray-500 mb-2">
                            Centang jika pertanyaan ini juga ingin dimunculkan pada bagian lain di prodi atau universitas tanpa duplikasi data.
                        </p>
                        <div class="max-h-28 overflow-y-auto space-y-1.5 pr-1">
                            <label
                                v-for="sec in props.allSections.filter(s => s.id !== questionForm.section_id)"
                                :key="sec.id"
                                class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer hover:bg-gray-100 p-1 rounded"
                            >
                                <input
                                    type="checkbox"
                                    :value="sec.id"
                                    v-model="questionForm.target_section_ids"
                                    class="rounded text-[#0D542B] focus:ring-[#0D542B]"
                                />
                                <span class="font-medium truncate">{{ sec.label }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Builder Opsi Dinamis untuk Tambah Baru -->
                    <div v-if="canHaveOptions(questionForm.type) && !isEditingQuestion" class="space-y-2 pt-2 border-t border-gray-100">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-gray-700">Pilihan Opsi Jawaban</label>
                            <button
                                type="button"
                                @click="addOptionToForm"
                                class="text-xs text-[#0D542B] font-bold hover:underline cursor-pointer"
                            >
                                + Tambah Opsi
                            </button>
                        </div>
                        <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                            <div v-for="(opt, oIdx) in questionForm.options" :key="oIdx" class="flex items-center gap-2">
                                <input
                                    v-model="opt.label"
                                    type="text"
                                    :placeholder="`Pilihan ${oIdx + 1}`"
                                    class="flex-1 px-2.5 py-1.5 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-[#0D542B]"
                                />
                                <input
                                    v-model.number="opt.score"
                                    type="number"
                                    placeholder="Skor"
                                    class="w-16 px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-[#0D542B]"
                                    title="Nilai Skor (Opsional)"
                                />
                                <button
                                    type="button"
                                    @click="removeOptionFromForm(oIdx)"
                                    class="p-1 text-gray-400 hover:text-rose-600 cursor-pointer"
                                    title="Hapus Opsi"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 border-t border-gray-100 pt-3">
                        <div class="flex items-center gap-2">
                            <input
                                id="vue-q-req"
                                v-model="questionForm.is_required"
                                type="checkbox"
                                class="rounded text-[#0D542B] focus:ring-[#0D542B]"
                            />
                            <label for="vue-q-req" class="cursor-pointer font-bold text-gray-700">
                                Wajib diisi oleh responden
                            </label>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                id="vue-q-reuse"
                                v-model="questionForm.is_reusable"
                                type="checkbox"
                                class="rounded text-[#0D542B] focus:ring-[#0D542B]"
                            />
                            <label for="vue-q-reuse" class="cursor-pointer font-bold text-gray-700">
                                Tersedia di Bank Pertanyaan untuk digunakan instrumen lain
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-4">
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

    <!-- MODAL BANK PERTANYAAN (REUSABLE QUESTIONS) -->
    <Teleport to="body">
        <div
            v-if="isBankModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
        >
            <div class="relative w-full max-w-3xl rounded-xl bg-white shadow-xl max-h-[90vh] flex flex-col overflow-hidden">
                <!-- Header Modal -->
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 bg-slate-50">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">Pilih dari Bank Pertanyaan</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Gunakan butir pertanyaan yang sudah tersedia dari universitas atau prodi lain tanpa perlu mengetik ulang.</p>
                    </div>
                    <button
                        type="button"
                        @click="closeBankModal"
                        class="text-gray-400 hover:text-gray-700 p-1 rounded-lg"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Konten Modal: Filter & List -->
                <div class="p-6 space-y-4 overflow-y-auto flex-1">
                    <!-- Penentuan Bagian Tujuan -->
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <label class="text-xs font-bold text-emerald-950">
                            Tempatkan pada Bagian (Section) Tujuan:
                        </label>
                        <select
                            v-model="targetSectionIdForBank"
                            class="px-3 py-1.5 border border-emerald-300 rounded-lg text-xs font-semibold bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                        >
                            <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.title }}</option>
                        </select>
                    </div>

                    <!-- Bar Filter & Pencarian Bank Soal -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <input
                                v-model="bankSearchQuery"
                                type="text"
                                placeholder="Cari kode pertanyaan atau teks..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            />
                        </div>
                        <div>
                            <select
                                v-model="bankOriginFilter"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            >
                                <option value="all">Semua Asal Pertanyaan</option>
                                <option v-for="origin in availableOrigins" :key="origin" :value="origin">{{ origin }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Daftar Kartu Pertanyaan -->
                    <div class="space-y-2 border border-gray-200 rounded-lg divide-y divide-gray-100 max-h-[360px] overflow-y-auto p-2">
                        <div v-if="filteredBankQuestions.length === 0" class="p-8 text-center text-xs text-gray-500">
                            Tidak ada butir pertanyaan yang sesuai dengan kriteria pencarian.
                        </div>

                        <div
                            v-for="item in filteredBankQuestions"
                            :key="item.id"
                            class="p-3 rounded-lg transition-colors flex items-start gap-3"
                            :class="[
                                item.is_already_added ? 'bg-slate-50 opacity-60' : (selectedBankQuestionIds.includes(item.id) ? 'bg-emerald-50/50 border border-emerald-200' : 'hover:bg-gray-50')
                            ]"
                        >
                            <input
                                type="checkbox"
                                :id="`bank-q-${item.id}`"
                                :checked="selectedBankQuestionIds.includes(item.id)"
                                :disabled="item.is_already_added"
                                @change="toggleBankSelection(item.id)"
                                class="mt-1 h-4 w-4 rounded border-gray-300 text-[#0D542B] focus:ring-[#0D542B]"
                            />
                            <label :for="`bank-q-${item.id}`" class="flex-1 cursor-pointer min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-bold text-slate-700">
                                        {{ item.kode }}
                                    </span>
                                    <span class="rounded bg-emerald-100/80 px-1.5 py-0.5 text-[10px] font-semibold text-[#0D542B]">
                                        Asal: {{ item.asal }}
                                    </span>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500">
                                        {{ typeLabels[item.tipe] || item.tipe }}
                                    </span>
                                    <span v-if="item.is_already_added" class="rounded bg-gray-200 px-1.5 py-0.5 text-[10px] font-semibold text-gray-600">
                                        Sudah Ada di Prodi Ini
                                    </span>
                                </div>
                                <p class="mt-1 text-xs font-semibold text-slate-800 leading-snug">
                                    {{ item.teks }}
                                </p>
                                <div v-if="item.options && item.options.length > 0" class="mt-1.5 flex flex-wrap gap-1">
                                    <span
                                        v-for="(opt, optIdx) in item.options.slice(0, 4)"
                                        :key="optIdx"
                                        class="rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[10px] text-gray-600"
                                    >
                                        {{ opt.label }}
                                    </span>
                                    <span v-if="item.options.length > 4" class="text-[10px] text-gray-400 self-center">
                                        +{{ item.options.length - 4 }} opsi lainnya
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="flex items-center justify-between border-t border-gray-200 bg-slate-50 px-6 py-3">
                    <span class="text-xs text-slate-600 font-semibold">
                        Dipilih: <strong class="text-[#0D542B]">{{ selectedBankQuestionIds.length }}</strong> pertanyaan
                    </span>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="closeBankModal"
                            class="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-100 transition-colors"
                        >
                            Tutup
                        </button>
                        <button
                            type="button"
                            :disabled="isSubmittingBank || selectedBankQuestionIds.length === 0"
                            @click="submitBankSelection"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 disabled:opacity-40 cursor-pointer"
                        >
                            <span>Terapkan Pertanyaan ke Kuesioner</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
