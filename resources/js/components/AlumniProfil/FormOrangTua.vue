<!--
  Komponen Anak (Child Component): Form Data Orang Tua
  File: resources/js/Pages/Alumni/Profil/Components/FormOrangTua.vue

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
 */
const props = defineProps({
    form: Object,
    provinces: Array,
    kabupatens: Array
});

</script>

<template>
    <div class="space-y-8">

        <!-- Bagian: Identitas Orang Tua / Wali -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-xs border border-gray-100">
            <div class="flex items-center justify-between mb-6 pb-3 border-b border-gray-100">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-[#0D542B] rounded-full inline-block"></span>
                    Identitas Orang Tua / Wali
                </h2>
                <span class="text-xs text-gray-500 font-medium">Data referensi, tidak dapat diubah dari tracer study</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Nama Lengkap Orang Tua -->
                <div>
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Nama Lengkap Orang Tua / Wali
                    </label>
                    <input
                        type="text"
                        :value="form.nama_orang_tua"
                        disabled
                        class="block w-full border border-gray-200 bg-gray-100 text-gray-700 rounded-xl px-4 py-3 text-sm font-medium cursor-not-allowed"
                        placeholder="Nama Lengkap Orang Tua / Wali"
                    />
                </div>

                <!-- Pekerjaan Orang Tua -->
                <div>
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Pekerjaan Orang Tua / Wali
                    </label>
                    <input
                        type="text"
                        :value="form.pekerjaan_orang_tua"
                        disabled
                        class="block w-full border border-gray-200 bg-gray-100 text-gray-700 rounded-xl px-4 py-3 text-sm font-medium cursor-not-allowed"
                        placeholder="Contoh: PNS, Wiraswasta, Karyawan Swasta, Petani..."
                    />
                </div>

                <!-- Nomor Telepon Orang Tua -->
                <div class="md:col-span-2">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Nomor Telepon / WhatsApp Orang Tua
                    </label>
                    <input
                        type="tel"
                        :value="form.nomor_telepon_orang_tua"
                        disabled
                        class="block w-full border border-gray-200 bg-gray-100 text-gray-700 rounded-xl px-4 py-3 text-sm font-mono font-medium cursor-not-allowed"
                        placeholder="Contoh: 081234567890"
                    />
                </div>
            </div>
        </div>

        <!-- Bagian: Alamat Tempat Tinggal Orang Tua -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-xs border border-gray-100">
            <div class="flex items-center justify-between mb-6 pb-3 border-b border-gray-100">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-[#0D542B] rounded-full inline-block"></span>
                    Alamat Domisili Orang Tua / Wali
                </h2>
                <span class="text-xs text-gray-500 font-medium">Bersumber dari data referensi lokal</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Alamat Lengkap -->
                <div class="md:col-span-2">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Alamat Lengkap
                    </label>
                    <textarea
                        :value="form.alamat_orang_tua"
                        disabled
                        rows="3"
                        class="block w-full border border-gray-200 bg-gray-100 text-gray-700 rounded-xl px-4 py-3 text-sm font-medium cursor-not-allowed"
                        placeholder="Nama Jalan, RT/RW, Dusun/Kompleks, Kelurahan..."
                    ></textarea>
                </div>

                <!-- Provinsi -->
                <div>
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Provinsi
                    </label>
                    <select
                        v-model="form.provinsi_id_orang_tua"
                        disabled
                        class="block w-full border border-gray-200 bg-gray-100 text-gray-700 rounded-xl px-4 py-3 text-sm font-medium cursor-not-allowed"
                    >
                        <option value="">-- Pilih Provinsi --</option>
                        <option v-for="prov in provinces" :key="prov.id" :value="prov.id">{{ prov.nama_provinsi }}</option>
                    </select>
                </div>

                <!-- Kabupaten / Kota -->
                <div>
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Kabupaten / Kota
                    </label>
                    <select
                        v-model="form.kabupaten_id_orang_tua"
                        disabled
                        class="block w-full border border-gray-200 bg-gray-100 text-gray-700 rounded-xl px-4 py-3 text-sm font-medium cursor-not-allowed"
                    >
                        <option value="">-- Pilih Kabupaten/Kota --</option>
                        <option v-for="kab in kabupatens" :key="kab.id" :value="kab.id" v-show="!form.provinsi_id_orang_tua || kab.province_id == form.provinsi_id_orang_tua">
                            {{ kab.nama_kabupaten }}
                        </option>
                    </select>
                </div>

                <!-- Kota / Keterangan Wilayah -->
                <div>
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Kota / Keterangan Wilayah
                    </label>
                    <input
                        type="text"
                        :value="form.kota_orang_tua"
                        disabled
                        autocomplete="off"
                        class="block w-full border border-gray-200 bg-gray-100 text-gray-700 rounded-xl px-4 py-3 text-sm font-medium cursor-not-allowed"
                        placeholder="Contoh: Sleman, Yogyakarta"
                    />
                </div>

                <!-- Kode Pos -->
                <div>
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Kode Pos
                    </label>
                    <input
                        type="text"
                        :value="form.kode_pos_orang_tua"
                        disabled
                        autocomplete="off"
                        class="block w-full border border-gray-200 bg-gray-100 text-gray-700 rounded-xl px-4 py-3 text-sm font-medium cursor-not-allowed"
                        placeholder="Kode Pos (5 digit)"
                    />
                </div>
            </div>
        </div>

    </div>
</template>
