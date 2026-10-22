<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

import Sidebar from '@/Pages/SuperAdmin/Components/Sidebar.vue';

const props = defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
    kuesioners: {
        type: Array,
        default: () => [],
    },
    questionnaires: {
        type: Array,
        default: () => [],
    },
});

const searchQuery = ref('');
const isReordering = ref(false);

const activeKuesioners = computed(() => {
    return (props.kuesioners && props.kuesioners.length > 0) ? props.kuesioners : props.questionnaires;
});

const activeKuesioner = computed(() => {
    return activeKuesioners.value.find((k) => k.is_active) || activeKuesioners.value[0];
});

const filteredSections = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.sections;
    }
    const query = searchQuery.value.toLowerCase();
    return props.sections.filter((sec) => {
        const matchTitle = sec.title?.toLowerCase().includes(query);
        const matchOrder = sec.order?.toString().includes(query);
        const matchKuesioner = (sec.kuesioner?.title || sec.questionnaire?.title)?.toLowerCase().includes(query);
        return matchTitle || matchOrder || matchKuesioner;
    });
});

const totalQuestionsCount = computed(() => {
    return props.sections.reduce((sum, sec) => sum + (sec.subpertanyaans_count ?? sec.questions_count ?? (sec.subpertanyaans?.length ?? 0)), 0);
});

// ========================================================
// SWEETALERT2: TAMBAH & EDIT SECTION
// ========================================================
const openSectionModal = (sectionToEdit = null) => {
    const isEdit = !!sectionToEdit;
    const initialKuesionerId = sectionToEdit?.kuesioner_id || (activeKuesioners.value[0]?.id || '');
    const initialTitle = sectionToEdit?.title || '';
    const initialDesc = sectionToEdit?.description || '';
    const initialOrder = sectionToEdit?.order || (props.sections.length + 1);

    const kuesionerOptionsHtml = activeKuesioners.value
        .map((k) => `<option value="${k.id}" ${k.id == initialKuesionerId ? 'selected' : ''}>${k.title} (${k.year || '-'})</option>`)
        .join('');

    const htmlContent = `
        <div class="text-left space-y-3.5 text-xs">
            <div>
                <label class="block font-bold text-gray-700 mb-1">Kuesioner Induk *</label>
                <select id="swal-kuesioner-id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700">
                    ${kuesionerOptionsHtml}
                </select>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Judul Bagian (Section Title) *</label>
                <input id="swal-section-title" type="text" value="${initialTitle}" placeholder="Contoh: Identitas & Biodata Alumni" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700" />
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Deskripsi / Petunjuk Pengisian (Opsional)</label>
                <textarea id="swal-section-desc" rows="3" placeholder="Ketikkan petunjuk pengisian bagi responden..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700 leading-relaxed">${initialDesc}</textarea>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Nomor Urut Posisi</label>
                <input id="swal-section-order" type="number" value="${initialOrder}" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700" />
            </div>
        </div>
    `;

    Swal.fire({
        title: isEdit ? 'Sunting Bagian Kuesioner' : 'Tambah Bagian Kuesioner',
        html: htmlContent,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Perubahan' : 'Tambah Bagian',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        width: '500px',
        focusConfirm: false,
        preConfirm: () => {
            const kuesioner_id = document.getElementById('swal-kuesioner-id').value;
            const title = document.getElementById('swal-section-title').value.trim();
            const description = document.getElementById('swal-section-desc').value.trim();
            const order = parseInt(document.getElementById('swal-section-order').value, 10) || null;

            if (!kuesioner_id) {
                Swal.showValidationMessage('Kuesioner induk wajib dipilih.');
                return false;
            }
            if (!title) {
                Swal.showValidationMessage('Judul bagian wajib diisi.');
                return false;
            }

            return {
                kuesioner_id,
                title,
                description,
                order,
            };
        },
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            if (isEdit) {
                router.put(`/superadmin/sections/${sectionToEdit.id}`, result.value, {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire('Berhasil', 'Bagian kuesioner berhasil diperbarui.', 'success');
                    },
                    onError: (errors) => {
                        Swal.fire('Gagal Menyimpan', Object.values(errors).join('<br>'), 'error');
                    },
                });
            } else {
                router.post('/superadmin/sections', result.value, {
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
// SWEETALERT2: DETAIL SECTION (IKON MATA)
// ========================================================
const openDetailModal = (sec) => {
    const questionCount = sec.subpertanyaans_count ?? sec.questions_count ?? (sec.subpertanyaans?.length ?? 0);
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
                    <span class="text-gray-500 block">Kuesioner Induk:</span>
                    <span class="font-bold text-gray-800 text-xs">${sec.kuesioner?.title || sec.questionnaire?.title || 'Kuesioner Umum'}</span>
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
    const questionCount = sec.subpertanyaans_count ?? sec.questions_count ?? (sec.subpertanyaans?.length ?? 0);
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
            router.delete(`/superadmin/sections/${sec.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('Berhasil', 'Bagian kuesioner telah dihapus.', 'success');
                },
                onError: () => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus data.', 'error');
                },
            });
        }
    });
};

const handleMoveSection = (sec, direction) => {
    isReordering.value = true;
    router.post(
        '/superadmin/sections/reorder',
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

// ========================================================
// SWEETALERT2: KELOLA KUESIONER INDUK
// ========================================================
const openKuesionerManagerModal = () => {
    const kList = activeKuesioners.value;

    const listHtml = kList.map((k) => `
        <div class="p-3 bg-white border ${k.is_active ? 'border-emerald-400 ring-1 ring-emerald-200' : 'border-gray-200'} rounded-xl text-left space-y-2 mb-2 shadow-2xs">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-bold text-gray-900 text-xs">${k.title}</span>
                    <span class="px-1.5 py-0.5 text-[10px] font-bold bg-gray-100 border border-gray-200 rounded text-gray-700">Tahun ${k.year}</span>
                    ${k.is_active 
                        ? '<span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300">● Aktif (Alumni)</span>' 
                        : '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-gray-100 text-gray-500">Nonaktif</span>'}
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" class="swal-btn-toggle px-2.5 py-1 text-[10px] font-bold rounded border ${k.is_active ? 'border-amber-300 text-amber-800 bg-amber-50 hover:bg-amber-100' : 'border-emerald-300 text-emerald-800 bg-emerald-50 hover:bg-emerald-100'} cursor-pointer" data-id="${k.id}">
                        ${k.is_active ? 'Nonaktifkan' : 'Jadikan Aktif'}
                    </button>
                    <button type="button" class="swal-btn-edit px-2 py-1 text-[10px] font-semibold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50 rounded border border-gray-200 cursor-pointer" data-id="${k.id}" title="Sunting">
                        Sunting
                    </button>
                    <button type="button" class="swal-btn-delete px-2 py-1 text-[10px] font-semibold text-red-600 hover:bg-red-50 rounded border border-gray-200 cursor-pointer" data-id="${k.id}" title="Hapus">
                        Hapus
                    </button>
                </div>
            </div>
            <p class="text-[11px] text-gray-500 leading-relaxed">${k.description || '<span class="italic text-gray-400">Tidak ada deskripsi tambahan.</span>'}</p>
            <div class="text-[10px] text-gray-400">Total Bagian: <strong class="text-gray-700">${k.kelompok_pertanyaan_count ?? k.sections_count ?? '-'} Seksi</strong></div>
        </div>
    `).join('');

    Swal.fire({
        title: 'Kelola Kuesioner Induk',
        html: `
            <div class="text-left space-y-3 text-xs">
                <div class="flex items-center justify-between pb-2 border-b border-gray-200">
                    <div>
                        <span class="text-gray-800 font-bold block">Daftar Instrumen Kuesioner (${kList.length})</span>
                        <span class="text-[11px] text-gray-500">Kuesioner yang aktif akan ditampilkan pada halaman pengisian alumni.</span>
                    </div>
                    <button id="swal-btn-create-kuesioner" type="button" class="px-3 py-1.5 bg-[#0D542B] hover:bg-[#08381c] text-white text-[11px] font-bold rounded-lg transition-colors cursor-pointer flex items-center gap-1 shadow-xs">
                        + Tambah Kuesioner
                    </button>
                </div>
                <div class="max-h-80 overflow-y-auto pr-1">
                    ${listHtml || '<p class="text-center text-gray-400 py-6">Belum ada data kuesioner.</p>'}
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Tutup',
        cancelButtonColor: '#6B7280',
        width: '600px',
        didOpen: () => {
            document.getElementById('swal-btn-create-kuesioner')?.addEventListener('click', () => {
                openKuesionerFormModal();
            });

            document.querySelectorAll('.swal-btn-edit').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.currentTarget.getAttribute('data-id'), 10);
                    const k = kList.find((item) => item.id === id);
                    if (k) openKuesionerFormModal(k);
                });
            });

            document.querySelectorAll('.swal-btn-toggle').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.currentTarget.getAttribute('data-id'), 10);
                    const k = kList.find((item) => item.id === id);
                    if (k) handleToggleKuesionerActive(k);
                });
            });

            document.querySelectorAll('.swal-btn-delete').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.currentTarget.getAttribute('data-id'), 10);
                    const k = kList.find((item) => item.id === id);
                    if (k) handleDeleteKuesioner(k);
                });
            });
        },
    });
};

const openKuesionerFormModal = (kuesionerToEdit = null) => {
    const isEdit = !!kuesionerToEdit;
    const initialTitle = kuesionerToEdit?.title || '';
    const initialYear = kuesionerToEdit?.year || new Date().getFullYear();
    const initialDesc = kuesionerToEdit?.description || '';
    const initialActive = isEdit ? !!kuesionerToEdit.is_active : true;

    const htmlContent = `
        <div class="text-left space-y-3.5 text-xs">
            <div>
                <label class="block font-bold text-gray-700 mb-1">Judul Kuesioner *</label>
                <input id="swal-kuesioner-title" type="text" value="${initialTitle}" placeholder="Contoh: Tracer Study UKDW 2021" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700" />
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Tahun Kuesioner *</label>
                <input id="swal-kuesioner-year" type="number" value="${initialYear}" min="2000" max="2100" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700" />
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Deskripsi / Keterangan (Opsional)</label>
                <textarea id="swal-kuesioner-desc" rows="3" placeholder="Contoh: Kuesioner Pelacakan Jejak Alumni Universitas Kristen Duta Wacana (Standar Tracer Study 2021)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700 leading-relaxed">${initialDesc}</textarea>
            </div>

            <div class="pt-1">
                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                    <input id="swal-kuesioner-active" type="checkbox" ${initialActive ? 'checked' : ''} class="w-4 h-4 rounded text-emerald-700 focus:ring-emerald-600 border-gray-300" />
                    <span class="font-bold text-gray-700 text-xs">Jadikan Kuesioner Aktif (Ditampilkan kepada Alumni)</span>
                </label>
                <p class="text-[11px] text-gray-500 mt-0.5 ml-6">Jika diaktifkan, kuesioner lain otomatis dinonaktifkan.</p>
            </div>
        </div>
    `;

    Swal.fire({
        title: isEdit ? 'Sunting Kuesioner Induk' : 'Tambah Kuesioner Induk',
        html: htmlContent,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Perubahan' : 'Tambah Kuesioner',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        width: '500px',
        focusConfirm: false,
        preConfirm: () => {
            const title = document.getElementById('swal-kuesioner-title').value.trim();
            const year = parseInt(document.getElementById('swal-kuesioner-year').value, 10);
            const description = document.getElementById('swal-kuesioner-desc').value.trim();
            const is_active = document.getElementById('swal-kuesioner-active').checked;

            if (!title) {
                Swal.showValidationMessage('Judul kuesioner wajib diisi.');
                return false;
            }
            if (!year || year < 2000 || year > 2100) {
                Swal.showValidationMessage('Tahun kuesioner harus antara 2000 - 2100.');
                return false;
            }

            return {
                title,
                year,
                description,
                is_active,
            };
        },
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            if (isEdit) {
                router.put(`/superadmin/kuesioner/${kuesionerToEdit.id}`, result.value, {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire('Berhasil', 'Data kuesioner berhasil diperbarui.', 'success');
                    },
                    onError: (errors) => {
                        Swal.fire('Gagal Menyimpan', Object.values(errors).join('<br>'), 'error');
                    },
                });
            } else {
                router.post('/superadmin/kuesioner', result.value, {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire('Berhasil', 'Kuesioner baru berhasil ditambahkan.', 'success');
                    },
                    onError: (errors) => {
                        Swal.fire('Gagal Menyimpan', Object.values(errors).join('<br>'), 'error');
                    },
                });
            }
        }
    });
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
                onSuccess: () => {
                    Swal.fire('Berhasil', `Kuesioner "${k.title}" berhasil diperbarui.`, 'success');
                },
                onError: () => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat memperbarui status.', 'error');
                },
            });
        }
    });
};

const handleDeleteKuesioner = (k) => {
    Swal.fire({
        title: 'Hapus Kuesioner Induk?',
        html: `Apakah Anda yakin ingin menghapus <strong>"${k.title}"</strong>?<br><br><span class="text-xs text-red-600">Perhatian: Seluruh seksi dan butir pertanyaan di dalam kuesioner ini akan ikut terhapus!</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6B7280',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/superadmin/kuesioner/${k.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('Berhasil', 'Kuesioner berhasil dihapus.', 'success');
                },
                onError: () => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus kuesioner.', 'error');
                },
            });
        }
    });
};
</script>

<template>
    <Head title="Kelola Bagian Kuesioner - Super Admin" />

    <div class="min-h-screen bg-slate-50 flex font-sans">
        <!-- Sidebar Terpadu Super Admin -->
        <Sidebar />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Halaman Bersih & Flat Terpadu -->
            <div class="admin-page-header bg-white border-b border-gray-200 px-6 py-5">
                <div class="w-full flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-gray-500 font-medium mb-1">
                            <Link href="/superadmin/dashboard" class="hover:text-emerald-700">Dashboard</Link>
                            <span>/</span>
                            <span>Manajemen Kuesioner</span>
                            <span>/</span>
                            <span class="text-gray-900 font-semibold">Kuesioner Universitas</span>
                        </div>
                        <h1 class="text-xl font-bold text-gray-900">
                            Kuesioner Universitas (Tracer Study Dikti)
                        </h1>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Instrumen kuesioner tingkat universitas, struktur bagian/seksi, opsi jawaban, dan kuesioner induk.
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            @click="openKuesionerManagerModal()"
                            class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
                            title="Kelola Kuesioner Induk Tracer Study"
                        >
                            <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Kelola Kuesioner Induk</span>
                            <span v-if="activeKuesioner" class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                                {{ activeKuesioner.year }}
                            </span>
                        </button>

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

            <!-- Konten Utama -->
            <main class="admin-page-main w-full p-6 space-y-4">
                <!-- TABS NAVIGASI TERPADU KUESIONER UNIVERSITAS -->
                <div class="flex items-center gap-2 flex-wrap">
                    <Link
                        href="/superadmin/pertanyaan"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 bg-white text-gray-600 hover:bg-gray-100 border border-gray-200"
                    >
                        <span>Butir Pertanyaan</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-gray-100 text-gray-700">
                            {{ totalQuestionsCount }}
                        </span>
                    </Link>

                    <Link
                        href="/superadmin/sections"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 bg-[#0D542B] text-white shadow-xs"
                    >
                        <span>Bagian / Seksi (Section)</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-white/20 text-white">
                            {{ sections.length }}
                        </span>
                    </Link>
                </div>
                <!-- Bilah Pencarian & Ringkasan -->
                <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3">
                        <span class="text-gray-600 font-medium">Total: <strong>{{ sections.length }}</strong> Bagian ({{ totalQuestionsCount }} Pertanyaan)</span>
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
                <div class="bg-white border border-gray-300 overflow-x-auto shadow-2xs">
                    <table class="w-full min-w-full text-left border-collapse border border-gray-300 text-xs bg-white">
                        <thead>
                            <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px] whitespace-nowrap">
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-16 whitespace-nowrap">Urut</th>
                                <th class="border border-gray-300 px-3 py-2 whitespace-nowrap">Judul & Deskripsi Bagian</th>
                                <th class="border border-gray-300 px-3 py-2 w-52 whitespace-nowrap">Kuesioner Induk</th>
                                <th class="border border-gray-300 px-3 py-2 w-32 text-center whitespace-nowrap">Jumlah Soal</th>
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-28 whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredSections.length === 0">
                                <td colspan="5" class="border border-gray-300 p-8 text-center text-gray-400 italic">
                                    Tidak ada bagian kuesioner yang sesuai kriteria pencarian.
                                </td>
                            </tr>
                            <tr
                                v-for="(sec, index) in filteredSections"
                                :key="sec.id"
                                class="hover:bg-gray-50/80 transition-colors"
                            >
                                <!-- Urutan -->
                                <td class="border border-gray-300 px-2.5 py-2 text-center">
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
                                <td class="border border-gray-300 px-3 py-2">
                                    <span class="font-bold text-gray-900 text-xs block">
                                        {{ sec.title }}
                                    </span>
                                    <p v-if="sec.description" class="text-gray-500 text-[11px] mt-0.5 leading-normal">
                                        {{ sec.description }}
                                    </p>
                                    <span v-else class="text-gray-400 text-[11px] italic">
                                        Tidak ada deskripsi tambahan
                                    </span>
                                </td>

                                <!-- Kuesioner Induk -->
                                <td class="border border-gray-300 px-3 py-2">
                                    <span class="text-gray-700 font-medium">
                                        {{ sec.kuesioner?.title || sec.questionnaire?.title || 'Kuesioner Umum' }}
                                        <span v-if="sec.kuesioner?.year || sec.questionnaire?.year" class="text-gray-500 text-[11px]">
                                            ({{ sec.kuesioner?.year || sec.questionnaire?.year }})
                                        </span>
                                    </span>
                                </td>

                                <!-- Jumlah Soal -->
                                <td class="border border-gray-300 px-3 py-2 text-center">
                                    <Link
                                        :href="`/superadmin/pertanyaan?sec_id=${sec.id}`"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-emerald-50 text-emerald-800 hover:bg-emerald-100 font-semibold text-[11px] border border-emerald-200 transition-colors"
                                        title="Buka daftar soal untuk bagian ini"
                                    >
                                        <span>{{ sec.subpertanyaans_count ?? sec.questions_count ?? (sec.subpertanyaans?.length ?? 0) }} Soal</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </td>

                                <!-- Aksi -->
                                <td class="border border-gray-300 px-2.5 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Detail (Mata) -->
                                        <button
                                            type="button"
                                            @click="openDetailModal(sec)"
                                            title="Detail Bagian"
                                            class="p-1 text-blue-600 hover:bg-blue-50 rounded cursor-pointer transition-colors"
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
                                            class="p-1 text-amber-600 hover:bg-amber-50 rounded cursor-pointer transition-colors"
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
                                            class="p-1 text-rose-600 hover:bg-rose-50 rounded cursor-pointer transition-colors"
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
            </main>
        </div>
    </div>
</template>
