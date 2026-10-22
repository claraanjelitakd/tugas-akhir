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

const openEditUserModal = (u) => {
    Swal.fire({
        title: `<span class="text-base font-bold text-slate-900">Edit</span>`,
        html: `
            <div class="text-left text-xs space-y-3 pt-1 text-slate-800">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nama Lengkap *</label>
                    <input id="swal-edit-name" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" value="${(u.name || '').replace(/"/g, '&quot;')}" />
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Email Terdaftar (Pribadi) *</label>
                    <input id="swal-edit-email" type="email" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" value="${u.email || ''}" />
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">NIM / Username *</label>
                    <input id="swal-edit-username" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" value="${u.username || ''}" />
                </div>
            </div>
        `,
        width: '480px',
        showCancelButton: true,
        confirmButtonText: 'Simpan Perubahan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        preConfirm: () => {
            const name = document.getElementById('swal-edit-name')?.value?.trim();
            const email = document.getElementById('swal-edit-email')?.value?.trim();
            const username = document.getElementById('swal-edit-username')?.value?.trim();

            if (!name || !email || !username) {
                Swal.showValidationMessage('Nama, Email, dan NIM wajib diisi.');
                return false;
            }

            return { name, email, username };
        }
    }).then((res) => {
        if (res.isConfirmed && res.value) {
            router.put(`/prodi/manajemen-akun/${u.id}`, res.value, {
                onSuccess: () => {
                    Swal.fire('Berhasil!', 'Data akun mahasiswa telah diperbarui.', 'success');
                }
            });
        }
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
    </div>
</template>
