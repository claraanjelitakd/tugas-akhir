<script setup>
// Halaman kelola evaluasi atasan: section stepper, pertanyaan, dan riwayat respons berasal dari backend.
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import Sidebar from '../Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    questions: {
        type: Array,
        default: () => [],
    },
    // Daftar section stepper yang dipakai bersama oleh admin dan formulir publik.
    sections: {
        type: Array,
        default: () => [],
    },
    // Opsi langkah stepper yang disediakan backend agar penempatan tidak hardcode di frontend.
    stepperOptions: {
        type: Array,
        default: () => [
            { value: 'instansi', label: 'Profil Perusahaan' },
            { value: 'karakteristik', label: 'Karakteristik Perusahaan' },
            { value: 'penilaian', label: 'Penilaian Kinerja' },
        ],
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

// Tab utama mengikuti pola Kuesioner Prodi: section, pertanyaan, lalu riwayat respons.
const activeTab = ref('sections');
const searchQuery = ref('');
// Filter section membatasi tabel pertanyaan berdasarkan langkah stepper yang dipilih.
const selectedSectionFilter = ref('all');

const filteredQuestions = computed(() => {
    const sectionFiltered = selectedSectionFilter.value === 'all'
        ? props.questions
        : props.questions.filter((item) => item.langkah === selectedSectionFilter.value);

    if (!searchQuery.value.trim()) return sectionFiltered;
    const q = searchQuery.value.toLowerCase();
    return sectionFiltered.filter(item =>
        item.aspek?.toLowerCase().includes(q) ||
        item.kode?.toLowerCase().includes(q) ||
        item.deskripsi?.toLowerCase().includes(q)
    );
});

// Mengambil label langkah dari data backend untuk ditampilkan di tabel.
const stepLabel = (question) => {
    return props.stepperOptions.find((option) => option.value === question?.langkah)?.label || 'Penilaian Kinerja';
};

// Modal Tambah / Edit Pertanyaan
const openQuestionModal = (question = null) => {
    const isEdit = !!question;
    const initialAspek = question?.aspek || '';
    const initialKode = question?.kode || '';
    const initialDeskripsi = question?.deskripsi || '';
    const initialLangkah = question?.langkah || 'penilaian';
    const isCompanyField = Boolean(question?.is_company_field);
    const initialOrder = question?.order || (props.questions.length + 1);

    const htmlContent = `
        <div class="text-left space-y-3 text-xs">
            <div>
                <label class="block font-bold text-gray-700 mb-1">Nama Aspek Penilaian *</label>
                <input id="swal-aspek" type="text" value="${initialAspek}" placeholder="Contoh: Integritas (Etika dan Moral)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Kode Aspek</label>
                    <input id="swal-kode" type="text" value="${initialKode}" ${isEdit ? 'disabled' : ''} placeholder="otomatis jika kosong" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono uppercase focus:outline-none focus:ring-1 focus:ring-emerald-700 ${isEdit ? 'bg-gray-100 text-gray-500' : ''}" />
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Nomor Urut</label>
                    <input id="swal-order" type="number" min="1" value="${initialOrder}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700" />
                </div>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Tampilkan di langkah stepper *</label>
                <select id="swal-langkah" ${isCompanyField ? 'disabled' : ''} class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700 ${isCompanyField ? 'bg-gray-100 text-gray-500' : ''}">
                    ${props.stepperOptions.map((option) => `<option value="${option.value}" ${option.value === initialLangkah ? 'selected' : ''}>${option.label}</option>`).join('')}
                </select>
                <p class="text-[11px] text-gray-500 mt-1">${isCompanyField ? 'Field karakteristik perusahaan terikat pada Langkah 2 agar tetap tersimpan ke kolom perusahaan.' : 'Pilihan ini menentukan bagian formulir publik tempat butir ditampilkan.'}</p>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Deskripsi / Penjelasan Butir</label>
                <textarea id="swal-deskripsi" rows="2" placeholder="Penjelasan singkat mengenai aspek ini..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700">${initialDeskripsi}</textarea>
            </div>
        </div>
    `;

    Swal.fire({
        title: isEdit ? 'Sunting Aspek Evaluasi Atasan' : 'Tambah Aspek Evaluasi Atasan Baru',
        html: htmlContent,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Perubahan' : 'Tambah Aspek',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        width: '520px',
        preConfirm: () => {
            const aspek = document.getElementById('swal-aspek')?.value?.trim();
            const kode = document.getElementById('swal-kode')?.value?.trim();
            const deskripsi = document.getElementById('swal-deskripsi')?.value?.trim() || null;
            const langkah = document.getElementById('swal-langkah')?.value?.trim() || 'penilaian';
            const order = parseInt(document.getElementById('swal-order')?.value, 10) || 1;

            if (!aspek) {
                Swal.showValidationMessage('Nama aspek penilaian wajib diisi.');
                return false;
            }

            return { aspek, kode, deskripsi, langkah, order };
        },
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            if (isEdit) {
                router.put(`/superadmin/evaluasi-atasan/pertanyaan/${question.id}`, result.value, {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire('Berhasil', 'Butir aspek evaluasi berhasil diperbarui.', 'success');
                    },
                });
            } else {
                router.post('/superadmin/evaluasi-atasan/pertanyaan', result.value, {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire('Berhasil', 'Butir aspek evaluasi baru berhasil ditambahkan.', 'success');
                    },
                });
            }
        }
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

const deleteQuestion = (question) => {
    Swal.fire({
        title: 'Hapus Butir Aspek?',
        text: `Apakah Anda yakin ingin menghapus butir "${question.aspek}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6B7280',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/superadmin/evaluasi-atasan/pertanyaan/${question.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('Berhasil', 'Butir aspek berhasil dihapus.', 'success');
                },
            });
        }
    });
};

// State Modal Vue Detail Respon Evaluasi Atasan
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

    <div class="min-h-screen bg-slate-50 flex font-sans">
        <!-- Sidebar Terpadu Super Admin -->
        <Sidebar :user="user" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Solid UKDW Green Konsisten -->
            <div class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full max-w-[1440px] mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-4">
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
                            Konfigurasi butir instrumen aspek kinerja kepuasan pengguna lulusan serta tinjau riwayat respon evaluasi atasan.
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <button
                            v-if="activeTab === 'questions'"
                            type="button"
                            @click="openQuestionModal()"
                            class="px-4 py-2 bg-white text-[#0D542B] hover:bg-emerald-50 font-bold text-xs rounded-lg transition-colors flex items-center gap-2 cursor-pointer shadow-sm"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Tambah Aspek Penilaian</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Kontainer Konten Utama -->
            <main class="admin-page-main w-full max-w-[1440px] mx-auto -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
                <!-- TABS NAVIGASI RESMI: 2-tone clean styling -->
                <div class="bg-white p-2 rounded-xl border border-gray-300 shadow-2xs flex items-center gap-2 overflow-x-auto">
                    <button
                        type="button"
                        @click="activeTab = 'sections'"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-2 cursor-pointer whitespace-nowrap"
                        :class="activeTab === 'sections' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100'"
                    >
                        <span>Daftar Bagian (Section)</span>
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold" :class="activeTab === 'sections' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'">{{ sections.length }}</span>
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'questions'"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-2 cursor-pointer whitespace-nowrap"
                        :class="activeTab === 'questions' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100'"
                    >
                        <span>Daftar Pertanyaan (Kuesioner)</span>
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold" :class="activeTab === 'questions' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'">{{ stats.active_questions }}</span>
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'responses'"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-2 cursor-pointer whitespace-nowrap"
                        :class="activeTab === 'responses' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100'"
                    >
                        <span>Riwayat Respon Pengguna Lulusan</span>
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold" :class="activeTab === 'responses' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'">{{ stats.submitted_evaluasi }} / {{ stats.total_evaluasi }}</span>
                    </button>
                </div>

                <!-- Konten Tab 1: Daftar Section Stepper -->
                <div v-if="activeTab === 'sections'" class="space-y-4">
                    <div class="bg-white p-4 rounded-xl border border-gray-300 shadow-2xs">
                        <p class="text-xs leading-relaxed text-gray-600">
                            Section evaluasi atasan mengikuti tiga langkah formulir publik. Urutan dan judul section menjadi kontrak bersama antara halaman kelola pertanyaan dan `/evaluasi-atasan`.
                        </p>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-300 overflow-x-auto shadow-2xs">
                        <table class="w-full min-w-[760px] text-left border-collapse border border-gray-300 text-xs bg-white">
                            <thead>
                                <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                    <th class="border border-gray-300 px-3 py-2 text-center w-16">No</th>
                                    <th class="border border-gray-300 px-3 py-2 min-w-[260px]">Nama Bagian</th>
                                    <th class="border border-gray-300 px-3 py-2">Deskripsi</th>
                                    <th class="border border-gray-300 px-3 py-2 min-w-[220px]">Sumber Tabel</th>
                                    <th class="border border-gray-300 px-3 py-2 text-center w-36">Jumlah Pertanyaan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="section in sections" :key="section.key" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="border border-gray-300 px-3 py-3 text-center font-bold text-gray-600">{{ section.order }}</td>
                                    <td class="border border-gray-300 px-3 py-3">
                                        <div class="font-bold text-gray-900">{{ section.title }}</div>
                                        <div class="text-[11px] text-emerald-700 mt-0.5">Kunci internal stepper: {{ section.key }}</div>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-3 text-gray-600">{{ section.description }}</td>
                                    <td class="border border-gray-300 px-3 py-3 font-mono text-[11px] text-slate-700">{{ section.source }}</td>
                                    <td class="border border-gray-300 px-3 py-3 text-center font-bold text-[#0D542B]">{{ section.question_count }}</td>
                                </tr>
                                <tr v-if="sections.length === 0">
                                    <td colspan="5" class="border border-gray-300 p-8 text-center text-gray-400 italic">Belum ada section evaluasi atasan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Konten Tab 2: Daftar Pertanyaan Evaluasi -->
                <div v-else-if="activeTab === 'questions'" class="space-y-4">
                    <!-- Bilah Pencarian -->
                    <div class="bg-white p-3.5 rounded-xl border border-gray-300 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 text-xs shadow-2xs">
                        <span class="text-gray-600 font-medium">
                            Menampilkan <strong class="text-gray-900">{{ filteredQuestions.length }}</strong> aspek evaluasi kepuasan pengguna lulusan
                        </span>
                        <div class="w-full sm:w-auto flex flex-col sm:flex-row sm:items-end gap-2">
                            <div class="w-full sm:w-80">
                                <label for="evaluation-section-filter" class="block text-[11px] font-bold text-gray-600 mb-1">Filter Section</label>
                                <select
                                    id="evaluation-section-filter"
                                    v-model="selectedSectionFilter"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700 bg-white text-gray-900"
                                    aria-label="Filter section pertanyaan"
                                >
                                    <option value="all">Semua section ({{ props.questions.length }})</option>
                                    <option v-for="section in sections" :key="section.key" :value="section.key">
                                        Langkah {{ section.order }} - {{ section.title }} ({{ section.question_count }})
                                    </option>
                                </select>
                            </div>
                            <input
                                type="text"
                                v-model="searchQuery"
                                placeholder="Cari aspek penilaian..."
                                class="w-full sm:w-64 px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700 bg-white text-gray-900 placeholder-gray-400"
                            />
                        </div>
                    </div>

                    <!-- Tabel Butir Aspek -->
                    <div class="bg-white rounded-xl border border-gray-300 overflow-x-auto shadow-2xs">
                        <table class="w-full min-w-full text-left border-collapse border border-gray-300 text-xs bg-white">
                            <thead>
                                <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px] whitespace-nowrap">
                                    <th class="border border-gray-300 px-2.5 py-2 text-center w-12 whitespace-nowrap">No</th>
                                    <th class="border border-gray-300 px-3 py-2 w-32 whitespace-nowrap">Kode Aspek</th>
                                    <th class="border border-gray-300 px-3 py-2 min-w-[240px] whitespace-nowrap">Aspek / Pertanyaan</th>
                                    <th class="border border-gray-300 px-3 py-2 min-w-[180px] whitespace-nowrap">Langkah Stepper</th>
                                    <th class="border border-gray-300 px-3 py-2 w-28 text-center whitespace-nowrap">Status</th>
                                    <th class="border border-gray-300 px-2.5 py-2 text-center w-24 whitespace-nowrap">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="filteredQuestions.length === 0">
                                    <td colspan="6" class="border border-gray-300 p-8 text-center text-gray-400 italic">
                                        Belum ada butir aspek evaluasi penilaian.
                                    </td>
                                </tr>
                                <tr v-for="(item, idx) in filteredQuestions" :key="item.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="border border-gray-300 px-2.5 py-2 text-center font-medium text-gray-600">{{ item.order || (idx + 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 font-mono text-[11px] text-gray-700 font-bold">{{ item.kode }}</td>
                                    <td class="border border-gray-300 px-3 py-2">
                                        <div class="font-bold text-gray-900 text-xs">{{ item.aspek }}</div>
                                        <div v-if="item.deskripsi" class="text-[11px] text-gray-500 mt-0.5">{{ item.deskripsi }}</div>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2">
                                        <span class="inline-flex px-2 py-1 rounded border border-slate-200 bg-slate-50 text-[11px] font-semibold text-slate-700">
                                            {{ stepLabel(item) }}
                                        </span>
                                        <div v-if="item.is_company_field" class="text-[10px] text-emerald-700 mt-1">Field perusahaan</div>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center">
                                        <button
                                            type="button"
                                            @click="toggleActive(item)"
                                            class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold cursor-pointer transition-colors border"
                                            :class="item.is_active ? 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 border-gray-200 hover:bg-gray-200'"
                                        >
                                            {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="border border-gray-300 px-2.5 py-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button
                                                type="button"
                                                @click="openQuestionModal(item)"
                                                class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Sunting Aspek"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="deleteQuestion(item)"
                                                class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                title="Hapus Aspek"
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

                <!-- Konten Tab 2: Daftar Undangan & Respon Atasan -->
                <div v-else class="space-y-4">
                    <div class="bg-white rounded-xl border border-gray-300 overflow-x-auto shadow-2xs">
                        <table class="w-full min-w-full text-left border-collapse border border-gray-300 text-xs bg-white">
                            <thead>
                                <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px] whitespace-nowrap">
                                    <th class="border border-gray-300 px-2.5 py-2 text-center w-12 whitespace-nowrap">No</th>
                                    <th class="border border-gray-300 px-3 py-2 min-w-[200px] whitespace-nowrap">Alumni Dinilai</th>
                                    <th class="border border-gray-300 px-3 py-2 min-w-[200px] whitespace-nowrap">Perusahaan / Instansi</th>
                                    <th class="border border-gray-300 px-3 py-2 min-w-[200px] whitespace-nowrap">Atasan Langsung</th>
                                    <th class="border border-gray-300 px-3 py-2 text-center w-36 whitespace-nowrap">Status Pengisian</th>
                                    <th class="border border-gray-300 px-2.5 py-2 text-center w-24 whitespace-nowrap">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!evaluasis.data || evaluasis.data.length === 0">
                                    <td colspan="6" class="border border-gray-300 p-8 text-center text-gray-400 italic">
                                        Belum ada data evaluasi atasan yang tersimpan.
                                    </td>
                                </tr>
                                <tr v-for="(ev, idx) in evaluasis.data" :key="ev.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="border border-gray-300 px-2.5 py-2 text-center font-medium text-gray-600">{{ idx + 1 }}</td>
                                    <td class="border border-gray-300 px-3 py-2">
                                        <div class="font-bold text-gray-900">{{ ev.biodata?.nama || ev.biodata?.data_akademik?.nama || '-' }}</div>
                                        <div class="text-[11px] text-gray-500">NIM: {{ ev.biodata?.nim || '-' }} &bull; {{ ev.biodata?.prodi?.nama_prodi || '-' }}</div>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2">
                                        <div class="font-bold text-gray-900">{{ ev.perusahaan?.nama_perusahaan || ev.nama_perusahaan || '-' }}</div>
                                        <div class="text-[11px] text-gray-500">{{ ev.perusahaan?.alamat || ev.alamat_lengkap || '-' }}</div>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2">
                                        <div class="font-bold text-gray-900">{{ ev.atasan?.nama || '-' }}</div>
                                        <div class="text-[11px] text-gray-500">{{ ev.atasan?.email || '-' }} &bull; {{ ev.atasan?.telepon || '-' }}</div>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center">
                                        <span
                                            class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold border"
                                            :class="ev.is_submitted ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-gray-100 text-gray-600 border-gray-200'"
                                        >
                                            {{ ev.is_submitted ? 'Sudah Diisi' : 'Menunggu Pengisian' }}
                                        </span>
                                    </td>
                                    <td class="border border-gray-300 px-2.5 py-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button
                                                type="button"
                                                @click="viewDetailEvaluasi(ev)"
                                                class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Lihat Detail Evaluasi"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            <a
                                                :href="`/evaluasi-atasan/${ev.token}`"
                                                target="_blank"
                                                class="p-1.5 text-gray-600 hover:text-[#0D542B] hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer"
                                                title="Buka Link Publik Kuesioner Atasan"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                            </a>
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

        <!-- MODAL FORMAL: DETAIL RESPO EVALUASI PENGGUNA LULUSAN -->
        <div
            v-if="showDetailModal && selectedEvaluasi"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full overflow-hidden border border-slate-200">
                <!-- Header Modal Formal -->
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between bg-slate-50">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-gray-900">
                                Lembar Evaluasi Pengguna Lulusan
                            </h3>
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                :class="selectedEvaluasi.is_submitted ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-gray-100 text-gray-600 border-gray-200'"
                            >
                                {{ selectedEvaluasi.is_submitted ? 'Terisi Lengkap' : 'Menunggu Respon' }}
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-0.5">
                            Hasil penilaian kinerja dan kompetensi alumni oleh pimpinan langsung perusahaan.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="closeDetailModal"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-200 transition-colors cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Konten Modal Formal -->
                <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto text-xs">
                    <!-- Kartu Informasi Alumni & Atasan (2 Kolom) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="p-3 bg-slate-50 rounded-lg border border-gray-200 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-gray-500 block">Identitas Alumni</span>
                            <div class="text-gray-900 font-bold text-sm">
                                {{ selectedEvaluasi.biodata?.nama || selectedEvaluasi.biodata?.data_akademik?.nama || '-' }}
                            </div>
                            <div class="text-gray-600">
                                NIM: <span class="font-mono font-semibold">{{ selectedEvaluasi.biodata?.nim || '-' }}</span>
                            </div>
                            <div class="text-gray-600">
                                Program Studi: {{ selectedEvaluasi.biodata?.prodi?.nama_prodi || '-' }}
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 rounded-lg border border-gray-200 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-gray-500 block">Pimpinan / Pengguna Lulusan</span>
                            <div class="text-gray-900 font-bold text-sm">
                                {{ selectedEvaluasi.atasan?.nama || '-' }}
                            </div>
                            <div class="text-gray-600">
                                Instansi: {{ selectedEvaluasi.perusahaan?.nama_perusahaan || selectedEvaluasi.nama_perusahaan || '-' }}
                            </div>
                            <div class="text-gray-600 truncate">
                                Kontak: {{ selectedEvaluasi.atasan?.email || '-' }} / {{ selectedEvaluasi.atasan?.telepon || '-' }}
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Hasil Penilaian Aspek Kinerja -->
                    <div>
                        <span class="font-bold text-gray-800 block mb-1.5 uppercase text-[11px] tracking-wider">
                            Rincian Penilaian Aspek Kinerja & Kompetensi
                        </span>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-200 text-[11px]">
                                        <th class="py-2 px-3 text-center w-10 border-r border-gray-200">No</th>
                                        <th class="py-2 px-3 border-r border-gray-200">Aspek Kinerja</th>
                                        <th class="py-2 px-3 text-center w-28">Skor / Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="selectedEvaluasi.respons && selectedEvaluasi.respons.length > 0">
                                        <tr
                                            v-for="(r, idx) in selectedEvaluasi.respons"
                                            :key="idx"
                                            class="border-b border-gray-100 hover:bg-gray-50/60"
                                        >
                                            <td class="py-2 px-3 text-center font-mono text-gray-500 border-r border-gray-100">
                                                {{ idx + 1 }}
                                            </td>
                                            <td class="py-2 px-3 font-medium text-gray-900 border-r border-gray-100">
                                                {{ r.pertanyaan?.aspek || '-' }}
                                            </td>
                                            <td class="py-2 px-3 text-center font-bold text-[#0D542B]">
                                                {{ r.nilai || '-' }}
                                            </td>
                                        </tr>
                                    </template>
                                    <tr v-else>
                                        <td colspan="3" class="py-6 text-center text-gray-400 italic">
                                            Belum ada respon butir penilaian yang tersimpan.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Catatan & Saran Tambahan Atasan -->
                    <div v-if="selectedEvaluasi.catatan_lainnya" class="space-y-1">
                        <span class="font-bold text-gray-800 block uppercase text-[11px] tracking-wider">
                            Catatan & Saran Pengguna Lulusan
                        </span>
                        <div class="p-3 bg-slate-50 rounded-lg border border-gray-200 text-gray-700 leading-relaxed italic">
                            &ldquo;{{ selectedEvaluasi.catatan_lainnya }}&rdquo;
                        </div>
                    </div>
                </div>

                <!-- Footer Modal Formal -->
                <div class="px-5 py-3 border-t border-gray-200 bg-gray-50 flex items-center justify-between">
                    <a
                        :href="`/evaluasi-atasan/${selectedEvaluasi.token}`"
                        target="_blank"
                        class="text-xs text-emerald-800 hover:text-emerald-950 font-semibold flex items-center gap-1.5"
                    >
                        <span>Buka Tautan Survei Asli</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>

                    <button
                        type="button"
                        @click="closeDetailModal"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs font-semibold rounded-lg transition-colors cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
