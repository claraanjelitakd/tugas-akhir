<!--
  Halaman: Pengaturan Kuesioner Evaluasi Pengguna Lulusan (Super Admin)
  File: resources/js/Pages/SuperAdmin/EvaluasiAtasan/Index.vue

  Tanggung Jawab:
  - Mengelola Bagian / Section Kuesioner Evaluasi Atasan (CRUD & Reorder Naik/Turun)
  - Mengelola Butir Pertanyaan / Aspek Penilaian Kinerja Lulusan (CRUD & Reorder Naik/Turun)
  - Meninjau Riwayat Pengisian Formulir Evaluasi oleh Atasan Lulusan
  - Tampilan Identik dengan Pengaturan Kuesioner Induk Universitas UKDW (Bebas Emoji, Solid Green #0D542B)
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import Sidebar from '../Components/Sidebar.vue';
import PaginationControls from '@/components/PaginationControls.vue';

const props = defineProps({
    user: Object,
    questions: {
        type: Array,
        default: () => [],
    },
    sections: {
        type: Array,
        default: () => [],
    },
    evaluasis: {
        type: Object,
        default: () => ({ data: [] }),
    },
    stats: {
        type: Object,
        default: () => ({
            total_questions: 0,
            total_sections: 0,
            active_questions: 0,
            total_evaluasi: 0,
            submitted_evaluasi: 0,
        }),
    },
});

// Tab Aktif: 'sections', 'questions', atau 'responses'
const activeTab = ref('sections');

const switchTab = (tab) => {
    activeTab.value = tab;
};

// Label Tipe Input Pertanyaan
const typeLabels = {
    likert_5: 'Skala Likert (1-5)',
    pilihan_ganda: 'Pilihan Ganda (Radio)',
    multiple_choice: 'Kotak Centang (Checkbox)',
    text: 'Jawaban Singkat (Text)',
    textarea: 'Paragraf (Textarea)',
};

const canHaveOptions = (type) => ['likert_5', 'pilihan_ganda', 'multiple_choice'].includes(type);

// ========================================================
// SECTION STATE & HANDLERS
// ========================================================
const sectionSearchQuery = ref('');
const isSectionModalOpen = ref(false);
const isEditingSection = ref(false);
const editingSectionId = ref(null);
const sectionFormErrors = ref({});
const sectionForm = ref({
    title: '',
    description: '',
    order: 1,
});

const filteredSections = computed(() => {
    if (!sectionSearchQuery.value.trim()) return props.sections;
    const q = sectionSearchQuery.value.toLowerCase();
    return props.sections.filter(s =>
        s.title?.toLowerCase().includes(q) ||
        s.description?.toLowerCase().includes(q)
    );
});

const openSectionModal = (secToEdit = null) => {
    isEditingSection.value = !!secToEdit;
    editingSectionId.value = secToEdit ? secToEdit.id : null;
    sectionFormErrors.value = {};

    if (secToEdit) {
        sectionForm.value = {
            title: secToEdit.title || '',
            description: secToEdit.description || '',
            order: secToEdit.order || 1,
        };
    } else {
        sectionForm.value = {
            title: '',
            description: '',
            order: props.sections.length + 1,
        };
    }
    isSectionModalOpen.value = true;
};

const closeSectionModal = () => {
    isSectionModalOpen.value = false;
    sectionFormErrors.value = {};
};

const submitSectionForm = () => {
    sectionFormErrors.value = {};
    if (!sectionForm.value.title?.trim()) {
        sectionFormErrors.value.title = 'Judul bagian kuesioner wajib diisi.';
        return;
    }

    if (isEditingSection.value) {
        router.put(`/superadmin/evaluasi-atasan/sections/${editingSectionId.value}`, sectionForm.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeSectionModal();
                Swal.fire('Berhasil', 'Bagian kuesioner berhasil diperbarui.', 'success');
            },
        });
    } else {
        router.post('/superadmin/evaluasi-atasan/sections', sectionForm.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeSectionModal();
                Swal.fire('Berhasil', 'Bagian kuesioner baru berhasil ditambahkan.', 'success');
            },
        });
    }
};

const handleReorderSection = (sectionId, direction) => {
    router.post('/superadmin/evaluasi-atasan/sections/reorder', {
        id: sectionId,
        direction,
    }, {
        preserveScroll: true,
    });
};

const handleDeleteSection = (sec) => {
    Swal.fire({
        title: 'Hapus Bagian Evaluasi Atasan?',
        text: `Apakah Anda yakin ingin menghapus bagian "${sec.title}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Bagian',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/superadmin/evaluasi-atasan/sections/${sec.id}`, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Terhapus', 'Bagian kuesioner berhasil dihapus.', 'success'),
                onError: (err) => {
                    const msg = err.message || 'Gagal menghapus bagian. Pastikan bagian tidak memiliki butir pertanyaan aktif.';
                    Swal.fire('Gagal Menghapus', msg, 'error');
                },
            });
        }
    });
};

// ========================================================
// QUESTION STATE & HANDLERS
// ========================================================
const selectedSectionFilter = ref('all');
const selectedRequiredFilter = ref('all');
const questionSearchQuery = ref('');
const isQuestionModalOpen = ref(false);
const isEditingQuestion = ref(false);
const editingQuestionId = ref(null);
const questionFormErrors = ref({});
const newOptionInput = ref('');

const questionForm = ref({
    section_id: '',
    kode: '',
    aspek: '',
    deskripsi: '',
    tipe: 'likert_5',
    pilihan_jawaban: [],
    order: 1,
});

const filteredQuestions = computed(() => {
    let result = props.questions;

    if (selectedSectionFilter.value !== 'all') {
        const secId = parseInt(selectedSectionFilter.value, 10);
        result = result.filter(q => q.section_id === secId);
    }

    if (selectedRequiredFilter.value === 'wajib') {
        result = result.filter(q => q.is_active);
    } else if (selectedRequiredFilter.value === 'opsional') {
        result = result.filter(q => !q.is_active);
    }

    if (questionSearchQuery.value.trim()) {
        const q = questionSearchQuery.value.toLowerCase();
        result = result.filter(item =>
            item.aspek?.toLowerCase().includes(q) ||
            item.kode?.toLowerCase().includes(q) ||
            item.deskripsi?.toLowerCase().includes(q)
        );
    }

    return result;
});

const openQuestionModal = (questionToEdit = null) => {
    isEditingQuestion.value = !!questionToEdit;
    editingQuestionId.value = questionToEdit ? questionToEdit.id : null;
    questionFormErrors.value = {};
    newOptionInput.value = '';

    if (questionToEdit) {
        let opts = [];
        if (Array.isArray(questionToEdit.pilihan_jawaban)) {
            opts = [...questionToEdit.pilihan_jawaban];
        } else if (typeof questionToEdit.pilihan_jawaban === 'string') {
            try {
                opts = JSON.parse(questionToEdit.pilihan_jawaban);
            } catch (e) {
                opts = [];
            }
        }

        questionForm.value = {
            section_id: questionToEdit.section_id || (props.sections[0]?.id || ''),
            kode: questionToEdit.kode || '',
            aspek: questionToEdit.aspek || '',
            deskripsi: questionToEdit.deskripsi || '',
            tipe: questionToEdit.tipe || 'likert_5',
            pilihan_jawaban: opts,
            order: questionToEdit.order || 1,
            is_company_field: Boolean(questionToEdit.is_company_field),
        };
    } else {
        const defaultSec = props.sections.length > 0 ? props.sections[props.sections.length - 1] : null;
        questionForm.value = {
            section_id: defaultSec?.id || (props.sections[0]?.id || ''),
            kode: `P${props.questions.length + 1}`,
            aspek: '',
            deskripsi: '',
            tipe: 'likert_5',
            pilihan_jawaban: ['Sangat Tinggi', 'Tinggi', 'Cukup', 'Kurang', 'Sangat Kurang'],
            order: props.questions.length + 1,
            is_company_field: false,
        };
    }

    isQuestionModalOpen.value = true;
};

const closeQuestionModal = () => {
    isQuestionModalOpen.value = false;
    questionFormErrors.value = {};
};

const handleTypeChange = () => {
    if (questionForm.value.tipe === 'likert_5') {
        questionForm.value.pilihan_jawaban = ['Sangat Tinggi', 'Tinggi', 'Cukup', 'Kurang', 'Sangat Kurang'];
    } else if (['pilihan_ganda', 'multiple_choice'].includes(questionForm.value.tipe)) {
        if (!questionForm.value.pilihan_jawaban || questionForm.value.pilihan_jawaban.length === 0) {
            questionForm.value.pilihan_jawaban = ['Opsi 1', 'Opsi 2'];
        }
    } else {
        questionForm.value.pilihan_jawaban = [];
    }
};

const addOption = () => {
    if (newOptionInput.value.trim()) {
        if (!Array.isArray(questionForm.value.pilihan_jawaban)) {
            questionForm.value.pilihan_jawaban = [];
        }
        questionForm.value.pilihan_jawaban.push(newOptionInput.value.trim());
        newOptionInput.value = '';
    }
};

const removeOption = (index) => {
    questionForm.value.pilihan_jawaban.splice(index, 1);
};

const submitQuestionForm = () => {
    questionFormErrors.value = {};
    if (!questionForm.value.section_id) {
        questionFormErrors.value.section_id = 'Bagian / section wajib dipilih.';
    }
    if (!questionForm.value.aspek?.trim()) {
        questionFormErrors.value.aspek = 'Teks pertanyaan / aspek evaluasi wajib diisi.';
    }

    if (Object.keys(questionFormErrors.value).length > 0) return;

    if (isEditingQuestion.value) {
        router.put(`/superadmin/evaluasi-atasan/pertanyaan/${editingQuestionId.value}`, questionForm.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeQuestionModal();
                Swal.fire('Berhasil', 'Pertanyaan evaluasi berhasil diperbarui.', 'success');
            },
        });
    } else {
        router.post('/superadmin/evaluasi-atasan/pertanyaan', questionForm.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeQuestionModal();
                Swal.fire('Berhasil', 'Pertanyaan evaluasi baru berhasil ditambahkan.', 'success');
            },
        });
    }
};

const handleReorderQuestion = (questionId, direction) => {
    router.post('/superadmin/evaluasi-atasan/pertanyaan/reorder', {
        id: questionId,
        direction,
    }, {
        preserveScroll: true,
    });
};

const toggleActive = (question) => {
    router.patch(`/superadmin/evaluasi-atasan/pertanyaan/${question.id}/toggle`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire('Status Diperbarui', `Aspek ${question.aspek} kini ${question.is_active ? 'dinonaktifkan' : 'diaktifkan'}.`, 'success');
        },
    });
};

const handleDeleteQuestion = (question) => {
    if (question.is_company_field) {
        Swal.fire('Tidak Dapat Dihapus', 'Butir ini merupakan field karakteristik perusahaan yang terikat dengan relasi profil institusi.', 'info');
        return;
    }

    Swal.fire({
        title: 'Hapus Pertanyaan Evaluasi?',
        text: `Apakah Anda yakin ingin menghapus [${question.kode}] "${question.aspek}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Pertanyaan',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/superadmin/evaluasi-atasan/pertanyaan/${question.id}`, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Terhapus', 'Pertanyaan evaluasi berhasil dihapus.', 'success'),
            });
        }
    });
};

// ========================================================
// RESPONS DETAIL STATE & MODAL
// ========================================================
const showDetailModal = ref(false);
const selectedEvaluasi = ref(null);

const viewDetailEvaluasi = (evaluasi) => {
    selectedEvaluasi.value = evaluasi;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedEvaluasi.value = null;
};
</script>

<template>
    <Head title="Pengaturan Kuesioner Evaluasi Atasan - Super Admin" />

    <div class="min-h-screen bg-slate-50 flex font-sans min-w-[1280px]">
        <!-- Sidebar Terpadu Super Admin -->
        <Sidebar :user="user" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Solid UKDW Green Konsisten -->
            <div class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-white/70 font-medium mb-1.5">
                            <Link href="/superadmin/dashboard" class="hover:text-white transition-colors">Dashboard</Link>
                            <span>/</span>
                            <span class="text-white font-semibold">Kuesioner Evaluasi Atasan</span>
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">
                            Pengaturan Kuesioner Evaluasi Pengguna Lulusan
                        </h1>
                        <p class="text-xs text-emerald-100/90 mt-1 max-w-2xl leading-relaxed">
                            Konfigurasi struktur bagian (section), butir instrumen aspek kinerja, serta audit riwayat respon evaluasi atasan.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Kontainer Konten Utama -->
            <main class="admin-page-main w-full -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
                <!-- Banner Status Instrumen Kuesioner Induk Aktif (Identik Gambar 1) -->
                <div class="bg-white rounded-2xl border border-gray-300 p-5 shadow-2xs">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block mb-1">
                                INSTRUMEN KUESIONER EVALUASI ATASAN AKTIF
                            </span>
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <div class="font-extrabold text-base sm:text-lg text-gray-900 flex items-center gap-2">
                                    <span>[Atasan] Evaluasi Kepuasan Pengguna Lulusan</span>
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        Aktif
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 4 Stat Box Counter -->
                        <div class="flex items-center gap-3 bg-gray-50/80 p-3 rounded-lg border border-gray-200 flex-wrap">
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-slate-900">{{ stats.total_sections }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Bagian / Section</span>
                            </div>
                            <div class="h-8 w-px bg-gray-200"></div>
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-[#0D542B]">{{ stats.total_questions }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Soal Evaluasi</span>
                            </div>
                            <div class="h-8 w-px bg-gray-200"></div>
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-slate-700">{{ stats.submitted_evaluasi }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Respon Masuk</span>
                            </div>
                            <div class="h-8 w-px bg-gray-200"></div>
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-slate-900">{{ stats.total_evaluasi }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Total Undangan</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigasi Tab Underline Style (Identik Gambar 1 & Gambar 3) -->
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

                    <!-- Tab 2: Pertanyaan Kuesioner Evaluasi Atasan -->
                    <button
                        @click="switchTab('questions')"
                        class="pb-3 px-3 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 shrink-0"
                        :class="[
                            activeTab === 'questions'
                                ? 'border-[#0D542B] text-[#0D542B]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Pertanyaan Kuesioner (Evaluasi)</span>
                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold rounded-full" :class="activeTab === 'questions' ? 'bg-[#0D542B]/10 text-[#0D542B]' : 'bg-slate-100 text-gray-600'">
                            {{ stats.total_questions }}
                        </span>
                    </button>

                    <!-- Tab 3: Riwayat Respon Atasan -->
                    <button
                        @click="switchTab('responses')"
                        class="pb-3 px-3 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 shrink-0"
                        :class="[
                            activeTab === 'responses'
                                ? 'border-[#0D542B] text-[#0D542B]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Riwayat Respon Atasan</span>
                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold rounded-full" :class="activeTab === 'responses' ? 'bg-[#0D542B]/10 text-[#0D542B]' : 'bg-slate-100 text-gray-600'">
                            {{ stats.submitted_evaluasi }}
                        </span>
                    </button>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 1: SECTION MANAGEMENT (Identik Gambar 1)             -->
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

                    <!-- Tabel Section (Identik Gambar 1) -->
                    <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                <tr>
                                    <th class="px-4 py-3 text-center w-20">Urutan</th>
                                    <th class="px-4 py-3 min-w-[260px]">Judul Bagian Section</th>
                                    <th class="px-4 py-3">Deskripsi & Petunjuk Pengisian</th>
                                    <th class="px-4 py-3 text-center w-40">Jumlah Pertanyaan</th>
                                    <th class="px-4 py-3 text-right w-24">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredSections.length === 0">
                                    <td colspan="5" class="p-8 text-center text-gray-400 italic">
                                        Belum ada bagian (section) evaluasi atasan yang sesuai pencarian.
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
                                        <div v-if="sec.key" class="text-[11px] text-emerald-800 font-mono font-normal mt-0.5">Kunci internal: {{ sec.key }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ sec.description || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-gray-700">
                                        {{ sec.pertanyaans_count || 0 }} Pertanyaan
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                @click="openSectionModal(sec)"
                                                class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer"
                                                title="Sunting Section"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="handleDeleteSection(sec)"
                                                class="text-gray-400 hover:text-red-600 transition-colors cursor-pointer"
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
                <!-- TAB 2: PERTANYAAN KUESIONER (Identik Gambar 3)           -->
                <!-- ======================================================== -->
                <div v-if="activeTab === 'questions'" class="space-y-4">
                    <!-- Filter Bar Box (Identik Gambar 3) -->
                    <div class="bg-white p-4 rounded-xl border border-gray-300 shadow-2xs space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3 items-end">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                                    FILTER SECTION
                                </label>
                                <select
                                    v-model="selectedSectionFilter"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B] focus:outline-none bg-white text-gray-900"
                                >
                                    <option value="all">Semua Section</option>
                                    <option v-for="sec in sections" :key="sec.id" :value="sec.id">
                                        {{ sec.title }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                                    SIFAT PENGISIAN
                                </label>
                                <select
                                    v-model="selectedRequiredFilter"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B] focus:outline-none bg-white text-gray-900"
                                >
                                    <option value="all">Semua Status</option>
                                    <option value="wajib">Aktif</option>
                                    <option value="opsional">Nonaktif</option>
                                </select>
                            </div>

                            <div class="sm:col-span-1 lg:col-span-2 flex justify-start sm:justify-end">
                                <button
                                    type="button"
                                    @click="openQuestionModal(null)"
                                    class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap"
                                >
                                    <span>+ Tambah Pertanyaan Kuesioner</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                                PENCARIAN TEKS
                            </label>
                            <input
                                type="text"
                                v-model="questionSearchQuery"
                                placeholder="Cari kode atau pertanyaan..."
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B] focus:outline-none bg-white placeholder-gray-400"
                            />
                        </div>
                    </div>

                    <!-- Tabel Pertanyaan (Identik Gambar 3) -->
                    <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                <tr>
                                    <th class="px-3 py-3 text-center w-20">Urutan</th>
                                    <th class="px-3 py-3 w-24">Kode</th>
                                    <th class="px-4 py-3">Butir Pertanyaan Kuesioner</th>
                                    <th class="px-3 py-3 w-48">Tipe & Bagian (Section)</th>
                                    <th class="px-4 py-3">Opsi Jawaban & Percabangan</th>
                                    <th class="px-3 py-3 text-center w-24">Status</th>
                                    <th class="px-3 py-3 text-right w-20">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredQuestions.length === 0">
                                    <td colspan="7" class="p-8 text-center text-gray-400 italic">
                                        Belum ada butir pertanyaan evaluasi atasan yang sesuai filter.
                                    </td>
                                </tr>
                                <tr v-for="(q, idx) in filteredQuestions" :key="q.id" class="hover:bg-gray-50/80 transition-colors">
                                    <!-- Urutan & Panah Naik/Turun -->
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

                                    <!-- Kode Pertanyaan -->
                                    <td class="px-3 py-3.5 font-mono font-bold text-gray-900">
                                        {{ q.kode }}
                                    </td>

                                    <!-- Butir Pertanyaan Kuesioner -->
                                    <td class="px-4 py-3.5">
                                        <p class="font-bold text-gray-900 leading-snug">
                                            {{ q.aspek }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-1.5 flex-wrap text-xs">
                                            <span class="text-gray-500">Kuesioner Evaluasi Atasan</span>
                                        </div>
                                        <p v-if="q.deskripsi" class="text-[11px] text-gray-500 italic mt-1">
                                            Keterangan: {{ q.deskripsi }}
                                        </p>
                                        <p v-if="q.is_company_field" class="text-[10px] text-emerald-700 font-semibold mt-1">
                                            Field Master Perusahaan
                                        </p>
                                    </td>

                                    <!-- Tipe & Bagian (Section) -->
                                    <td class="px-3 py-3.5">
                                        <span class="font-bold text-gray-800 block text-xs">
                                            {{ typeLabels[q.tipe] || q.tipe }}
                                        </span>
                                        <p class="text-[11px] text-gray-500 mt-1">
                                            {{ q.section ? `Bagian ${q.section.order}: ${q.section.title}` : (q.kategori || '-') }}
                                        </p>
                                    </td>

                                    <!-- Opsi Jawaban & Percabangan (Identik Gambar 3) -->
                                    <td class="px-4 py-3.5 max-w-md">
                                        <div v-if="canHaveOptions(q.tipe)" class="space-y-1.5">
                                            <div
                                                v-for="(opt, optIdx) in (Array.isArray(q.pilihan_jawaban) ? q.pilihan_jawaban : (typeof q.pilihan_jawaban === 'string' ? JSON.parse(q.pilihan_jawaban || '[]') : []))"
                                                :key="optIdx"
                                                class="flex items-center justify-between p-1.5 bg-gray-50 border border-gray-200 rounded text-[11px]"
                                            >
                                                <div class="flex items-center gap-1.5 min-w-0 pr-2">
                                                    <span class="font-mono text-gray-500 font-bold shrink-0">[{{ optIdx + 1 }}]</span>
                                                    <span class="text-gray-800 font-medium truncate">{{ opt }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <span v-else class="text-gray-400 text-[11px] italic">
                                            Tidak menggunakan pilihan opsi (isian teks bebas).
                                        </span>
                                    </td>

                                    <!-- Status master pertanyaan, tetap mempertahankan jawaban historis saat diubah. -->
                                    <td class="px-3 py-3.5 text-center">
                                        <button
                                            type="button"
                                            class="inline-flex min-w-[64px] items-center justify-center rounded-lg border px-3 py-1.5 text-[11px] font-semibold transition-colors"
                                            :class="q.is_active ? 'border-emerald-200 bg-emerald-50 text-[#0D542B] hover:bg-emerald-100' : 'border-gray-200 bg-gray-50 text-gray-500 hover:bg-gray-100'"
                                            :title="q.is_active ? 'Klik untuk menonaktifkan pertanyaan' : 'Klik untuk mengaktifkan pertanyaan'"
                                            @click="toggleActive(q)"
                                        >
                                            {{ q.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="px-3 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                @click="openQuestionModal(q)"
                                                class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer"
                                                title="Sunting Pertanyaan"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="!q.is_company_field"
                                                type="button"
                                                @click="handleDeleteQuestion(q)"
                                                class="text-gray-400 hover:text-red-600 transition-colors cursor-pointer"
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
                <!-- TAB 3: RIWAYAT RESPON EVALUASI ATASAN                    -->
                <!-- ======================================================== -->
                <div v-else-if="activeTab === 'responses'" class="space-y-4">
                    <div class="bg-white p-3.5 rounded-xl border border-gray-300 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 text-xs shadow-2xs">
                        <span class="text-gray-600 font-medium">
                            Menampilkan <strong class="text-gray-900">{{ evaluasis.data.length }}</strong> dari total <strong class="text-gray-900">{{ stats.total_evaluasi }}</strong> evaluasi atasan
                        </span>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-300 overflow-x-auto shadow-2xs">
                        <table class="w-full min-w-full text-left border-collapse border border-gray-300 text-xs bg-white">
                            <thead class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px] whitespace-nowrap">
                                <tr>
                                    <th class="border border-gray-300 px-3 py-2 text-center w-12">No</th>
                                    <th class="border border-gray-300 px-3 py-2 min-w-[180px]">Nama Alumni</th>
                                    <th class="border border-gray-300 px-3 py-2 min-w-[160px]">Program Studi</th>
                                    <th class="border border-gray-300 px-3 py-2 min-w-[180px]">Perusahaan & Atasan</th>
                                    <th class="border border-gray-300 px-3 py-2 w-32 text-center">Status Respon</th>
                                    <th class="border border-gray-300 px-3 py-2 w-36">Waktu Pengisian</th>
                                    <th class="border border-gray-300 px-2.5 py-2 text-center w-24">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(ev, idx) in evaluasis.data" :key="ev.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="border border-gray-300 px-3 py-2 text-center font-medium text-gray-600">{{ idx + 1 }}</td>
                                    <td class="border border-gray-300 px-3 py-2 font-bold text-gray-900">
                                        {{ ev.biodata?.user?.name || ev.biodata?.nama_lengkap || ev.biodata?.data_akademik?.nama_mahasiswa || '-' }}
                                        <div class="text-[11px] text-gray-500 font-mono font-normal">NIM: {{ ev.biodata?.nim || '-' }}</div>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-gray-700">
                                        {{ ev.biodata?.prodi?.nama_prodi || '-' }}
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-gray-700">
                                        <div class="font-bold text-gray-900">{{ ev.perusahaan?.nama_perusahaan || '-' }}</div>
                                        <div class="text-[11px] text-gray-500">Penilai: {{ ev.atasan?.nama_atasan || '-' }}</div>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center">
                                        <span
                                            class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold border"
                                            :class="ev.is_submitted ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200'"
                                        >
                                            {{ ev.is_submitted ? 'Sudah Mengisi' : 'Belum Mengisi' }}
                                        </span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-gray-600 text-[11px]">
                                        {{ ev.submitted_at || '-' }}
                                    </td>
                                    <td class="border border-gray-300 px-2.5 py-2 text-center">
                                        <button
                                            v-if="ev.is_submitted"
                                            type="button"
                                            @click="viewDetailEvaluasi(ev)"
                                            class="px-2.5 py-1 bg-white hover:bg-emerald-50 text-[#0D542B] border border-emerald-700/30 rounded text-[11px] font-bold cursor-pointer"
                                        >
                                            Lihat Respon
                                        </button>
                                        <span v-else class="text-gray-400 text-[11px] italic">Menunggu</span>
                                    </td>
                                </tr>
                                <tr v-if="evaluasis.data.length === 0">
                                    <td colspan="7" class="border border-gray-300 p-8 text-center text-gray-400 italic">
                                        Belum ada riwayat evaluasi atasan yang masuk.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination riwayat evaluasi memakai pola yang sama dengan seluruh master data. -->
                    <PaginationControls :pagination="evaluasis" summary-label="evaluasi atasan" />
                </div>
            </main>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL 1: TAMBAH / SUNTING BAGIAN SECTION (Identik Gambar 2) -->
        <!-- ======================================================== -->
        <div v-if="isSectionModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full border border-gray-300 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="font-bold text-base text-gray-900">
                        {{ isEditingSection ? 'Sunting Bagian Kuesioner Evaluasi Baru' : 'Tambah Bagian Kuesioner Evaluasi Baru' }}
                    </h3>
                    <button type="button" @click="closeSectionModal" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-gray-800 text-xs mb-1.5">Judul Bagian (Section Title) *</label>
                        <input
                            type="text"
                            v-model="sectionForm.title"
                            placeholder="Contoh: Identitas & Biodata Mahasiswa"
                            class="w-full px-3.5 py-2.5 border rounded-lg focus:outline-none focus:ring-1 focus:ring-[#0D542B] text-xs text-gray-900"
                            :class="sectionFormErrors.title ? 'border-red-500' : 'border-gray-300'"
                        />
                        <p v-if="sectionFormErrors.title" class="text-[11px] text-red-600 mt-1">{{ sectionFormErrors.title }}</p>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-800 text-xs mb-1.5">Deskripsi / Petunjuk Pengisian (Opsional)</label>
                        <textarea
                            v-model="sectionForm.description"
                            rows="3"
                            placeholder="Ketikkan petunjuk pengisian bagi responden..."
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#0D542B] text-xs text-gray-900"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-800 text-xs mb-1.5">Nomor Urut Posisi</label>
                        <input
                            type="number"
                            min="1"
                            v-model.number="sectionForm.order"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#0D542B] text-xs text-gray-900"
                        />
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end gap-2.5 text-xs">
                    <button
                        type="button"
                        @click="closeSectionModal"
                        class="px-5 py-2.5 border border-gray-200 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-bold cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitSectionForm"
                        class="px-5 py-2.5 bg-[#0D542B] hover:bg-[#08381c] text-white rounded-lg font-bold cursor-pointer"
                    >
                        {{ isEditingSection ? 'Simpan Perubahan' : 'Tambah Bagian' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL 2: TAMBAH / SUNTING PERTANYAAN (QUESTION)          -->
        <!-- ======================================================== -->
        <div v-if="isQuestionModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
            <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-gray-200 bg-white px-6 py-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ isEditingQuestion ? 'Sunting Pertanyaan Kuesioner' : 'Tambah Pertanyaan Kuesioner Baru' }}
                        </h3>
                        <p class="mt-0.5 text-[11px] text-gray-500">Konfigurasi butir pertanyaan dan aturan pengisian responden.</p>
                    </div>
                    <button type="button" @click="closeQuestionModal" class="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-6 text-xs">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block font-bold text-gray-700">Kode Pertanyaan <span class="text-rose-600">*</span></label>
                            <input
                                type="text"
                                v-model="questionForm.kode"
                                :disabled="isEditingQuestion"
                                placeholder="Misal: P43"
                                class="w-full rounded-lg border px-3 py-2 font-mono text-xs font-bold uppercase focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                :class="isEditingQuestion ? 'bg-gray-100 text-gray-500' : 'border-gray-300'"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block font-bold text-gray-700">Bagian / Section <span class="text-rose-600">*</span></label>
                            <select
                                v-model="questionForm.section_id"
                                :disabled="questionForm.is_company_field"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                :class="questionFormErrors.section_id ? 'border-red-500' : 'border-gray-300'"
                            >
                                <option v-for="sec in sections" :key="sec.id" :value="sec.id">
                                    {{ sec.title }}
                                </option>
                            </select>
                            <p v-if="questionFormErrors.section_id" class="text-[11px] text-red-600 mt-1">{{ questionFormErrors.section_id }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block font-bold text-gray-700">Teks Pertanyaan <span class="text-rose-600">*</span></label>
                        <textarea
                            v-model="questionForm.aspek"
                            rows="3"
                            placeholder="Ketik kalimat pertanyaan..."
                            class="w-full rounded-lg border px-3 py-2 text-xs leading-relaxed focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            :class="questionFormErrors.aspek ? 'border-red-500' : 'border-gray-300'"
                        ></textarea>
                        <p v-if="questionFormErrors.aspek" class="text-[11px] text-red-600 mt-1">{{ questionFormErrors.aspek }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block font-bold text-gray-700">Tipe Pertanyaan <span class="text-rose-600">*</span></label>
                            <select
                                v-model="questionForm.tipe"
                                @change="handleTypeChange"
                                :disabled="questionForm.is_company_field"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            >
                                <option value="likert_5">Skala Likert (1-5)</option>
                                <option value="pilihan_ganda">Pilihan Ganda (Radio)</option>
                                <option value="multiple_choice">Kotak Centang (Checkbox)</option>
                                <option value="text">Jawaban Singkat (Text)</option>
                                <option value="textarea">Paragraf (Textarea)</option>
                            </select>
                            <p class="text-[11px] text-gray-400 mt-1">Opsi default otomatis dibuat & dapat disesuaikan.</p>
                        </div>
                        <div>
                            <label class="mb-1 block font-bold text-gray-700">Nomor Urut</label>
                            <input
                                type="number"
                                min="1"
                                v-model.number="questionForm.order"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            />
                        </div>
                    </div>

                    <!-- Kelola Opsi Jawaban bila Pilihan Ganda / Checkbox / Likert -->
                    <div v-if="canHaveOptions(questionForm.tipe)" class="border border-gray-200 bg-gray-50 p-3.5 rounded-lg space-y-2">
                        <label class="block font-bold text-gray-700">Pilihan Opsi Jawaban</label>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="(opt, oIdx) in questionForm.pilihan_jawaban"
                                :key="oIdx"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white border border-gray-300 rounded-md text-[11px] font-medium text-gray-700 shadow-2xs"
                            >
                                <span>{{ opt }}</span>
                                <button
                                    v-if="!questionForm.is_company_field && questionForm.tipe !== 'likert_5'"
                                    type="button"
                                    @click="removeOption(oIdx)"
                                    class="text-red-500 hover:text-red-700 cursor-pointer ml-1"
                                >
                                    &times;
                                </button>
                            </span>
                        </div>
                        <div v-if="['pilihan_ganda', 'multiple_choice'].includes(questionForm.tipe) && !questionForm.is_company_field" class="flex gap-2 pt-1.5">
                            <input
                                type="text"
                                v-model="newOptionInput"
                                @keydown.enter.prevent="addOption"
                                placeholder="Ketik opsi jawaban baru..."
                                class="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            />
                            <button
                                type="button"
                                @click="addOption"
                                class="px-3 py-1.5 bg-[#0D542B] hover:bg-[#08381c] text-white font-bold rounded-lg cursor-pointer"
                            >
                                Tambah Opsi
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block font-bold text-gray-700">Keterangan / Petunjuk Pengisian (Opsional)</label>
                        <textarea
                            v-model="questionForm.deskripsi"
                            rows="2"
                            placeholder="Ketikkan petunjuk pengisian bagi responden..."
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs leading-relaxed focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                        ></textarea>
                    </div>
                </div>

                <div class="flex shrink-0 items-center justify-end gap-2 border-t border-gray-200 bg-white px-6 py-4 text-xs">
                    <button
                        type="button"
                        @click="closeQuestionModal"
                        class="rounded-lg border border-gray-300 bg-gray-50 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-100"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitQuestionForm"
                        class="rounded-lg bg-[#0D542B] px-4 py-2 font-semibold text-white hover:bg-[#08381c]"
                    >
                        {{ isEditingQuestion ? 'Simpan Perubahan' : 'Tambah Pertanyaan' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL 3: LIHAT DETAIL RESPON EVALUASI ATASAN             -->
        <!-- ======================================================== -->
        <div v-if="showDetailModal && selectedEvaluasi" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full border border-gray-300 overflow-hidden max-h-[85vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between shrink-0">
                    <div>
                        <h3 class="font-bold text-sm text-gray-900">Rincian Jawaban Evaluasi Pengguna Lulusan</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">
                            Alumni: {{ selectedEvaluasi.biodata?.user?.name || selectedEvaluasi.biodata?.nama_lengkap || '-' }} ({{ selectedEvaluasi.biodata?.nim || '-' }})
                        </p>
                    </div>
                    <button type="button" @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs overflow-y-auto flex-1">
                    <div class="grid grid-cols-2 gap-3 bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <div>
                            <span class="text-gray-500 block">Nama Perusahaan</span>
                            <strong class="text-gray-900">{{ selectedEvaluasi.perusahaan?.nama_perusahaan || '-' }}</strong>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Penilai / Atasan</span>
                            <strong class="text-gray-900">{{ selectedEvaluasi.atasan?.nama_atasan || '-' }} ({{ selectedEvaluasi.atasan?.telepon_atasan || '-' }})</strong>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-bold text-gray-800 mb-2">Jawaban Aspek Penilaian Kinerja</h4>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-gray-100 text-gray-700 font-semibold border-b border-gray-200 text-[11px]">
                                        <th class="p-2.5 w-12 text-center">No</th>
                                        <th class="p-2.5">Aspek / Pertanyaan</th>
                                        <th class="p-2.5 w-36 text-center">Nilai Respon</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(resp, rIdx) in selectedEvaluasi.respons" :key="resp.id" class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="p-2.5 text-center text-gray-500">{{ rIdx + 1 }}</td>
                                        <td class="p-2.5">
                                            <div class="font-medium text-gray-900">{{ resp.pertanyaan?.aspek || '-' }}</div>
                                            <div v-if="resp.catatan" class="text-[11px] text-emerald-800 italic mt-0.5">Catatan: {{ resp.catatan }}</div>
                                        </td>
                                        <td class="p-2.5 text-center font-bold text-[#0D542B]">
                                            {{ resp.nilai }}
                                        </td>
                                    </tr>
                                    <tr v-if="!selectedEvaluasi.respons || selectedEvaluasi.respons.length === 0">
                                        <td colspan="3" class="p-4 text-center text-gray-400 italic">Tidak ada respon butir terekam.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-3.5 border-t border-gray-200 bg-gray-50 flex items-center justify-end text-xs shrink-0">
                    <button
                        type="button"
                        @click="closeDetailModal"
                        class="px-5 py-2.5 bg-gray-700 hover:bg-gray-800 text-white font-bold rounded-lg cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
