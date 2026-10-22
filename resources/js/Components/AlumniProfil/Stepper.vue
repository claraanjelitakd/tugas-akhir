<script setup>
defineProps({
    tabs: {
        type: Array,
        required: true,
    },
    activeTab: {
        type: String,
        required: true,
    },
    isTabCompleted: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['select-tab']);
</script>

<template>
    <div class="w-full bg-white/95 backdrop-blur-md border-b border-gray-200 overflow-x-auto py-3 sm:py-4 no-scrollbar rounded-t-2xl shadow-2xs">
        <div class="flex items-center justify-between px-6 sm:px-10 md:px-14 w-full min-w-[500px]">
            <template v-for="(tab, index) in tabs" :key="tab.id">
                <!-- Bulatan & Label Tahapan Stepper Profil -->
                <div 
                    class="flex flex-col items-center justify-center cursor-pointer group px-2 py-1 transition-all"
                    @click="emit('select-tab', tab.id)"
                    :title="'Langkah ' + tab.step + ': ' + tab.name"
                >
                    <!-- Lingkaran Angka Tahapan -->
                    <div 
                        class="flex items-center justify-center w-9 h-9 sm:w-11 sm:h-11 rounded-full font-bold text-xs sm:text-sm transition-all duration-200 z-10 shadow-xs relative"
                        :class="[
                            activeTab === tab.id
                                ? 'bg-[#0D542B] text-white ring-4 ring-[#0D542B]/20 shadow-md scale-105' : 
                            (isTabCompleted(tab.id)
                                ? 'bg-[#0D542B] text-white shadow-xs group-hover:bg-[#08381c] group-hover:scale-105' : 
                                'bg-slate-100 text-slate-500 border border-slate-300 group-hover:bg-slate-200 group-hover:text-slate-700')
                        ]"
                    >
                        <span>{{ tab.step }}</span>

                        <!-- Badge mini centang di sudut kanan atas jika tahapan aktif ini sudah lengkap -->
                        <span 
                            v-if="false"
                            class="absolute -top-1 -right-1 w-4 h-4 bg-emerald-600 text-white rounded-full flex items-center justify-center text-[9px] font-bold shadow-xs ring-2 ring-white"
                            title="Langkah ini sudah lengkap"
                        >
                            ✓
                        </span>
                    </div>

                    <!-- Judul Nama Langkah di Bawah Lingkaran -->
                    <span 
                        class="text-[11px] sm:text-xs mt-1.5 text-center leading-tight transition-colors"
                        :class="[
                            activeTab === tab.id
                                ? 'text-[#0D542B] font-extrabold' : 
                            (isTabCompleted(tab.id)
                                ? 'text-slate-800 font-semibold group-hover:text-slate-900' : 
                                'text-slate-400 group-hover:text-slate-600 font-medium')
                        ]"
                    >
                        {{ tab.name }}
                    </span>

                    <!-- Keterangan Status Kelengkapan Langkah -->
                    <span 
                        v-if="isTabCompleted(tab.id)" 
                        class="text-[9px] sm:text-[10px] text-emerald-700 font-bold mt-0.5"
                    >
                        Sudah Lengkap
                    </span>
                    <span 
                        v-else 
                        class="text-[9px] sm:text-[10px] text-slate-400 font-normal mt-0.5"
                    >
                        {{ tab.subtitle }}
                    </span>
                </div>

                <!-- Garis Penghubung Antar Lingkaran Stepper -->
                <div 
                    v-if="index < tabs.length - 1" 
                    class="flex-1 h-1 sm:h-1.5 rounded-full transition-colors duration-300 mx-3 sm:mx-6 min-w-[30px]" 
                    :class="isTabCompleted(tab.id) ? 'bg-[#0D542B]' : 'bg-slate-200'"
                ></div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
