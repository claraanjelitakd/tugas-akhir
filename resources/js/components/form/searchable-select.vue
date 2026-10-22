<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    options: {
        type: Array,
        default: () => []
    },
    placeholder: {
        type: String,
        default: 'Pilih salah satu...'
    },
    required: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const searchQuery = ref('');
const dropdownRef = ref(null);

const filteredOptions = computed(() => {
    if (!searchQuery.value) return props.options;
    const lowerQuery = searchQuery.value.toLowerCase();
    return props.options.filter(opt => 
        opt.option_text.toLowerCase().includes(lowerQuery)
    );
});

const selectedOptionText = computed(() => {
    const selected = props.options.find(opt => opt.option_text === props.modelValue);
    return selected ? selected.option_text : '';
});

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
    }
};

const selectOption = (opt) => {
    emit('update:modelValue', opt.option_text);
    isOpen.value = false;
    searchQuery.value = '';
};

// Close when clicking outside
const closeOnClickOutside = (e) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeOnClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', closeOnClickOutside);
});
</script>

<template>
    <div class="relative w-full" ref="dropdownRef">
        <!-- Input field that shows the selected value but toggles the dropdown -->
        <div 
            @click="toggleDropdown"
            class="w-full rounded-xl border border-gray-200 bg-gray-50/90 p-3.5 sm:p-4 text-sm sm:text-base text-gray-900 focus-within:bg-white focus-within:border-green-500 focus-within:ring-2 focus-within:ring-green-200 transition-all font-medium cursor-pointer flex justify-between items-center shadow-xs"
        >
            <span v-if="modelValue" class="text-green-800 font-bold">{{ selectedOptionText }}</span>
            <span v-else class="text-gray-400">{{ placeholder }}</span>
            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>
        
        <!-- Hidden input for HTML required validation -->
        <input type="text" class="absolute w-0 h-0 opacity-0" :value="modelValue" :required="required" tabindex="-1">

        <!-- Dropdown Menu -->
        <div v-if="isOpen" class="absolute z-50 w-full mt-2 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden">
            <!-- Search bar -->
            <div class="p-3 border-b border-gray-100 bg-gray-50 sticky top-0">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input 
                        type="text" 
                        v-model="searchQuery" 
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-green-500 focus:border-green-500 outline-none"
                        placeholder="Ketik untuk mencari..."
                        autofocus
                    >
                </div>
            </div>
            
            <!-- Options List -->
            <ul class="max-h-48 overflow-y-auto">
                <li 
                    v-for="opt in filteredOptions" 
                    :key="opt.code"
                    @click="selectOption(opt)"
                    class="px-4 py-3 hover:bg-green-50 hover:text-[#005B3C] cursor-pointer text-sm font-medium transition-colors border-b border-gray-50 last:border-b-0"
                    :class="{'bg-green-100 text-[#005B3C]': modelValue === opt.option_text}"
                >
                    {{ opt.option_text }}
                </li>
                <li v-if="filteredOptions.length === 0" class="px-4 py-4 text-center text-sm text-gray-500">
                    Tidak ada hasil yang cocok.
                </li>
            </ul>
        </div>
    </div>
</template>
