<!--
  Halaman: Pengaturan Kuesioner Universitas (Super Admin)
  File: resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue

  Desain Standar Enterprise UKDW:
  - Tampilan seragam dan rapi persis seperti Pengaturan Kuesioner Program Studi.
  - Multi-tab terpadu:
    1. Daftar Bagian (Section)
    2. Pertanyaan Kuesioner (Tracer)
    3. Pertanyaan Profil Alumni
    4. Kelola Kuesioner Induk
  - Bebas Emoticon / Emojis (Menggunakan SVG Icons Resmi & Bahasa Formal).
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import Sidebar from '../Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    subpertanyaans: {
        type: Array,
        default: () => [],
    },
    sections: {
        type: Array,
        default: () => [],
    },
    kuesioners: {
        type: Array,
        default: () => [],
    },
    activeKuesioner: {
        type: Object,
        default: () => ({}),
    },
    prodis: {
        type: Array,
        default: () => [],
    },
    availableJumpTargets: {
        type: Array,
        default: () => [],
    },
    targetQuestionMap: {
        type: Object,
        default: () => ({}),
    },
    initialTab: {
        type: String,
        default: 'sections',
    },
    stats: {
        type: Object,
        default: () => ({
            total_kuesioners: 0,
            total_sections: 0,
            total_tracer_questions: 0,
            total_profile_questions: 0,
            total_questions: 0,
        }),
    },
});

// ========================================================
// HELPER LOKASI TAMPIL
// ========================================================
const isProfileTarget = (target) => target === 'profil' || target === 'profile';
const isBothTarget = (target) => target === 'both';
const isTracerTarget = (target) => target === 'kuesioner' || (!target && !isProfileTarget(target));

const getTargetBadge = (target) => {
    if (isProfileTarget(target)) {
        return {
            label: 'Profil Alumni',
            classes: 'text-gray-600',
        };
    }
    if (isBothTarget(target)) {
        return {
            label: 'Kuesioner & Profil',
            classes: 'text-gray-600',
        };
    }
    return {
        label: 'Kuesioner Tracer',
        classes: 'text-gray-600',
    };
};

// ========================================================
// TAB AKTIF STATE ('sections', 'tracer', 'profile', 'kuesioner')
// ========================================================
const getInitialTab = () => {
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        const t = urlParams.get('tab');
        if (t && ['sections', 'tracer', 'questions', 'profile', 'kuesioner'].includes(t)) {
            if (t === 'questions') return 'tracer';
            return t;
        }
    }
    return props.initialTab === 'questions' ? 'tracer' : (props.initialTab || 'sections');
};

const activeTab = ref(getInitialTab());

const switchTab = (tabName) => {
    activeTab.value = tabName;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url);
    }
};

// ========================================================
// 1. SECTION STATE & FILTERS
// ========================================================
const sectionSearchQuery = ref('');
const filteredSections = computed(() => {
    if (!sectionSearchQuery.value.trim()) return props.sections;
    const q = sectionSearchQuery.value.toLowerCase();
    return props.sections.filter(s =>
        s.title?.toLowerCase().includes(q) ||
        s.description?.toLowerCase().includes(q) ||
        s.order?.toString().includes(q)
    );
});

// ========================================================
// 2. QUESTION TYPES
// ========================================================
const typeLabels = {
    radio: 'Pilihan Ganda (Radio)',
    single_choice: 'Pilihan Ganda (Radio)',
    radio_text: 'Pilihan Ganda + Lainnya (Radio + Teks)',
    radio_input: 'Radio + Angka (Bulan)',
    multiple_choice: 'Kotak Centang (Checkbox)',
    checkbox: 'Kotak Centang (Checkbox)',
    dropdown: 'Pilihan Dropdown (Select)',
    searchable_select: 'Pencarian Dropdown',
    rating_5: 'Skala Rating (1-5)',
    multiple_number: 'Rincian Nominal / Gaji (Take Home Pay)',
    matrix: 'Matriks Skala',
    matrix_dual: 'Dual Matrix',
    multiple_textbox: 'Multiple Textbox',
    text: 'Jawaban Singkat (Text)',
    textarea: 'Paragraf (Textarea)',
    number: 'Isian Angka (Number)',
    date: 'Tanggal',
    time: 'Waktu',
    file: 'Unggah Berkas (File)',
    header: 'Header / Judul Bagian',
};

const univSelectableTypes = {
    radio: 'Pilihan Ganda (Radio)',
    radio_text: 'Pilihan Ganda + Lainnya (Radio + Teks)',
    radio_input: 'Radio + Angka (Bulan)',
    multiple_choice: 'Kotak Centang (Checkbox)',
    dropdown: 'Pilihan Dropdown (Select)',
    searchable_select: 'Pencarian Dropdown',
    rating_5: 'Skala Rating (1-5)',
    multiple_number: 'Rincian Nominal / Gaji (Take Home Pay)',
    text: 'Jawaban Singkat (Text)',
    textarea: 'Paragraf (Textarea)',
    number: 'Isian Angka (Number)',
    date: 'Tanggal',
    time: 'Waktu',
    file: 'Unggah Berkas (File)',
    header: 'Header / Judul Bagian',
};

const typeInstructions = {
    radio: 'Pilih salah satu jawaban.',
    single_choice: 'Pilih salah satu jawaban.',
    radio_text: 'Pilih salah satu jawaban. Jika memilih lainnya, tuliskan jawaban.',
    radio_input: 'Pilih salah satu jawaban dan isi angka jika diperlukan.',
    multiple_choice: 'Jawaban dapat lebih dari satu.',
    checkbox: 'Jawaban dapat lebih dari satu.',
    dropdown: 'Pilih salah satu jawaban.',
    searchable_select: 'Cari dan pilih salah satu jawaban.',
    rating_5: 'Pilih satu nilai pada skala 1 sampai 5.',
    multiple_number: 'Isi nominal pada setiap komponen yang sesuai.',
    matrix: 'Pilih satu jawaban pada setiap baris.',
    matrix_dual: 'Berikan penilaian pada setiap pernyataan.',
    multiple_textbox: 'Isi jawaban pada kolom yang tersedia.',
    text: 'Isi jawaban singkat.',
    textarea: 'Isi jawaban pada kolom yang tersedia.',
    number: 'Isi jawaban dalam angka.',
    date: 'Pilih tanggal yang sesuai.',
    time: 'Pilih waktu yang sesuai.',
    file: 'Unggah berkas sesuai petunjuk.',
};

const getTypeInstruction = (type) => typeInstructions[type] || '';

const isAutomaticInstruction = (value) => [
    ...Object.values(typeInstructions),
    'Pilih satu jawaban',
    'Jawaban bisa lebih dari satu',
].includes(value);

const OPTION_SUPPORTED_TYPES = [
    'radio',
    'single_choice',
    'radio_input',
    'radio_text',
    'multiple_choice',
    'checkbox',
    'dropdown',
    'searchable_select',
    'rating_5',
    'multiple_number',
    'matrix',
    'matrix_dual',
    'multiple_textbox',
];

const canHaveOptions = (type) => OPTION_SUPPORTED_TYPES.includes(type);

// ========================================================
// 3. TAB TRACER QUESTIONS STATE & FILTERS
// ========================================================
const selectedTracerSectionFilter = ref('all');
const selectedTracerRequiredFilter = ref('all');
const selectedTracerTargetFilter = ref('all');
const tracerSearchQuery = ref('');

const filteredTracerQuestions = computed(() => {
    return props.subpertanyaans.filter((q) => {
        // Tampilkan hanya pertanyaan tracer (kuesioner atau both)
        if (isProfileTarget(q.tampil_di)) return false;

        if (selectedTracerSectionFilter.value !== 'all') {
            const secId = parseInt(selectedTracerSectionFilter.value, 10);
            if (q.kelompok_pertanyaan_id !== secId) return false;
        }

        if (selectedTracerRequiredFilter.value !== 'all') {
            const isReq = selectedTracerRequiredFilter.value === 'wajib';
            if (Boolean(q.wajib) !== isReq) return false;
        }

        if (selectedTracerTargetFilter.value !== 'all') {
            if (selectedTracerTargetFilter.value === 'kuesioner' && !isTracerTarget(q.tampil_di)) return false;
            if (selectedTracerTargetFilter.value === 'both' && !isBothTarget(q.tampil_di)) return false;
        }

        if (tracerSearchQuery.value.trim()) {
            const query = tracerSearchQuery.value.toLowerCase();
            const matchCode = q.kode_pertanyaan?.toLowerCase().includes(query);
            const matchText = q.subpertanyaan?.toLowerCase().includes(query);
            const matchType = (typeLabels[q.type] || q.type)?.toLowerCase().includes(query);
            return matchCode || matchText || matchType;
        }

        return true;
    });
});

// ========================================================
// 4. TAB PROFILE QUESTIONS STATE & FILTERS
// ========================================================
const selectedProfileSectionFilter = ref('all');
const selectedProfileRequiredFilter = ref('all');
const selectedProfileTargetFilter = ref('all');
const profileSearchQuery = ref('');

const filteredProfileQuestions = computed(() => {
    return props.subpertanyaans.filter((q) => {
        // Tampilkan hanya pertanyaan profil alumni (profil atau both)
        if (!isProfileTarget(q.tampil_di) && !isBothTarget(q.tampil_di)) return false;

        if (selectedProfileSectionFilter.value !== 'all') {
            const secId = parseInt(selectedProfileSectionFilter.value, 10);
            if (q.kelompok_pertanyaan_id !== secId) return false;
        }

        if (selectedProfileRequiredFilter.value !== 'all') {
            const isReq = selectedProfileRequiredFilter.value === 'wajib';
            if (Boolean(q.wajib) !== isReq) return false;
        }

        if (selectedProfileTargetFilter.value !== 'all') {
            if (selectedProfileTargetFilter.value === 'profil' && !isProfileTarget(q.tampil_di)) return false;
            if (selectedProfileTargetFilter.value === 'both' && !isBothTarget(q.tampil_di)) return false;
        }

        if (profileSearchQuery.value.trim()) {
            const query = profileSearchQuery.value.toLowerCase();
            const matchCode = q.kode_pertanyaan?.toLowerCase().includes(query);
            const matchText = q.subpertanyaan?.toLowerCase().includes(query);
            const matchType = (typeLabels[q.type] || q.type)?.toLowerCase().includes(query);
            return matchCode || matchText || matchType;
        }

        return true;
    });
});

// ========================================================
// 5. KUESIONER INDUK STATE & FILTERS
// ========================================================
const kuesionerSearchQuery = ref('');
const filteredKuesioners = computed(() => {
    if (!kuesionerSearchQuery.value.trim()) return props.kuesioners;
    const q = kuesionerSearchQuery.value.toLowerCase();
    return props.kuesioners.filter(k =>
        k.title?.toLowerCase().includes(q) ||
        k.description?.toLowerCase().includes(q) ||
        k.year?.toString().includes(q)
    );
});

// ========================================================
// MODAL HANDLERS: SECTION (TAMBAH / EDIT / REORDER / HAPUS)
// ========================================================
const openSectionModal = (sectionToEdit = null) => {
    const isEdit = !!sectionToEdit;
    const initialKuesionerId = sectionToEdit?.kuesioner_id || (props.kuesioners[0]?.id || '');
    const initialTitle = sectionToEdit?.title || '';
    const initialDesc = sectionToEdit?.description || '';
    const initialOrder = sectionToEdit?.order || (props.sections.length + 1);

    const kuesionerOptionsHtml = props.kuesioners
        .map((k) => `<option value="${k.id}" ${k.id == initialKuesionerId ? 'selected' : ''}>${k.title} (${k.year || '-'})</option>`)
        .join('');

    Swal.fire({
        title: isEdit ? 'Sunting Bagian Kuesioner Universitas' : 'Tambah Bagian Kuesioner Universitas Baru',
        html: `
            <div class="text-left space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Kuesioner Induk *</label>
                    <select id="swal-sec-kuesioner-id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]">
                        ${kuesionerOptionsHtml || '<option value="">-- Buat Kuesioner Induk Dulu --</option>'}
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Judul Bagian (Section Title) *</label>
                    <input id="swal-sec-title" type="text" value="${initialTitle}" placeholder="Contoh: Identitas & Biodata Mahasiswa" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]" />
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Deskripsi / Petunjuk Pengisian (Opsional)</label>
                    <textarea id="swal-sec-desc" rows="3" placeholder="Ketikkan petunjuk pengisian bagi responden..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]">${initialDesc}</textarea>
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Nomor Urut Posisi</label>
                    <input id="swal-sec-order" type="number" value="${initialOrder}" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]" />
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Perubahan' : 'Tambah Bagian',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        preConfirm: () => {
            const kuesioner_id = document.getElementById('swal-sec-kuesioner-id').value;
            const title = document.getElementById('swal-sec-title').value.trim();
            const description = document.getElementById('swal-sec-desc').value.trim();
            const order = parseInt(document.getElementById('swal-sec-order').value, 10) || null;

            if (!title) {
                Swal.showValidationMessage('Judul bagian kuesioner wajib diisi.');
                return false;
            }

            return {
                kuesioner_id,
                title,
                description,
                order,
            };
        },
    }).then((res) => {
        if (res.isConfirmed && res.value) {
            if (isEdit) {
                router.put(`/superadmin/sections/${sectionToEdit.id}`, res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Bagian kuesioner berhasil diperbarui.', 'success'),
                });
            } else {
                router.post('/superadmin/sections', res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Bagian kuesioner baru berhasil ditambahkan.', 'success'),
                });
            }
        }
    });
};

const handleReorderSection = (sectionId, direction) => {
    router.post('/superadmin/sections/reorder', {
        id: sectionId,
        direction,
    }, {
        preserveScroll: true,
    });
};

const handleDeleteSection = (section) => {
    Swal.fire({
        title: 'Hapus Bagian Kuesioner?',
        text: `Apakah Anda yakin ingin menghapus bagian "${section.title}" beserta seluruh butir pertanyaan di dalamnya?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Bagian',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/superadmin/sections/${section.id}`, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Terhapus', 'Bagian kuesioner berhasil dihapus.', 'success'),
            });
        }
    });
};

// ========================================================
// MODAL HANDLERS: QUESTION (TAMBAH / EDIT / REORDER / HAPUS)
// ========================================================
const openQuestionModal = (questionToEdit = null, defaultTarget = 'kuesioner') => {
    const isEdit = !!questionToEdit;
    const initialCode = questionToEdit?.kode_pertanyaan || '';
    const initialText = questionToEdit?.subpertanyaan || '';
    const initialType = questionToEdit?.type || 'radio';
    const initialWajib = questionToEdit?.wajib !== undefined ? questionToEdit.wajib : true;
    const initialTarget = questionToEdit ? (isProfileTarget(questionToEdit.tampil_di) ? 'profil' : (questionToEdit.tampil_di || 'kuesioner')) : defaultTarget;
    const initialKeterangan = questionToEdit?.keterangan || getTypeInstruction(initialType);
    const initialSecId = questionToEdit?.kelompok_pertanyaan_id || (props.sections[0]?.id || '');
    const initialOrder = questionToEdit?.order || (props.subpertanyaans.length + 1);

    const sectionOptions = props.sections.map(s => `<option value="${s.id}" ${initialSecId == s.id ? 'selected' : ''}>Bagian ${s.order}: ${s.title}</option>`).join('');
    const typeOptions = Object.entries(univSelectableTypes).map(([val, label]) => `<option value="${val}" ${initialType === val ? 'selected' : ''}>${label}</option>`).join('');

    Swal.fire({
        title: isEdit ? 'Sunting Pertanyaan' : (defaultTarget === 'profil' ? 'Tambah Pertanyaan Profil Alumni' : 'Tambah Pertanyaan Kuesioner Tracer'),
        html: `
            <div class="text-left space-y-3 text-xs max-h-[72vh] overflow-y-auto pr-1">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Kode Pertanyaan *</label>
                        <input id="swal-q-code" type="text" value="${initialCode}" placeholder="Contoh: F301, F13, BIO_BIDANG_DIVISI" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono font-bold uppercase focus:ring-1 focus:ring-[#0D542B]" />
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Lokasi Tampil *</label>
                        <select id="swal-q-target" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white font-bold text-[#0D542B] focus:ring-1 focus:ring-[#0D542B]">
                            <option value="kuesioner" ${initialTarget === 'kuesioner' ? 'selected' : ''}>Kuesioner Tracer Saja</option>
                            <option value="profil" ${initialTarget === 'profil' || initialTarget === 'profile' ? 'selected' : ''}>Profil Alumni Saja</option>
                            <option value="both" ${initialTarget === 'both' ? 'selected' : ''}>Keduanya (Kuesioner & Profil)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Bagian / Section *</label>
                    <select id="swal-q-sec" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B]">
                        ${sectionOptions || '<option value="">-- Buat Bagian (Section) Dulu --</option>'}
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Butir Pertanyaan *</label>
                    <textarea id="swal-q-text" rows="3" placeholder="Ketik kalimat butir pertanyaan..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B]">${initialText}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Tipe Pertanyaan *</label>
                        <select id="swal-q-type" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B]">
                            ${typeOptions}
                        </select>
                        <p class="text-[10px] text-gray-500 mt-1">Opsi pilihan dibuat otomatis jika tipe pertanyaan membutuhkannya.</p>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Nomor Urut</label>
                        <input id="swal-q-order" type="number" value="${initialOrder}" min="1" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B]" />
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Keterangan / Batasan Nilai (Opsional)</label>
                    <input id="swal-q-keterangan" type="text" value="${initialKeterangan}" placeholder="Contoh: Minimal Rp 1.000, kelipatan ribuan, format tanggal" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B]" />
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <input id="swal-q-req" type="checkbox" ${initialWajib ? 'checked' : ''} class="rounded text-[#0D542B] focus:ring-[#0D542B]" />
                    <label for="swal-q-req" class="font-bold text-gray-700">Wajib diisi oleh alumni responden</label>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Perubahan' : 'Tambah Pertanyaan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        didOpen: () => {
            const typeSelect = document.getElementById('swal-q-type');
            const keteranganInput = document.getElementById('swal-q-keterangan');

            if (!typeSelect || !keteranganInput) {
                return;
            }

            let lastAutoInstruction = getTypeInstruction(typeSelect.value);

            typeSelect.addEventListener('change', () => {
                const nextInstruction = getTypeInstruction(typeSelect.value);
                const currentInstruction = keteranganInput.value.trim();

                if (isAutomaticInstruction(currentInstruction) || currentInstruction === lastAutoInstruction) {
                    keteranganInput.value = nextInstruction;
                }

                lastAutoInstruction = nextInstruction;
            });
        },
        preConfirm: () => {
            const kode_pertanyaan = document.getElementById('swal-q-code').value.trim();
            const subpertanyaan = document.getElementById('swal-q-text').value.trim();
            const type = document.getElementById('swal-q-type').value;
            const kelompok_pertanyaan_id = document.getElementById('swal-q-sec').value;
            const tampil_di = document.getElementById('swal-q-target').value;
            const order = parseInt(document.getElementById('swal-q-order').value, 10) || null;
            const keterangan = document.getElementById('swal-q-keterangan').value.trim() || null;
            const wajib = document.getElementById('swal-q-req').checked;

            if (!kode_pertanyaan || !subpertanyaan) {
                Swal.showValidationMessage('Kode dan teks pertanyaan wajib diisi.');
                return false;
            }
            if (!kelompok_pertanyaan_id) {
                Swal.showValidationMessage('Pilih Bagian (Section) terlebih dahulu.');
                return false;
            }

            return {
                kode_pertanyaan,
                subpertanyaan,
                type,
                kelompok_pertanyaan_id,
                tampil_di,
                order,
                keterangan,
                wajib,
            };
        },
    }).then((res) => {
        if (res.isConfirmed && res.value) {
            if (isEdit) {
                router.put(`/superadmin/pertanyaan/${questionToEdit.id}`, res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Pertanyaan berhasil diperbarui.', 'success'),
                });
            } else {
                router.post('/superadmin/pertanyaan', res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Pertanyaan berhasil ditambahkan.', 'success'),
                });
            }
        }
    });
};

const handleReorderQuestion = (questionId, direction) => {
    router.post('/superadmin/pertanyaan/reorder', {
        id: questionId,
        direction,
    }, {
        preserveScroll: true,
    });
};

const handleDeleteQuestion = (question) => {
    Swal.fire({
        title: 'Hapus Pertanyaan?',
        text: `Apakah Anda yakin ingin menghapus pertanyaan [${question.kode_pertanyaan}] "${question.subpertanyaan?.substring(0, 45)}..." beserta seluruh opsi jawabannya?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Pertanyaan',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/superadmin/pertanyaan/${question.id}`, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Terhapus', 'Pertanyaan berhasil dihapus.', 'success'),
            });
        }
    });
};

// ========================================================
// MODAL HANDLERS: OPSI JAWABAN (TAMBAH / EDIT / HAPUS)
// ========================================================
const openOptionModal = (question, optionToEdit = null) => {
    const isEdit = !!optionToEdit;
    const initialText = optionToEdit?.option_text || optionToEdit?.opsi || '';
    const initialCode = optionToEdit?.kode_opsi || `${question.kode_pertanyaan}-${String((question.detils?.length || 0) + 1).padStart(2, '0')}`;
    const initialJump = optionToEdit?.jump_to || '';
    const initialOrder = optionToEdit?.order || ((question.detils?.length || 0) + 1);

    const jumpOptions = ['<option value="">-- Tanpa Percabangan (Lanjut Normal) --</option>']
        .concat(props.availableJumpTargets.map(t => `<option value="${t.kode_pertanyaan}" ${initialJump === t.kode_pertanyaan ? 'selected' : ''}>Lompat ke: [${t.kode_pertanyaan}] ${t.text?.substring(0, 40)}...</option>`))
        .join('');

    Swal.fire({
        title: isEdit ? `Edit Opsi [${question.kode_pertanyaan}]` : `Tambah Opsi Jawaban [${question.kode_pertanyaan}]`,
        html: `
            <div class="text-left space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Kode Opsi *</label>
                    <input id="swal-opt-code" type="text" value="${initialCode}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono" />
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Teks Opsi Jawaban *</label>
                    <input id="swal-opt-text" type="text" value="${initialText}" placeholder="Contoh: Sangat Setuju" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs" />
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Alur Percabangan (Lompat Ke / Jump To)</label>
                    <select id="swal-opt-jump" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white">
                        ${jumpOptions}
                    </select>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Opsi' : 'Tambah Opsi',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        preConfirm: () => {
            const kode_opsi = document.getElementById('swal-opt-code').value.trim();
            const option_text = document.getElementById('swal-opt-text').value.trim();
            const jump_to = document.getElementById('swal-opt-jump').value;

            if (!kode_opsi || !option_text) {
                Swal.showValidationMessage('Kode opsi dan teks opsi wajib diisi.');
                return false;
            }

            return {
                kode_opsi,
                option_text,
                jump_to: jump_to || null,
                order: initialOrder,
            };
        },
    }).then((res) => {
        if (res.isConfirmed && res.value) {
            if (isEdit) {
                router.put(`/superadmin/pertanyaan/options/${optionToEdit.id}`, res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Opsi jawaban berhasil diperbarui.', 'success'),
                });
            } else {
                router.post(`/superadmin/pertanyaan/${question.id}/options`, res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Opsi jawaban baru berhasil ditambahkan.', 'success'),
                });
            }
        }
    });
};

const handleDeleteOption = (opt) => {
    Swal.fire({
        title: 'Hapus Opsi Jawaban?',
        text: `Apakah Anda yakin ingin menghapus opsi "${opt.option_text || opt.opsi}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Opsi',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/superadmin/pertanyaan/options/${opt.id}`, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Terhapus', 'Opsi jawaban berhasil dihapus.', 'success'),
            });
        }
    });
};

// ========================================================
// MODAL HANDLERS: KUESIONER INDUK (TAMBAH / EDIT / TOGGLE / HAPUS)
// ========================================================
const openKuesionerModal = (kuesionerToEdit = null) => {
    const isEdit = !!kuesionerToEdit;
    const initialTitle = kuesionerToEdit?.title || '';
    const initialYear = kuesionerToEdit?.year || new Date().getFullYear();
    const initialDesc = kuesionerToEdit?.description || '';
    const initialActive = isEdit ? Boolean(kuesionerToEdit.is_active) : true;

    Swal.fire({
        title: isEdit ? 'Sunting Kuesioner Induk' : 'Tambah Kuesioner Induk Baru',
        html: `
            <div class="text-left space-y-3.5 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Judul Kuesioner Induk *</label>
                    <input id="swal-k-title" type="text" value="${initialTitle}" placeholder="Contoh: Tracer Study UKDW 2026" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B]" />
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Tahun Kuesioner *</label>
                    <input id="swal-k-year" type="number" value="${initialYear}" min="2000" max="2100" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B]" />
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Deskripsi / Keterangan (Opsional)</label>
                    <textarea id="swal-k-desc" rows="3" placeholder="Ketik keterangan atau standar instrumen tracer study..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B]">${initialDesc}</textarea>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <input id="swal-k-active" type="checkbox" ${initialActive ? 'checked' : ''} class="rounded text-[#0D542B] focus:ring-[#0D542B]" />
                    <label for="swal-k-active" class="font-bold text-gray-700">Jadikan Kuesioner Aktif (Ditampilkan kepada Alumni)</label>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Simpan Perubahan' : 'Tambah Kuesioner',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        preConfirm: () => {
            const title = document.getElementById('swal-k-title').value.trim();
            const year = parseInt(document.getElementById('swal-k-year').value, 10);
            const description = document.getElementById('swal-k-desc').value.trim();
            const is_active = document.getElementById('swal-k-active').checked;

            if (!title) {
                Swal.showValidationMessage('Judul kuesioner induk wajib diisi.');
                return false;
            }
            if (!year || year < 2000 || year > 2100) {
                Swal.showValidationMessage('Tahun kuesioner harus antara 2000 - 2100.');
                return false;
            }

            return {
                title,
                year,
                description,
                is_active,
            };
        },
    }).then((res) => {
        if (res.isConfirmed && res.value) {
            if (isEdit) {
                router.put(`/superadmin/kuesioner/${kuesionerToEdit.id}`, res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Kuesioner induk berhasil diperbarui.', 'success'),
                });
            } else {
                router.post('/superadmin/kuesioner', res.value, {
                    preserveScroll: true,
                    onSuccess: () => Swal.fire('Berhasil', 'Kuesioner induk berhasil ditambahkan.', 'success'),
                });
            }
        }
    });
};

const handleToggleKuesionerActive = (k) => {
    const actionText = k.is_active ? 'menonaktifkan' : 'mengaktifkan';
    Swal.fire({
        title: 'Konfirmasi Status',
        text: `Apakah Anda yakin ingin ${actionText} kuesioner "${k.title}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Lanjutkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
    }).then((res) => {
        if (res.isConfirmed) {
            router.patch(`/superadmin/kuesioner/${k.id}/toggle-active`, {}, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Berhasil', `Kuesioner "${k.title}" berhasil diperbarui.`, 'success'),
            });
        }
    });
};

const handleDeleteKuesioner = (k) => {
    Swal.fire({
        title: 'Hapus Kuesioner Induk?',
        html: `Apakah Anda yakin ingin menghapus <strong>"${k.title}"</strong>?<br><br><span class="text-xs text-rose-600">Perhatian: Seluruh seksi dan butir pertanyaan di dalam kuesioner ini akan ikut terhapus!</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Kuesioner',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/superadmin/kuesioner/${k.id}`, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Terhapus', 'Kuesioner induk berhasil dihapus.', 'success'),
            });
        }
    });
};
</script>

<template>
    <Head title="Pengaturan Kuesioner Universitas - Super Admin UKDW" />

    <div class="min-h-screen bg-[#F8FAFC] text-slate-800 font-sans flex">
        <Sidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Solid Hijau Resmi UKDW #0D542B -->
            <header class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full max-w-[1440px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <!-- Breadcrumbs -->
                        <nav class="flex items-center gap-2 text-xs text-emerald-100 font-medium mb-2.5">
                            <Link href="/superadmin/dashboard" class="hover:text-white transition-colors flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                <span>Dashboard</span>
                            </Link>
                            <span class="text-emerald-300/60">/</span>
                            <span class="text-white font-bold bg-white/10 px-2 py-0.5 rounded-md">Kuesioner Universitas</span>
                        </nav>

                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white tracking-tight">
                            Pengaturan Kuesioner Universitas
                        </h1>
                        <p class="text-emerald-100/90 text-xs sm:text-sm font-normal mt-1.5 max-w-2xl leading-relaxed">
                            Kelola instrumen kuesioner induk, struktur bagian (section), butir kuesioner tracer, dan biodata profil alumni.
                        </p>
                    </div>
                </div>
            </header>

            <main class="admin-page-main w-full max-w-[1440px] mx-auto -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
                <!-- Bar Informasi Kuesioner Induk & Ringkasan Data (2 Tone) -->
                <div class="bg-white rounded-xl p-5 border border-gray-300 shadow-2xs">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Instrumen Kuesioner Induk Aktif
                            </label>
                            <div class="flex items-center gap-3">
                                <div class="px-3.5 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm font-bold text-[#0D542B] flex items-center gap-2">
                                    <span>{{ activeKuesioner?.title ? `[${activeKuesioner.year}] ${activeKuesioner.title}` : 'Belum Ada Kuesioner Aktif' }}</span>
                                    <span v-if="activeKuesioner?.is_active" class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        Aktif
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 bg-gray-50/80 p-3 rounded-lg border border-gray-200 flex-wrap">
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-slate-900">{{ stats.total_sections }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Bagian / Section</span>
                            </div>
                            <div class="h-8 w-px bg-gray-200"></div>
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-[#0D542B]">{{ stats.total_tracer_questions ?? 0 }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Soal Tracer</span>
                            </div>
                            <div class="h-8 w-px bg-gray-200"></div>
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-slate-700">{{ stats.total_profile_questions ?? 0 }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Data Profil</span>
                            </div>
                            <div class="h-8 w-px bg-gray-200"></div>
                            <div class="text-center px-2">
                                <span class="block text-lg font-extrabold text-slate-900">{{ stats.total_kuesioners }}</span>
                                <span class="text-[10px] text-gray-500 font-semibold uppercase">Kuesioner Induk</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigasi Tab (4 Bagian Terpadu) -->
                <div class="flex border-b border-slate-200 space-x-4 overflow-x-auto pb-px">
                    <!-- Tab 1: Section -->
                    <button
                        @click="switchTab('sections')"
                        class="pb-3 px-3 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 shrink-0"
                        :class="[
                            activeTab === 'sections'
                                ? 'border-[#0D542B] text-[#0D542B]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <span>Daftar Bagian (Section)</span>
                    </button>

                    <!-- Tab 2: Pertanyaan Kuesioner Tracer -->
                    <button
                        @click="switchTab('tracer')"
                        class="pb-3 px-3 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 shrink-0"
                        :class="[
                            activeTab === 'tracer'
                                ? 'border-[#0D542B] text-[#0D542B]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Pertanyaan Kuesioner (Tracer)</span>
                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold rounded-full" :class="activeTab === 'tracer' ? 'bg-[#0D542B]/10 text-[#0D542B]' : 'bg-slate-100 text-gray-600'">
                            {{ stats.total_tracer_questions ?? 0 }}
                        </span>
                    </button>

                    <!-- Tab 3: Pertanyaan Profil Alumni -->
                    <button
                        @click="switchTab('profile')"
                        class="pb-3 px-3 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 shrink-0"
                        :class="[
                            activeTab === 'profile'
                                ? 'border-[#0D542B] text-[#0D542B]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Pertanyaan Profil Alumni</span>
                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold rounded-full" :class="activeTab === 'profile' ? 'bg-[#0D542B]/10 text-[#0D542B]' : 'bg-slate-100 text-gray-600'">
                            {{ stats.total_profile_questions ?? 0 }}
                        </span>
                    </button>

                    <!-- Tab 4: Kelola Kuesioner Induk -->
                    <button
                        @click="switchTab('kuesioner')"
                        class="pb-3 px-3 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 shrink-0"
                        :class="[
                            activeTab === 'kuesioner'
                                ? 'border-[#0D542B] text-[#0D542B]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                        </svg>
                        <span>Kelola Kuesioner Induk</span>
                    </button>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 1: SECTION UNIVERSITAS MANAGEMENT                     -->
                <!-- ======================================================== -->
                <div v-if="activeTab === 'sections'" class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="w-full sm:w-72">
                            <input
                                type="text"
                                v-model="sectionSearchQuery"
                                placeholder="Cari judul bagian section..."
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B] focus:outline-none bg-white"
                            />
                        </div>
                        <button
                            type="button"
                            @click="openSectionModal(null)"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <span>+ Tambah Section Baru</span>
                        </button>
                    </div>

                    <!-- Tabel Section (2 Tone) -->
                    <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                <tr>
                                    <th class="px-4 py-3 text-center w-20">Urutan</th>
                                    <th class="px-4 py-3">Judul Bagian Section</th>
                                    <th class="px-4 py-3">Deskripsi & Petunjuk Pengisian</th>
                                    <th class="px-4 py-3 text-center">Jumlah Pertanyaan</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredSections.length === 0">
                                    <td colspan="5" class="p-8 text-center text-gray-400 italic">
                                        Belum ada bagian (section) kuesioner universitas yang dibuat.
                                    </td>
                                </tr>
                                <tr v-for="(sec, idx) in filteredSections" :key="sec.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-1 font-bold text-gray-700">
                                            <span>#{{ sec.order }}</span>
                                            <div class="flex flex-col gap-0.5 ml-1">
                                                <button
                                                    type="button"
                                                    @click="handleReorderSection(sec.id, 'up')"
                                                    :disabled="idx === 0"
                                                    class="p-0.5 rounded text-gray-400 hover:text-emerald-700 disabled:opacity-30 cursor-pointer"
                                                    title="Naikkan Urutan"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="handleReorderSection(sec.id, 'down')"
                                                    :disabled="idx === filteredSections.length - 1"
                                                    class="p-0.5 rounded text-gray-400 hover:text-emerald-700 disabled:opacity-30 cursor-pointer"
                                                    title="Turunkan Urutan"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-bold text-sm text-gray-900">
                                        {{ sec.title }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 max-w-sm">
                                        {{ sec.description || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-700 font-medium text-xs">
                                        {{ sec.subpertanyaans_count ?? 0 }} Pertanyaan
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                @click="openSectionModal(sec)"
                                                class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Edit Section"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="handleDeleteSection(sec)"
                                                class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                title="Hapus Section"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 2: PERTANYAAN KUESIONER TRACER STUDY                 -->
                <!-- ======================================================== -->
                <div v-if="activeTab === 'tracer'" class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-gray-300 shadow-2xs">
                        <div class="flex flex-wrap items-center gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Filter Section</label>
                                <select v-model="selectedTracerSectionFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Section</option>
                                    <option v-for="s in sections" :key="s.id" :value="s.id">Bagian {{ s.order }}: {{ s.title }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Sifat Pengisian</label>
                                <select v-model="selectedTracerRequiredFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Sifat</option>
                                    <option value="wajib">Wajib (Required)</option>
                                    <option value="opsional">Opsional</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Lokasi Tampil</label>
                                <select v-model="selectedTracerTargetFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua (Kuesioner & Keduanya)</option>
                                    <option value="kuesioner">Hanya Kuesioner Tracer</option>
                                    <option value="both">Kuesioner & Profil</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Pencarian Teks</label>
                                <input type="text" v-model="tracerSearchQuery" placeholder="Cari kode atau pertanyaan..." class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs w-48 bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]" />
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="openQuestionModal(null, 'kuesioner')"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer shrink-0"
                        >
                            <span>+ Tambah Pertanyaan Kuesioner</span>
                        </button>
                    </div>

                    <!-- Tabel Pertanyaan Kuesioner Tracer (2 Tone) -->
                    <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                <tr>
                                    <th class="px-3 py-3 text-center w-20">Urutan</th>
                                    <th class="px-3 py-3 w-20">Kode</th>
                                    <th class="px-4 py-3">Butir Pertanyaan Kuesioner</th>
                                    <th class="px-3 py-3">Tipe & Bagian (Section)</th>
                                    <th class="px-4 py-3">Opsi Jawaban & Percabangan</th>
                                    <th class="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredTracerQuestions.length === 0">
                                    <td colspan="6" class="p-8 text-center text-gray-400 italic">
                                        Belum ada butir pertanyaan kuesioner tracer ditemukan yang sesuai filter.
                                    </td>
                                </tr>
                                <tr v-for="(q, idx) in filteredTracerQuestions" :key="q.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-3 py-3.5 text-center">
                                        <div class="flex items-center justify-center gap-1 font-bold text-gray-700">
                                            <span>#{{ q.order }}</span>
                                            <div class="flex flex-col gap-0.5 ml-1">
                                                <button
                                                    type="button"
                                                    @click="handleReorderQuestion(q.id, 'up')"
                                                    :disabled="idx === 0"
                                                    class="p-0.5 text-gray-400 hover:text-emerald-700 disabled:opacity-30 cursor-pointer"
                                                    title="Naikkan Urutan"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="handleReorderQuestion(q.id, 'down')"
                                                    :disabled="idx === filteredTracerQuestions.length - 1"
                                                    class="p-0.5 text-gray-400 hover:text-emerald-700 disabled:opacity-30 cursor-pointer"
                                                    title="Turunkan Urutan"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 font-mono font-bold text-gray-900">
                                        {{ q.kode_pertanyaan }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <p class="font-bold text-gray-900 leading-snug">
                                            {{ q.subpertanyaan }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-1.5 flex-wrap text-xs">
                                            <span :class="q.wajib ? 'font-bold text-gray-900' : 'text-gray-400'">{{ q.wajib ? 'Wajib' : 'Opsional' }}</span>
                                            <span class="text-gray-300">&bull;</span>
                                            <span class="text-gray-500">{{ getTargetBadge(q.tampil_di).label }}</span>
                                        </div>
                                        <p v-if="q.keterangan" class="text-[11px] text-gray-500 italic mt-1">
                                            Keterangan: {{ q.keterangan }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <span class="font-bold text-gray-800 block text-xs">
                                            {{ typeLabels[q.type] || q.type }}
                                        </span>
                                        <p class="text-[11px] text-gray-500 mt-1">
                                            {{ q.kelompok_pertanyaan ? `Bagian ${q.kelompok_pertanyaan.order}: ${q.kelompok_pertanyaan.title}` : '-' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3.5 max-w-md">
                                        <div v-if="canHaveOptions(q.type)" class="space-y-1.5">
                                            <div
                                                v-for="opt in q.detils"
                                                :key="opt.id"
                                                class="flex items-center justify-between p-1.5 bg-gray-50 border border-gray-200 rounded text-[11px]"
                                            >
                                                <div class="flex items-center gap-1.5 min-w-0 pr-2">
                                                    <span class="font-mono text-gray-500 font-bold shrink-0">[{{ opt.kode_opsi || `${q.kode_pertanyaan}-${opt.order}` }}]</span>
                                                    <span class="text-gray-800 font-medium truncate">{{ opt.option_text || opt.opsi }}</span>
                                                    <span v-if="opt.jump_to" class="text-gray-500 text-[10px] font-medium shrink-0">
                                                        &rarr; Lompat ke: [{{ opt.jump_to }}]
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <button
                                                        type="button"
                                                        @click="openOptionModal(q, opt)"
                                                        class="text-gray-500 hover:text-gray-900 font-medium cursor-pointer"
                                                    >
                                                        Edit
                                                    </button>
                                                    <span class="text-gray-300">|</span>
                                                    <button
                                                        type="button"
                                                        @click="handleDeleteOption(opt)"
                                                        class="text-gray-400 hover:text-rose-600 font-medium cursor-pointer"
                                                    >
                                                        Hapus
                                                    </button>
                                                </div>
                                            </div>

                                            <button
                                                type="button"
                                                @click="openOptionModal(q, null)"
                                                class="px-2.5 py-1 text-[11px] font-semibold text-gray-700 bg-white hover:bg-gray-50 rounded border border-gray-300 flex items-center gap-1 cursor-pointer transition-colors mt-1"
                                            >
                                                <span>+ Tambah Opsi Jawaban</span>
                                            </button>
                                        </div>
                                        <div v-else class="text-gray-400 italic text-[11px]">
                                            Input langsung tanpa pilihan opsi.
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                @click="openQuestionModal(q, 'kuesioner')"
                                                class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Edit Pertanyaan"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="handleDeleteQuestion(q)"
                                                class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                title="Hapus Pertanyaan"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 3: PERTANYAAN PROFIL ALUMNI                          -->
                <!-- ======================================================== -->
                <div v-if="activeTab === 'profile'" class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-gray-300 shadow-2xs">
                        <div class="flex flex-wrap items-center gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Filter Section</label>
                                <select v-model="selectedProfileSectionFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Section</option>
                                    <option v-for="s in sections" :key="s.id" :value="s.id">Bagian {{ s.order }}: {{ s.title }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Sifat Pengisian</label>
                                <select v-model="selectedProfileRequiredFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua Sifat</option>
                                    <option value="wajib">Wajib (Required)</option>
                                    <option value="opsional">Opsional</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Lokasi Tampil</label>
                                <select v-model="selectedProfileTargetFilter" class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]">
                                    <option value="all">Semua (Profil & Keduanya)</option>
                                    <option value="profil">Hanya Profil Alumni</option>
                                    <option value="both">Kuesioner & Profil</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Pencarian Teks</label>
                                <input type="text" v-model="profileSearchQuery" placeholder="Cari kode atau pertanyaan..." class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs w-48 bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]" />
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="openQuestionModal(null, 'profil')"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer shrink-0"
                        >
                            <span>+ Tambah Pertanyaan Profil</span>
                        </button>
                    </div>

                    <!-- Tabel Pertanyaan Profil Alumni (2 Tone) -->
                    <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                <tr>
                                    <th class="px-3 py-3 text-center w-20">Urutan</th>
                                    <th class="px-3 py-3 w-28">Kode Field</th>
                                    <th class="px-4 py-3">Butir Data & Pertanyaan Profil</th>
                                    <th class="px-3 py-3">Tipe & Bagian (Section)</th>
                                    <th class="px-4 py-3">Opsi Jawaban (Jika Ada)</th>
                                    <th class="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredProfileQuestions.length === 0">
                                    <td colspan="6" class="p-8 text-center text-gray-400 italic">
                                        Belum ada butir pertanyaan profil alumni ditemukan yang sesuai filter.
                                    </td>
                                </tr>
                                <tr v-for="(q, idx) in filteredProfileQuestions" :key="q.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-3 py-3.5 text-center">
                                        <div class="flex items-center justify-center gap-1 font-bold text-gray-700">
                                            <span>#{{ q.order }}</span>
                                            <div class="flex flex-col gap-0.5 ml-1">
                                                <button
                                                    type="button"
                                                    @click="handleReorderQuestion(q.id, 'up')"
                                                    :disabled="idx === 0"
                                                    class="p-0.5 text-gray-400 hover:text-emerald-700 disabled:opacity-30 cursor-pointer"
                                                    title="Naikkan Urutan"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="handleReorderQuestion(q.id, 'down')"
                                                    :disabled="idx === filteredProfileQuestions.length - 1"
                                                    class="p-0.5 text-gray-400 hover:text-emerald-700 disabled:opacity-30 cursor-pointer"
                                                    title="Turunkan Urutan"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 font-mono font-bold text-gray-900">
                                        {{ q.kode_pertanyaan }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <p class="font-bold text-gray-900 leading-snug">
                                            {{ q.subpertanyaan }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-1.5 flex-wrap text-xs">
                                            <span :class="q.wajib ? 'font-bold text-gray-900' : 'text-gray-400'">{{ q.wajib ? 'Wajib' : 'Opsional' }}</span>
                                            <span class="text-gray-300">&bull;</span>
                                            <span class="text-gray-500">{{ getTargetBadge(q.tampil_di).label }}</span>
                                        </div>
                                        <p v-if="q.keterangan" class="text-[11px] text-gray-500 italic mt-1">
                                            Keterangan: {{ q.keterangan }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <span class="font-bold text-gray-800 block text-xs">
                                            {{ typeLabels[q.type] || q.type }}
                                        </span>
                                        <p class="text-[11px] text-gray-500 mt-1">
                                            {{ q.kelompok_pertanyaan ? `Bagian ${q.kelompok_pertanyaan.order}: ${q.kelompok_pertanyaan.title}` : '-' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3.5 max-w-md">
                                        <div v-if="canHaveOptions(q.type)" class="space-y-1.5">
                                            <div
                                                v-for="opt in q.detils"
                                                :key="opt.id"
                                                class="flex items-center justify-between p-1.5 bg-gray-50 border border-gray-200 rounded text-[11px]"
                                            >
                                                <div class="flex items-center gap-1.5 min-w-0 pr-2">
                                                    <span class="font-mono text-gray-500 font-bold shrink-0">[{{ opt.kode_opsi || `${q.kode_pertanyaan}-${opt.order}` }}]</span>
                                                    <span class="text-gray-800 font-medium truncate">{{ opt.option_text || opt.opsi }}</span>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <button
                                                        type="button"
                                                        @click="openOptionModal(q, opt)"
                                                        class="text-gray-500 hover:text-gray-900 font-medium cursor-pointer"
                                                    >
                                                        Edit
                                                    </button>
                                                    <span class="text-gray-300">|</span>
                                                    <button
                                                        type="button"
                                                        @click="handleDeleteOption(opt)"
                                                        class="text-gray-400 hover:text-rose-600 font-medium cursor-pointer"
                                                    >
                                                        Hapus
                                                    </button>
                                                </div>
                                            </div>

                                            <button
                                                type="button"
                                                @click="openOptionModal(q, null)"
                                                class="px-2.5 py-1 text-[11px] font-semibold text-gray-700 bg-white hover:bg-gray-50 rounded border border-gray-300 flex items-center gap-1 cursor-pointer transition-colors mt-1"
                                            >
                                                <span>+ Tambah Opsi Jawaban</span>
                                            </button>
                                        </div>
                                        <div v-else class="text-gray-400 italic text-[11px]">
                                            Input isian langsung profil tanpa opsi.
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                @click="openQuestionModal(q, 'profil')"
                                                class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Edit Pertanyaan"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="handleDeleteQuestion(q)"
                                                class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                title="Hapus Pertanyaan"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 4: KELOLA KUESIONER INDUK                            -->
                <!-- ======================================================== -->
                <div v-if="activeTab === 'kuesioner'" class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="w-full sm:w-72">
                            <input
                                type="text"
                                v-model="kuesionerSearchQuery"
                                placeholder="Cari judul kuesioner atau tahun..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] focus:outline-none bg-white"
                            />
                        </div>
                        <button
                            type="button"
                            @click="openKuesionerModal(null)"
                            class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-bold rounded-lg shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <span>+ Tambah Kuesioner Induk</span>
                        </button>
                    </div>

                    <!-- Tabel Kuesioner Induk (2 Tone) -->
                    <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                <tr>
                                    <th class="px-4 py-3 text-center w-24">Tahun</th>
                                    <th class="px-4 py-3">Judul Kuesioner Induk</th>
                                    <th class="px-4 py-3">Deskripsi & Keterangan</th>
                                    <th class="px-4 py-3 text-center">Status Pengisian</th>
                                    <th class="px-4 py-3 text-center">Jumlah Seksi</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="filteredKuesioners.length === 0">
                                    <td colspan="6" class="p-8 text-center text-gray-400 italic">
                                        Belum ada kuesioner induk yang terdaftar.
                                    </td>
                                </tr>
                                <tr v-for="k in filteredKuesioners" :key="k.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-4 py-3 text-center font-bold text-gray-900 text-sm">
                                        {{ k.year }}
                                    </td>
                                    <td class="px-4 py-3 font-bold text-sm text-gray-900">
                                        {{ k.title }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 max-w-sm">
                                        {{ k.description || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            v-if="k.is_active"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"
                                        >
                                            Aktif (Alumni)
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-600 border border-gray-200"
                                        >
                                            Nonaktif
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-700 font-medium text-xs">
                                        {{ k.kelompok_pertanyaan_count ?? 0 }} Seksi
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                @click="handleToggleKuesionerActive(k)"
                                                class="px-2.5 py-1 rounded-lg border text-xs font-semibold transition-colors cursor-pointer mr-1"
                                                :class="k.is_active ? 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50' : 'border-[#0D542B] text-[#0D542B] bg-emerald-50 hover:bg-emerald-100'"
                                            >
                                                {{ k.is_active ? 'Nonaktifkan' : 'Jadikan Aktif' }}
                                            </button>
                                            <button
                                                type="button"
                                                @click="openKuesionerModal(k)"
                                                class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Edit Kuesioner"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="handleDeleteKuesioner(k)"
                                                class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                title="Hapus Kuesioner"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
