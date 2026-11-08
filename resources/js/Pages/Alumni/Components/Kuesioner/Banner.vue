<!--
  Komponen: Banner Judul Bagian Kuesioner Tracer Study Alumni
  File: resources/js/Pages/Alumni/Components/Kuesioner/Banner.vue
  Fungsi: Menampilkan kartu banner hijau judul section aktif dengan indikator "Bagian X dari Y" dan instruksi pengisian.
-->
<script setup>
defineProps({
    currentIndex: {
        type: Number,
        required: true,
    },
    totalSections: {
        type: Number,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: 'Pilih jawaban yang paling sesuai. Kolom bertanda * wajib diisi.',
    },
    badgeText: {
        type: String,
        default: 'Kuesioner Universitas',
    },
    percentage: {
        type: Number,
        default: null,
    },
    progressLabel: {
        type: String,
        default: null,
    },
    hasChanges: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['reset']);
</script>

<template>
    <div class="mb-4 sm:mb-6 bg-white p-5 sm:p-7 md:p-8 rounded-2xl sm:rounded-3xl shadow-xs border border-gray-100 w-full">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="inline-block bg-[#0D542B] text-white text-[10px] sm:text-[11px] font-bold uppercase px-2 py-0.5 rounded tracking-wider shadow-2xs">
                        BAGIAN {{ currentIndex + 1 }} DARI {{ totalSections }}
                    </span>
                    <span class="text-[11px] sm:text-xs text-gray-500 font-bold">
                        {{ badgeText }}
                    </span>
                </div>
                <!-- Judul Nama Bagian -->
                <h1 class="text-xl sm:text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight leading-snug">
                    {{ title }}
                </h1>
                <!-- Petunjuk Singkat -->
                <p class="text-gray-600 font-medium text-xs sm:text-sm mt-1">
                    <span v-if="description && !description.toLowerCase().includes('wajib diisi')">
                        {{ description }} Kolom bertanda <span class="text-rose-500 font-bold">*</span> wajib diisi.
                    </span>
                    <span v-else-if="description">
                        {{ description }}
                    </span>
                    <span v-else>
                        Pilih jawaban yang paling sesuai. Kolom bertanda <span class="text-rose-500 font-bold">*</span> wajib diisi.
                    </span>
                </p>
            </div>

            <!-- Tombol Aksi Pembatalan Perubahan Jawaban -->
            <div v-if="hasChanges" class="shrink-0 flex items-center">
                <button
                    type="button"
                    @click="$emit('reset')"
                    class="px-3.5 py-2 rounded-xl border border-gray-300 hover:border-rose-300 bg-white hover:bg-rose-50 text-gray-700 hover:text-rose-700 text-xs font-bold transition-all shadow-2xs flex items-center gap-1.5 cursor-pointer"
                    title="Batalkan perubahan jawaban di formulir dan kembalikan ke data tersimpan"
                >
                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span>Batalkan Perubahan Jawaban</span>
                </button>
            </div>
        </div>
    </div>
</template>
