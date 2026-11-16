<script setup>
/**
 * Kontrol pagination terpadu untuk tabel server-side Laravel dan tabel lokal.
 * Komponen ini menjaga label, format ringkasan, dan jumlah tombol halaman tetap
 * konsisten tanpa merender label HTML mentah dari paginator backend.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

// Kontrak payload paginator server dan state pagination lokal.
const props = defineProps({
    pagination: { type: Object, default: () => ({}) },
    currentPage: { type: Number, default: 1 },
    total: { type: Number, default: 0 },
    pageSize: { type: Number, default: 10 },
    summaryLabel: { type: String, default: 'data' },
    showSummary: { type: Boolean, default: true },
});

// Event perubahan halaman untuk pagination lokal; pagination server memakai URL dari Laravel.
const emit = defineEmits(['update:currentPage']);

// Mendeteksi apakah payload berasal dari paginator Laravel.
const isServerPagination = computed(() => Array.isArray(props.pagination?.links));

// Menentukan halaman aktif dari paginator server atau state lokal.
const activePage = computed(() => Math.max(1, Number(isServerPagination.value ? props.pagination?.current_page : props.currentPage) || 1));

// Menentukan jumlah halaman dan total data dari sumber yang sedang dipakai.
const totalPages = computed(() => {
    if (isServerPagination.value) {
        return Math.max(1, Number(props.pagination?.last_page) || 1);
    }

    return Math.max(1, Math.ceil((Number(props.total) || 0) / Math.max(1, Number(props.pageSize) || 1)));
});

// Mengambil rentang data yang sedang terlihat untuk ringkasan pagination.
const range = computed(() => {
    if (isServerPagination.value) {
        return {
            from: Number(props.pagination?.from) || 0,
            to: Number(props.pagination?.to) || 0,
            total: Number(props.pagination?.total) || 0,
        };
    }

    const total = Number(props.total) || 0;
    const pageSize = Math.max(1, Number(props.pageSize) || 1);

    return {
        from: total > 0 ? ((activePage.value - 1) * pageSize) + 1 : 0,
        to: total > 0 ? Math.min(activePage.value * pageSize, total) : 0,
        total,
    };
});

// Menyusun nomor halaman ringkas agar paginator panjang tidak memenuhi layar.
const pageItems = computed(() => {
    const lastPage = totalPages.value;

    if (lastPage <= 7) {
        return Array.from({ length: lastPage }, (_, index) => index + 1);
    }

    const pages = new Set([1, lastPage, activePage.value - 1, activePage.value, activePage.value + 1]);

    if (activePage.value <= 3) {
        pages.add(2);
        pages.add(3);
        pages.add(4);
    }

    if (activePage.value >= lastPage - 2) {
        pages.add(lastPage - 1);
        pages.add(lastPage - 2);
        pages.add(lastPage - 3);
    }

    const orderedPages = [...pages].filter((page) => page >= 1 && page <= lastPage).sort((a, b) => a - b);
    const items = [];

    orderedPages.forEach((page, index) => {
        if (index > 0 && page - orderedPages[index - 1] > 1) {
            items.push(`ellipsis-${page}`);
        }

        items.push(page);
    });

    return items;
});

// Mengambil link numerik paginator server berdasarkan nomor halaman.
const serverPageLinks = computed(() => {
    const links = new Map();

    (props.pagination?.links || []).forEach((link) => {
        const page = Number.parseInt(String(link.label || '').replace(/[^0-9]/g, ''), 10);

        if (Number.isInteger(page) && page > 0) {
            links.set(page, link);
        }
    });

    return links;
});

// Mengambil link sebelumnya atau berikutnya tanpa mempertahankan label Inggris dari backend.
const directionalLink = (direction) => {
    const labelMatcher = direction === 'previous' ? /previous|sebelum/i : /next|berikut/i;
    const link = (props.pagination?.links || []).find((item) => labelMatcher.test(String(item.label || '')));

    if (link) {
        return link;
    }

    return {
        url: direction === 'previous' ? props.pagination?.prev_page_url : props.pagination?.next_page_url,
        active: false,
    };
};

// Memproses klik halaman lokal dengan batas aman agar tidak melewati halaman tersedia.
const goToLocalPage = (page) => {
    const nextPage = Math.min(Math.max(1, page), totalPages.value);

    if (nextPage !== activePage.value) {
        emit('update:currentPage', nextPage);
    }
};
</script>

<template>
    <nav
        v-if="totalPages > 1"
        class="flex flex-col gap-3 border-t border-slate-200 pt-3 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between"
        aria-label="Navigasi halaman tabel"
    >
        <span v-if="showSummary">
            Menampilkan <strong class="font-semibold text-slate-800">{{ range.from }}–{{ range.to }}</strong>
            dari <strong class="font-semibold text-slate-800">{{ range.total }}</strong> {{ summaryLabel }}
        </span>

        <div class="flex flex-wrap items-center gap-1.5" :class="{ 'sm:ml-auto': !showSummary }">
            <template v-if="isServerPagination">
                <Link
                    v-if="directionalLink('previous').url"
                    :href="directionalLink('previous').url"
                    preserve-scroll
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                >
                    Sebelumnya
                </Link>
                <span
                    v-else
                    class="cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 font-semibold text-slate-400"
                >
                    Sebelumnya
                </span>

                <template v-for="item in pageItems" :key="item">
                    <span v-if="typeof item === 'string'" class="px-1.5 text-slate-400">…</span>
                    <Link
                        v-else-if="serverPageLinks.get(item)?.url"
                        :href="serverPageLinks.get(item).url"
                        preserve-scroll
                        class="min-w-8 rounded-lg border px-2.5 py-1.5 text-center font-semibold transition-colors"
                        :class="serverPageLinks.get(item).active ? 'border-[#0D542B] bg-[#0D542B] text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
                    >
                        {{ item }}
                    </Link>
                    <span
                        v-else
                        class="min-w-8 rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1.5 text-center font-semibold text-slate-400"
                    >
                        {{ item }}
                    </span>
                </template>

                <Link
                    v-if="directionalLink('next').url"
                    :href="directionalLink('next').url"
                    preserve-scroll
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                >
                    Selanjutnya
                </Link>
                <span
                    v-else
                    class="cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 font-semibold text-slate-400"
                >
                    Selanjutnya
                </span>
            </template>

            <template v-else>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                    :disabled="activePage <= 1"
                    @click="goToLocalPage(activePage - 1)"
                >
                    Sebelumnya
                </button>
                <template v-for="item in pageItems" :key="item">
                    <span v-if="typeof item === 'string'" class="px-1.5 text-slate-400">…</span>
                    <button
                        v-else
                        type="button"
                        class="min-w-8 rounded-lg border px-2.5 py-1.5 text-center font-semibold transition-colors"
                        :class="item === activePage ? 'border-[#0D542B] bg-[#0D542B] text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
                        @click="goToLocalPage(item)"
                    >
                        {{ item }}
                    </button>
                </template>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                    :disabled="activePage >= totalPages"
                    @click="goToLocalPage(activePage + 1)"
                >
                    Selanjutnya
                </button>
            </template>
        </div>
    </nav>
</template>
