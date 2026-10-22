<template>
    <transition name="fade">
        <div v-if="isLoading" class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-black/65 backdrop-blur-xs transition-opacity">
            
            <!-- Container Spinner Dotted (Gaya Gambar 3) -->
            <div class="relative flex items-center justify-center w-36 h-36">

                <!-- 12-Dot Circular Spinner berputar -->
                <svg class="absolute inset-0 w-full h-full animate-dots-spin" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- 12 dots disusun melingkar dengan gradasi opacity seperti Gambar 3 -->
                    <circle cx="60" cy="12" r="5.2" fill="#ffffff" opacity="1.0" />
                    <circle cx="84" cy="18.43" r="5.2" fill="#ffffff" opacity="0.91" />
                    <circle cx="101.57" cy="36" r="5.2" fill="#ffffff" opacity="0.82" />
                    <circle cx="108" cy="60" r="5.2" fill="#ffffff" opacity="0.73" />
                    <circle cx="101.57" cy="84" r="5.2" fill="#ffffff" opacity="0.64" />
                    <circle cx="84" cy="101.57" r="5.2" fill="#ffffff" opacity="0.55" />
                    <circle cx="60" cy="108" r="5.2" fill="#ffffff" opacity="0.45" />
                    <circle cx="36" cy="101.57" r="5.2" fill="#ffffff" opacity="0.36" />
                    <circle cx="18.43" cy="84" r="5.2" fill="#ffffff" opacity="0.27" />
                    <circle cx="12" cy="60" r="5.2" fill="#ffffff" opacity="0.18" />
                    <circle cx="18.43" cy="36" r="5.2" fill="#ffffff" opacity="0.11" />
                    <circle cx="36" cy="18.43" r="5.2" fill="#ffffff" opacity="0.06" />
                </svg>

                <!-- Gambar Pigo saat ini tetap di tengah lingkaran dot -->
                <img
                    src="/uploads/pigo/loading-pigo.png"
                    alt="Loading"
                    class="w-16 h-16 object-contain z-10 drop-shadow-md select-none pointer-events-none"
                    onerror="this.src='/uploads/landing/2.png'"
                />
            </div>

            <!-- Teks Loading persis seperti Gambar 3 -->
            <p class="text-white text-base font-normal tracking-wide mt-4 select-none drop-shadow-sm">Loading...</p>

        </div>
    </transition>
</template>

<script setup>
defineProps({
    isLoading: {
        type: Boolean,
        default: true
    }
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Animasi putaran 12 langkah seperti indikator standar Apple / Gambar 3 */
.animate-dots-spin {
    animation: dots-spin 1s steps(12, end) infinite;
}

@keyframes dots-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>
