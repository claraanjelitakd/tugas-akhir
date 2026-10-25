<!--
  Halaman: Pengaturan Kuesioner Universitas (Super Admin)
  File: resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue

  Desain Standar Enterprise UKDW:
  - Tampilan seragam dan rapi persis seperti Pengaturan Kuesioner Program Studi.
  - Multi-tab terpadu:
    1. Daftar Bagian (Section)
    2. Pertanyaan Kuesioner (Tracer)
    3. Pertanyaan Profil Alumni
    4. Kelola Kuesioner Induk
  - Bebas Emoticon / Emojis (Menggunakan SVG Icons Resmi & Bahasa Formal).
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import Sidebar from '../Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    subpertanyaans: {
        type: Array,
        default: () => [],
    },
    sections: {
        type: Array,
        default: () => [],
    },
    kuesioners: {
        type: Array,
        default: () => [],
    },
    activeKuesioner: {
        type: Object,
        default: () => ({}),
    },
    prodis: {
        type: Array,
        default: () => [],
    },
    availableJumpTargets: {
        type: Array,
        default: () => [],
    },
    targetQuestionMap: {
        type: Object,
        default: () => ({}),
    },
    initialTab: {
        type: String,
        default: 'sections',
    },
    stats: {
        type: Object,
        default: () => ({
            total_kuesioners: 0,
            total_sections: 0,
            total_tracer_questions: 0,
            total_profile_questions: 0,
            total_questions: 0,
        }),
    },
});

// ========================================================
// HELPER LOKASI TAMPIL
// ========================================================
const isProfileTarget = (target) => target === 'profil' || target === 'profile';
const isBothTarget = (target) => target === 'both';
const isTracerTarget = (target) => target === 'kuesioner' || (!target && !isProfileTarget(target));

const getTargetBadge = (target) => {
    if (isProfileTarget(target)) {
        return {
            label: 'Profil Alumni',
            classes: 'text-gray-600',
        };
    }
    if (isBothTarget(target)) {
        return {
            label: 'Kuesioner & Profil',
            classes: 'text-gray-600',
        };
    }
    return {
        label: 'Kuesioner Tracer',
        classes: 'text-gray-600',
    };
};

// ========================================================
// TAB AKTIF STATE ('sections', 'tracer', 'profile', 'kuesioner')
// ========================================================
const getInitialTab = () => {
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        const t = urlParams.get('tab');
        if (t && ['sections', 'tracer', 'questions', 'profile', 'kuesioner'].includes(t)) {
            if (t === 'questions') return 'tracer';
            return t;
        }
    }
    return props.initialTab === 'questions' ? 'tracer' : (props.initialTab || 'sections');
};

const activeTab = ref(getInitialTab());

const switchTab = (tabName) => {
    activeTab.value = tabName;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url);
    }
};

// ========================================================
// 1. SECTION STATE & FILTERS
// ========================================================
const sectionSearchQuery = ref('');
const filteredSections = computed(() => {
    if (!sectionSearchQuery.value.trim()) return props.sections;
    const q = sectionSearchQuery.value.toLowerCase();
    return props.sections.filter(s =>
        s.title?.toLowerCase().includes(q) ||
        s.description?.toLowerCase().includes(q) ||
        s.order?.toString().includes(q)
    );
});

// ========================================================
// 2. QUESTION TYPES
// ========================================================
const typeLabels = {
    radio: 'Pilihan Ganda (Radio)',
    single_choice: 'Pilihan Ganda (Radio)',
    radio_text: 'Pilihan Ganda + Lainnya (Radio + Teks)',
    radio_input: 'Radio + Angka (Bulan)',
    multiple_choice: 'Kotak Centang (Checkbox)',
    checkbox: 'Kotak Centang (Checkbox)',
    dropdown: 'Pilihan Dropdown (Select)',
    searchable_select: 'Pencarian Dropdown',
    rating_5: 'Skala Rating (1-5)',
    multiple_number: 'Rincian Nominal / Gaji (Take Home Pay)',
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

const univSelectableTypes = {
    radio: 'Pilihan Ganda (Radio)',
    radio_text: 'Pilihan Ganda + Lainnya (Radio + Teks)',
    radio_input: 'Radio + Angka (Bulan)',
    multiple_choice: 'Kotak Centang (Checkbox)',
    dropdown: 'Pilihan Dropdown (Select)',
    searchable_select: 'Pencarian Dropdown',
    rating_5: 'Skala Rating (1-5)',
    multiple_number: 'Rincian Nominal / Gaji (Take Home Pay)',
    text: 'Jawaban Singkat (Text)',
    textarea: 'Paragraf (Textarea)',
    number: 'Isian Angka (Number)',
    date: 'Tanggal',
    time: 'Waktu',
    file: 'Unggah Berkas (File)',
    header: 'Header / Judul Bagian',
};

const typeInstructions = {
    radio: 'Pilih salah satu jawaban.',
    single_choice: 'Pilih salah satu jawaban.',
    radio_text: 'Pilih salah satu jawaban. Jika memilih lainnya, tuliskan jawaban.',
    radio_input: 'Pilih salah satu jawaban dan isi angka jika diperlukan.',
    multiple_choice: 'Jawaban dapat lebih dari satu.',
    checkbox: 'Jawaban dapat lebih dari satu.',
    dropdown: 'Pilih salah satu jawaban.',
    searchable_select: 'Cari dan pilih salah satu jawaban.',
    rating_5: 'Pilih satu nilai pada skala 1 sampai 5.',
    multiple_number: 'Isi nominal pada setiap komponen yang sesuai.',
    matrix: 'Pilih satu jawaban pada setiap baris.',
    matrix_dual: 'Berikan penilaian pada setiap pernyataan.',
    multiple_textbox: 'Isi jawaban pada kolom yang tersedia.',
    text: 'Isi jawaban singkat.',
    textarea: 'Isi jawaban pada kolom yang tersedia.',
    number: 'Isi jawaban dalam angka.',
    date: 'Pilih tanggal yang sesuai.',
    time: 'Pilih waktu yang sesuai.',
    file: 'Unggah berkas sesuai petunjuk.',
};

const getTypeInstruction = (type) => typeInstructions[type] || '';

const isAutomaticInstruction = (value) => [
    ...Object.values(typeInstructions),
    'Pilih satu jawaban',
    'Jawaban bisa lebih dari satu',
].includes(value);

const OPTION_SUPPORTED_TYPES = [
    'radio',
    'single_choice',
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

// ========================================================
// 3. TAB TRACER QUESTIONS STATE & FILTERS
// ========================================================
const selectedTracerSectionFilter = ref('all');
const selectedTracerRequiredFilter = ref('all');
const selectedTracerTargetFilter = ref('all');
const tracerSearchQuery = ref('');

const filteredTracerQuestions = computed(() => {
    return props.subpertanyaans.filter((q) => {
        // Tampilkan hanya pertanyaan tracer (kuesioner atau both)
        if (isProfileTarget(q.tampil_di)) return false;

        if (selectedTracerSectionFilter.value !== 'all') {
            const secId = parseInt(selectedTracerSectionFilter.value, 10);
            if (q.kelompok_pertanyaan_id !== secId) return false;
        }

        if (selectedTracerRequiredFilter.value !== 'all') {
            const isReq = selectedTracerRequiredFilter.value === 'wajib';
            if (Boolean(q.wajib) !== isReq) return false;
        }

        if (selectedTracerTargetFilter.value !== 'all') {
            if (selectedTracerTargetFilter.value === 'kuesioner' && !isTracerTarget(q.tampil_di)) return false;
            if (selectedTracerTargetFilter.value === 'both' && !isBothTarget(q.tampil_di)) return false;
        }

        if (tracerSearchQuery.value.trim()) {
            const query = tracerSearchQuery.value.toLowerCase();
            const matchCode = q.kode_pertanyaan?.toLowerCase().includes(query);
            const matchText = q.subpertanyaan?.toLowerCase().includes(query);
            const matchType = (typeLabels[q.type] || q.type)?.toLowerCase().includes(query);
            return matchCode || matchText || matchType;
        }

        return true;
    });
});

// ========================================================
// 4. TAB PROFILE QUESTIONS STATE & FILTERS
// ========================================================
const selectedProfileSectionFilter = ref('all');
const selectedProfileRequiredFilter = ref('all');
const selectedProfileTargetFilter = ref('all');
const profileSearchQuery = ref('');

const filteredProfileQuestions = computed(() => {
    return props.subpertanyaans.filter((q) => {
        // Tampilkan hanya pertanyaan profil alumni (profil atau both)
        if (!isProfileTarget(q.tampil_di) && !isBothTarget(q.tampil_di)) return false;

        if (selectedProfileSectionFilter.value !== 'all') {
            const secId = parseInt(selectedProfileSectionFilter.value, 10);
            if (q.kelompok_pertanyaan_id !== secId) return false;
        }

        if (selectedProfileRequiredFilter.value !== 'all') {
            const isReq = selectedProfileRequiredFilter.value === 'wajib';
            if (Boolean(q.wajib) !== isReq) return false;
        }

        if (selectedProfileTargetFilter.value !== 'all') {
            if (selectedProfileTargetFilter.value === 'profil' && !isProfileTarget(q.tampil_di)) return false;
            if (selectedProfileTargetFilter.value === 'both' && !isBothTarget(q.tampil_di)) return false;
        }

        if (profileSearchQuery.value.trim()) {
            const query = profileSearchQuery.value.toLowerCase();
            const matchCode = q.kode_pertanyaan?.toLowerCase().includes(query);
            const matchText = q.subpertanyaan?.toLowerCase().includes(query);
            const matchType = (typeLabels[q.type] || q.type)?.toLowerCase().includes(query);
            return matchCode || matchText || matchType;
        }

        return true;
    });
});

// ========================================================
// 5. KUESIONER INDUK STATE & FILTERS
// ========================================================
const kuesionerSearchQuery = ref('');
const filteredKuesioners = computed(() => {
    if (!kuesionerSearchQuery.value.trim()) return props.kuesioners;
    const q = kuesionerSearchQuery.value.toLowerCase();
    return props.kuesioners.filter(k =>
        k.title?.toLowerCase().includes(q) ||
        k.description?.toLowerCase().includes(q) ||
        k.year?.toString().includes(q)
    );
});

// ========================================================
// MODAL HANDLERS: SECTION (TAMBAH / EDIT / REORDER / HAPUS)
// ========================================================
// ========================================================
// STATE REAKTIF UNTUK MODAL VUE 3 (COMPOSITION API)
// ========================================================
// Catatan Belajar: 'ref' adalah fungsi dasar Composition API di Vue 3 untuk menyimpan data yang reaktif.
// Saat nilai di dalam 'ref' diubah (contoh: isQuestionModalOpen.value = true), Vue secara otomatis mendeteksi
// perubahan dan merender ulang bagian template yang bersangkutan.

// 1. STATE MODAL BAGIAN (SECTION)
const isSectionModalOpen = ref(false);
const isEditingSection = ref(false);
const editingSectionId = ref(null);
const sectionFormData = ref({
    kuesioner_id: '',
    title: '',
    description: '',
    order: 1,
});
const sectionFormErrors = ref({});
const isSubmittingSection = ref(false);

// 2. STATE MODAL BUTIR PERTANYAAN (QUESTION)
const isQuestionModalOpen = ref(false);
const isEditingQuestion = ref(false);
const editingQuestionId = ref(null);
const questionModalTitle = ref('Tambah Pertanyaan');
const questionFormData = ref({
    kode_pertanyaan: '',
    subpertanyaan: '',
    type: 'radio',
    kelompok_pertanyaan_id: '',
    tampil_di: 'kuesioner',
    order: 1,
    keterangan: '',
    wajib: true,
});
const questionFormErrors = ref({});
const isSubmittingQuestion = ref(false);

// 3. STATE MODAL OPSI JAWABAN (OPTION)
const isOptionModalOpen = ref(false);
const isEditingOption = ref(false);
const editingOptionId = ref(null);
const activeQuestionForOption = ref(null);
const optionFormData = ref({
    kode_opsi: '',
    option_text: '',
    jump_to: '',
    order: 1,
});
const optionFormErrors = ref({});
const isSubmittingOption = ref(false);

// 4. STATE MODAL KUESIONER INDUK
const isKuesionerModalOpen = ref(false);
const isEditingKuesioner = ref(false);
const editingKuesionerId = ref(null);
const kuesionerFormData = ref({
    title: '',
    year: new Date().getFullYear(),
    description: '',
    is_active: true,
});
const kuesionerFormErrors = ref({});
const isSubmittingKuesioner = ref(false);

// ========================================================
// FUNGSI KONTROL: MODAL BAGIAN (SECTION)
// ========================================================

/**
 * Membuka modal form tambah / sunting bagian kuesioner
 */
const openSectionModal = (sectionToEdit = null) => {
    sectionFormErrors.value = {};

    if (sectionToEdit) {
        // Mode Sunting
        isEditingSection.value = true;
        editingSectionId.value = sectionToEdit.id;
        sectionFormData.value = {
            kuesioner_id: sectionToEdit.kuesioner_id || (props.kuesioners[0]?.id || ''),
            title: sectionToEdit.title || '',
            description: sectionToEdit.description || '',
            order: sectionToEdit.order ?? (props.sections.length + 1),
        };
    } else {
        // Mode Tambah Baru
        isEditingSection.value = false;
        editingSectionId.value = null;
        sectionFormData.value = {
            kuesioner_id: props.activeKuesioner?.id || (props.kuesioners[0]?.id || ''),
            title: '',
            description: '',
            order: props.sections.length + 1,
        };
    }

    isSectionModalOpen.value = true;
};

/**
 * Menutup modal bagian kuesioner
 */
const closeSectionModal = () => {
    isSectionModalOpen.value = false;
    isSubmittingSection.value = false;
    sectionFormErrors.value = {};
};

/**
 * Mengirimkan data form bagian ke backend Laravel
 */
const submitSectionForm = () => {
    sectionFormErrors.value = {};

    if (!sectionFormData.value.kuesioner_id) {
        sectionFormErrors.value.kuesioner_id = 'Kuesioner induk wajib dipilih.';
    }
    if (!sectionFormData.value.title || !sectionFormData.value.title.trim()) {
        sectionFormErrors.value.title = 'Judul bagian kuesioner wajib diisi.';
    }

    if (Object.keys(sectionFormErrors.value).length > 0) {
        return;
    }

    isSubmittingSection.value = true;

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
            onError: (errs) => {
                sectionFormErrors.value = errs;
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
    router.post('/superadmin/sections/reorder', {
        id: sectionId,
        direction,
    }, {
        preserveScroll: true,
    });
};

const handleDeleteSection = (section) => {
    Swal.fire({
        title: 'Hapus Bagian Kuesioner?',
        text: `Apakah Anda yakin ingin menghapus bagian "${section.title}" beserta seluruh butir pertanyaan di dalamnya?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Bagian',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/superadmin/sections/${section.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terhapus',
                        text: 'Bagian kuesioner berhasil dihapus.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
            });
        }
    });
};

// ========================================================
// FUNGSI KONTROL: MODAL BUTIR PERTANYAAN (QUESTION)
// ========================================================

/**
 * Menangani perubahan tipe pertanyaan.
 * Menghasilkan instruksi pengisian default secara otomatis jika pengguna belum mengetik petunjuk kustom.
 */
const handleQuestionTypeChange = () => {
    const nextInstruction = getTypeInstruction(questionFormData.value.type);
    const currentInstruction = (questionFormData.value.keterangan || '').trim();

    if (!currentInstruction || isAutomaticInstruction(currentInstruction)) {
        questionFormData.value.keterangan = nextInstruction;
    }
};

/**
 * Membuka modal form butir pertanyaan
 */
const openQuestionModal = (questionToEdit = null, defaultTarget = 'kuesioner') => {
    questionFormErrors.value = {};

    if (questionToEdit) {
        // Mode Sunting
        isEditingQuestion.value = true;
        editingQuestionId.value = questionToEdit.id;
        questionModalTitle.value = `Sunting Pertanyaan [${questionToEdit.kode_pertanyaan}]`;

        const target = isProfileTarget(questionToEdit.tampil_di)
            ? 'profil'
            : (questionToEdit.tampil_di || 'kuesioner');

        questionFormData.value = {
            kode_pertanyaan: questionToEdit.kode_pertanyaan || '',
            subpertanyaan: questionToEdit.subpertanyaan || '',
            type: questionToEdit.type || 'radio',
            kelompok_pertanyaan_id: questionToEdit.kelompok_pertanyaan_id || (props.sections[0]?.id || ''),
            tampil_di: target,
            order: questionToEdit.order ?? (props.subpertanyaans.length + 1),
            keterangan: questionToEdit.keterangan || getTypeInstruction(questionToEdit.type || 'radio'),
            wajib: questionToEdit.wajib !== undefined ? Boolean(questionToEdit.wajib) : true,
        };
    } else {
        // Mode Tambah Baru
        isEditingQuestion.value = false;
        editingQuestionId.value = null;
        questionModalTitle.value = defaultTarget === 'profil'
            ? 'Tambah Pertanyaan Profil Alumni'
            : 'Tambah Pertanyaan Kuesioner Tracer';

        const defaultType = 'radio';
        questionFormData.value = {
            kode_pertanyaan: '',
            subpertanyaan: '',
            type: defaultType,
            kelompok_pertanyaan_id: props.sections[0]?.id || '',
            tampil_di: defaultTarget,
            order: props.subpertanyaans.length + 1,
            keterangan: getTypeInstruction(defaultType),
            wajib: true,
        };
    }

    isQuestionModalOpen.value = true;
};

/**
 * Menutup modal butir pertanyaan
 */
const closeQuestionModal = () => {
    isQuestionModalOpen.value = false;
    isSubmittingQuestion.value = false;
    questionFormErrors.value = {};
};

/**
 * Menyimpan data butir pertanyaan ke controller Laravel
 */
const submitQuestionForm = () => {
    questionFormErrors.value = {};

    if (!questionFormData.value.kode_pertanyaan.trim()) {
        questionFormErrors.value.kode_pertanyaan = 'Kode pertanyaan wajib diisi.';
    }
    if (!questionFormData.value.subpertanyaan.trim()) {
        questionFormErrors.value.subpertanyaan = 'Teks pertanyaan wajib diisi.';
    }
    if (!questionFormData.value.kelompok_pertanyaan_id) {
        questionFormErrors.value.kelompok_pertanyaan_id = 'Bagian (Section) wajib dipilih.';
    }

    if (Object.keys(questionFormErrors.value).length > 0) {
        return;
    }

    isSubmittingQuestion.value = true;

    // Normalisasi kode pertanyaan agar selalu huruf besar (kapital)
    const payload = {
        ...questionFormData.value,
        kode_pertanyaan: questionFormData.value.kode_pertanyaan.trim().toUpperCase(),
    };

    if (isEditingQuestion.value) {
        router.put(`/superadmin/pertanyaan/${editingQuestionId.value}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                closeQuestionModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Pertanyaan berhasil diperbarui.',
                    timer: 2000,
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
        router.post('/superadmin/pertanyaan', payload, {
            preserveScroll: true,
            onSuccess: () => {
                closeQuestionModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Pertanyaan baru berhasil ditambahkan.',
                    timer: 2000,
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
    router.post('/superadmin/pertanyaan/reorder', {
        id: questionId,
        direction,
    }, {
        preserveScroll: true,
    });
};

const handleDeleteQuestion = (question) => {
    Swal.fire({
        title: 'Hapus Pertanyaan?',
        text: `Apakah Anda yakin ingin menghapus pertanyaan [${question.kode_pertanyaan}] "${question.subpertanyaan?.substring(0, 45)}..." beserta seluruh opsi jawabannya?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Pertanyaan',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/superadmin/pertanyaan/${question.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terhapus',
                        text: 'Pertanyaan berhasil dihapus.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
            });
        }
    });
};

// ========================================================
// FUNGSI KONTROL: MODAL OPSI JAWABAN (OPTION)
// ========================================================

/**
 * Membuka modal tambah / sunting opsi jawaban untuk suatu pertanyaan
 */
const openOptionModal = (question, optionToEdit = null) => {
    optionFormErrors.value = {};
    activeQuestionForOption.value = question;

    if (optionToEdit) {
        // Mode Sunting Opsi
        isEditingOption.value = true;
        editingOptionId.value = optionToEdit.id;
        optionFormData.value = {
            kode_opsi: optionToEdit.kode_opsi || '',
            option_text: optionToEdit.option_text || optionToEdit.opsi || '',
            jump_to: optionToEdit.jump_to || '',
            order: optionToEdit.order ?? 1,
        };
    } else {
        // Mode Tambah Opsi Baru: buatkan kode opsi otomatis (contoh: F301-01)
        isEditingOption.value = false;
        editingOptionId.value = null;
        const nextIndex = (question.detils?.length || 0) + 1;
        const autoCode = `${question.kode_pertanyaan}-${String(nextIndex).padStart(2, '0')}`;
        optionFormData.value = {
            kode_opsi: autoCode,
            option_text: '',
            jump_to: '',
            order: nextIndex,
        };
    }

    isOptionModalOpen.value = true;
};

/**
 * Menutup modal opsi jawaban
 */
const closeOptionModal = () => {
    isOptionModalOpen.value = false;
    isSubmittingOption.value = false;
    activeQuestionForOption.value = null;
    optionFormErrors.value = {};
};

/**
 * Menyimpan data opsi jawaban ke backend
 */
const submitOptionForm = () => {
    optionFormErrors.value = {};

    if (!optionFormData.value.kode_opsi.trim()) {
        optionFormErrors.value.kode_opsi = 'Kode opsi wajib diisi.';
    }
    if (!optionFormData.value.option_text.trim()) {
        optionFormErrors.value.option_text = 'Teks opsi jawaban wajib diisi.';
    }

    if (Object.keys(optionFormErrors.value).length > 0) {
        return;
    }

    isSubmittingOption.value = true;

    if (isEditingOption.value) {
        router.put(`/superadmin/pertanyaan/options/${editingOptionId.value}`, optionFormData.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeOptionModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Opsi jawaban berhasil diperbarui.',
                    timer: 2000,
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
        router.post(`/superadmin/pertanyaan/${activeQuestionForOption.value.id}/options`, optionFormData.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeOptionModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Opsi jawaban baru berhasil ditambahkan.',
                    timer: 2000,
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
        text: `Apakah Anda yakin ingin menghapus opsi "${opt.option_text || opt.opsi}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Opsi',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/superadmin/pertanyaan/options/${opt.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terhapus',
                        text: 'Opsi jawaban berhasil dihapus.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
            });
        }
    });
};

// ========================================================
// FUNGSI KONTROL: MODAL KUESIONER INDUK
// ========================================================

/**
 * Membuka modal form tambah / sunting kuesioner induk
 */
const openKuesionerModal = (kuesionerToEdit = null) => {
    kuesionerFormErrors.value = {};

    if (kuesionerToEdit) {
        // Mode Sunting Kuesioner
        isEditingKuesioner.value = true;
        editingKuesionerId.value = kuesionerToEdit.id;
        kuesionerFormData.value = {
            title: kuesionerToEdit.title || '',
            year: kuesionerToEdit.year || new Date().getFullYear(),
            description: kuesionerToEdit.description || '',
            is_active: Boolean(kuesionerToEdit.is_active),
        };
    } else {
        // Mode Tambah Kuesioner Baru
        isEditingKuesioner.value = false;
        editingKuesionerId.value = null;
        kuesionerFormData.value = {
            title: '',
            year: new Date().getFullYear(),
            description: '',
            is_active: true,
        };
    }

    isKuesionerModalOpen.value = true;
};

/**
 * Menutup modal kuesioner induk
 */
const closeKuesionerModal = () => {
    isKuesionerModalOpen.value = false;
    isSubmittingKuesioner.value = false;
    kuesionerFormErrors.value = {};
};

/**
 * Menyimpan data kuesioner induk ke backend
 */
const submitKuesionerForm = () => {
    kuesionerFormErrors.value = {};

    if (!kuesionerFormData.value.title.trim()) {
        kuesionerFormErrors.value.title = 'Judul kuesioner induk wajib diisi.';
    }
    const yr = parseInt(kuesionerFormData.value.year, 10);
    if (!yr || yr < 2000 || yr > 2100) {
        kuesionerFormErrors.value.year = 'Tahun kuesioner harus berupa angka antara 2000 - 2100.';
    }

    if (Object.keys(kuesionerFormErrors.value).length > 0) {
        return;
    }

    isSubmittingKuesioner.value = true;

    if (isEditingKuesioner.value) {
        router.put(`/superadmin/kuesioner/${editingKuesionerId.value}`, kuesionerFormData.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeKuesionerModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Kuesioner induk berhasil diperbarui.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: (errs) => {
                kuesionerFormErrors.value = errs;
            },
            onFinish: () => {
                isSubmittingKuesioner.value = false;
            },
        });
    } else {
        router.post('/superadmin/kuesioner', kuesionerFormData.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeKuesionerModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Kuesioner induk baru berhasil ditambahkan.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: (errs) => {
                kuesionerFormErrors.value = errs;
            },
            onFinish: () => {
                isSubmittingKuesioner.value = false;
            },
        });
    }
};

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
                onSuccess: () => Swal.fire('Berhasil', `Kuesioner "${k.title}" berhasil diperbarui.`, 'success'),
            });
        }
    });
};

const handleDeleteKuesioner = (k) => {
    Swal.fire({
        title: 'Hapus Kuesioner Induk?',
        html: `Apakah Anda yakin ingin menghapus <strong>"${k.title}"</strong>?<br><br><span class="text-xs text-rose-600">Perhatian: Seluruh seksi dan butir pertanyaan di dalam kuesioner ini akan ikut terhapus!</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Kuesioner',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/superadmin/kuesioner/${k.id}`, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Terhapus', 'Kuesioner induk berhasil dihapus.', 'success'),
            });
        }
    });
};
</script>

<template>
    <Head title="Pengaturan Kuesioner Universitas - Super Admin UKDW" />

    <div class="min-h-screen bg-[#F8FAFC] text-slate-800 font-sans flex">
        <Sidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Solid Hijau Resmi UKDW #0D542B -->
            <header class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full max-w-[1440px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <!-- Breadcrumbs -->
                        <nav class="flex items-center gap-2 text-xs text-emerald-100 font-medium mb-2.5">
                            <Link href="/superadmin/dashboard" class="hover:text-white transition-colors flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                <span>Dashboard</span>
                            </Link>
                            <span class="text-emerald-300/60">/</span>
                            <span class="text-white font-bold bg-white/10 px-2 py-0.5 rounded-md">Kuesioner Universitas</span>
                        </nav>

                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white tracking-tight">
                            Pengaturan Kuesioner Universitas
                        </h1>
                        <p class="text-emerald-100/90 text-xs sm:text-sm font-normal mt-1.5 max-w-2xl leading-relaxed">
                            Kelola instrumen kuesioner induk, struktur bagian (section), butir kuesioner tracer, dan biodata profil alumni.
                        </p>
                    </div>
                </div>
            </header>

            <main class="admin-page-main w-full max-w-[1440px] mx-auto -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
                <!-- Bar Informasi Kuesioner Induk & Ringkasan Data (2 Tone) -->
                <div class="bg-white rounded-xl p-5 border border-gray-300 shadow-2xs">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Instrumen Kuesioner Induk Aktif
                            </label>
                            <div class="flex items-center gap-3">
                                <div class="px-3.5 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm font-bold text-[#0D542B] flex items-center gap-2">
                                    <span>{{ activeKuesioner?.title ? `[${activeKuesioner.year}] ${activeKuesioner.title}` : 'Belum Ada Kuesioner Aktif' }}</span>
                                    <span v-if="activeKuesioner?.is_active" class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        Aktif
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 bg-gray-50/80 p-3 rounded-lg border border-gray-200 flex-wrap">
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-slate-900">{{ stats.total_sections }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Bagian / Section</span>
                            </div>
                            <div class="h-8 w-px bg-gray-200"></div>
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-[#0D542B]">{{ stats.total_tracer_questions ?? 0 }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Soal Tracer</span>
                            </div>
                            <div class="h-8 w-px bg-gray-200"></div>
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-slate-700">{{ stats.total_profile_questions ?? 0 }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Data Profil</span>
                            </div>
                            <div class="h-8 w-px bg-gray-200"></div>
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-slate-900">{{ stats.total_kuesioners }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Kuesioner Induk</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigasi Tab (4 Bagian Terpadu) -->
                <div class="flex border-b border-slate-200 space-x-4 overflow-x-auto pb-px">
                    <!-- Tab 1: Section -->
                    <button
                        @click="switchTab('sections')"
                        class="pb-3 px-3 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 shrink-0"
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

                    <!-- Tab 2: Pertanyaan Kuesioner Tracer -->
                    <button
                        @click="switchTab('tracer')"
                        class="pb-3 px-3 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 shrink-0"
                        :class="[
                            activeTab === 'tracer'
                                ? 'border-[#0D542B] text-[#0D542B]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Pertanyaan Kuesioner (Tracer)</span>
                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold rounded-full" :class="activeTab === 'tracer' ? 'bg-[#0D542B]/10 text-[#0D542B]' : 'bg-slate-100 text-gray-600'">
                            {{ stats.total_tracer_questions ?? 0 }}
                        </span>
                    </button>

                    <!-- Tab 3: Pertanyaan Profil Alumni -->
                    <button
                        @click="switchTab('profile')"
                        class="pb-3 px-3 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 shrink-0"
                        :class="[
                            activeTab === 'profile'
                                ? 'border-[#0D542B] text-[#0D542B]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Pertanyaan Profil Alumni</span>
                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold rounded-full" :class="activeTab === 'profile' ? 'bg-[#0D542B]/10 text-[#0D542B]' : 'bg-slate-100 text-gray-600'">
                            {{ stats.total_profile_questions ?? 0 }}
                        </span>
                    </button>

                    <!-- Tab 4: Kelola Kuesioner Induk -->
                    <button
                        @click="switchTab('kuesioner')"
                        class="pb-3 px-3 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 shrink-0"
                        :class="[
                            activeTab === 'kuesioner'
                                ? 'border-[#0D542B] text-[#0D542B]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                        </svg>
                        <span>Kelola Kuesioner Induk</span>
                    </button>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 1: SECTION UNIVERSITAS MANAGEMENT                     -->
                <!-- ======================================================== -->
                <div v-if="activeTab === 'sections'" class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="w-full sm:w-72">
                            <input
                                type="text"
                                v-model="sectionSearchQuery"
                                placeholder="Cari judul bagian section..."
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B] focus:outline-none bg-white"
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
                                        Belum ada bagian (section) kuesioner universitas yang dibuat.
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
                                        {{ sec.subpertanyaans_count ?? 0 }} Pertanyaan
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

                <!-- ======================================================== -->
                <!-- TAB 2: PERTANYAAN KUESIONER TRACER STUDY                 -->
                <!-- ======================================================== -->
                <div v-if="activeTab === 'tracer'" class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-gray-300 shadow-2xs">
                        <div class="flex flex-wrap items-center gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Filter Section</label>
                                <select v-model="selectedTracerSectionFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Section</option>
                                    <option v-for="s in sections" :key="s.id" :value="s.id">Bagian {{ s.order }}: {{ s.title }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Sifat Pengisian</label>
                                <select v-model="selectedTracerRequiredFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Sifat</option>
                                    <option value="wajib">Wajib (Required)</option>
                                    <option value="opsional">Opsional</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Lokasi Tampil</label>
                                <select v-model="selectedTracerTargetFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua (Kuesioner & Keduanya)</option>
                                    <option value="kuesioner">Hanya Kuesioner Tracer</option>
                                    <option value="both">Kuesioner & Profil</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Pencarian Teks</label>
                                <input type="text" v-model="tracerSearchQuery" placeholder="Cari kode atau pertanyaan..." class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs w-48 bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]" />
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="openQuestionModal(null, 'kuesioner')"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer shrink-0"
                        >
                            <span>+ Tambah Pertanyaan Kuesioner</span>
                        </button>
                    </div>

                    <!-- Tabel Pertanyaan Kuesioner Tracer (2 Tone) -->
                    <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                <tr>
                                    <th class="px-3 py-3 text-center w-20">Urutan</th>
                                    <th class="px-3 py-3 w-20">Kode</th>
                                    <th class="px-4 py-3">Butir Pertanyaan Kuesioner</th>
                                    <th class="px-3 py-3">Tipe & Bagian (Section)</th>
                                    <th class="px-4 py-3">Opsi Jawaban & Percabangan</th>
                                    <th class="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredTracerQuestions.length === 0">
                                    <td colspan="6" class="p-8 text-center text-gray-400 italic">
                                        Belum ada butir pertanyaan kuesioner tracer ditemukan yang sesuai filter.
                                    </td>
                                </tr>
                                <tr v-for="(q, idx) in filteredTracerQuestions" :key="q.id" class="hover:bg-gray-50/80 transition-colors">
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
                                                    :disabled="idx === filteredTracerQuestions.length - 1"
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
                                    <td class="px-3 py-3.5 font-mono font-bold text-gray-900">
                                        {{ q.kode_pertanyaan }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <p class="font-bold text-gray-900 leading-snug">
                                            {{ q.subpertanyaan }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-1.5 flex-wrap text-xs">
                                            <span :class="q.wajib ? 'font-bold text-gray-900' : 'text-gray-400'">{{ q.wajib ? 'Wajib' : 'Opsional' }}</span>
                                            <span class="text-gray-300">&bull;</span>
                                            <span class="text-gray-500">{{ getTargetBadge(q.tampil_di).label }}</span>
                                        </div>
                                        <p v-if="q.keterangan" class="text-[11px] text-gray-500 italic mt-1">
                                            Keterangan: {{ q.keterangan }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <span class="font-bold text-gray-800 block text-xs">
                                            {{ typeLabels[q.type] || q.type }}
                                        </span>
                                        <p class="text-[11px] text-gray-500 mt-1">
                                            {{ q.kelompok_pertanyaan ? `Bagian ${q.kelompok_pertanyaan.order}: ${q.kelompok_pertanyaan.title}` : '-' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3.5 max-w-md">
                                        <div v-if="canHaveOptions(q.type)" class="space-y-1.5">
                                            <div
                                                v-for="opt in q.detils"
                                                :key="opt.id"
                                                class="flex items-center justify-between p-1.5 bg-gray-50 border border-gray-200 rounded text-[11px]"
                                            >
                                                <div class="flex items-center gap-1.5 min-w-0 pr-2">
                                                    <span class="font-mono text-gray-500 font-bold shrink-0">[{{ opt.kode_opsi || `${q.kode_pertanyaan}-${opt.order}` }}]</span>
                                                    <span class="text-gray-800 font-medium truncate">{{ opt.option_text || opt.opsi }}</span>
                                                    <span v-if="opt.jump_to" class="text-gray-500 text-[10px] font-medium shrink-0">
                                                        &rarr; Lompat ke: [{{ opt.jump_to }}]
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <button
                                                        type="button"
                                                        @click="openOptionModal(q, opt)"
                                                        class="text-gray-500 hover:text-gray-900 font-medium cursor-pointer"
                                                    >
                                                        Edit
                                                    </button>
                                                    <span class="text-gray-300">|</span>
                                                    <button
                                                        type="button"
                                                        @click="handleDeleteOption(opt)"
                                                        class="text-gray-400 hover:text-rose-600 font-medium cursor-pointer"
                                                    >
                                                        Hapus
                                                    </button>
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
                                        <div v-else class="text-gray-400 italic text-[11px]">
                                            Input langsung tanpa pilihan opsi.
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                @click="openQuestionModal(q, 'kuesioner')"
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

                <!-- ======================================================== -->
                <!-- TAB 3: PERTANYAAN PROFIL ALUMNI                          -->
                <!-- ======================================================== -->
                <div v-if="activeTab === 'profile'" class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-gray-300 shadow-2xs">
                        <div class="flex flex-wrap items-center gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Filter Section</label>
                                <select v-model="selectedProfileSectionFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Section</option>
                                    <option v-for="s in sections" :key="s.id" :value="s.id">Bagian {{ s.order }}: {{ s.title }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Sifat Pengisian</label>
                                <select v-model="selectedProfileRequiredFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Sifat</option>
                                    <option value="wajib">Wajib (Required)</option>
                                    <option value="opsional">Opsional</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Lokasi Tampil</label>
                                <select v-model="selectedProfileTargetFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua (Profil & Keduanya)</option>
                                    <option value="profil">Hanya Profil Alumni</option>
                                    <option value="both">Kuesioner & Profil</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Pencarian Teks</label>
                                <input type="text" v-model="profileSearchQuery" placeholder="Cari kode atau pertanyaan..." class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs w-48 bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]" />
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="openQuestionModal(null, 'profil')"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer shrink-0"
                        >
                            <span>+ Tambah Pertanyaan Profil</span>
                        </button>
                    </div>

                    <!-- Tabel Pertanyaan Profil Alumni (2 Tone) -->
                    <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                <tr>
                                    <th class="px-3 py-3 text-center w-20">Urutan</th>
                                    <th class="px-3 py-3 w-28">Kode Field</th>
                                    <th class="px-4 py-3">Butir Data & Pertanyaan Profil</th>
                                    <th class="px-3 py-3">Tipe & Bagian (Section)</th>
                                    <th class="px-4 py-3">Opsi Jawaban (Jika Ada)</th>
                                    <th class="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredProfileQuestions.length === 0">
                                    <td colspan="6" class="p-8 text-center text-gray-400 italic">
                                        Belum ada butir pertanyaan profil alumni ditemukan yang sesuai filter.
                                    </td>
                                </tr>
                                <tr v-for="(q, idx) in filteredProfileQuestions" :key="q.id" class="hover:bg-gray-50/80 transition-colors">
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
                                                    :disabled="idx === filteredProfileQuestions.length - 1"
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
                                    <td class="px-3 py-3.5 font-mono font-bold text-gray-900">
                                        {{ q.kode_pertanyaan }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <p class="font-bold text-gray-900 leading-snug">
                                            {{ q.subpertanyaan }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-1.5 flex-wrap text-xs">
                                            <span :class="q.wajib ? 'font-bold text-gray-900' : 'text-gray-400'">{{ q.wajib ? 'Wajib' : 'Opsional' }}</span>
                                            <span class="text-gray-300">&bull;</span>
                                            <span class="text-gray-500">{{ getTargetBadge(q.tampil_di).label }}</span>
                                        </div>
                                        <p v-if="q.keterangan" class="text-[11px] text-gray-500 italic mt-1">
                                            Keterangan: {{ q.keterangan }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <span class="font-bold text-gray-800 block text-xs">
                                            {{ typeLabels[q.type] || q.type }}
                                        </span>
                                        <p class="text-[11px] text-gray-500 mt-1">
                                            {{ q.kelompok_pertanyaan ? `Bagian ${q.kelompok_pertanyaan.order}: ${q.kelompok_pertanyaan.title}` : '-' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3.5 max-w-md">
                                        <div v-if="canHaveOptions(q.type)" class="space-y-1.5">
                                            <div
                                                v-for="opt in q.detils"
                                                :key="opt.id"
                                                class="flex items-center justify-between p-1.5 bg-gray-50 border border-gray-200 rounded text-[11px]"
                                            >
                                                <div class="flex items-center gap-1.5 min-w-0 pr-2">
                                                    <span class="font-mono text-gray-500 font-bold shrink-0">[{{ opt.kode_opsi || `${q.kode_pertanyaan}-${opt.order}` }}]</span>
                                                    <span class="text-gray-800 font-medium truncate">{{ opt.option_text || opt.opsi }}</span>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <button
                                                        type="button"
                                                        @click="openOptionModal(q, opt)"
                                                        class="text-gray-500 hover:text-gray-900 font-medium cursor-pointer"
                                                    >
                                                        Edit
                                                    </button>
                                                    <span class="text-gray-300">|</span>
                                                    <button
                                                        type="button"
                                                        @click="handleDeleteOption(opt)"
                                                        class="text-gray-400 hover:text-rose-600 font-medium cursor-pointer"
                                                    >
                                                        Hapus
                                                    </button>
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
                                        <div v-else class="text-gray-400 italic text-[11px]">
                                            Input isian langsung profil tanpa opsi.
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                @click="openQuestionModal(q, 'profil')"
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

                <!-- ======================================================== -->
                <!-- TAB 4: KELOLA KUESIONER INDUK                            -->
                <!-- ======================================================== -->
                <div v-if="activeTab === 'kuesioner'" class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="w-full sm:w-72">
                            <input
                                type="text"
                                v-model="kuesionerSearchQuery"
                                placeholder="Cari judul kuesioner atau tahun..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] focus:outline-none bg-white"
                            />
                        </div>
                        <button
                            type="button"
                            @click="openKuesionerModal(null)"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <span>+ Tambah Kuesioner Induk</span>
                        </button>
                    </div>

                    <!-- Tabel Kuesioner Induk (2 Tone) -->
                    <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                <tr>
                                    <th class="px-4 py-3 text-center w-24">Tahun</th>
                                    <th class="px-4 py-3">Judul Kuesioner Induk</th>
                                    <th class="px-4 py-3">Deskripsi & Keterangan</th>
                                    <th class="px-4 py-3 text-center">Status Pengisian</th>
                                    <th class="px-4 py-3 text-center">Jumlah Seksi</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredKuesioners.length === 0">
                                    <td colspan="6" class="p-8 text-center text-gray-400 italic">
                                        Belum ada kuesioner induk yang terdaftar.
                                    </td>
                                </tr>
                                <tr v-for="k in filteredKuesioners" :key="k.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-4 py-3 text-center font-bold text-gray-900 text-sm">
                                        {{ k.year }}
                                    </td>
                                    <td class="px-4 py-3 font-bold text-sm text-gray-900">
                                        {{ k.title }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 max-w-sm">
                                        {{ k.description || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            v-if="k.is_active"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"
                                        >
                                            Aktif (Alumni)
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-600 border border-gray-200"
                                        >
                                            Nonaktif
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-700 font-medium text-xs">
                                        {{ k.kelompok_pertanyaan_count ?? 0 }} Seksi
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                @click="handleToggleKuesionerActive(k)"
                                                class="px-2.5 py-1 rounded-lg border text-xs font-semibold transition-colors cursor-pointer mr-1"
                                                :class="k.is_active ? 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50' : 'border-[#0D542B] text-[#0D542B] bg-emerald-50 hover:bg-emerald-100'"
                                            >
                                                {{ k.is_active ? 'Nonaktifkan' : 'Jadikan Aktif' }}
                                            </button>
                                            <button
                                                type="button"
                                                @click="openKuesionerModal(k)"
                                                class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Edit Kuesioner"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="handleDeleteKuesioner(k)"
                                                class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                title="Hapus Kuesioner"
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
        </div>

        <!-- ======================================================== -->
        <!-- 1. MODAL TELEPORT VUE 3: BAGIAN (SECTION) -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="isSectionModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
                @click.self="closeSectionModal"
                @keydown.esc="closeSectionModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-6 py-4 bg-white border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ isEditingSection ? 'Sunting Bagian Kuesioner Universitas' : 'Tambah Bagian Kuesioner Universitas Baru' }}
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

                    <form @submit.prevent="submitSectionForm" class="p-6 space-y-4 text-xs">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Kuesioner Induk <span class="text-rose-600">*</span>
                            </label>
                            <select
                                v-model="sectionFormData.kuesioner_id"
                                class="w-full px-3 py-2 border rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                :class="sectionFormErrors.kuesioner_id ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            >
                                <option value="" disabled>Pilih Kuesioner Induk...</option>
                                <option v-for="k in kuesioners" :key="k.id" :value="k.id">
                                    {{ k.title }} ({{ k.year || '-' }})
                                </option>
                            </select>
                            <p v-if="sectionFormErrors.kuesioner_id" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ sectionFormErrors.kuesioner_id }}
                            </p>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Judul Bagian (Section Title) <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="sectionFormData.title"
                                placeholder="Contoh: Identitas & Biodata Mahasiswa"
                                class="w-full px-3 py-2 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                :class="sectionFormErrors.title ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            />
                            <p v-if="sectionFormErrors.title" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ sectionFormErrors.title }}
                            </p>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Deskripsi / Petunjuk Pengisian (Opsional)
                            </label>
                            <textarea
                                v-model="sectionFormData.description"
                                rows="3"
                                placeholder="Ketikkan petunjuk pengisian bagi responden..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B] leading-relaxed"
                            ></textarea>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Nomor Urut Posisi
                            </label>
                            <input
                                type="number"
                                v-model.number="sectionFormData.order"
                                min="1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            />
                        </div>

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
        <!-- 2. MODAL TELEPORT VUE 3: BUTIR PERTANYAAN (QUESTION) -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="isQuestionModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
                @click.self="closeQuestionModal"
                @keydown.esc="closeQuestionModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-xl max-h-[90vh] flex flex-col overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-6 py-4 bg-white border-b border-gray-200 flex items-center justify-between shrink-0">
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ questionModalTitle }}
                        </h3>
                        <button
                            type="button"
                            @click="closeQuestionModal"
                            class="text-gray-400 hover:text-gray-600 p-1 rounded-md hover:bg-gray-100 transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitQuestionForm" class="p-6 space-y-3.5 text-xs overflow-y-auto">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">
                                    Kode Pertanyaan <span class="text-rose-600">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="questionFormData.kode_pertanyaan"
                                    placeholder="Contoh: F301, F13, BIO_BIDANG"
                                    class="w-full px-3 py-2 border rounded-lg text-xs font-mono font-bold uppercase focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                    :class="questionFormErrors.kode_pertanyaan ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                                />
                                <p v-if="questionFormErrors.kode_pertanyaan" class="text-rose-600 text-[11px] mt-1 font-medium">
                                    {{ questionFormErrors.kode_pertanyaan }}
                                </p>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">
                                    Lokasi Tampil <span class="text-rose-600">*</span>
                                </label>
                                <select
                                    v-model="questionFormData.tampil_di"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white font-bold text-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                >
                                    <option value="kuesioner">Kuesioner Tracer Saja</option>
                                    <option value="profil">Profil Alumni Saja</option>
                                    <option value="both">Keduanya (Kuesioner & Profil)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Bagian / Section <span class="text-rose-600">*</span>
                            </label>
                            <select
                                v-model="questionFormData.kelompok_pertanyaan_id"
                                class="w-full px-3 py-2 border rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                :class="questionFormErrors.kelompok_pertanyaan_id ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            >
                                <option value="" disabled>Pilih Bagian Kuesioner...</option>
                                <option v-for="s in sections" :key="s.id" :value="s.id">
                                    Bagian {{ s.order }}: {{ s.title }}
                                </option>
                            </select>
                            <p v-if="questionFormErrors.kelompok_pertanyaan_id" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ questionFormErrors.kelompok_pertanyaan_id }}
                            </p>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Butir Pertanyaan <span class="text-rose-600">*</span>
                            </label>
                            <textarea
                                v-model="questionFormData.subpertanyaan"
                                rows="3"
                                placeholder="Ketik kalimat butir pertanyaan..."
                                class="w-full px-3 py-2 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B] leading-relaxed"
                                :class="questionFormErrors.subpertanyaan ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            ></textarea>
                            <p v-if="questionFormErrors.subpertanyaan" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ questionFormErrors.subpertanyaan }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">
                                    Tipe Pertanyaan <span class="text-rose-600">*</span>
                                </label>
                                <select
                                    v-model="questionFormData.type"
                                    @change="handleQuestionTypeChange"
                                    class="w-full px-2.5 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                >
                                    <option v-for="(lbl, val) in univSelectableTypes" :key="val" :value="val">
                                        {{ lbl }}
                                    </option>
                                </select>
                                <p class="text-[10px] text-gray-500 mt-1">
                                    Opsi pilihan dapat ditambahkan setelah pertanyaan disimpan.
                                </p>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Nomor Urut</label>
                                <input
                                    type="number"
                                    v-model.number="questionFormData.order"
                                    min="1"
                                    class="w-full px-2.5 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Keterangan / Batasan Nilai (Opsional)
                            </label>
                            <input
                                type="text"
                                v-model="questionFormData.keterangan"
                                placeholder="Contoh: Minimal Rp 1.000, kelipatan ribuan, format tanggal"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            />
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <input
                                id="modal-q-req"
                                type="checkbox"
                                v-model="questionFormData.wajib"
                                class="rounded text-[#0D542B] focus:ring-[#0D542B] cursor-pointer"
                            />
                            <label for="modal-q-req" class="font-bold text-gray-700 select-none cursor-pointer">
                                Wajib diisi oleh alumni responden
                            </label>
                        </div>

                        <div class="pt-3 border-t border-gray-100 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="closeQuestionModal"
                                :disabled="isSubmittingQuestion"
                                class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors cursor-pointer disabled:opacity-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSubmittingQuestion"
                                class="px-4 py-2 text-xs font-semibold text-white bg-[#0D542B] hover:bg-[#08381c] rounded-lg transition-colors cursor-pointer flex items-center gap-1.5 disabled:opacity-50 shadow-xs"
                            >
                                <svg v-if="isSubmittingQuestion" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>{{ isSubmittingQuestion ? 'Menyimpan...' : (isEditingQuestion ? 'Simpan Perubahan' : 'Tambah Pertanyaan') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ======================================================== -->
        <!-- 3. MODAL TELEPORT VUE 3: OPSI JAWABAN (OPTION) -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="isOptionModalOpen && activeQuestionForOption"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
                @click.self="closeOptionModal"
                @keydown.esc="closeOptionModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-6 py-4 bg-white border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ isEditingOption ? 'Sunting Opsi Jawaban' : 'Tambah Opsi Jawaban' }} [{{ activeQuestionForOption.kode_pertanyaan }}]
                        </h3>
                        <button
                            type="button"
                            @click="closeOptionModal"
                            class="text-gray-400 hover:text-gray-600 p-1 rounded-md hover:bg-gray-100 transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitOptionForm" class="p-6 space-y-4 text-xs">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Kode Opsi <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="optionFormData.kode_opsi"
                                class="w-full px-3 py-2 border rounded-lg text-xs font-mono font-bold focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                :class="optionFormErrors.kode_opsi ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            />
                            <p v-if="optionFormErrors.kode_opsi" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ optionFormErrors.kode_opsi }}
                            </p>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Teks Opsi Jawaban <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="optionFormData.option_text"
                                placeholder="Contoh: Sangat Sesuai, Bekerja Penuh Waktu"
                                class="w-full px-3 py-2 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                :class="optionFormErrors.option_text ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            />
                            <p v-if="optionFormErrors.option_text" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ optionFormErrors.option_text }}
                            </p>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Alur Percabangan (Lompat Ke / Jump To)
                            </label>
                            <select
                                v-model="optionFormData.jump_to"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            >
                                <option value="">-- Tanpa Percabangan (Lanjut Normal) --</option>
                                <option
                                    v-for="t in availableJumpTargets"
                                    :key="t.kode_pertanyaan"
                                    :value="t.kode_pertanyaan"
                                >
                                    Lompat ke: [{{ t.kode_pertanyaan }}] {{ t.text?.substring(0, 45) }}...
                                </option>
                            </select>
                            <p class="text-[10px] text-gray-400 mt-1">
                                Jika responden memilih opsi ini, kuesioner akan melompat langsung ke pertanyaan target.
                            </p>
                        </div>

                        <div class="pt-3 border-t border-gray-100 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="closeOptionModal"
                                :disabled="isSubmittingOption"
                                class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors cursor-pointer disabled:opacity-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSubmittingOption"
                                class="px-4 py-2 text-xs font-semibold text-white bg-[#0D542B] hover:bg-[#08381c] rounded-lg transition-colors cursor-pointer flex items-center gap-1.5 disabled:opacity-50 shadow-xs"
                            >
                                <svg v-if="isSubmittingOption" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>{{ isSubmittingOption ? 'Menyimpan...' : (isEditingOption ? 'Simpan Opsi' : 'Tambah Opsi') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ======================================================== -->
        <!-- 4. MODAL TELEPORT VUE 3: KUESIONER INDUK -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="isKuesionerModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
                @click.self="closeKuesionerModal"
                @keydown.esc="closeKuesionerModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-6 py-4 bg-white border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ isEditingKuesioner ? 'Sunting Kuesioner Induk' : 'Tambah Kuesioner Induk Baru' }}
                        </h3>
                        <button
                            type="button"
                            @click="closeKuesionerModal"
                            class="text-gray-400 hover:text-gray-600 p-1 rounded-md hover:bg-gray-100 transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitKuesionerForm" class="p-6 space-y-4 text-xs">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Judul Kuesioner Induk <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="kuesionerFormData.title"
                                placeholder="Contoh: Tracer Study UKDW 2026"
                                class="w-full px-3 py-2 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                :class="kuesionerFormErrors.title ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            />
                            <p v-if="kuesionerFormErrors.title" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ kuesionerFormErrors.title }}
                            </p>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Tahun Kuesioner <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="number"
                                v-model.number="kuesionerFormData.year"
                                min="2000"
                                max="2100"
                                class="w-full px-3 py-2 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                :class="kuesionerFormErrors.year ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            />
                            <p v-if="kuesionerFormErrors.year" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ kuesionerFormErrors.year }}
                            </p>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Deskripsi / Keterangan (Opsional)
                            </label>
                            <textarea
                                v-model="kuesionerFormData.description"
                                rows="3"
                                placeholder="Ketik keterangan atau standar instrumen tracer study..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B] leading-relaxed"
                            ></textarea>
                        </div>

                        <div class="pt-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    v-model="kuesionerFormData.is_active"
                                    class="w-4 h-4 rounded text-[#0D542B] focus:ring-[#0D542B] border-gray-300 cursor-pointer"
                                />
                                <span class="font-bold text-gray-700 text-xs">Jadikan Kuesioner Aktif (Ditampilkan kepada Alumni)</span>
                            </label>
                            <p class="text-[11px] text-gray-400 italic mt-0.5 ml-6">
                                Mengaktifkan kuesioner ini akan secara otomatis menonaktifkan kuesioner induk lainnya.
                            </p>
                        </div>

                        <div class="pt-3 border-t border-gray-100 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="closeKuesionerModal"
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
