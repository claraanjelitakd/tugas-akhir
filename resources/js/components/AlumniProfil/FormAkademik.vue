<!--
  Komponen Anak (Child Component): Form Akademik
  File: resources/js/Pages/Alumni/Profil/Components/FormAkademik.vue
  
  DIPANGGIL OLEH (Parent Component):
  - resources/js/Pages/Alumni/Profil/Index.vue
  
  SUMBER DATA DARI BACKEND:
  - Controller: App\Http\Controllers\Alumni\Profil\ProfilController.php
-->
<script setup>
/**
 * ====================================================================
 * MENERIMA DATA (PROPS) DARI PARENT (Index.vue)
 * ====================================================================
 * Variabel 'form' di bawah ini BUKAN dibuat langsung di file ini!
 * 'form' ini adalah titipan / operan dari komponen induk (Index.vue).
 * 
 * Di Index.vue ada kode:
 *   const form = useForm(props.formData);
 * Lalu dioper ke komponen ini dengan:
 *   <FormAkademik :form="form" />
 * 
 * Isi 'form' ini menampung data akademik alumni dari database (tabel 'data_akademiks'):
 * - form.nim                  : Nomor Induk Mahasiswa
 * - form.angkatan_masuk       : Tahun angkatan masuk kuliah
 * - form.status_mahasiswa     : Status kelulusan (Lulus)
 * - form.tahun_akademik_lulus : Semester/tahun akademik lulus
 * - form.tahun_lulus          : Tahun kalender kelulusan
 * - form.ipk                  : Nilai IPK kelulusan
 * - form.total_sks            : Jumlah SKS yang ditempuh
 * - form.total_angka_kualitas : Total nilai mutu akademik
 * - form.jalur_masuk          : Jalur seleksi saat masuk kampus
 * - form.beasiswa             : Keterangan penerima beasiswa
 * Data pembimbing/penguji, publikasi, dan email institusi tidak dikirim ke
 * profil self-service alumni karena merupakan data referensi resmi.
 */
defineProps({
    form: Object,
});

// Input styling: CSS kelas abu-abu untuk kolom yang terkunci (read-only/disabled)
// karena data akademik resmi berasal dari data kampus dan tidak boleh diubah sembarangan oleh alumni
const lockedInputClass = "block w-full border border-gray-200 bg-gray-100 text-gray-700 rounded-xl px-4 py-3 text-sm font-medium cursor-not-allowed select-none";
const labelClass = "block text-sm font-semibold text-gray-700 mb-1.5";
</script>

<template>
    <div class="space-y-8">
        
        <!-- Bagian 1: Informasi Akademik -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-xs border border-gray-100">
            <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                <span class="w-1.5 h-5 bg-[#0D542B] rounded-full inline-block"></span>
                Informasi Akademik
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label :class="labelClass">Nomor Induk Mahasiswa (NIM)</label>
                    <input type="text" :value="form.nim" disabled :class="lockedInputClass" />
                </div>

                <div>
                    <label :class="labelClass">Angkatan Masuk</label>
                    <input type="text" :value="form.angkatan_masuk" disabled :class="lockedInputClass" />
                </div>

                <div>
                    <label :class="labelClass">Status Mahasiswa</label>
                    <input type="text" :value="form.status_mahasiswa || 'Lulus'" disabled :class="lockedInputClass" />
                </div>

                <div>
                    <label :class="labelClass">Semester Kelulusan</label>
                    <input type="text" :value="form.tahun_akademik_lulus" disabled :class="lockedInputClass" />
                </div>

                <div>
                    <label :class="labelClass">Tahun Lulus</label>
                    <input type="text" :value="form.tahun_lulus" disabled :class="lockedInputClass" />
                </div>

                <div>
                    <label :class="labelClass">Indeks Prestasi Kumulatif (IPK)</label>
                    <input type="text" :value="form.ipk" disabled :class="lockedInputClass" />
                </div>

                <div>
                    <label :class="labelClass">Total SKS Tempuh</label>
                    <input type="text" :value="form.total_sks" disabled :class="lockedInputClass" />
                </div>

                <div>
                    <label :class="labelClass">Total Angka Kualitas</label>
                    <input type="text" :value="form.total_angka_kualitas" disabled :class="lockedInputClass" />
                </div>

            </div>
        </div>

        <!-- Bagian 2: Tugas Akhir & Kelulusan -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-xs border border-gray-100">
            <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                <span class="w-1.5 h-5 bg-[#0D542B] rounded-full inline-block"></span>
                Tugas Akhir & Kelulusan
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label :class="labelClass">Judul Tugas Akhir / Skripsi</label>
                    <textarea :value="form.judul_ta" rows="3" disabled :class="lockedInputClass"></textarea>
                </div>

                <div class="md:col-span-2">
                    <label :class="labelClass">Judul Tugas Akhir (Bahasa Inggris)</label>
                    <textarea :value="form.judul_ta_inggris" rows="3" disabled :class="lockedInputClass"></textarea>
                </div>

                <div>
                    <label :class="labelClass">Tanggal Kelulusan</label>
                    <input type="date" :value="form.tanggal_lulus" disabled :class="lockedInputClass" />
                </div>
            </div>
        </div>
    </div>
</template>
