<script setup>
defineProps({
    eyebrow: { type: String, default: 'Tracer Study' },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    contextLabel: { type: String, default: '' },
    contextValue: { type: String, default: '' },
});
</script>

<template>
    <header class="bg-[#0D542B] px-6 py-7 text-white lg:px-8">
        <div class="flex w-full flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <div class="mb-2 flex items-center gap-2 text-xs font-medium text-emerald-100">
                    <span>Tracer Study</span>
                    <span class="text-emerald-300">/</span>
                    <span class="font-bold text-white">{{ eyebrow }}</span>
                </div>
                <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">{{ title }}</h1>
                <p v-if="description" class="mt-1.5 max-w-3xl text-xs leading-relaxed text-emerald-100 sm:text-sm">{{ description }}</p>
            </div>

            <div v-if="contextLabel || contextValue" class="shrink-0 rounded-lg bg-[#FDC700] px-3.5 py-2 text-[#0D542B]">
                <span v-if="contextLabel" class="block text-[10px] font-bold uppercase tracking-wider">{{ contextLabel }}</span>
                <span class="text-xs font-extrabold">{{ contextValue }}</span>
            </div>
        </div>
    </header>
</template>
