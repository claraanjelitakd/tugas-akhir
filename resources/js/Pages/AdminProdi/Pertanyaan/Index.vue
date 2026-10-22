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
// 2. SWEETALERT2: MODAL TAMBAH & EDIT PERTANYAAN
// ========================================================
const openQuestionModal = (questionToEdit = null) => {
    const isEdit = !!questionToEdit;
    const initialSectionId = questionToEdit?.prodi_question_section_id 
        || (selectedSectionFilter.value !== 'all' ? parseInt(selectedSectionFilter.value, 10) : (props.sections[0]?.id || ''));
    const initialCode = questionToEdit?.code || '';
    const initialText = questionToEdit?.question_text || '';
    const rawType = questionToEdit?.type || 'single_choice';
    const initialType = rawType === 'radio' ? 'single_choice' : rawType;
    const initialRequired = questionToEdit ? (questionToEdit.is_required ? '1' : '0') : '1';
    const initialOrder = questionToEdit?.order || (props.questions.length + 1);

    const sectionOptionsHtml = props.sections.length > 0
        ? props.sections
            .map((s) => `<option value="${s.id}" ${s.id == initialSectionId ? 'selected' : ''}>Bagian ${s.order}: ${s.title}</option>`)
            .join('')
        : '<option value="">-- Belum ada bagian (otomatis dibuat) --</option>';

    const typeOptionsHtml = Object.entries(prodiSelectableTypes)
        .map(([val, label]) => `<option value="${val}" ${val === initialType ? 'selected' : ''}>${label}</option>`)
        .join('');

    const htmlContent = `
        <div class="text-left space-y-3.5 text-xs">
            <div>
                <label class="block font-bold text-gray-700 mb-1">Bagian Kuesioner (Section) *</label>
                <select id="swal-section-id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700">
                    ${sectionOptionsHtml}
                </select>
                <p class="text-[11px] text-gray-400 mt-1">Pilih bagian bab tempat pertanyaan ini akan ditampilkan.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Kode Pertanyaan *</label>
                    <input id="swal-code" type="text" placeholder="Contoh: P01, P02, EVAL_01" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono uppercase focus:outline-none focus:ring-1 focus:ring-emerald-700" />
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Sifat Pengisian *</label>
                    <select id="swal-is-required" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700">
                        <option value="1" ${initialRequired === '1' ? 'selected' : ''}>Wajib Diisi</option>
                        <option value="0" ${initialRequired === '0' ? 'selected' : ''}>Opsional (Boleh Kosong)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Tipe Input Pertanyaan *</label>
                    <select id="swal-type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700">
                        ${typeOptionsHtml}
                    </select>
                    <p class="text-[10px] text-emerald-700 font-semibold mt-1">Opsi pilihan default akan otomatis terbuat & dapat disesuaikan.</p>
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Nomor Urut Tampil</label>
                    <input id="swal-order" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700" />
                </div>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Bunyi Kalimat Pertanyaan *</label>
                <textarea id="swal-question-text" rows="3" placeholder="Ketikkan rumusan kalimat pertanyaan untuk alumni program studi..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700 leading-relaxed"></textarea>
            </div>
        </div>
    `;

    Swal.fire({
        title: isEdit ? 'Sunting Butir Pertanyaan' : 'Tambah Pertanyaan Baru',
        html: htmlContent,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Perubahan' : 'Tambah Pertanyaan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        width: '540px',
        focusConfirm: false,
        didOpen: () => {
            const elCode = document.getElementById('swal-code');
            if (elCode) elCode.value = initialCode;
            const elText = document.getElementById('swal-question-text');
            if (elText) elText.value = initialText;
            const elOrder = document.getElementById('swal-order');
            if (elOrder) elOrder.value = initialOrder;
            if (elCode && !isEdit) elCode.focus();
        },
        preConfirm: () => {
            const prodi_question_section_id = document.getElementById('swal-section-id').value;
            const code = document.getElementById('swal-code').value.trim();
            const type = document.getElementById('swal-type').value;
            const is_required = document.getElementById('swal-is-required').value === '1';
            const question_text = document.getElementById('swal-question-text').value.trim();
            const order = parseInt(document.getElementById('swal-order').value, 10) || null;

            if (!code) {
                Swal.showValidationMessage('Kode pertanyaan wajib diisi.');
                return false;
            }
            if (!question_text) {
                Swal.showValidationMessage('Bunyi kalimat pertanyaan wajib diisi.');
                return false;
            }

            return {
                prodi_question_section_id: prodi_question_section_id ? parseInt(prodi_question_section_id, 10) : null,
                code,
                type,
                is_required,
                question_text,
                order,
            };
        },
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            if (isEdit) {
                router.put(`/prodi/pertanyaan/${questionToEdit.id}`, result.value, {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire('Berhasil', 'Pertanyaan berhasil diperbarui.', 'success');
                    },
                    onError: (errors) => {
                        Swal.fire('Gagal Menyimpan', Object.values(errors).join('<br>'), 'error');
                    },
                });
            } else {
                router.post('/prodi/pertanyaan', result.value, {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire('Berhasil', 'Pertanyaan baru berhasil ditambahkan.', 'success');
                    },
                    onError: (errors) => {
                        Swal.fire('Gagal Menyimpan', Object.values(errors).join('<br>'), 'error');
                    },
                });
            }
        }
    });
};

// ========================================================
// 3. SWEETALERT2: MODAL TAMBAH & EDIT OPSI JAWABAN
// ========================================================
const openAddOptionModal = (question, optionToEdit = null) => {
    const isEdit = !!optionToEdit;
    const initialText = optionToEdit?.option_text || '';
    const initialJump = optionToEdit?.jump_to || '';
    const initialOrder = optionToEdit?.order || ((question.options?.length || 0) + 1);
    const initialCode = optionToEdit?.code || `${question.code}-${String(initialOrder).padStart(2, '0')}`;

    // Target jump_to yang tersedia
    const targetCodes = (props.availableJumpTargets && props.availableJumpTargets.length > 0)
        ? props.availableJumpTargets.filter((c) => c !== question.code)
        : props.questions.map((q) => q.code).filter((c) => c && c !== question.code);

    const jumpOptionsHtml = targetCodes.length > 0
        ? targetCodes.map((c) => `<option value="${c}" ${c === initialJump ? 'selected' : ''}>${c}</option>`).join('')
        : '';

    const htmlContent = `
        <div class="text-left space-y-3.5 text-xs">
            <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200">
                <span class="text-gray-500 font-medium block">Pertanyaan Induk:</span>
                <span class="font-bold text-gray-900 mt-0.5 block leading-snug">
                    <span class="font-mono text-emerald-800">[${question.code}]</span> ${question.question_text}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Kode Opsi (Opsional)</label>
                    <input id="swal-option-code" type="text" value="${initialCode}" placeholder="Contoh: ${question.code}-01" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono uppercase focus:outline-none focus:ring-1 focus:ring-emerald-700" />
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Nomor Urut Opsi</label>
                    <input id="swal-option-order" type="number" value="${initialOrder}" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700" />
                </div>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Teks Pilihan Jawaban *</label>
                <input id="swal-option-text" type="text" value="${initialText}" placeholder="Contoh: Sangat Relevan / Sesuai Bidang" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700" />
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Alur Lompatan / Jump To (Opsional)</label>
                <select id="swal-option-jump" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700">
                    <option value="">-- Lanjut ke Pertanyaan Berikutnya (Normal) --</option>
                    ${jumpOptionsHtml}
                </select>
                <p class="text-[11px] text-gray-400 mt-1">Jika opsi ini dipilih responden, kuesioner akan melompat ke kode pertanyaan tersebut.</p>
            </div>
        </div>
    `;

    Swal.fire({
        title: isEdit ? 'Sunting Opsi Pilihan Jawaban' : 'Tambah Opsi Pilihan Jawaban',
        html: htmlContent,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Perubahan' : 'Tambah Opsi',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        width: '500px',
        focusConfirm: false,
        didOpen: () => {
            const elText = document.getElementById('swal-option-text');
            if (elText && !isEdit) elText.focus();
        },
        preConfirm: () => {
            const option_text = document.getElementById('swal-option-text').value.trim();
            const code = document.getElementById('swal-option-code').value.trim() || null;
            const jump_to = document.getElementById('swal-option-jump').value || null;
            const order = parseInt(document.getElementById('swal-option-order').value, 10) || null;

            if (!option_text) {
                Swal.showValidationMessage('Teks pilihan jawaban wajib diisi.');
                return false;
            }

            return {
                question_id: question.id,
                option_text,
                code,
                jump_to,
                order,
            };
        },
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            if (isEdit) {
                router.put(`/prodi/opsi/${optionToEdit.id}`, result.value, {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire('Berhasil', 'Opsi jawaban berhasil diperbarui.', 'success');
                    },
                    onError: (errors) => {
                        Swal.fire('Gagal Menyimpan', Object.values(errors).join('<br>'), 'error');
                    },
                });
            } else {
                router.post('/prodi/opsi', result.value, {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire('Berhasil', 'Opsi jawaban baru berhasil ditambahkan.', 'success');
                    },
                    onError: (errors) => {
                        Swal.fire('Gagal Menyimpan', Object.values(errors).join('<br>'), 'error');
                    },
                });
            }
        }
    });
};

// ========================================================
// 4. SWEETALERT2: MODAL DETAIL PERTANYAAN (IKON MATA)
// ========================================================
const openDetailModal = (q) => {
    const hasOptions = canHaveOptions(q.type) && q.options && q.options.length > 0;
    const optionsListHtml = hasOptions
        ? q.options
            .map((opt, idx) => `
                <div class="p-2 bg-white rounded border border-gray-200 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded bg-gray-100 font-mono font-bold flex items-center justify-center text-[10px] text-gray-700">${idx + 1}</span>
                        <span class="font-medium text-gray-800">${opt.option_text}</span>
                    </div>
                    ${opt.jump_to ? `<span class="px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 text-[10px] font-bold border border-purple-200">→ Lompat ke: ${opt.jump_to}</span>` : ''}
                </div>
            `)
            .join('')
        : '<p class="text-xs text-gray-400 italic">Pertanyaan ini tidak memiliki daftar opsi pilihan jawaban terdaftar.</p>';

    const contentHtml = `
        <div class="text-left space-y-3.5 text-xs">
            <div class="p-3 rounded-lg bg-gray-50 border border-gray-200 space-y-1.5">
                <div class="flex items-center gap-2">
                    <span class="font-mono font-bold px-2 py-0.5 rounded bg-white border border-gray-200 text-gray-900 text-xs">${q.code}</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold ${q.is_required ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-gray-100 text-gray-600'}">
                        ${q.is_required ? 'Wajib Diisi' : 'Opsional'}
                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        ${typeLabels[q.type] || q.type}
                    </span>
                </div>
                <p class="font-bold text-gray-900 text-sm leading-snug pt-1">${q.question_text}</p>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200">
                    <span class="text-gray-500 block text-[11px]">Bagian Kuesioner:</span>
                    <span class="font-bold text-gray-800 text-xs">
                        ${q.section ? `Bagian ${q.section.order}: ${q.section.title}` : '-'}
                    </span>
                </div>
                <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200">
                    <span class="text-gray-500 block text-[11px]">Nomor Urut Tampil:</span>
                    <span class="font-bold text-gray-800 text-xs">Urutan #${q.order}</span>
                </div>
            </div>

            <div class="p-3 rounded-lg bg-slate-50 border border-gray-200 space-y-2">
                <span class="font-bold text-gray-800 text-xs block">
                    Daftar Opsi Jawaban (${q.options?.length || 0} Opsi):
                </span>
                <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                    ${optionsListHtml}
                </div>
            </div>
        </div>
    `;

    Swal.fire({
        title: `Detail Pertanyaan [${q.code}]`,
        html: contentHtml,
        confirmButtonText: 'Tutup',
        confirmButtonColor: '#0D542B',
        width: '540px',
    });
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
</template>
