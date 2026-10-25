<!--
  Halaman Manajemen Akun Pengguna Terpadu (Super Admin)
  File: resources/js/Pages/SuperAdmin/ManajemenAkun/Index.vue

  Fitur:
  1. Sunting/Edit Data Pengguna (Email, Nama, Username, Role, Prodi/Fakultas).
  2. Reset Password 1-Klik Otomatis ke Tanggal Lahir (ddmmyyyy) + Kirim Email Konfirmasi ke Email Terdaftar.
  3. Desain Bersih Enterprise tanpa highlight background aneh pada sidebar.
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import Sidebar from '../Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    users: Object,
    prodis: Array,
    fakultas: Array,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters.search || '');
const selectedRole = ref(props.filters.role || 'all');
const selectedProdi = ref(props.filters.prodi_id || '');
const selectedFakultas = ref(props.filters.fakultas_id || '');

const applyFilters = () => {
    router.get('/superadmin/manajemen-akun', {
        search: search.value || undefined,
        role: selectedRole.value !== 'all' ? selectedRole.value : undefined,
        prodi_id: selectedProdi.value || undefined,
        fakultas_id: selectedFakultas.value || undefined,
    }, { preserveState: true, replace: true });
};

// ========================================================
// STATE REAKTIF UNTUK MODAL EDIT PENGGUNA (VUE 3)
// ========================================================
// Catatan Belajar: 'ref' membuat variabel reaktif yang dapat dipantau oleh Vue.
// Ketika nilai 'isEditModalOpen.value' berubah menjadi true, Vue langsung merender popup modal.

// Menentukan apakah modal edit akun pengguna sedang terbuka
const isEditModalOpen = ref(false);
// Menyimpan ID pengguna yang datanya sedang disunting
const editingUserId = ref(null);
// Objek penampung nilai form edit akun
const editForm = ref({
    name: '',
    email: '',
    username: '',
    role: 'alumni',
    prodi_id: '',
    fakultas_id: '',
});
// Menampung pesan error validasi input
const editErrors = ref({});
// Status loading saat request pengiriman sedang berlangsung
const isSubmitting = ref(false);

/**
 * Membuka modal edit pengguna dan mengisi form dengan data yang dipilih
 * Catatan Belajar: Mengisi objek form sebelum membuka modal memastikan pengguna
 * langsung melihat data lama yang siap disunting.
 */
const openEditUserModal = (u) => {
    editErrors.value = {};
    editingUserId.value = u.id;

    const currentFakultasId = u.fakultas_id || u.prodi?.fakultas_id || u.prodi?.fakultas?.id || '';

    editForm.value = {
        name: u.name || '',
        email: u.email || '',
        username: u.username || '',
        role: u.role || 'alumni',
        prodi_id: u.prodi_id || '',
        fakultas_id: currentFakultasId,
    };

    isEditModalOpen.value = true;
};

/**
 * Menutup modal edit pengguna dan mereset state error
 */
const closeEditUserModal = () => {
    isEditModalOpen.value = false;
    editingUserId.value = null;
    editErrors.value = {};
    isSubmitting.value = false;
};

/**
 * Menangani perubahan pilihan Program Studi.
 * Saat Program Studi dipilih, Fakultas yang menaunginya akan otomatis terpilih secara cerdas.
 */
const handleProdiChange = () => {
    if (!editForm.value.prodi_id) return;
    const foundProdi = props.prodis.find((p) => p.id == editForm.value.prodi_id);
    if (foundProdi && foundProdi.fakultas_id) {
        editForm.value.fakultas_id = foundProdi.fakultas_id;
    }
};

/**
 * Mengirimkan data pembaruan akun pengguna ke server (Laravel Controller)
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
        editErrors.value.username = 'Username / NIM wajib diisi.';
    }

    if (Object.keys(editErrors.value).length > 0) {
        return;
    }

    isSubmitting.value = true;

    router.put(`/superadmin/manajemen-akun/${editingUserId.value}`, editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            closeEditUserModal();
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Data akun pengguna telah diperbarui.',
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

// Reset kredensial awal alumni tanpa menampilkan nilai password
const handleResetToDefaultAndNotify = (userData) => {
    Swal.fire({
        title: 'Reset Kredensial Awal?',
        html: `
            <div class="text-xs text-left space-y-2">
                <p>Kredensial awal akun <strong>${userData.name}</strong> akan direset. Nilai password tidak ditampilkan demi keamanan.</p>
                <p class="pt-1">Alumni wajib mengganti password setelah berhasil login.</p>
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
            router.post(`/superadmin/manajemen-akun/${userData.id}/reset-default-notify`, {}, {
                onSuccess: () => {
                    Swal.fire('Berhasil Di-reset!', `Kredensial awal ${userData.name} telah direset.`, 'success');
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Manajemen Akun - Super Admin UKDW" />

    <div class="min-h-screen bg-slate-50 flex font-sans">
        <Sidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Solid UKDW Green Konsisten -->
            <div class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full max-w-[1440px] mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-white/70 font-medium mb-1.5">
                            <Link href="/superadmin/dashboard" class="hover:text-white transition-colors">Dashboard</Link>
                            <span>/</span>
                            <span class="text-white font-semibold">Manajemen Akun</span>
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">
                            Manajemen Akun Pengguna
                        </h1>
                        <p class="text-xs text-emerald-100/90 mt-1 max-w-2xl leading-relaxed">
                            Pengelolaan akun terpadu, pembaruan identitas, dan reset kredensial awal alumni.
                        </p>
                    </div>

                    <!-- Widget Statistik 2-Tone Bersih -->
                    <div class="bg-white/10 border border-white/20 rounded-xl px-6 py-3 flex items-center gap-6 text-center shadow-xs">
                        <div>
                            <div class="text-2xl font-black text-white leading-none mb-1">{{ stats?.total_admin_fakultas + stats?.total_admin_prodi || 0 }}</div>
                            <div class="text-[10px] font-bold text-emerald-100/80 tracking-wide uppercase">ADMIN FAKULTAS &amp; PRODI</div>
                        </div>
                        <div class="w-px h-8 bg-white/20"></div>
                        <div>
                            <div class="text-2xl font-black text-[#FDC700] leading-none mb-1">{{ stats?.total_alumni || 0 }}</div>
                            <div class="text-[10px] font-bold text-emerald-100/80 tracking-wide uppercase">MAHASISWA &amp; ALUMNI</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kontainer Konten Utama -->
            <main class="admin-page-main w-full max-w-[1440px] mx-auto -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
                <!-- Filter bar -->
                <div class="bg-white p-3.5 rounded-xl border border-gray-300 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs shadow-2xs">
                    <div class="flex items-center gap-3 flex-wrap flex-1">
                        <input
                            v-model="search"
                            @keyup.enter="applyFilters"
                            type="text"
                            placeholder="Cari nama, email, atau username..."
                            class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs w-64 focus:outline-none focus:ring-1 focus:ring-[#0D542B] bg-white text-gray-900 placeholder-gray-400"
                        />

                        <!-- Filter Role -->
                        <select v-model="selectedRole" @change="applyFilters" class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs bg-white text-gray-700 font-medium">
                            <option value="all">Semua Peran (Role)</option>
                            <option value="superadmin">Super Admin</option>
                            <option value="admin_biro3">Admin Biro 3</option>
                            <option value="admin_fakultas">Admin Fakultas</option>
                            <option value="admin_prodi">Admin Prodi</option>
                            <option value="alumni">Alumni</option>
                        </select>

                        <!-- Filter Prodi -->
                        <select v-model="selectedProdi" @change="applyFilters" class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs bg-white text-gray-700 font-medium">
                            <option value="">Semua Program Studi</option>
                            <option v-for="p in prodis" :key="p.id" :value="p.id">{{ p.nama_prodi }}</option>
                        </select>
                    </div>
                </div>

                <!-- Table Pengguna -->
                <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-full text-left border-collapse border border-gray-300 text-xs bg-white">
                        <thead>
                            <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px] whitespace-nowrap">
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-12 whitespace-nowrap">No</th>
                                <th class="border border-gray-300 px-3 py-2 whitespace-nowrap">Nama Pengguna</th>
                                <th class="border border-gray-300 px-3 py-2 whitespace-nowrap">Email Terdaftar (Pribadi)</th>
                                <th class="border border-gray-300 px-3 py-2 whitespace-nowrap">Role / Peran</th>
                                <th class="border border-gray-300 px-3 py-2 whitespace-nowrap">Program Studi / Fakultas</th>
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-24 whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!users.data || users.data.length === 0">
                                <td colspan="6" class="border border-gray-300 p-8 text-center text-gray-400 italic">
                                    Tidak ada data pengguna yang sesuai dengan kriteria filter.
                                </td>
                            </tr>
                            <tr v-for="(u, idx) in users.data" :key="u.id" class="hover:bg-gray-50/80 transition-colors">
                                <td class="border border-gray-300 px-2.5 py-2 text-center text-gray-600 font-medium">
                                    {{ ((users.current_page - 1) * users.per_page) + idx + 1 }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <div class="font-bold text-gray-900">{{ u.name }}</div>
                                    <div class="text-[11px] text-gray-500 font-mono">@{{ u.username }}</div>
                                </td>
                                <td class="border border-gray-300 px-3 py-2 font-medium text-gray-800">
                                    {{ u.email }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <span v-if="u.role === 'superadmin' || u.role?.startsWith('admin')" class="inline-block px-2.5 py-0.5 rounded-full font-semibold text-[10px] text-[#0D542B] bg-emerald-50 border border-emerald-200">
                                        {{ u.role === 'superadmin' ? 'Super Admin' : (u.role === 'admin_fakultas' ? 'Admin Fakultas' : (u.role === 'admin_prodi' ? 'Admin Prodi' : (u.role === 'admin_biro3' ? 'Admin Biro 3' : u.role))) }}
                                    </span>
                                    <span v-else class="inline-block px-2.5 py-0.5 rounded-full font-semibold text-[10px] text-gray-700 bg-gray-100 border border-gray-200">
                                        {{ u.role === 'alumni' ? 'Alumni' : u.role }}
                                    </span>
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-gray-700">
                                    <template v-if="u.role === 'alumni' || u.role === 'admin_prodi'">
                                        <div class="font-medium text-gray-900">{{ u.prodi?.nama_prodi || '-' }}</div>
                                        <div v-if="u.prodi?.fakultas" class="text-[11px] text-gray-500 mt-0.5">{{ u.prodi.fakultas.nama_fakultas }}</div>
                                    </template>
                                    <template v-else>
                                        {{ u.fakultas?.nama_fakultas || u.prodi?.nama_prodi || '-' }}
                                    </template>
                                </td>
                                <td class="border border-gray-300 px-2.5 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Tombol Edit User (Pencil) -->
                                        <button
                                            type="button"
                                            @click="openEditUserModal(u)"
                                            class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                            title="Sunting Pengguna"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <!-- Tombol Reset Password (Key) -->
                                        <button
                                            v-if="u.role === 'alumni'"
                                            type="button"
                                            @click="handleResetToDefaultAndNotify(u)"
                                            class="p-1.5 text-gray-600 hover:text-[#0D542B] hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer"
                                            title="Reset kredensial awal alumni"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                    <!-- Pagination -->
                    <div v-if="users.links && users.links.length > 3" class="px-4 py-3 border-t border-gray-200 bg-slate-50 flex items-center justify-between text-xs text-gray-600">
                        <div>
                            Menampilkan {{ users.from || 0 }} - {{ users.to || 0 }} dari total {{ users.total || 0 }} pengguna.
                        </div>
                        <div class="flex gap-1">
                            <Link
                                v-for="(link, lIdx) in users.links"
                                :key="lIdx"
                                :href="link.url || '#'"
                                v-html="link.label"
                                class="px-2.5 py-1 rounded text-xs font-semibold transition-colors"
                                :class="[
                                    link.active
                                        ? 'bg-[#0D542B] text-white'
                                        : link.url
                                            ? 'bg-white text-gray-700 hover:bg-gray-200 border border-gray-300'
                                            : 'text-gray-400 cursor-not-allowed pointer-events-none'
                                ]"
                            />
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL TELEPORT VUE 3: SUNTING DATA PENGGUNA -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="isEditModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
                @click.self="closeEditUserModal"
                @keydown.esc="closeEditUserModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <!-- Header Modal -->
                    <div class="px-6 py-4 bg-white border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900">
                            Sunting Data Pengguna
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
                                placeholder="Masukkan nama lengkap pengguna..."
                                class="w-full px-3 py-2 border rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                :class="editErrors.name ? 'border-rose-400 bg-rose-50/30' : 'border-slate-300'"
                            />
                            <p v-if="editErrors.name" class="text-rose-600 text-[11px] mt-1 font-medium">
                                {{ editErrors.name }}
                            </p>
                        </div>

                        <!-- Email & Username -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">
                                    Username / NIM <span class="text-rose-600">*</span>
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
                        </div>

                        <!-- Peran (Role), Prodi, Fakultas -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Peran (Role)</label>
                                <select
                                    v-model="editForm.role"
                                    class="w-full px-2.5 py-2 border border-slate-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                >
                                    <option value="superadmin">Super Admin</option>
                                    <option value="admin_biro3">Admin Biro 3</option>
                                    <option value="admin_fakultas">Admin Fakultas</option>
                                    <option value="admin_prodi">Admin Prodi</option>
                                    <option value="alumni">Alumni</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Program Studi</label>
                                <select
                                    v-model="editForm.prodi_id"
                                    @change="handleProdiChange"
                                    class="w-full px-2.5 py-2 border border-slate-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                >
                                    <option value="">-- Pilih Prodi --</option>
                                    <option v-for="p in prodis" :key="p.id" :value="p.id">
                                        {{ p.nama_prodi }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Fakultas</label>
                                <select
                                    v-model="editForm.fakultas_id"
                                    class="w-full px-2.5 py-2 border border-slate-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                >
                                    <option value="">-- Pilih Fakultas --</option>
                                    <option v-for="f in fakultas" :key="f.id" :value="f.id">
                                        {{ f.nama_fakultas }}
                                    </option>
                                </select>
                            </div>
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
