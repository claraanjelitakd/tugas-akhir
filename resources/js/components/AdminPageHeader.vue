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
    <header class="border-b border-slate-200 bg-white px-6 py-5">
        <div class="flex w-full flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs font-medium text-slate-500">
                    <span>Tracer Study</span>
                    <span>/</span>
                    <span class="font-bold text-slate-800">{{ eyebrow }}</span>
                </div>
                <h1 class="text-xl font-bold text-slate-900">{{ title }}</h1>
                <p v-if="description" class="mt-0.5 text-xs text-slate-500">{{ description }}</p>
            </div>

            <div v-if="contextLabel || contextValue" class="flex items-center gap-2.5">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-1.5 text-xs text-slate-700">
                    <span v-if="contextLabel" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ contextLabel }}</span>
                    <span class="text-xs font-extrabold text-[#0D542B]">{{ contextValue }}</span>
                </div>
            </div>
        </div>
    </header>
</template>
