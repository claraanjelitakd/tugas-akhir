<!--
  Halaman: Kelola Bagian (Section) Kuesioner Program Studi
  File: resources/js/Pages/AdminProdi/Section/Index.vue

  Desain Standar Kelola Kuesioner UKDW (Identik dengan Super Admin):
  - Sidebar Terpadu Admin Program Studi
  - Header Bersih, Flat & Profesional
  - Tabel Lapang dengan Tombol Reorder Cepat (Naik/Turun)
  - Modal SweetAlert2 untuk Tambah, Edit, Detail, dan Hapus
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

import Sidebar from '../Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    prodi: Object,
    sections: {
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

const searchQuery = ref('');
const isReordering = ref(false);

const filteredSections = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.sections;
    }
    const query = searchQuery.value.toLowerCase();
    return props.sections.filter((sec) => {
        const matchTitle = sec.title?.toLowerCase().includes(query);
        const matchDesc = sec.description?.toLowerCase().includes(query);
        const matchOrder = sec.order?.toString().includes(query);
        return matchTitle || matchDesc || matchOrder;
    });
});

const totalQuestionsCount = computed(() => {
    return props.stats?.total_questions ?? props.sections.reduce((sum, sec) => sum + (sec.questions_count ?? (sec.questions?.length ?? 0)), 0);
});

// ========================================================
// SWEETALERT2: TAMBAH & EDIT SECTION PRODI
// ========================================================
const openSectionModal = (sectionToEdit = null) => {
    const isEdit = !!sectionToEdit;
    const initialTitle = sectionToEdit?.title || '';
    const initialDesc = sectionToEdit?.description || '';
    const initialOrder = sectionToEdit?.order || (props.sections.length + 1);

    const htmlContent = `
        <div class="text-left space-y-3.5 text-xs">
            <div>
                <label class="block font-bold text-gray-700 mb-1">Program Studi</label>
                <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 font-semibold text-xs">
                    ${props.prodi?.nama_prodi || 'Program Studi Terhubung'}
                </div>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Judul Bagian (Section Title) *</label>
                <input id="swal-section-title" type="text" value="${initialTitle}" placeholder="Contoh: Relevansi Kurikulum & Kompetensi Kerja" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700" />
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Deskripsi / Petunjuk Pengisian (Opsional)</label>
                <textarea id="swal-section-desc" rows="3" placeholder="Ketikkan petunjuk pengisian bagi responden alumni..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700 leading-relaxed">${initialDesc}</textarea>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Nomor Urut Posisi</label>
                <input id="swal-section-order" type="number" value="${initialOrder}" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700" />
            </div>
        </div>
    `;

    Swal.fire({
        title: isEdit ? 'Sunting Bagian Kuesioner' : 'Tambah Bagian Kuesioner Baru',
        html: htmlContent,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Perubahan' : 'Tambah Bagian',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        width: '500px',
        focusConfirm: false,
        didOpen: () => {
            const elTitle = document.getElementById('swal-section-title');
            if (elTitle && !isEdit) elTitle.focus();
        },
        preConfirm: () => {
            const title = document.getElementById('swal-section-title').value.trim();
            const description = document.getElementById('swal-section-desc').value.trim();
            const order = parseInt(document.getElementById('swal-section-order').value, 10) || null;

            if (!title) {
                Swal.showValidationMessage('Judul bagian kuesioner wajib diisi.');
                return false;
            }

            return {
                title,
                description,
                order,
            };
        },
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            if (isEdit) {
                router.put(`/prodi/sections/${sectionToEdit.id}`, result.value, {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire('Berhasil', 'Bagian kuesioner berhasil diperbarui.', 'success');
                    },
                    onError: (errors) => {
                        Swal.fire('Gagal Menyimpan', Object.values(errors).join('<br>'), 'error');
                    },
                });
            } else {
                router.post('/prodi/sections', result.value, {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire('Berhasil', 'Bagian kuesioner baru berhasil ditambahkan.', 'success');
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
// SWEETALERT2: DETAIL SECTION
// ========================================================
const openDetailModal = (sec) => {
    const questionCount = sec.questions_count ?? (sec.questions?.length ?? 0);
    const contentHtml = `
        <div class="text-left space-y-3 text-xs">
            <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200">
                <span class="text-gray-500 font-medium block">Judul Bagian:</span>
                <p class="text-gray-900 font-bold mt-0.5 text-sm">${sec.title}</p>
                ${sec.description ? `<p class="text-gray-600 mt-1.5 leading-relaxed">${sec.description}</p>` : '<p class="text-gray-400 italic mt-1">Tidak ada deskripsi tambahan.</p>'}
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="p-2 rounded-lg bg-gray-50 border border-gray-200">
                    <span class="text-gray-500 block">Nomor Urut:</span>
                    <span class="font-bold text-gray-800 text-xs">Bagian ${sec.order}</span>
                </div>
                <div class="p-2 rounded-lg bg-gray-50 border border-gray-200">
                    <span class="text-gray-500 block">Program Studi:</span>
                    <span class="font-bold text-gray-800 text-xs">${props.prodi?.nama_prodi || 'Program Studi'}</span>
                </div>
            </div>

            <div class="p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-between">
                <span class="font-bold text-emerald-900">Total Butir Pertanyaan Terdaftar:</span>
                <span class="font-bold text-emerald-900 text-xs">${questionCount} Butir Soal</span>
            </div>
        </div>
    `;

    Swal.fire({
        title: `Detail Bagian #${sec.order}`,
        html: contentHtml,
        confirmButtonText: 'Tutup',
        confirmButtonColor: '#0D542B',
        width: '500px',
    });
};

// ========================================================
// SWEETALERT2: HAPUS SECTION
// ========================================================
const handleDeleteSection = (sec) => {
    const questionCount = sec.questions_count ?? (sec.questions?.length ?? 0);
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
            router.delete(`/prodi/sections/${sec.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('Berhasil', 'Bagian kuesioner telah dihapus.', 'success');
                },
                onError: () => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus data bagian.', 'error');
                },
            });
        }
    });
};

// ========================================================
// SWEETALERT2: PINDAHKAN URUTAN (REORDER)
// ========================================================
const handleMoveSection = (sec, direction) => {
    isReordering.value = true;
    router.post(
        '/prodi/sections/reorder',
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
</script>

<template>
    <Head :title="`Kelola Bagian Kuesioner - ${prodi?.nama_prodi || 'Program Studi'}`" />

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
                            <span class="text-gray-800 font-semibold">Kelola Bagian Kuesioner</span>
                        </div>
                        <h1 class="text-xl font-bold text-gray-900">
                            Kelola Bagian Kuesioner (Section)
                        </h1>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Pengaturan kelompok tahapan kuesioner dan urutan pengisian alumni Program Studi {{ prodi?.nama_prodi }}.
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <Link
                            href="/prodi/pertanyaan"
                            class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Kelola Butir Pertanyaan</span>
                        </Link>

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

            <!-- Konten Tabel Utama -->
            <main class="admin-page-main w-full p-6 space-y-4">
                <!-- Bilah Pencarian & Ringkasan -->
                <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3">
                        <span class="text-gray-600 font-medium">
                            Total: <strong>{{ sections.length }}</strong> Bagian (<strong>{{ totalQuestionsCount }}</strong> Butir Pertanyaan Terdaftar)
                        </span>
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
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-2xs">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-3 w-16 text-center">Urut</th>
                                <th class="py-3 px-4">Judul & Deskripsi Bagian</th>
                                <th class="py-3 px-4 w-52">Program Studi</th>
                                <th class="py-3 px-3 w-32 text-center">Jumlah Soal</th>
                                <th class="py-3 px-3 w-28 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr
                                v-for="(sec, index) in filteredSections"
                                :key="sec.id"
                                class="hover:bg-slate-50/70 transition-colors"
                            >
                                <!-- Urutan -->
                                <td class="py-3 px-2 text-center">
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
                                <td class="py-3 px-4">
                                    <span class="font-bold text-gray-900 text-xs block">
                                        {{ sec.title }}
                                    </span>
                                    <p v-if="sec.description" class="text-gray-500 text-[11px] mt-0.5 leading-normal">
                                        {{ sec.description }}
                                    </p>
                                    <span v-else class="text-gray-400 text-[11px] italic">
                                        Tidak ada petunjuk khusus
                                    </span>
                                </td>

                                <!-- Program Studi -->
                                <td class="py-3 px-4">
                                    <span class="inline-block px-2.5 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        {{ prodi?.nama_prodi || 'Program Studi' }}
                                    </span>
                                </td>

                                <!-- Jumlah Soal -->
                                <td class="py-3 px-3 text-center">
                                    <Link
                                        :href="`/prodi/pertanyaan?sec_id=${sec.id}`"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-emerald-50 text-emerald-800 hover:bg-emerald-100 font-semibold text-[11px] border border-emerald-200 transition-colors"
                                        title="Buka butir pertanyaan pada bagian ini"
                                    >
                                        <span>{{ sec.questions_count ?? (sec.questions?.length ?? 0) }} Soal</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </td>

                                <!-- Aksi -->
                                <td class="py-3 px-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Detail (Mata) -->
                                        <button
                                            type="button"
                                            @click="openDetailModal(sec)"
                                            title="Detail Bagian"
                                            class="p-1 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded cursor-pointer"
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
                                            class="p-1 text-gray-500 hover:text-emerald-800 hover:bg-emerald-50 rounded cursor-pointer"
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
                                            class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="filteredSections.length === 0">
                                <td colspan="5" class="py-10 text-center text-gray-500">
                                    <p class="font-bold text-gray-700">Tidak ada bagian kuesioner yang cocok</p>
                                    <p class="text-xs text-gray-400 mt-1">Coba gunakan kata kunci pencarian yang lain atau tambah bagian baru.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
</template>
