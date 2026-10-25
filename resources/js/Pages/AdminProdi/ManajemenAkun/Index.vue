<!--
  Halaman Manajemen Akun Mahasiswa / Alumni Prodi (Admin Prodi)
  File: resources/js/Pages/AdminProdi/ManajemenAkun/Index.vue
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import Sidebar from '../Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    users: Object,
    currentProdi: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters.search || '');

const applyFilters = () => {
    router.get('/prodi/manajemen-akun', {
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
};

// ========================================================
// STATE REAKTIF UNTUK MODAL EDIT AKUN MAHASISWA / ALUMNI
// ========================================================
// Catatan Belajar: 'ref' adalah fungsi dasar Composition API Vue untuk membuat data reaktif.
// Nilai di dalam ref dapat diakses atau diubah menggunakan '.value' di dalam blok script.

// Menentukan apakah modal edit akun sedang terbuka
const isEditModalOpen = ref(false);
// Menyimpan ID user yang datanya sedang diedit
const editingUserId = ref(null);
// Objek penampung nilai input form edit
const editForm = ref({
    name: '',
    email: '',
    username: '',
});
// Penampung pesan error validasi input
const editErrors = ref({});
// Indikator loading saat proses simpan data berjalan
const isSubmitting = ref(false);

/**
 * Membuka modal edit dan mengisi form dengan data pengguna yang diklik
 */
const openEditUserModal = (u) => {
    editErrors.value = {};
    editingUserId.value = u.id;
    editForm.value = {
        name: u.name || '',
        email: u.email || '',
        username: u.username || '',
    };
    isEditModalOpen.value = true;
};

/**
 * Menutup modal edit pengguna dan membersihkan error
 */
const closeEditUserModal = () => {
    isEditModalOpen.value = false;
    editingUserId.value = null;
    editErrors.value = {};
    isSubmitting.value = false;
};

/**
 * Mengirimkan data pembaruan akun ke backend Laravel
 */
const submitEditUser = () => {
    editErrors.value = {};

    // Validasi frontend
    if (!editForm.value.name.trim()) {
        editErrors.value.name = 'Nama lengkap wajib diisi.';
    }
    if (!editForm.value.email.trim()) {
        editErrors.value.email = 'Email wajib diisi.';
    }
    if (!editForm.value.username.trim()) {
        editErrors.value.username = 'NIM / Username wajib diisi.';
    }

    if (Object.keys(editErrors.value).length > 0) {
        return;
    }

    isSubmitting.value = true;

    router.put(`/prodi/manajemen-akun/${editingUserId.value}`, editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            closeEditUserModal();
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Data akun mahasiswa telah diperbarui.',
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: (errs) => {
            editErrors.value = errs;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const handleResetToDefaultAndNotify = (userData) => {
    Swal.fire({
        title: 'Reset Kredensial Awal?',
        html: `
            <div class="text-xs text-left space-y-2">
                <p>Kredensial awal akun <strong>${userData.name}</strong> akan direset. Nilai password tidak ditampilkan demi keamanan.</p>
                <p class="pt-1">Alumni wajib mengganti password setelah berhasil login:</p>
                <div class="font-mono font-bold text-[#0D542B] bg-emerald-50 p-2 rounded border border-emerald-200">${userData.email}</div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Reset & Kirim Email',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
    }).then((res) => {
        if (res.isConfirmed) {
            router.post(`/prodi/manajemen-akun/${userData.id}/reset-default-notify`, {}, {
                onSuccess: () => {
                    Swal.fire('Berhasil Di-reset!', `Kredensial awal ${userData.name} telah direset.`, 'success');
                }
            });
        }
    });
};
</script>

<template>
    <Head :title="`Manajemen Akun - ${currentProdi?.nama_prodi || 'Program Studi'}`" />

    <div class="min-h-screen bg-slate-50 flex font-sans">
        <Sidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <div class="admin-page-header bg-white border-b border-gray-200 px-6 py-5">
                <div class="w-full flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-gray-500 font-medium mb-1">
                            <Link href="/prodi/dashboard" class="hover:underline hover:text-[#0D542B]">Dashboard</Link>
                            <span>/</span>
                            <span class="text-gray-800 font-semibold">{{ currentProdi?.nama_prodi }}</span>
                            <span>/</span>
                            <span class="text-[#0D542B] font-bold">Manajemen Akun Mahasiswa</span>
                        </div>
                        <h1 class="text-xl font-bold text-gray-900">
                            Manajemen Akun Mahasiswa & Alumni {{ currentProdi?.nama_prodi }}
                        </h1>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Pengelolaan akun mahasiswa prodi, pembaruan email pribadi, dan 1-Klik reset password ke tanggal lahir.
                        </p>
                    </div>

                    <div class="bg-slate-50/80 border border-slate-200/80 rounded-2xl px-8 py-3.5 flex items-center gap-8 text-center shadow-2xs">
                        <div>
                            <div class="text-2xl font-black text-[#0D542B] leading-none mb-1.5">{{ stats.total_alumni }}</div>
                            <div class="text-[11px] font-bold text-slate-600 tracking-wide uppercase">TOTAL MAHASISWA PRODI</div>
                        </div>
                    </div>
                </div>
            </div>

            <main class="admin-page-main w-full p-6 space-y-4">
                <div class="bg-white p-4 rounded-lg border border-gray-200 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs shadow-2xs">
                    <div class="flex items-center gap-3 flex-wrap flex-1">
                        <input
                            v-model="search"
                            @keyup.enter="applyFilters"
                            type="text"
                            placeholder="Cari nama, email, atau NIM..."
                            class="px-3 py-2 border border-gray-300 rounded-lg text-xs w-64 focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                        />
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-2xs">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100 border-b border-gray-200 text-slate-700 font-bold uppercase text-[11px]">
                                <th class="py-3 px-4">Nama Mahasiswa</th>
                                <th class="py-3 px-4">Email Terdaftar (Pribadi)</th>
                                <th class="py-3 px-4">Program Studi</th>
                                <th class="py-3 px-4 text-center">Aksi Manajemen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="u in users.data" :key="u.id" class="hover:bg-slate-50/80">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ u.name }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">@{{ u.username }}</div>
                                </td>
                                <td class="py-3 px-4 font-medium text-slate-800">
                                    {{ u.email }}
                                </td>
                                <td class="py-3 px-4 text-slate-700">
                                    {{ u.prodi?.nama_prodi || currentProdi?.nama_prodi || '-' }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            @click="openEditUserModal(u)"
                                            class="px-2.5 py-1 bg-slate-100 text-slate-700 font-bold rounded text-[11px] hover:bg-slate-200 border border-slate-300 cursor-pointer"
                                            title="Sunting email, nama, atau NIM"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="handleResetToDefaultAndNotify(u)"
                                            class="px-2.5 py-1 bg-[#0D542B] text-white font-semibold rounded text-[11px] hover:bg-[#08381c] cursor-pointer"
                                            title="Reset kredensial awal alumni"
                                        >
                                            Reset Password
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL TELEPORT VUE 3: SUNTING DATA MAHASISWA / ALUMNI PRODI -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="isEditModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
                @click.self="closeEditUserModal"
                @keydown.esc="closeEditUserModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <!-- Header Modal -->
                    <div class="px-6 py-4 bg-white border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900">
                            Sunting Data Akun
                        </h3>
                        <button
                            type="button"
                            @click="closeEditUserModal"
                            class="text-gray-400 hover:text-gray-600 p-1 rounded-md hover:bg-gray-100 transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Form Isi Modal -->
                    <form @submit.prevent="submitEditUser" class="p-6 space-y-4 text-xs">
                        <!-- Nama Lengkap -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">
                                Nama Lengkap <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="editForm.name"
                                placeholder="Masukkan nama lengkap mahasiswa/alumni..."
                                class="w-full px-3 py-2 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                :class="editErrors.name ? 'border-rose-400 bg-rose-50/30' : 'border-slate-300'"
                            />
                            <p v-if="editErrors.name" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ editErrors.name }}
                            </p>
                        </div>

                        <!-- Email Terdaftar -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">
                                Email Terdaftar (Pribadi) <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="email"
                                v-model="editForm.email"
                                placeholder="nama@email.com"
                                class="w-full px-3 py-2 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                :class="editErrors.email ? 'border-rose-400 bg-rose-50/30' : 'border-slate-300'"
                            />
                            <p v-if="editErrors.email" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ editErrors.email }}
                            </p>
                        </div>

                        <!-- NIM / Username -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">
                                NIM / Username <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="editForm.username"
                                placeholder="Contoh: 71200001"
                                class="w-full px-3 py-2 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                :class="editErrors.username ? 'border-rose-400 bg-rose-50/30' : 'border-slate-300'"
                            />
                            <p v-if="editErrors.username" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ editErrors.username }}
                            </p>
                        </div>

                        <!-- Tombol Aksi Bawah -->
                        <div class="pt-3 border-t border-gray-100 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="closeEditUserModal"
                                :disabled="isSubmitting"
                                class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors cursor-pointer disabled:opacity-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSubmitting"
                                class="px-4 py-2 text-xs font-semibold text-white bg-[#0D542B] hover:bg-[#08381c] rounded-lg transition-colors cursor-pointer flex items-center gap-1.5 disabled:opacity-50 shadow-xs"
                            >
                                <svg v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>{{ isSubmitting ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
