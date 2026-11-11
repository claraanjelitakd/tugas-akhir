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
    profileManagedCodes: {
        type: Array,
        default: () => [],
    },
    bankQuestions: {
        type: Array,
        default: () => [],
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
// HELPER LOKASI TAMPIL & VISIBILITAS
// ========================================================
const isProfileTarget = (target) => target === 'profil' || target === 'profile';
const isBothTarget = (target) => target === 'both';
const isTracerTarget = (target) => target === 'kuesioner' || (!target && !isProfileTarget(target));
const isShownInProfile = (q) => isProfileTarget(q?.tampil_di) || isBothTarget(q?.tampil_di);
const isCompoundField = (code) => ['F510', 'F5A1', 'F5A2', 'F2F'].includes(String(code || '').toUpperCase());

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

// Menyimpan konteks tab dan filter pada URL sebelum request CRUD dikirim.
const syncQuestionnaireContext = () => {
    if (typeof window === 'undefined') return;

    const url = new URL(window.location.href);
    url.searchParams.set('tab', activeTab.value);

    const context = {
        section_search: sectionSearchQuery.value,
        tracer_section: selectedTracerSectionFilter.value,
        tracer_required: selectedTracerRequiredFilter.value,
        tracer_target: selectedTracerTargetFilter.value,
        tracer_search: tracerSearchQuery.value,
        profile_section: selectedProfileSectionFilter.value,
        profile_required: selectedProfileRequiredFilter.value,
        profile_visibility: selectedProfileVisibilityFilter.value,
        profile_target: selectedProfileTargetFilter.value,
        profile_search: profileSearchQuery.value,
        kuesioner_search: kuesionerSearchQuery.value,
    };

    Object.entries(context).forEach(([key, value]) => {
        if (value && value !== 'all') {
            url.searchParams.set(key, String(value).trim());
        } else {
            url.searchParams.delete(key);
        }
    });

    window.history.replaceState({}, '', url);
};

// Membaca nilai konteks dari URL saat halaman hasil redirect CRUD dimuat ulang.
const getContextQuery = (key, fallback = '') => {
    if (typeof window === 'undefined') return fallback;

    return new URLSearchParams(window.location.search).get(key) || fallback;
};

const switchTab = (tabName) => {
    activeTab.value = tabName;
    syncQuestionnaireContext();
};

// ========================================================
// 1. SECTION STATE & FILTERS
// ========================================================
const sectionSearchQuery = ref(getContextQuery('section_search'));
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
    multiple_choice_text: 'Kotak Centang + Isian (Checkbox + Teks)',
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
    multiple_choice_text: 'Kotak Centang + Isian (Checkbox + Teks)',
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
    radio_input: 'Pilih satu jawaban; isi angka bulan jika diminta.',
    multiple_choice: 'Jawaban dapat lebih dari satu.',
    checkbox: 'Jawaban dapat lebih dari satu.',
    multiple_choice_text: 'Jawaban dapat lebih dari satu. Opsi Lainnya menyediakan kolom isian.',
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
    'multiple_choice_text',
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
const selectedTracerSectionFilter = ref(getContextQuery('tracer_section', 'all'));
const selectedTracerRequiredFilter = ref(getContextQuery('tracer_required', 'all'));
const selectedTracerTargetFilter = ref(getContextQuery('tracer_target', 'all'));
const tracerSearchQuery = ref(getContextQuery('tracer_search'));

const filteredTracerQuestions = computed(() => {
    return props.subpertanyaans.filter((q) => {
        // Tampilkan hanya pertanyaan tracer (kuesioner atau both)
        if (isProfileTarget(q.tampil_di)) return false;

        if (selectedTracerSectionFilter.value !== 'all') {
            const secId = parseInt(selectedTracerSectionFilter.value, 10);
            if (q.section_id !== secId) return false;
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
const selectedProfileSectionFilter = ref(getContextQuery('profile_section', 'all'));
const selectedProfileRequiredFilter = ref(getContextQuery('profile_required', 'all'));
const selectedProfileVisibilityFilter = ref(getContextQuery('profile_visibility', 'all'));
const selectedProfileTargetFilter = ref(getContextQuery('profile_target', 'all'));
const profileSearchQuery = ref(getContextQuery('profile_search'));

const filteredProfileQuestions = computed(() => {
    return props.subpertanyaans.filter((q) => {
        const codeUpper = String(q.kode_pertanyaan || '').toUpperCase();
        const isManagedProfile = (props.profileManagedCodes || []).includes(codeUpper)
            || isProfileTarget(q.tampil_di)
            || isBothTarget(q.tampil_di);

        // Hanya tampilkan jika butir ini relevan dengan profil alumni
        if (!isManagedProfile) return false;

        // Filter status visibilitas (Ditampilkan vs Disembunyikan)
        if (selectedProfileVisibilityFilter.value !== 'all') {
            const isVisible = isShownInProfile(q);
            if (selectedProfileVisibilityFilter.value === 'visible' && !isVisible) return false;
            if (selectedProfileVisibilityFilter.value === 'hidden' && isVisible) return false;
        }

        if (selectedProfileSectionFilter.value !== 'all') {
            const secId = parseInt(selectedProfileSectionFilter.value, 10);
            if (q.section_id !== secId) return false;
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
const kuesionerSearchQuery = ref(getContextQuery('kuesioner_search'));
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
    section_id: '',
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
    code: '',
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
    target_response_rate: 60.0,
});
const kuesionerFormErrors = ref({});
const isSubmittingKuesioner = ref(false);

// ========================================================
// 5. BANK PERTANYAAN (MODAL SELECTION & ATTACH UNIV)
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
    targetSectionIdForBank.value = selectedTracerSectionFilter.value !== 'all' ? selectedTracerSectionFilter.value : props.sections[0]?.id;
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
    router.post('/superadmin/pertanyaan/bank/attach', {
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
                text: 'Pertanyaan dari Bank Soal berhasil disematkan ke kuesioner universitas.',
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
            section_id: questionToEdit.section_id || (props.sections[0]?.id || ''),
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
            section_id: props.sections[0]?.id || '',
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
    if (!questionFormData.value.section_id) {
        questionFormErrors.value.section_id = 'Bagian (Section) wajib dipilih.';
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

/**
 * Mengubah visibilitas tampilnya butir pertanyaan (di Profil Alumni atau Kuesioner Tracer).
 */
const handleToggleVisibility = (question, target = 'profil') => {
    router.patch(`/superadmin/pertanyaan/${question.id}/toggle-visibility`, {
        target: target,
    }, {
        preserveScroll: true,
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Terjadi kendala saat mengubah status visibilitas pertanyaan.',
            });
        },
    });
};

/**
 * Mengubah status aktif master pertanyaan tanpa mengubah visibilitas section.
 */
const handleToggleQuestionActive = (question) => {
    const actionText = question.is_active ? 'menonaktifkan' : 'mengaktifkan';

    Swal.fire({
        icon: 'warning',
        title: 'Ubah status pertanyaan?',
        text: `Anda akan ${actionText} pertanyaan [${question.kode_pertanyaan}]. Jawaban historis tetap dipertahankan.`,
        showCancelButton: true,
        confirmButtonColor: '#0D542B',
        confirmButtonText: question.is_active ? 'Nonaktifkan' : 'Aktifkan',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(`/superadmin/pertanyaan/${question.id}/toggle-active`, {}, {
                preserveScroll: true,
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
            code: optionToEdit.code || '',
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
            code: autoCode,
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

    if (!optionFormData.value.code.trim()) {
        optionFormErrors.value.code = 'Kode opsi wajib diisi.';
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
            target_response_rate: kuesionerToEdit.target_response_rate ? Number(kuesionerToEdit.target_response_rate) : 60.0,
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
            target_response_rate: 60.0,
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
    const targetRate = parseFloat(kuesionerFormData.value.target_response_rate);
    if (isNaN(targetRate) || targetRate < 1 || targetRate > 100) {
        kuesionerFormErrors.value.target_response_rate = 'Target response rate harus berupa persentase antara 1 - 100%.';
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

    <div class="min-h-screen bg-[#F8FAFC] text-slate-800 font-sans flex min-w-[1280px]">
        <Sidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Solid Hijau Resmi UKDW #0D542B -->
            <header class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full flex flex-col md:flex-row md:items-center justify-between gap-6">
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

            <main class="admin-page-main w-full -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
                <!-- Bar Informasi Kuesioner Induk & Ringkasan Data (2 Tone) -->
                <div class="bg-white rounded-xl p-5 border border-gray-300 shadow-2xs">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                {{ activeKuesioner?.is_active ? 'Instrumen Kuesioner Induk Aktif' : 'Pratinjau Kuesioner Induk (Status Nonaktif)' }}
                            </label>
                            <div class="flex items-center gap-3 flex-wrap">
                                <div class="px-3.5 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm font-bold text-[#0D542B] flex items-center gap-2">
                                    <span>{{ activeKuesioner?.title ? `[${activeKuesioner.year}] ${activeKuesioner.title}` : 'Belum Ada Kuesioner Terdaftar' }}</span>
                                    <span v-if="activeKuesioner?.is_active" class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        Aktif
                                    </span>
                                    <span v-else-if="activeKuesioner" class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-slate-100 text-slate-600 border border-slate-300">
                                        Nonaktif (Draft / Arsip)
                                    </span>
                                </div>
                                <button
                                    v-if="activeKuesioner && !activeKuesioner.is_active"
                                    type="button"
                                    @click="handleToggleKuesionerActive(activeKuesioner)"
                                    class="px-3 py-1.5 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-semibold rounded-lg transition-colors cursor-pointer flex items-center gap-1.5"
                                    title="Aktifkan kuesioner ini agar dapat diisi oleh alumni"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Aktifkan Kuesioner Ini</span>
                                </button>
                                <button
                                    v-else-if="activeKuesioner && activeKuesioner.is_active"
                                    type="button"
                                    @click="handleToggleKuesionerActive(activeKuesioner)"
                                    class="px-3 py-1.5 border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold rounded-lg transition-colors cursor-pointer"
                                    title="Nonaktifkan kuesioner ini (alumni tidak dapat mengisi)"
                                >
                                    <span>Nonaktifkan</span>
                                </button>
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
                                    @input="syncQuestionnaireContext"
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
                                <select v-model="selectedTracerSectionFilter" @change="syncQuestionnaireContext" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Section</option>
                                    <option v-for="s in sections" :key="s.id" :value="s.id">Bagian {{ s.order }}: {{ s.title }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Sifat Pengisian</label>
                                <select v-model="selectedTracerRequiredFilter" @change="syncQuestionnaireContext" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Sifat</option>
                                    <option value="wajib">Wajib (Required)</option>
                                    <option value="opsional">Opsional</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Lokasi Tampil</label>
                                <select v-model="selectedTracerTargetFilter" @change="syncQuestionnaireContext" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua (Kuesioner & Keduanya)</option>
                                    <option value="kuesioner">Hanya Kuesioner Tracer</option>
                                    <option value="both">Kuesioner & Profil</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Pencarian Teks</label>
                                <input type="text" v-model="tracerSearchQuery" @input="syncQuestionnaireContext" placeholder="Cari kode atau pertanyaan..." class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs w-48 bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]" />
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="openBankModal"
                                class="px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-lg border border-slate-300 shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer shrink-0"
                            >
                                <span>Pilih dari Bank Pertanyaan</span>
                            </button>
                            <button
                                type="button"
                                @click="openQuestionModal(null, 'kuesioner')"
                                class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer shrink-0"
                            >
                                <span>+ Tambah Pertanyaan Baru</span>
                            </button>
                        </div>
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
                                    <th class="px-3 py-3 text-center w-24">Status</th>
                                    <th class="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredTracerQuestions.length === 0">
                                    <td colspan="7" class="p-8 text-center text-gray-400 italic">
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
                                            {{ q.section ? `Bagian ${q.section.order}: ${q.section.title}` : '-' }}
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
                                                    <span class="font-mono text-gray-500 font-bold shrink-0">[{{ opt.code || `${q.kode_pertanyaan}-${opt.order}` }}]</span>
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
                                    <td class="px-3 py-3.5 text-center">
                                        <button
                                            type="button"
                                            class="inline-flex min-w-[64px] items-center justify-center rounded-lg border px-3 py-1.5 text-[11px] font-semibold transition-colors"
                                            :class="q.is_active ? 'border-emerald-200 bg-emerald-50 text-[#0D542B] hover:bg-emerald-100' : 'border-gray-200 bg-gray-50 text-gray-500 hover:bg-gray-100'"
                                            :title="q.is_active ? 'Klik untuk menonaktifkan pertanyaan' : 'Klik untuk mengaktifkan pertanyaan'"
                                            @click="handleToggleQuestionActive(q)"
                                        >
                                            {{ q.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
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
                                <select v-model="selectedProfileSectionFilter" @change="syncQuestionnaireContext" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Section</option>
                                    <option v-for="s in sections" :key="s.id" :value="s.id">Bagian {{ s.order }}: {{ s.title }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Status Profil</label>
                                <select v-model="selectedProfileVisibilityFilter" @change="syncQuestionnaireContext" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Status</option>
                                    <option value="visible">Ditampilkan (Aktif)</option>
                                    <option value="hidden">Disembunyikan</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Sifat Pengisian</label>
                                <select v-model="selectedProfileRequiredFilter" @change="syncQuestionnaireContext" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Sifat</option>
                                    <option value="wajib">Wajib (Required)</option>
                                    <option value="opsional">Opsional</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Lokasi Tampil</label>
                                <select v-model="selectedProfileTargetFilter" @change="syncQuestionnaireContext" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua (Profil & Keduanya)</option>
                                    <option value="profil">Hanya Profil Alumni</option>
                                    <option value="both">Kuesioner & Profil</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Pencarian Teks</label>
                                <input type="text" v-model="profileSearchQuery" @input="syncQuestionnaireContext" placeholder="Cari kode atau pertanyaan..." class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs w-48 bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]" />
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
                                    <th class="px-3 py-3 text-center w-24">Status</th>
                                    <th class="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredProfileQuestions.length === 0">
                                    <td colspan="7" class="p-8 text-center text-gray-400 italic">
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
                                            <span class="text-gray-300">&bull;</span>
                                            <span
                                                :class="isShownInProfile(q) ? 'bg-emerald-50 text-[#0D542B] border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold border"
                                            >
                                                {{ isShownInProfile(q) ? 'Ditampilkan' : 'Disembunyikan' }}
                                            </span>
                                            <span
                                                v-if="isCompoundField(q.kode_pertanyaan)"
                                                class="px-1.5 py-0.5 bg-slate-100 text-slate-700 rounded text-[10px] font-medium border border-slate-200"
                                            >
                                                Data Gabungan Alamat
                                            </span>
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
                                            {{ q.section ? `Bagian ${q.section.order}: ${q.section.title}` : '-' }}
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
                                                    <span class="font-mono text-gray-500 font-bold shrink-0">[{{ opt.code || `${q.kode_pertanyaan}-${opt.order}` }}]</span>
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
                                    <td class="px-3 py-3.5 text-center">
                                        <button
                                            type="button"
                                            class="inline-flex min-w-[64px] items-center justify-center rounded-lg border px-3 py-1.5 text-[11px] font-semibold transition-colors"
                                            :class="q.is_active ? 'border-emerald-200 bg-emerald-50 text-[#0D542B] hover:bg-emerald-100' : 'border-gray-200 bg-gray-50 text-gray-500 hover:bg-gray-100'"
                                            :title="q.is_active ? 'Klik untuk menonaktifkan pertanyaan' : 'Klik untuk mengaktifkan pertanyaan'"
                                            @click="handleToggleQuestionActive(q)"
                                        >
                                            {{ q.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="px-3 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                @click="handleToggleVisibility(q, 'profil')"
                                                :class="isShownInProfile(q) ? 'text-[#0D542B] hover:text-[#08381c] hover:bg-emerald-50' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-100'"
                                                class="p-1.5 rounded-lg transition-colors cursor-pointer"
                                                :title="isShownInProfile(q) ? 'Pertanyaan aktif di profil (klik untuk menyembunyikan)' : 'Pertanyaan disembunyikan (klik untuk menampilkan di profil)'"
                                            >
                                                <svg v-if="isShownInProfile(q)" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                                </svg>
                                            </button>
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
                                @input="syncQuestionnaireContext"
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
                                    <th class="px-4 py-3 text-center">Target Response Rate</th>
                                    <th class="px-4 py-3">Deskripsi & Keterangan</th>
                                    <th class="px-4 py-3 text-center">Status Pengisian</th>
                                    <th class="px-4 py-3 text-center">Jumlah Seksi</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredKuesioners.length === 0">
                                    <td colspan="7" class="p-8 text-center text-gray-400 italic">
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
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            {{ k.target_response_rate ? Number(k.target_response_rate).toFixed(1) : '60.0' }}%
                                        </span>
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
                                        {{ k.sections_count ?? 0 }} Seksi
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
                <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
                    <div class="flex shrink-0 items-start justify-between border-b border-gray-200 bg-white px-6 py-4">
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ questionModalTitle }}
                        </h3>
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
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
                                    Lokasi Tampil
                                </label>
                                <select
                                    v-model="questionFormData.tampil_di"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white font-bold text-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                >
                                    <option value="both">Keduanya (Kuesioner & Profil)</option>
                                    <option value="profil">Profil Alumni Saja</option>
                                    <option value="kuesioner">Kuesioner Tracer Saja (Sembunyi di Profil)</option>
                                    <option value="hidden">Disembunyikan (Nonaktif dari Keduanya)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block font-bold text-gray-700">
                                Bagian / Section <span class="text-rose-600">*</span>
                            </label>
                            <select
                                v-model="questionFormData.section_id"
                                class="w-full px-3 py-2 border rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                :class="questionFormErrors.section_id ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            >
                                <option value="" disabled>Pilih Bagian Kuesioner...</option>
                                <option v-for="s in sections" :key="s.id" :value="s.id">
                                    Bagian {{ s.order }}: {{ s.title }}
                                </option>
                            </select>
                            <p v-if="questionFormErrors.section_id" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ questionFormErrors.section_id }}
                            </p>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Teks Pertanyaan <span class="text-rose-600">*</span>
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

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
                                <p class="mt-1 text-[10px] text-gray-500">
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
                                Keterangan / Petunjuk Pengisian (Opsional)
                            </label>
                            <textarea
                                v-model="questionFormData.keterangan"
                                rows="2"
                                placeholder="Ketikkan petunjuk pengisian bagi responden..."
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs leading-relaxed focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            ></textarea>
                        </div>

                        <div class="flex items-center gap-2 border-t border-gray-100 pt-3">
                            <input
                                id="modal-q-req"
                                type="checkbox"
                                v-model="questionFormData.wajib"
                                class="rounded text-[#0D542B] focus:ring-[#0D542B] cursor-pointer"
                            />
                            <label for="modal-q-req" class="font-bold text-gray-700 select-none cursor-pointer">
                                Wajib diisi oleh responden
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-4">
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
                                v-model="optionFormData.code"
                                class="w-full px-3 py-2 border rounded-lg text-xs font-mono font-bold focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                :class="optionFormErrors.code ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                            />
                            <p v-if="optionFormErrors.code" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ optionFormErrors.code }}
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
                                    Lompat ke: [{{ t.kode_pertanyaan }}] {{ t.text }}
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

                        <!-- Target Response Rate (%) -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">
                                Target Response Rate (%) <span class="text-rose-600">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    type="number"
                                    step="0.1"
                                    min="1"
                                    max="100"
                                    v-model.number="kuesionerFormData.target_response_rate"
                                    placeholder="60.0"
                                    class="w-full px-3 py-2 pr-8 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                    :class="kuesionerFormErrors.target_response_rate ? 'border-rose-400 bg-rose-50/30' : 'border-gray-300'"
                                />
                                <span class="absolute right-3 top-2 text-gray-400 font-bold text-xs">%</span>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-1">
                                Standar nasional Kemendikbudristek & IKU 1 adalah 60,0%. Target ini akan otomatis mengkalibrasi garis batas merah pada grafik analitik Indikator 01.
                            </p>
                            <p v-if="kuesionerFormErrors.target_response_rate" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ kuesionerFormErrors.target_response_rate }}
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

        <!-- ======================================================== -->
        <!-- MODAL 5: PILIH PERTANYAAN DARI BANK SOAL (UNIVERSITAS)   -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="isBankModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            >
                <div class="relative w-full max-w-3xl rounded-xl bg-white shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
                    <!-- Header Modal -->
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 bg-slate-50">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900">Pilih dari Bank Pertanyaan</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Sematkan butir pertanyaan yang sudah tersedia dari repositori Bank Soal ke kuesioner universitas tanpa perlu mengetik ulang.</p>
                        </div>
                        <button
                            type="button"
                            @click="closeBankModal"
                            class="text-gray-400 hover:text-gray-700 p-1 rounded-lg cursor-pointer"
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
                                Tempatkan pada Bagian (Section) Universitas:
                            </label>
                            <select
                                v-model="targetSectionIdForBank"
                                class="px-3 py-1.5 border border-emerald-300 rounded-lg text-xs font-semibold bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            >
                                <option v-for="s in sections" :key="s.id" :value="s.id">Bagian {{ s.order }}: {{ s.title }}</option>
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
                                    class="mt-1 h-4 w-4 rounded border-gray-300 text-[#0D542B] focus:ring-[#0D542B] cursor-pointer"
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
                                            Sudah Ada di Instrumen Universitas
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
                                class="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-100 transition-colors cursor-pointer"
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
    </div>
</template>
