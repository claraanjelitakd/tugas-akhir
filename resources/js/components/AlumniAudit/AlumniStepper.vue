<!--
  AlumniStepper.vue
  Komponen Bersama: Navigasi Stepper Tahapan Audit Mahasiswa & Alumni UKDW
  Lokasi: resources/js/Components/AlumniAudit/AlumniStepper.vue
  
  Mendukung 4 peran administrator (Super Admin, Fakultas, Prodi, Biro 3)
  dengan visual formal, flat, bersih, tanpa ikon dekoratif berlebihan.
-->
<script setup>
/**
 * Definisi properti komponen stepper audit alumni.
 */
const props = defineProps({
    /**
     * Daftar tab tahapan audit alumni.
     * Tiap item memuat: id, title, subtitle, isComplete, statusText.
     */
    tabs: {
        type: Array,
        required: true,
    },
    /**
     * ID tab yang sedang aktif saat ini (v-model).
     */
    modelValue: {
        type: String,
        required: true,
    },
});

/**
 * Emit event untuk update nilai v-model tab aktif.
 */
const emit = defineEmits(['update:modelValue']);

/**
 * Pengecekan apakah garis penghubung antar bulatan stepper sudah berstatus selesai.
 *
 * @param {number} index - Indeks tab saat ini.
 * @returns {boolean} Benar jika kedua tab yang terhubung sudah selesai.
 */
const isLineCompleted = (index) => {
    const currentTab = props.tabs[index];
    const nextTab = props.tabs[index + 1];
    return Boolean(currentTab?.isComplete && nextTab?.isComplete);
};

/**
 * Handler saat pengguna mengklik salah satu tahapan stepper.
 *
 * @param {string} tabId - Identifier tab yang diklik.
 */
const selectTab = (tabId) => {
    emit('update:modelValue', tabId);
};
</script>

<template>
    <div class="w-full bg-white rounded-2xl shadow-xs border border-gray-200 overflow-x-auto py-3.5 sm:py-4 px-4 sm:px-6">
        <div class="flex items-center justify-between min-w-max max-w-5xl mx-auto">
            <template v-for="(tab, index) in tabs" :key="tab.id">
                <!-- 1. Bulatan & Label Tahapan -->
                <div 
                    class="flex flex-col relative items-center justify-center cursor-pointer group px-2 sm:px-4 py-1 transition-all duration-200"
                    @click="selectTab(tab.id)"
                    :title="tab.title"
                >
                    <!-- Lingkaran Angka / Centang Selesai -->
                    <div 
                        class="flex items-center justify-center w-10 h-10 sm:w-11 sm:h-11 md:w-12 md:h-12 rounded-full font-black text-xs sm:text-sm md:text-base transition-all duration-200 z-10 shadow-xs relative"
                        :class="[
                            modelValue === tab.id
                                ? 'bg-[#FFD700] text-[#005B3C] shadow-md scale-105 sm:scale-110 ring-2 sm:ring-4 ring-[#005B3C]/20' : 
                            (tab.isComplete 
                                ? 'bg-[#005B3C] text-white shadow-xs group-hover:bg-[#00482f] group-hover:scale-105' : 
                                'bg-gray-100 text-gray-400 group-hover:bg-gray-200 group-hover:text-gray-600')
                        ]"
                    >
                        <span>{{ index + 1 }}</span>

                        <!-- Badge mini centang di sudut kanan atas jika tahapan aktif ini sudah lengkap -->
                        <span 
                            v-if="false"
                            class="absolute -top-1 -right-1 w-4 h-4 bg-[#005B3C] text-white rounded-full flex items-center justify-center text-[9px] font-black shadow-xs ring-2 ring-white"
                            title="Bagian ini sudah lengkap"
                        >
                            ✓
                        </span>
                    </div>
                    
                    <!-- Judul Nama Seksi di Bawah Lingkaran -->
                    <span 
                        class="text-[10px] sm:text-[11px] md:text-xs mt-1.5 sm:mt-2 text-center w-24 sm:w-28 leading-tight transition-colors line-clamp-2"
                        :class="[
                            modelValue === tab.id
                                ? 'text-[#005B3C] font-black' : 
                            (tab.isComplete 
                                ? 'text-[#005B3C] font-bold group-hover:text-[#00482f]' : 
                                'text-gray-400 group-hover:text-gray-600 font-medium')
                        ]"
                    >
                        {{ tab.title }}
                    </span>

                    <!-- Subtitle / Nama Prodi jika ada -->
                    <span 
                        v-if="tab.subtitle"
                        class="text-[9px] sm:text-[10px] text-center text-gray-400 truncate max-w-[110px]"
                    >
                        {{ tab.subtitle }}
                    </span>

                    <!-- Keterangan Status Kelengkapan di Bawah Judul -->
                    <span 
                        class="text-[9px] sm:text-[10px] mt-0.5 text-center font-bold"
                        :class="tab.isComplete ? 'text-[#005B3C]' : 'text-gray-400'"
                    >
                        {{ tab.statusText }}
                    </span>
                </div>
                
                <!-- 2. Garis Penghubung Antar Lingkaran Stepper -->
                <div 
                    v-if="index < tabs.length - 1" 
                    class="flex-1 h-1 sm:h-1.5 rounded-full transition-colors duration-300 mx-1 sm:mx-2 md:mx-3 min-w-[14px] sm:min-w-[20px]" 
                    :class="isLineCompleted(index) ? 'bg-[#005B3C]' : 'bg-gray-200'"
                ></div>
            </template>
        </div>
    </div>
</template>
