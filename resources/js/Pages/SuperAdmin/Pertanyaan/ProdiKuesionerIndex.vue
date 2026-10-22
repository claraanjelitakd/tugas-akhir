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
const openSectionModal = (sectionToEdit = null) => {
    const isEdit = !!sectionToEdit;
    const initialTitle = sectionToEdit?.title || '';
    const initialDesc = sectionToEdit?.description || '';
    const initialOrder = sectionToEdit?.order || (props.sections.length + 1);

    Swal.fire({
        title: isEdit ? 'Sunting Bagian Kuesioner Prodi' : 'Tambah Bagian Kuesioner Prodi Baru',
        html: `
            <div class="text-left space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Program Studi Target</label>
                    <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg font-bold text-[#0D542B]">
                        ${props.selectedProdi?.nama_prodi || 'Program Studi Target'}
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Judul Bagian (Section Title) *</label>
                    <input id="swal-sec-title" type="text" value="${initialTitle}" placeholder="Contoh: Evaluasi Capaian Pembelajaran Prodi" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs" />
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Deskripsi / Petunjuk Pengisian (Opsional)</label>
                    <textarea id="swal-sec-desc" rows="3" placeholder="Ketikkan petunjuk pengisian bagi responden alumni..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs">${initialDesc}</textarea>
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Nomor Urut Posisi</label>
                    <input id="swal-sec-order" type="number" value="${initialOrder}" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs" />
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Perubahan' : 'Tambah Bagian',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        preConfirm: () => {
            const title = document.getElementById('swal-sec-title').value.trim();
            const description = document.getElementById('swal-sec-desc').value.trim();
            const order = parseInt(document.getElementById('swal-sec-order').value, 10) || null;

            if (!title) {
                Swal.showValidationMessage('Judul bagian kuesioner prodi wajib diisi.');
                return false;
            }

            return {
                prodi_id: props.selectedProdi.id,
                title,
                description,
                order,
            };
        },
    }).then((res) => {
        if (res.isConfirmed && res.value) {
            if (isEdit) {
                router.put(`${props.basePath}/sections/${sectionToEdit.id}`, res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Bagian kuesioner prodi diperbarui.', 'success'),
                });
            } else {
                router.post(`${props.basePath}/sections`, res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Bagian kuesioner prodi ditambahkan.', 'success'),
                });
            }
        }
    });
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
const openQuestionModal = (questionToEdit = null) => {
    const isEdit = !!questionToEdit;
    const initialCode = questionToEdit?.code || `P${props.questions.length + 1}`;
    const initialText = questionToEdit?.question_text || '';
    const initialType = questionToEdit?.type || 'single_choice';
    const initialRequired = questionToEdit?.is_required !== undefined ? questionToEdit.is_required : true;
    const initialSecId = questionToEdit?.prodi_question_section_id || (props.sections[0]?.id || '');
    const initialOrder = questionToEdit?.order || (props.questions.length + 1);

    const sectionOptions = props.sections.map(s => `<option value="${s.id}" ${initialSecId == s.id ? 'selected' : ''}>${s.title}</option>`).join('');
    const typeOptions = Object.entries(prodiSelectableTypes).map(([val, label]) => `<option value="${val}" ${initialType === val ? 'selected' : ''}>${label}</option>`).join('');

    Swal.fire({
        title: isEdit ? 'Sunting Pertanyaan Kuesioner Prodi' : 'Tambah Pertanyaan Kuesioner Prodi Baru',
        html: `
            <div class="text-left space-y-3 text-xs max-h-[70vh] overflow-y-auto pr-1">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Kode Pertanyaan *</label>
                    <input id="swal-q-code" type="text" value="${initialCode}" placeholder="Contoh: P01" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono font-bold" />
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Bagian / Section *</label>
                    <select id="swal-q-sec" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs">
                        ${sectionOptions || '<option value="">-- Buat Section Baru Dulu --</option>'}
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Pertanyaan / Teks Kuesioner *</label>
                    <textarea id="swal-q-text" rows="3" placeholder="Ketik kalimat pertanyaan..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs">${initialText}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Tipe Pertanyaan *</label>
                        <select id="swal-q-type" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-xs">
                            ${typeOptions}
                        </select>
                        <p class="text-[10px] text-emerald-700 font-semibold mt-1">Opsi default otomatis dibuat & dapat disesuaikan.</p>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Nomor Urut</label>
                        <input id="swal-q-order" type="number" value="${initialOrder}" min="1" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-xs" />
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <input id="swal-q-req" type="checkbox" ${initialRequired ? 'checked' : ''} class="rounded text-[#0D542B] focus:ring-[#0D542B]" />
                    <label for="swal-q-req" class="font-bold text-gray-700">Wajib diisi oleh alumni responden</label>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Perubahan' : 'Tambah Pertanyaan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        preConfirm: () => {
            const code = document.getElementById('swal-q-code').value.trim();
            const question_text = document.getElementById('swal-q-text').value.trim();
            const type = document.getElementById('swal-q-type').value;
            const prodi_question_section_id = document.getElementById('swal-q-sec').value;
            const order = parseInt(document.getElementById('swal-q-order').value, 10) || null;
            const is_required = document.getElementById('swal-q-req').checked;

            if (!code || !question_text) {
                Swal.showValidationMessage('Kode dan teks pertanyaan wajib diisi.');
                return false;
            }

            return {
                prodi_id: props.selectedProdi.id,
                code,
                question_text,
                type,
                prodi_question_section_id,
                order,
                is_required,
            };
        },
    }).then((res) => {
        if (res.isConfirmed && res.value) {
            if (isEdit) {
                router.put(`${props.basePath}/pertanyaan/${questionToEdit.id}`, res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Pertanyaan kuesioner prodi diperbarui.', 'success'),
                });
            } else {
                router.post(`${props.basePath}/pertanyaan`, res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Pertanyaan kuesioner prodi ditambahkan.', 'success'),
                });
            }
        }
    });
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
const openOptionModal = (question, optionToEdit = null) => {
    const isEdit = !!optionToEdit;
    const initialCode = optionToEdit?.code || `${question.code}-${String((question.options?.length || 0) + 1).padStart(2, '0')}`;
    const initialText = optionToEdit?.option_text || '';
    const initialJump = optionToEdit?.jump_to || '';
    const initialOrder = optionToEdit?.order || ((question.options?.length || 0) + 1);

    const jumpOptions = ['<option value="">-- Tanpa Percabangan (Lanjut Normal) --</option>']
        .concat(props.availableJumpTargets.map(code => `<option value="${code}" ${initialJump === code ? 'selected' : ''}>Lompat ke Pertanyaan: [${code}]</option>`))
        .join('');

    Swal.fire({
        title: isEdit ? `Edit Opsi [${question.code}]` : `Tambah Opsi Jawaban [${question.code}]`,
        html: `
            <div class="text-left space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Kode Opsi *</label>
                    <input id="swal-opt-code" type="text" value="${initialCode}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono" />
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Teks Opsi Jawaban *</label>
                    <input id="swal-opt-text" type="text" value="${initialText}" placeholder="Contoh: Sangat Relevan" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs" />
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Alur Percabangan (Lompat Ke / Jump To)</label>
                    <select id="swal-opt-jump" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs">
                        ${jumpOptions}
                    </select>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Opsi' : 'Tambah Opsi',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        preConfirm: () => {
            const code = document.getElementById('swal-opt-code').value.trim();
            const option_text = document.getElementById('swal-opt-text').value.trim();
            const jump_to = document.getElementById('swal-opt-jump').value;

            if (!code || !option_text) {
                Swal.showValidationMessage('Kode opsi dan teks opsi wajib diisi.');
                return false;
            }

            return {
                prodi_question_id: question.id,
                code,
                option_text,
                jump_to: jump_to || null,
                order: initialOrder,
            };
        },
    }).then((res) => {
        if (res.isConfirmed && res.value) {
            if (isEdit) {
                router.put(`${props.basePath}/opsi/${optionToEdit.id}`, res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Opsi jawaban diperbarui.', 'success'),
                });
            } else {
                router.post(`${props.basePath}/opsi`, res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Opsi jawaban ditambahkan.', 'success'),
                });
            }
        }
    });
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
</template>
