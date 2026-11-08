<script setup>
/**
 * Komponen Visualisasi Word Cloud Interaktif (Tag/Wordle Cloud).
 *
 * Mengolah sekumpulan teks bebas (jawaban terbuka, opsi lainnya, aspirasi, saran)
 * menjadi awan kata dinamis berbasis SVG vektor resolusi tinggi.
 *
 * Fitur Utama:
 * 1. Ekstraksi otomatis kata kunci & eliminasi stop words bahasa Indonesia & Inggris.
 * 2. Tata letak spiral dinamis dengan variasi rotasi horizontal dan vertikal ala Wordle.
 * 3. Skala ukuran font proporsional dengan frekuensi kemunculan kata.
 * 4. Palet warna harmonis UKDW (hijau institusi, emerald, slate, amber, terracotta).
 * 5. Interaksi hover dengan tooltip frekuensi dan klik untuk filter inline seketika.
 */
import { computed, ref, watch } from 'vue';

const props = defineProps({
    // Sumber data: bisa berupa array string teks, array objek record, atau array kata terformat
    texts: { type: Array, default: () => [] },
    records: { type: Array, default: () => [] },
    words: { type: Array, default: () => [] },
    // Dimensi kontainer visualisasi
    height: { type: Number, default: 380 },
    // Kata yang sedang terpilih untuk cross-filtering
    selectedWord: { type: String, default: '' },
    // Judul & deskripsi visualisasi
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    emptyText: { type: String, default: 'Belum ada data teks untuk ditampilkan dalam word cloud.' },
    // Batas maksimal kata unik yang ditampilkan
    maxWords: { type: Number, default: 150 },
});

const emit = defineEmits(['select', 'reset']);

// State lokal kata yang dipilih dan tooltip
const activeWord = ref(props.selectedWord || '');
const hoveredWord = ref(null);
const tooltipPos = ref({ x: 0, y: 0 });
const containerRef = ref(null);
const searchQuery = ref('');

watch(() => props.selectedWord, (newVal) => {
    activeWord.value = newVal || '';
});

// Daftar stop words bahasa Indonesia & Inggris yang diabaikan dalam ekstraksi kata
const stopWords = new Set([
    // Kata sambung, preposisi, kata ganti Bahasa Indonesia
    'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'pada', 'dengan', 'ini', 'itu',
    'atau', 'karena', 'adalah', 'agar', 'bisa', 'sudah', 'saya', 'kami', 'kita',
    'mereka', 'juga', 'lebih', 'sangat', 'akan', 'ada', 'tidak', 'dapat', 'hanya',
    'dalam', 'hal', 'oleh', 'maka', 'bagi', 'tentang', 'seperti', 'sebagai', 'banyak',
    'harus', 'tetapi', 'namun', 'masih', 'belum', 'lagi', 'supaya', 'ketika', 'saat',
    'secara', 'antara', 'tanpa', 'lainnya', 'lain', 'dll', 'dsb', 'dst', 'ia', 'dia',
    'kamu', 'anda', 'kalian', 'apa', 'siapa', 'dimana', 'kapan', 'mengapa', 'bagaimana',
    'tersebut', 'setiap', 'selalu', 'sering', 'terkadang', 'jarang', 'pernah', 'selama',
    'setelah', 'sebelum', 'hingga', 'sampai', 'sejak', 'sehingga', 'meskipun', 'walaupun',
    'adanya', 'menjadi', 'merupakan', 'yaitu', 'yakni', 'ialah', 'pun', 'saja', 'hanya',
    'pula', 'tentu', 'pasti', 'agak', 'cukup', 'amat', 'terlalu', 'paling', 'kurang',
    'sedikit', 'bila', 'jika', 'kalau', 'supaya', 'biar', 'mohon', 'tolong', 'silakan',
    'baik', 'bagus', 'terima', 'kasih', 'makasih', 'halo', 'hai', 'hehe', 'ya', 'tidak',
    // Kata umum Bahasa Inggris
    'the', 'and', 'to', 'of', 'in', 'for', 'on', 'with', 'is', 'at', 'by', 'from',
    'as', 'it', 'an', 'are', 'this', 'that', 'be', 'or', 'was', 'have', 'has', 'had',
    'not', 'but', 'what', 'all', 'were', 'when', 'we', 'there', 'can', 'so', 'said',
]);

// Palet warna harmonis UKDW (berakar pada hijau UKDW #0D542B dan aksen penyeimbang hangat)
const palette = [
    '#0D542B', // Hijau Institusi Utama
    '#15803D', // Hijau Daun
    '#059669', // Emerald
    '#D97706', // Amber Emas
    '#B45309', // Amber Tua
    '#C2410C', // Oranye Terracotta
    '#334155', // Slate Tegas
    '#0E7490', // Teal Dalam
    '#1E6B37', // Hijau Lumut
    '#475569', // Slate Netral
];

// Helper ekstraksi kata dari teks
const extractWordsFromTexts = (textList) => {
    const frequencyMap = new Map();

    textList.forEach((raw) => {
        if (!raw || typeof raw !== 'string') return;

        // Bersihkan prefix opsi jika ada (misal "Lainnya: ")
        let clean = raw.replace(/^(?:lainnya|other)\s*:\s*/i, ' ');
        // Ganti tanda baca dengan spasi
        clean = clean.replace(/[^a-zA-Z0-9\s-]/g, ' ').toLowerCase();

        const tokens = clean.split(/\s+/).filter((t) => t.length >= 3 && !/^\d+$/.test(t));

        tokens.forEach((token) => {
            if (stopWords.has(token)) return;
            const count = frequencyMap.get(token) || 0;
            frequencyMap.set(token, count + 1);
        });
    });

    const list = [];
    frequencyMap.forEach((count, text) => {
        list.push({ text, count, value: count });
    });

    return list.sort((a, b) => b.count - a.count);
};

// Hitung daftar kata beserta frekuensinya
const processedWords = computed(() => {
    if (props.words && props.words.length > 0) {
        return [...props.words].map((w) => ({
            text: w.text || w.label || w.name,
            count: Number(w.count || w.value || 1),
            value: Number(w.value || w.count || 1),
        })).sort((a, b) => b.count - a.count);
    }

    const allTexts = [];
    if (props.texts && props.texts.length > 0) {
        allTexts.push(...props.texts);
    }
    if (props.records && props.records.length > 0) {
        props.records.forEach((r) => {
            const val = r.answer || r.jawaban_label || r.jawaban_teks || r.text || r.catatan;
            if (typeof val === 'string' && val.trim() !== '') {
                allTexts.push(val);
            }
        });
    }

    return extractWordsFromTexts(allTexts);
});

// Filter kata berdasarkan pencarian pengguna (cari ke seluruh repositori kata sebelum dibatasi maxWords)
const filteredWords = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return processedWords.value.slice(0, props.maxWords);
    }
    return processedWords.value.filter((w) => w.text.toLowerCase().includes(q)).slice(0, props.maxWords);
});

// Hitung tata letak visual Word Cloud (koordinat X, Y, rotasi, ukuran font)
const layoutWords = computed(() => {
    const list = filteredWords.value;
    if (list.length === 0) return [];

    const width = 800;
    const height = 400;
    const centerX = width / 2;
    const centerY = height / 2;

    const maxCount = Math.max(...list.map((w) => w.count), 1);
    const minCount = Math.min(...list.map((w) => w.count), 1);

    // Bounding boxes dari kata-kata yang sudah ditempatkan
    const placedBoxes = [];

    // Helper uji tabrakan dua kotak
    const intersects = (b1, b2) => {
        return !(
            b1.x + b1.w < b2.x ||
            b1.x > b2.x + b2.w ||
            b1.y + b1.h < b2.y ||
            b1.y > b2.y + b2.h
        );
    };

    return list.map((item, index) => {
        // Rentang ukuran font kontras & pembeda visual tajam sesuai permintaan:
        // Semakin banyak frekuensinya -> semakin BESAR dan TEBAL (hingga 62px, font-black 900)
        // Semakin sedikit frekuensinya -> semakin KECIL dan LEMBUT (11px - 14px, font-medium 500)
        const ratio = maxCount === minCount ? 1 : (item.count - minCount) / (maxCount - minCount);

        let fontSize;
        let fontWeight;
        let color;
        let tierLabel;

        if (index === 0) {
            // Peringkat 1 (paling dominan): tampil paling besar di tengah
            fontSize = Math.min(62, Math.max(48, Math.round(48 + ratio * 14)));
            fontWeight = '900';
            color = '#0D542B'; // Hijau UKDW Primer Sangat Tegas
            tierLabel = 'Topik Paling Dominan';
        } else if (index < 3) {
            // Peringkat 2 - 3: sangat besar & kontras
            fontSize = Math.min(46, Math.max(34, Math.round(34 + ratio * 12)));
            fontWeight = '900';
            color = index === 1 ? '#C2410C' : '#15803D'; // Terracotta Hangat / Hijau Daun
            tierLabel = 'Topik Utama';
        } else if (index < 8) {
            // Peringkat 4 - 7: frekuensi tinggi
            fontSize = Math.round(24 + Math.pow(ratio, 1.1) * 10); // 24px - 34px
            fontWeight = '800';
            color = ['#D97706', '#0E7490', '#0D542B', '#B45309', '#059669'][index % 5];
            tierLabel = 'Frekuensi Tinggi';
        } else if (index < 20) {
            // Peringkat 8 - 19: frekuensi sedang
            fontSize = Math.round(15 + Math.pow(ratio, 1.4) * 7); // 15px - 21px
            fontWeight = '700';
            color = ['#334155', '#475569', '#166534', '#9A3412', '#0F766E'][index % 5];
            tierLabel = 'Frekuensi Sedang';
        } else {
            // Peringkat 20 ke atas (frekuensi sedikit: 1 - 2 responden): ukuran kecil & warna netral lembut
            fontSize = Math.round(11 + Math.pow(ratio, 1.8) * 3); // 11px - 13px
            fontWeight = '500';
            color = ['#64748B', '#94A3B8', '#78716C', '#6B7280'][index % 4];
            tierLabel = 'Frekuensi Rendah';
        }

        // Rotasi: 80% horizontal (0°), 20% vertikal (-90° atau 90°)
        // Kata top 3 selalu horizontal agar fokus utama jelas terbaca
        let rotate = 0;
        if (index >= 3 && (index % 5 === 2 || index % 7 === 3)) {
            rotate = index % 2 === 0 ? -90 : 90;
        }

        // Estimasi dimensi kotak teks berdasarkan panjang kata dan ukuran font
        const textLen = item.text.length;
        const approxCharWidth = fontSize * 0.58;
        const boxWidth = rotate === 0 ? textLen * approxCharWidth : fontSize * 1.25;
        const boxHeight = rotate === 0 ? fontSize * 1.25 : textLen * approxCharWidth;

        // Penempatan spiral Archimedes (r = a * theta)
        let posX = centerX;
        let posY = centerY;
        let placed = false;

        if (index === 0) {
            posX = centerX;
            posY = centerY;
            placed = true;
        } else {
            const step = 0.38;
            const spiralGrowth = 3.6;
            let theta = (index * 1.35) % (Math.PI * 2);
            let radius = 10;

            const marginX = 20;
            const marginY = 18;

            for (let stepIdx = 0; stepIdx < 450; stepIdx++) {
                radius = spiralGrowth * theta;
                // Sedikit elips mendatar agar sesuai proporsi layar (16:9 / 2:1)
                const currentX = centerX + Math.cos(theta) * radius * 1.35;
                const currentY = centerY + Math.sin(theta) * radius * 0.85;

                const candidateBox = {
                    x: currentX - boxWidth / 2,
                    y: currentY - boxHeight / 2,
                    w: boxWidth + 6,
                    h: boxHeight + 4,
                };

                // Pastikan berada di dalam batas kanvas yang aman tanpa terpotong
                const insideBounds =
                    candidateBox.x >= marginX &&
                    candidateBox.x + candidateBox.w <= width - marginX &&
                    candidateBox.y >= marginY &&
                    candidateBox.y + candidateBox.h <= height - marginY;

                if (insideBounds) {
                    const hasCollision = placedBoxes.some((box) => intersects(box, candidateBox));
                    if (!hasCollision) {
                        posX = currentX;
                        posY = currentY;
                        placed = true;
                        break;
                    }
                }

                theta += step;
            }
        }

        // Jika spiral penuh, posisikan dengan offset deterministik yang dijamin aman di dalam batas kanvas
        if (!placed) {
            const angle = (index / list.length) * Math.PI * 2;
            const halfW = boxWidth / 2 + 18;
            const halfH = boxHeight / 2 + 15;
            const maxRadiusX = Math.max(40, centerX - halfW);
            const maxRadiusY = Math.max(30, centerY - halfH);
            const rRatio = 0.45 + ((index % 6) * 0.08);

            posX = Math.min(width - halfW, Math.max(halfW, centerX + Math.cos(angle) * (maxRadiusX * rRatio)));
            posY = Math.min(height - halfH, Math.max(halfH, centerY + Math.sin(angle) * (maxRadiusY * rRatio)));
        }

        placedBoxes.push({
            x: posX - boxWidth / 2,
            y: posY - boxHeight / 2,
            w: boxWidth + 6,
            h: boxHeight + 4,
        });

        return {
            text: item.text,
            count: item.count,
            x: posX,
            y: posY,
            boxX: posX - boxWidth / 2,
            boxY: posY - boxHeight / 2,
            boxW: boxWidth + 8,
            boxH: boxHeight + 6,
            fontSize,
            fontWeight,
            rotate,
            color,
            tierLabel,
            isTop: index < 5,
        };
    });
});

// Penanganan hover kata untuk menampilkan tooltip presisi anti-potong
const handleWordHover = (event, word) => {
    hoveredWord.value = word;
    if (containerRef.value) {
        const rect = containerRef.value.getBoundingClientRect();
        const rawX = event.clientX - rect.left;
        const rawY = event.clientY - rect.top;

        // Cegah tooltip terpotong di tepi kiri/kanan kartu
        const clampedX = Math.min(rect.width - 130, Math.max(130, rawX));

        // Auto-flip: Jika kursor berada di 75px teratas kartu, tampilkan tooltip di bawah kursor
        // Jika kursor berada lebih ke bawah, tampilkan di atas kursor
        const isNearTop = rawY < 75;

        tooltipPos.value = {
            x: clampedX,
            y: isNearTop ? rawY + 18 : rawY - 12,
            placement: isNearTop ? 'bottom' : 'top',
        };
    }
};

const handleWordLeave = () => {
    hoveredWord.value = null;
};

// Penanganan klik kata untuk cross-filtering
const handleWordClick = (word) => {
    if (activeWord.value === word.text) {
        activeWord.value = '';
        emit('reset');
    } else {
        activeWord.value = word.text;
        emit('select', word.text);
    }
};

// Reset seleksi kata
const resetWordFilter = () => {
    activeWord.value = '';
    emit('reset');
};
</script>

<template>
    <div ref="containerRef" class="relative flex flex-col rounded-xl border border-slate-200 bg-white p-4 transition-all">
        <!-- Header Word Cloud & Toolbar -->
        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3">
            <div>
                <h4 v-if="title" class="text-sm font-bold text-slate-900">{{ title }}</h4>
                <p v-if="subtitle" class="text-xs text-slate-500">{{ subtitle }}</p>
                <p v-else class="text-xs text-slate-500">
                    Menampilkan <strong class="text-slate-800">{{ layoutWords.length }}</strong> kata kunci paling sering muncul (ukuran proporsional frekuensi).
                </p>
            </div>

            <!-- Toolbar Pencarian Kata & Indikator Kata Aktif -->
            <div class="flex flex-wrap items-center gap-2">
                <div v-if="activeWord" class="flex items-center gap-1.5 rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs">
                    <span class="text-slate-600">Kata terpilih:</span>
                    <strong class="text-[#0D542B] font-bold">{{ activeWord }}</strong>
                    <button
                        type="button"
                        class="ml-1 text-slate-400 hover:text-slate-700 font-bold cursor-pointer"
                        title="Hapus filter kata"
                        @click="resetWordFilter"
                    >
                        ×
                    </button>
                </div>

                <div class="relative">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari kata kunci..."
                        class="w-36 sm:w-44 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-800 placeholder-slate-400 outline-none focus:border-[#0D542B] focus:bg-white focus:ring-1 focus:ring-[#0D542B]"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute right-2 top-1 text-xs text-slate-400 hover:text-slate-600 cursor-pointer"
                        @click="searchQuery = ''"
                    >
                        ×
                    </button>
                </div>
            </div>
        </div>

        <!-- Kanvas SVG Word Cloud (Bebas overflow-hidden agar elemen SVG & margin tidak terpotong) -->
        <div v-if="layoutWords.length > 0" class="relative w-full flex items-center justify-center bg-radial from-slate-50/60 to-transparent rounded-lg">
            <svg
                viewBox="0 0 800 400"
                class="w-full h-auto select-none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <!-- Render Tiap Kata dengan Hit Target Stabil Bebas Jitter -->
                <g
                    v-for="word in layoutWords"
                    :key="word.text"
                    class="cursor-pointer word-cloud-group"
                    @mouseenter="handleWordHover($event, word)"
                    @mouseleave="handleWordLeave"
                    @click="handleWordClick(word)"
                >
                    <!-- Background Pill Saat Kata Aktif Dipilih -->
                    <rect
                        v-if="activeWord && activeWord.toLowerCase() === word.text.toLowerCase()"
                        :x="word.boxX"
                        :y="word.boxY"
                        :width="word.boxW"
                        :height="word.boxH"
                        rx="6"
                        ry="6"
                        fill="#ECFDF5"
                        stroke="#0D542B"
                        stroke-width="1.5"
                    />

                    <!-- Hit-Box Transparan untuk Target Klik yang Stabil (Mencegah Kursor Keluar-Masuk Antarhuruf) -->
                    <rect
                        :x="word.boxX"
                        :y="word.boxY"
                        :width="word.boxW"
                        :height="word.boxH"
                        fill="transparent"
                    />

                    <!-- Elemen Teks Vektor Statis (Bebas dari Lonjakan Ukuran Font/Transform) -->
                    <text
                        :x="word.x"
                        :y="word.y"
                        :font-size="word.fontSize"
                        :fill="word.color"
                        :transform="word.rotate !== 0 ? `rotate(${word.rotate}, ${word.x}, ${word.y})` : undefined"
                        text-anchor="middle"
                        dominant-baseline="central"
                        font-family="system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"
                        :font-weight="word.fontWeight"
                        :letter-spacing="word.isTop ? '-0.5px' : '0'"
                        class="transition-opacity duration-150 select-none"
                        :class="[
                            activeWord && activeWord.toLowerCase() === word.text.toLowerCase()
                                ? 'opacity-100'
                                : activeWord
                                ? 'opacity-30 hover:opacity-90'
                                : 'opacity-90 hover:opacity-100'
                        ]"
                    >
                        {{ word.text }}
                    </text>
                </g>
            </svg>
        </div>

        <!-- Empty State HANYA jika tidak ada data kata yang valid -->
        <div v-else class="flex flex-col items-center justify-center p-10 text-center rounded-lg bg-slate-50 border border-dashed border-slate-200" :style="{ minHeight: `${height}px` }">
            <p class="text-sm font-semibold text-slate-700">{{ emptyText }}</p>
            <p class="mt-1 text-xs text-slate-500">Kata kunci akan otomatis terbentang ketika responden mengisi opsi ini.</p>
        </div>

        <!-- Tooltip Interaktif Hover di Level Kartu (Bebas Potongan dan Mendukung Auto-Flip Atas/Bawah) -->
        <div
            v-if="hoveredWord"
            class="pointer-events-none absolute z-50 -translate-x-1/2 rounded-md border border-slate-700 bg-slate-900/95 px-2.5 py-1.5 text-xs text-white shadow-xl backdrop-blur-xs transition-opacity duration-150"
            :class="tooltipPos.placement === 'top' ? '-translate-y-full' : ''"
            :style="{ left: `${tooltipPos.x}px`, top: `${tooltipPos.y}px` }"
        >
            <div class="font-bold flex items-center gap-1.5">
                <span>{{ hoveredWord.text }}</span>
                <span class="rounded bg-emerald-500/30 px-1.5 py-0.5 text-[10px] text-emerald-300 font-extrabold">
                    {{ hoveredWord.count }} kali
                </span>
                <span class="text-[10px] text-slate-300 font-normal">({{ hoveredWord.tierLabel }})</span>
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">Klik kata untuk memfilter rincian responden</div>
        </div>

        <!-- Legenda Pembeda Skala Frekuensi & Keterangan Interaksi -->
        <div class="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-2.5 text-[11px] text-slate-500">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <!-- Legenda Skala Ukuran & Warna -->
                <div class="flex flex-wrap items-center gap-3">
                    <span class="font-medium text-slate-600">Pembeda Ukuran:</span>
                    <div class="flex items-center gap-1">
                        <span class="inline-block h-3 w-3 rounded-xs bg-[#0D542B]"></span>
                        <span class="text-[10px] font-bold text-slate-700">Dominan (Paling Besar)</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="inline-block h-2.5 w-2.5 rounded-xs bg-[#C2410C]"></span>
                        <span class="text-[10px] font-semibold text-slate-700">Topik Utama</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="inline-block h-2 w-2 rounded-xs bg-[#0E7490]"></span>
                        <span class="text-[10px] text-slate-600">Sedang</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="inline-block h-1.5 w-1.5 rounded-xs bg-[#94A3B8]"></span>
                        <span class="text-[10px] text-slate-500">Rendah (Paling Kecil)</span>
                    </div>
                </div>

                <span v-if="activeWord" class="font-bold text-[#0D542B]">
                    Menyaring respon yang memuat kata "{{ activeWord }}"
                </span>
            </div>
            <div class="text-[10px] text-slate-400">
                Tip: Klik kata dalam Word Cloud untuk menyaring langsung tabel rincian responden di bawahnya.
            </div>
        </div>
    </div>
</template>

<style scoped>
.word-cloud-group {
    user-select: none;
}
.word-cloud-group:hover text {
    opacity: 1;
}
</style>

