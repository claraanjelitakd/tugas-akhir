<script setup>
// Komponen navigasi stepper kuesioner program studi dengan label tahapan lengkap.
import { ref, watch, onMounted } from 'vue';

// Properti data section dan fungsi validasi navigasi dari halaman kuesioner.
const props = defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
    activeIndex: {
        type: Number,
        default: 0,
    },
    completedIndices: {
        type: Object, // Set
        default: () => new Set(),
    },
    isSectionCompleted: {
        type: Function,
        required: true,
    },
    isLineCompleted: {
        type: Function,
        required: true,
    },
    canSelectSection: {
        type: Function,
        required: true,
    },
});

// Event perpindahan section ketika pengguna memilih langkah yang tersedia.
const emit = defineEmits(['select-section']);

// Referensi container dan elemen langkah untuk auto-scroll ke section aktif.
const stepperContainerRef = ref(null);
const stepRefs = ref({});

// Mendaftarkan elemen DOM langkah berdasarkan indeks section.
const setStepRef = (el, index) => {
    if (el) stepRefs.value[index] = el;
};

// Menggeser stepper agar section aktif tetap terlihat setelah navigasi.
const scrollActiveStepIntoView = (index) => {
    if (typeof window === 'undefined') return;
    setTimeout(() => {
        const el = stepRefs.value[index];
        if (el && stepperContainerRef.value) {
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }, 50);
};

watch(() => props.activeIndex, (newIdx) => {
    scrollActiveStepIntoView(newIdx);
});

onMounted(() => {
    scrollActiveStepIntoView(props.activeIndex);
});
</script>

<template>
    <div 
        v-if="sections && sections.length > 0" 
        ref="stepperContainerRef"
        class="w-full bg-white/95 backdrop-blur-md border-b border-gray-200 overflow-x-auto py-2 sm:py-3 custom-scrollbar"
    >
        <div class="flex items-center justify-between px-3 sm:px-4 md:px-8 min-w-max max-w-6xl xl:max-w-7xl mx-auto">
            <template v-for="(section, index) in sections" :key="section.id">
                
                <!-- Bulatan & Label Tahapan Seksi -->
                <div 
                    :ref="el => setStepRef(el, index)"
                    class="flex shrink-0 flex-col relative items-center justify-center group px-1 sm:px-2 md:px-3 py-0.5 sm:py-1 transition-all duration-200"
                    :class="canSelectSection(index) ? 'cursor-pointer' : 'cursor-not-allowed opacity-50'"
                    @click="canSelectSection(index) && emit('select-section', index)"
                    :title="canSelectSection(index) ? ('Bagian ' + (index + 1) + ': ' + section.title) : 'Lengkapi bagian sebelumnya terlebih dahulu'"
                    :aria-disabled="!canSelectSection(index)"
                >
                    <!-- Lingkaran Angka / Centang Selesai -->
                    <div 
                        class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 rounded-full font-bold text-xs sm:text-sm md:text-base transition-all duration-200 z-10 shadow-xs relative"
                        :class="[
                            activeIndex === index
                                ? 'bg-[#0D542B] text-white ring-4 ring-[#0D542B]/20 shadow-md scale-105 sm:scale-110' : 
                            (isSectionCompleted(index) 
                                ? 'bg-[#0D542B] text-white shadow-xs group-hover:bg-[#08381c] group-hover:scale-105' : 
                                'bg-slate-100 text-slate-500 group-hover:bg-slate-200 group-hover:text-slate-700')
                        ]"
                    >
                        <!-- Tanda centang putih jika seksi sudah lengkap dan tidak sedang dibuka -->
                        <svg 
                            v-if="isSectionCompleted(index) && activeIndex !== index" 
                            class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-white" 
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                        </svg>
                        
                        <!-- Angka urutan (1, 2, 3..) -->
                        <span v-else>{{ index + 1 }}</span>

                        <!-- Badge mini centang di sudut kanan atas jika tahapan aktif ini sudah lengkap -->
                        <span 
                            v-if="activeIndex === index && isSectionCompleted(index)" 
                            class="absolute -top-1 -right-1 w-3.5 h-3.5 sm:w-4 sm:h-4 bg-emerald-600 text-white rounded-full flex items-center justify-center text-[8px] sm:text-[10px] font-bold shadow-xs ring-1.5 sm:ring-2 ring-white"
                            title="Bagian ini sudah lengkap"
                        >
                            ✓
                        </span>
                    </div>
                    
                    <!-- Judul section ditampilkan utuh dan boleh membungkus beberapa baris. -->
                    <span 
                        class="mt-1 block w-28 whitespace-normal text-center text-[9px] leading-snug transition-colors sm:mt-2 sm:w-36 sm:text-[11px] md:w-40 md:text-xs lg:w-44 xl:w-48"
                        :class="[
                            activeIndex === index
                                ? 'text-[#0D542B] font-extrabold' : 
                            (isSectionCompleted(index) 
                                ? 'text-slate-800 font-semibold group-hover:text-slate-900' : 
                                'text-slate-400 group-hover:text-slate-600 font-medium')
                        ]"
                    >
                        {{ section.title }}
                    </span>
                </div>
                
                <!-- Garis Penghubung Antar Lingkaran Stepper -->
                <div 
                    v-if="index < sections.length - 1" 
                    class="flex-1 h-1 sm:h-1.5 rounded-full transition-colors duration-300 mx-1 md:mx-2 min-w-[14px] sm:min-w-[20px]" 
                    :class="isLineCompleted(index) ? 'bg-[#0D542B]' : 'bg-slate-200'"
                ></div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    display: none;
}
.custom-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
