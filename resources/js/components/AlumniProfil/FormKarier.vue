<!--
  Komponen Anak (Child Component): Form Karier & Riwayat Pekerjaan
  File: resources/js/Pages/Alumni/Profil/Components/FormKarier.vue

  Deskripsi:
  Mengelola data karier alumni, mencakup peran/aktivitas saat ini (Pekerja / Karyawan, Wirausaha / Founder, Melanjutkan Pendidikan),
  data perusahaan/instansi (lokasi dalam/luar negeri, filter negara/provinsi/kabupaten sebelum memilih perusahaan, dan penambahan perusahaan),
  nominal penghasilan bulanan (tanpa auto-multiplier), serta data kontak atasan langsung.
-->
<script setup>
// Form karier alumni: opsi perusahaan, wilayah, negara, dan pertanyaan berasal dari props database.
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

// ============================================================================
// 1. PROPS DARI PARENT (Index.vue)
// ============================================================================
const props = defineProps({
    form: Object,
    provinces: Array,
    kabupatens: Array,
    negaras: Array,
    companies: Array,
    alumniData: Object,
    refOptions: Object,
    profileQuestions: {
        type: Array,
        default: () => [],
    },
    profileQuestionConfigs: {
        type: Object,
        default: () => ({}),
    },
    evaluasiAtasan: Object,
    /** Mode baca-saja untuk detail alumni di luar lingkup admin. */
    readonly: {
        type: Boolean,
        default: false,
    },
});

// Helper pembacaan konfigurasi pertanyaan dinamis dari Super Admin
const getQuestionLabel = (code, fallback) => {
    return props.profileQuestionConfigs?.[code]?.subpertanyaan || fallback;
};

const isQuestionRequired = (code, fallback = true) => {
    return props.profileQuestionConfigs?.[code]?.wajib ?? fallback;
};

const getQuestionHint = (code, fallback = '') => {
    return props.profileQuestionConfigs?.[code]?.keterangan || fallback;
};

const isQuestionVisible = (code) => {
    if (!props.profileQuestionConfigs || Object.keys(props.profileQuestionConfigs).length === 0) {
        return true;
    }
    return Boolean(props.profileQuestionConfigs[code]);
};

if (!props.form.profile_answers) {
    props.form.profile_answers = {};
}

if (!props.form.profile_answer_custom) {
    props.form.profile_answer_custom = {};
}

// Container refs untuk mendeteksi klik di luar area dropdown (click-outside)
const provinsiContainerRef = ref(null);
const kabupatenContainerRef = ref(null);
const negaraContainerRef = ref(null);
const companyContainerRef = ref(null);

// ============================================================================
// 2. OPSI DINAMIS DARI DATABASE (kuesioner_opsi)
// ============================================================================

// Pilihan Jenis Instansi / Perusahaan (F11)
const jenisPerusahaanOptions = computed(() => {
    if (props.refOptions?.F11 && props.refOptions.F11.length > 0) {
        return props.refOptions.F11.map(opt => ({
            value: opt.option_text,
            label: `${opt.kode_opsi} - ${opt.option_text}`
        }));
    }
    return [
        { value: 'Instansi pemerintah', label: '1 - Instansi pemerintah' },
        { value: 'Organisasi non-profit/Lembaga Swadaya Masyarakat', label: '2 - Organisasi non-profit / LSM' },
        { value: 'Perusahaan swasta', label: '3 - Perusahaan swasta' },
        { value: 'Wiraswasta/perusahaan sendiri', label: '4 - Wiraswasta / Perusahaan sendiri' },
        { value: 'BUMN/BUMD', label: '6 - BUMN / BUMD' },
        { value: 'Institusi/Organisasi Multilateral', label: '7 - Institusi / Organisasi Multilateral' },
        { value: 'Lainnya', label: '5 - Lainnya' },
    ];
});

// Pilihan Bentuk Perusahaan / Institusi (Sinkron dengan Evaluasi Atasan & Super Admin)
const bentukPerusahaanOptions = computed(() => {
    const configured = props.refOptions?.BIO_INST_BENTUK || [];
    if (configured.length > 0) {
        const options = configured.map(opt => ({
            value: opt.option_text,
            label: opt.option_text,
        }));
        if (!options.some(opt => isOtherOptionValue(opt.value))) {
            options.push({ value: 'Lainnya', label: 'Lainnya' });
        }

        return options;
    }
    return [
        { value: 'BUMN', label: 'BUMN' },
        { value: 'Perusahaan Terbatas', label: 'Perusahaan Terbatas' },
        { value: 'Koperasi', label: 'Koperasi' },
        { value: 'CV', label: 'CV' },
        { value: 'Firma', label: 'Firma' },
        { value: 'Lainnya', label: 'Lainnya' },
    ];
});

// Pilihan Posisi Jabatan Struktural (F2G) dari konfigurasi Super Admin
const posisiJabatanOptions = computed(() => {
    const configured = props.refOptions?.F2G || [];
    if (configured.length > 0) {
        const opts = configured.map(opt => ({
            value: opt.option_text,
            label: `${opt.kode_opsi} - ${opt.option_text}`,
        }));
        if (!opts.some(opt => isOtherOptionValue(opt.value))) {
            opts.push({ value: 'Lainnya', label: 'Lainnya (Tuliskan jabatan spesifik)' });
        }
        return opts;
    }
    return [];
});

// Pilihan Posisi / Jabatan Wiraswasta (F5C) dari konfigurasi Super Admin
const posisiWiraswastaOptions = computed(() => {
    const configured = props.refOptions?.F5C || [];
    if (configured.length > 0) {
        return configured.map(opt => ({
            value: opt.option_text,
            label: opt.option_text,
        }));
    }
    return [
        { value: 'Owner', label: 'Owner' },
        { value: 'Founder', label: 'Founder' },
        { value: 'Co-Founder', label: 'Co-Founder' },
        { value: 'Direktur Utama', label: 'Direktur Utama' },
        { value: 'Pengelola Usaha', label: 'Pengelola Usaha' },
        { value: 'Freelancer / Konsultan Mandiri', label: 'Freelancer / Konsultan Mandiri' },
        { value: 'Lainnya', label: 'Lainnya' },
    ];
});

// Pilihan Skala Perusahaan (F2H / F5D)
const skalaOptions = computed(() => {
    let opts = [];
    if (props.refOptions?.F2H && props.refOptions.F2H.length > 0) {
        opts = props.refOptions.F2H.map(opt => ({
            value: opt.option_text,
            label: opt.option_text
        }));
    } else {
        opts = [
            { value: 'Regional/Lokal', label: 'Regional / Lokal' },
            { value: 'Nasional', label: 'Nasional' },
            { value: 'Internasional', label: 'Internasional' },
        ];
    }

    // Sinkronisasi nilai 'Lokal' dari form evaluasi atasan ke 'Regional/Lokal' bila ada di opsi F2H
    if (props.form?.company_skala === 'Lokal' && opts.some(o => o.value === 'Regional/Lokal') && !opts.some(o => o.value === 'Lokal')) {
        props.form.company_skala = 'Regional/Lokal';
        props.form.perusahaan_skala = 'Regional/Lokal';
    }

    return opts;
});

// Pilihan bidang pekerjaan (BIO_BIDANG_DIVISI) disimpan langsung ke biodata.
const DEFAULT_BIDANG_PEKERJAAN_OPTIONS = [
    { value: 'Akademik / Pendidikan', label: '1 - Akademik / Pendidikan' },
    { value: 'Administrasi / Tata Usaha', label: '2 - Administrasi / Tata Usaha' },
    { value: 'Keuangan / Akuntansi', label: '3 - Keuangan / Akuntansi' },
    { value: 'Sumber Daya Manusia', label: '4 - Sumber Daya Manusia' },
    { value: 'Teknologi Informasi', label: '5 - Teknologi Informasi' },
    { value: 'Operasional', label: '6 - Operasional' },
    { value: 'Pemasaran / Komunikasi', label: '7 - Pemasaran / Komunikasi' },
    { value: 'Penjualan / Pengembangan Bisnis', label: '8 - Penjualan / Pengembangan Bisnis' },
    { value: 'Penelitian dan Pengembangan', label: '9 - Penelitian dan Pengembangan' },
    { value: 'Pelayanan / Customer Service', label: '10 - Pelayanan / Customer Service' },
    { value: 'Produksi / Manufaktur', label: '11 - Produksi / Manufaktur' },
    { value: 'Kesehatan', label: '12 - Kesehatan' },
    { value: 'Hukum', label: '13 - Hukum' },
    { value: 'Teknik', label: '14 - Teknik' },
    { value: 'Lainnya', label: '15 - Lainnya' },
];

const bidangPekerjaanOptions = computed(() => {
    const configured = props.refOptions?.BIO_BIDANG_DIVISI || [];
    if (configured.length > 0) {
        const options = configured.map(opt => ({
            value: opt.option_text,
            label: `${opt.kode_opsi} - ${opt.option_text}`,
        }));

        if (!options.some(opt => isOtherOptionValue(opt.value))) {
            options.push({ value: 'Lainnya', label: 'Lainnya' });
        }

        return options;
    }

    return DEFAULT_BIDANG_PEKERJAAN_OPTIONS;
});

const isOtherOptionValue = (value) => {
    const normalized = String(value || '').trim().toLowerCase();

    return normalized.includes('lainnya') || normalized.includes('tuliskan') || normalized.includes('...');
};

const isConfiguredBidangPekerjaan = (value) => {
    const normalized = String(value || '').trim().toLowerCase();

    return bidangPekerjaanOptions.value.some(opt => opt.value.toLowerCase() === normalized && !isOtherOptionValue(opt.value));
};

const bidangPekerjaanValue = String(props.form?.bidang_pekerjaan || '').trim();
const bidangPekerjaanSelection = ref(
    bidangPekerjaanValue && isConfiguredBidangPekerjaan(bidangPekerjaanValue)
        ? bidangPekerjaanValue
        : bidangPekerjaanValue
            ? 'Lainnya'
            : ''
);

if (bidangPekerjaanSelection.value === 'Lainnya' && bidangPekerjaanValue && !isOtherOptionValue(bidangPekerjaanValue)) {
    props.form.bidang_pekerjaan_lainnya = bidangPekerjaanValue;
}

if (isOtherOptionValue(bidangPekerjaanValue)) {
    props.form.bidang_pekerjaan_lainnya = '';
}

const syncBidangPekerjaan = () => {
    if (isOtherOptionValue(bidangPekerjaanSelection.value)) {
        props.form.bidang_pekerjaan = props.form.bidang_pekerjaan_lainnya || '';

        return;
    }

    props.form.bidang_pekerjaan = bidangPekerjaanSelection.value;
    props.form.bidang_pekerjaan_lainnya = '';
};

// Logika sinkronisasi Posisi Jabatan Struktural (F2G)
const isConfiguredPosisiJabatan = (value) => {
    const normalized = String(value || '').trim().toLowerCase();
    return posisiJabatanOptions.value.some(opt => opt.value.toLowerCase() === normalized && !isOtherOptionValue(opt.value));
};

const posisiJabatanValue = String(props.form?.posisi_jabatan || '').trim();
const posisiJabatanSelection = ref(
    posisiJabatanValue && isConfiguredPosisiJabatan(posisiJabatanValue)
        ? posisiJabatanValue
        : posisiJabatanValue
            ? 'Lainnya'
            : ''
);

const posisiJabatanKustom = ref(
    posisiJabatanValue && !isConfiguredPosisiJabatan(posisiJabatanValue)
        ? posisiJabatanValue
        : ''
);

const syncPosisiJabatan = () => {
    if (isOtherOptionValue(posisiJabatanSelection.value)) {
        props.form.posisi_jabatan = posisiJabatanKustom.value;
    } else {
        props.form.posisi_jabatan = posisiJabatanSelection.value;
    }
};

const isProfileQuestionSelect = (question) => {
    return question.detils?.length > 0;
};

const isOtherProfileOption = (value) => {
    const normalized = String(value || '').trim().toLowerCase();

    return normalized.includes('lainnya') || normalized.includes('tuliskan') || normalized.includes('...');
};

const pendidikanTingkatLabels = {
    D3: 'D3 (Diploma 3)',
    D4: 'D4 (Diploma 4 / Sarjana Terapan)',
    S1: 'S1 (Sarjana)',
    S2: 'S2 (Magister / Master)',
    S3: 'S3 (Doktor / Ph.D)',
    Profesi: 'Pendidikan Profesi',
    Spesialis: 'Pendidikan Spesialis',
    Lainnya: 'Lainnya',
};

const pendidikanTingkatOptions = computed(() => {
    const configured = props.refOptions?.BIO_PEND_TINGKAT || [];
    const options = configured.map((option) => {
        const value = String(option.option_text || '').trim();

        return {
            value,
            label: pendidikanTingkatLabels[value] || value,
        };
    }).filter((option) => option.value !== '');

    if (options.length === 0) {
        return Object.entries(pendidikanTingkatLabels).map(([value, label]) => ({ value, label }));
    }

    if (!options.some((option) => isOtherOptionValue(option.value))) {
        options.push({ value: 'Lainnya', label: 'Lainnya' });
    }

    return options;
});

const pendidikanTingkatValue = String(props.form?.pendidikan_tingkat || '').trim();
const pendidikanTingkatSelection = ref(
    pendidikanTingkatValue && pendidikanTingkatOptions.value.some((option) => option.value.toLowerCase() === pendidikanTingkatValue.toLowerCase() && !isOtherOptionValue(option.value))
        ? pendidikanTingkatValue
        : pendidikanTingkatValue
            ? 'Lainnya'
            : ''
);
const pendidikanTingkatLainnya = ref(
    pendidikanTingkatSelection.value === 'Lainnya' && !isOtherOptionValue(pendidikanTingkatValue)
        ? pendidikanTingkatValue
        : ''
);

const syncPendidikanTingkat = () => {
    if (isOtherOptionValue(pendidikanTingkatSelection.value)) {
        props.form.pendidikan_tingkat = pendidikanTingkatLainnya.value || 'Lainnya';

        return;
    }

    props.form.pendidikan_tingkat = pendidikanTingkatSelection.value || null;
};

const needsProfileQuestionCustomText = (question) => {
    return isOtherProfileOption(props.form.profile_answers?.[question.id]);
};

// ============================================================================
// 3. LOGIKA PERAN KERJA & STATUS STUDI LANJUT
// ============================================================================
// isOwner HANYA berlaku jika alumni memilih kategori 'Wiraswasta'
const isOwner = computed(() => {
    return props.form.kategori_pekerjaan === 'Wiraswasta';
});

// Status sedang melanjutkan studi lanjut (Independen dari status pekerjaan)
const isMelanjutkanStudi = ref(
    Boolean(
        props.form.pendidikan_tingkat ||
        props.form.perguruan_tinggi ||
        props.form.pendidikan_prodi ||
        props.form.kategori_pekerjaan === 'Melanjutkan Pendidikan'
    )
);

// Normalisasi kategori_pekerjaan agar kompatibel dengan data LinkedIn ('Pekerja', 'Bekerja (Full Time)', 'Karyawan', dll.)
if (props.form.kategori_pekerjaan) {
    const rawKat = String(props.form.kategori_pekerjaan).trim();
    if (rawKat === 'Melanjutkan Pendidikan') {
        isMelanjutkanStudi.value = true;
        props.form.kategori_pekerjaan = '';
    } else if (rawKat === 'Pekerja' || rawKat.toLowerCase().includes('bekerja') || rawKat.toLowerCase().includes('pekerja') || rawKat.toLowerCase().includes('karyawan')) {
        props.form.kategori_pekerjaan = 'Pekerja';
    } else if (rawKat === 'Wiraswasta' || rawKat.toLowerCase().includes('wira') || rawKat.toLowerCase().includes('founder') || rawKat.toLowerCase().includes('owner')) {
        props.form.kategori_pekerjaan = 'Wiraswasta';
    } else if (['Tidak Bekerja', 'Mencari Kerja', 'Belum Bekerja', 'Belum Memungkinkan Bekerja'].some(s => rawKat.toLowerCase().includes(s.toLowerCase()))) {
        props.form.kategori_pekerjaan = 'Tidak Bekerja';
    }
}

const toggleMelanjutkanStudi = () => {
    isMelanjutkanStudi.value = !isMelanjutkanStudi.value;
    if (!isMelanjutkanStudi.value) {
        props.form.pendidikan_tingkat = null;
        props.form.perguruan_tinggi = null;
        props.form.pendidikan_prodi = null;
        pendidikanTingkatSelection.value = '';
        pendidikanTingkatLainnya.value = '';
    }
};

// Helper validasi email
const isValidEmail = (val) => {
    if (!val) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(val).trim());
};

// Helper validasi telepon
const isValidPhone = (val) => {
    if (!val) return false;
    const clean = String(val).replace(/[^0-9+]/g, '');
    return clean.length >= 10 && clean.length <= 15;
};

// Helper validasi URL
const isValidUrl = (val) => {
    if (!val) return false;
    return /^https?:\/\/.+/i.test(String(val).trim());
};

// Cek apakah perusahaan tempat bekerja sudah berstatus Terverifikasi resmi
const isVerifiedCompany = computed(() => {
    return props.form.company_status_verifikasi === 'Terverifikasi' || props.form.perusahaan_status_verifikasi === 'Terverifikasi';
});

// Alumni selalu dapat melengkapi/memperbarui detail perusahaan agar tersinkronisasi dua arah dengan form atasan
const canEditCompanyDetails = computed(() => {
    return !props.readonly;
});

// Mengisi data atasan otomatis dengan kontak alumni jika pemilik usaha
const autoFillAtasanOwner = () => {
    props.form.nama_atasan = props.form.nama || '';
    props.form.email_atasan = props.form.email_pribadi || props.form.email || '';
    props.form.telepon_atasan = props.form.nomor_telepon || '';
};

// Pemilihan peran eksklusif Pekerja vs Wiraswasta (Single Choice)
const selectRole = (newRole) => {
    if (props.readonly) return;

    if (props.form.kategori_pekerjaan === newRole) {
        props.form.kategori_pekerjaan = '';
        props.form.posisi_jabatan = null;
        props.form.posisi_wiraswasta = null;
        props.form.posisi_wiraswasta_lainnya = null;
        return;
    }

    props.form.kategori_pekerjaan = newRole;

    if (newRole === 'Pekerja') {
        props.form.posisi_wiraswasta = null;
        props.form.posisi_wiraswasta_lainnya = null;

        if (props.form.nama_atasan === props.form.nama || props.form.email_atasan === (props.form.email_pribadi || props.form.email)) {
            props.form.nama_atasan = '';
            props.form.email_atasan = '';
            props.form.telepon_atasan = '';
        }
    } else if (newRole === 'Wiraswasta') {
        props.form.posisi_jabatan = null;
        if (!props.form.posisi_wiraswasta) {
            props.form.posisi_wiraswasta = '';
        }
        autoFillAtasanOwner();
    } else if (newRole === 'Tidak Bekerja' || newRole === 'Mencari Kerja' || newRole === 'Belum Bekerja') {
        props.form.posisi_jabatan = null;
        props.form.posisi_wiraswasta = null;
        props.form.posisi_wiraswasta_lainnya = null;
        props.form.gaji = null;
        if (props.form.nama_atasan === props.form.nama || props.form.email_atasan === (props.form.email_pribadi || props.form.email)) {
            props.form.nama_atasan = '';
            props.form.email_atasan = '';
            props.form.telepon_atasan = '';
        }
    }
};

// ============================================================================
// 3. PENGIRIMAN MANUAL EMAIL KUESIONER EVALUASI ATASAN
// ============================================================================
const sendingEmail = ref(false);

const handleKirimEmailAtasan = () => {
    const email = props.form.email_atasan?.trim();
    if (!email || !isValidEmail(email)) {
        Swal.fire({
            title: 'Email Belum Valid',
            text: 'Harap masukkan alamat email atasan yang valid (contoh: atasan@perusahaan.co.id) sebelum mengirimkan undangan survei.',
            icon: 'warning',
            confirmButtonColor: '#005B3C',
        });
        return;
    }

    Swal.fire({
        title: 'Kirim Email ke Atasan?',
        html: `Sistem akan mengirimkan email undangan kuesioner evaluasi atasan langsung ke: <br><strong class="text-base text-gray-900">${email}</strong><br><span class="text-xs text-gray-500 mt-2 block">Atasan akan menerima tautan khusus untuk mengisi kuesioner evaluasi pengguna lulusan secara mandiri.</span>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#005B3C',
        cancelButtonColor: '#9CA3AF',
        confirmButtonText: 'Ya, Kirim Email',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            sendingEmail.value = true;
            router.post('/alumni/kirim-email-atasan', {
                biodata_id: props.alumniData?.id || props.form?.biodata_id || props.form?.id,
                nama_atasan: props.form.nama_atasan?.trim() || '',
                email_atasan: email,
                telepon_atasan: props.form.telepon_atasan?.trim() || '',
            }, {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    sendingEmail.value = false;
                },
                onSuccess: () => {
                    Swal.fire({
                        title: 'Email Terkirim!',
                        text: `Undangan kuesioner evaluasi atasan berhasil dikirim ke ${email}.`,
                        icon: 'success',
                        confirmButtonColor: '#005B3C',
                    });
                },
                onError: (err) => {
                    Swal.fire({
                        title: 'Gagal Mengirim Email',
                        text: err?.email_atasan || err?.message || 'Terjadi kesalahan sistem saat mengirim email ke atasan.',
                        icon: 'error',
                        confirmButtonColor: '#005B3C',
                    });
                }
            });
        }
    });
};

// Watcher sinkronisasi dua arah field perusahaan agar tersimpan sempurna ke backend
watch(() => props.form.company_skala, (newVal) => {
    props.form.perusahaan_skala = newVal;
});
watch(() => props.form.perusahaan_skala, (newVal) => {
    if (newVal && !props.form.company_skala) props.form.company_skala = newVal;
});
watch(() => props.form.company_bentuk, (newVal) => {
    props.form.perusahaan_bentuk = newVal;
});
watch(() => props.form.perusahaan_bentuk, (newVal) => {
    if (newVal && !props.form.company_bentuk) props.form.company_bentuk = newVal;
});
watch(() => props.form.company_no_telp_fax, (newVal) => {
    props.form.perusahaan_no_telp_fax = newVal;
});
watch(() => props.form.perusahaan_no_telp_fax, (newVal) => {
    if (newVal && !props.form.company_no_telp_fax) props.form.company_no_telp_fax = newVal;
});
watch(() => props.form.company_homepage, (newVal) => {
    props.form.perusahaan_homepage = newVal;
});
watch(() => props.form.perusahaan_homepage, (newVal) => {
    if (newVal && !props.form.company_homepage) props.form.company_homepage = newVal;
});
watch(() => props.form.company_alamat, (newVal) => {
    props.form.perusahaan_alamat = newVal;
});
watch(() => props.form.perusahaan_alamat, (newVal) => {
    if (newVal && !props.form.company_alamat) props.form.company_alamat = newVal;
});
watch(() => props.form.company_jenis_perusahaan, (newVal) => {
    props.form.perusahaan_jenis_perusahaan = newVal;
});
watch(() => props.form.company_jenis_perusahaan_lainnya, (newVal) => {
    props.form.perusahaan_jenis_perusahaan_lainnya = newVal;
});

// ============================================================================
// 4. FORMATTING NOMINAL PENGHASILAN (GAJI) - SINGLE SOURCE OF TRUTH
// ============================================================================
const formatRupiah = (val) => {
    if (!val && val !== 0) return 'Rp 0';
    let num = Number(val);
    if (isNaN(num) || num <= 0) return 'Rp 0';
    return 'Rp ' + num.toLocaleString('id-ID');
};

const formattedGaji = computed({
    get() {
        if (!props.form.gaji && props.form.gaji !== 0) return '';
        const num = Number(props.form.gaji);
        return isNaN(num) || num <= 0 ? '' : new Intl.NumberFormat('id-ID').format(num);
    },
    set(val) {
        const clean = String(val || '').replace(/[^0-9]/g, '');
        props.form.gaji = clean ? parseInt(clean, 10) : null;
    }
});

// ============================================================================
// 5. DATA PERUSAHAAN & LOKASI
// ============================================================================
const localCompanies = ref([...(props.companies || [])]);
watch(() => props.companies, (newVal) => {
    if (newVal) localCompanies.value = [...newVal];
}, { deep: true });

// Inisialisasi default lokasi perusahaan
if (!props.form.company_jenis_lokasi && !props.form.perusahaan_jenis_lokasi) {
    props.form.company_jenis_lokasi = 'Dalam Negeri';
    props.form.perusahaan_jenis_lokasi = 'Dalam Negeri';
}
if (!props.form.company_kode_negara && !props.form.perusahaan_kode_negara) {
    props.form.company_kode_negara = props.form.company_jenis_lokasi === 'Luar Negeri' ? '' : 'ID';
    props.form.perusahaan_kode_negara = props.form.company_kode_negara;
}

watch(() => props.form.company_jenis_lokasi, (newVal) => {
    props.form.perusahaan_jenis_lokasi = newVal;
    if (newVal === 'Luar Negeri') {
        props.form.company_kode_provinsi = null;
        props.form.perusahaan_kode_provinsi = null;
        props.form.company_kode_kabupaten = null;
        props.form.perusahaan_kode_kabupaten = null;
        if (!props.form.company_kode_negara || props.form.company_kode_negara === 'ID') {
            props.form.company_kode_negara = '';
            props.form.perusahaan_kode_negara = '';
        }
    } else {
        props.form.company_kode_negara = 'ID';
        props.form.perusahaan_kode_negara = 'ID';
    }
});

watch(() => props.form.company_kode_negara, (newVal) => {
    props.form.perusahaan_kode_negara = newVal;
});

// Dropdown Provinsi
const showProvinsiDropdown = ref(false);
const searchProvinsiQuery = ref('');

const selectedProvinceName = computed(() => {
    if (!props.form.company_kode_provinsi || !props.provinces) return '';
    const prov = props.provinces.find(p => p.kode_provinsi == props.form.company_kode_provinsi);
    return prov ? prov.nama_provinsi : '';
});

const filteredProvinces = computed(() => {
    const list = props.provinces || [];
    if (!searchProvinsiQuery.value.trim()) return list;
    const q = searchProvinsiQuery.value.toLowerCase();
    return list.filter(p => p.nama_provinsi.toLowerCase().includes(q));
});

const selectProvinsi = (prov) => {
    props.form.company_kode_provinsi = prov.kode_provinsi;
    props.form.perusahaan_kode_provinsi = prov.kode_provinsi;
    props.form.company_kode_kabupaten = '';
    props.form.perusahaan_kode_kabupaten = '';
    showProvinsiDropdown.value = false;
    searchProvinsiQuery.value = '';
};

const clearProvinsi = () => {
    props.form.company_kode_provinsi = null;
    props.form.perusahaan_kode_provinsi = null;
    props.form.company_kode_kabupaten = null;
    props.form.perusahaan_kode_kabupaten = null;
    showProvinsiDropdown.value = false;
    searchProvinsiQuery.value = '';
};

// Dropdown Kabupaten
const showKabupatenDropdown = ref(false);
const searchKabupatenQuery = ref('');

const selectedKabupatenName = computed(() => {
    if (!props.form.company_kode_kabupaten || !props.kabupatens) return '';
    const kab = props.kabupatens.find(k => k.kode_kabupaten == props.form.company_kode_kabupaten);
    return kab ? kab.nama_kabupaten : '';
});

const filteredKabupatens = computed(() => {
    let list = props.kabupatens || [];
    if (props.form.company_kode_provinsi) {
        list = list.filter(k => k.kode_provinsi == props.form.company_kode_provinsi);
    }
    if (!searchKabupatenQuery.value.trim()) return list;
    const q = searchKabupatenQuery.value.toLowerCase();
    return list.filter(k => k.nama_kabupaten.toLowerCase().includes(q));
});

const selectKabupaten = (kab) => {
    props.form.company_kode_kabupaten = kab.kode_kabupaten;
    props.form.perusahaan_kode_kabupaten = kab.kode_kabupaten;
    showKabupatenDropdown.value = false;
    searchKabupatenQuery.value = '';
};

const clearKabupaten = () => {
    props.form.company_kode_kabupaten = null;
    props.form.perusahaan_kode_kabupaten = null;
    showKabupatenDropdown.value = false;
    searchKabupatenQuery.value = '';
};

// Dropdown Negara Luar Negeri
const showNegaraDropdown = ref(false);
const searchNegaraQuery = ref('');

const selectedNegaraName = computed(() => {
    if (!props.form.company_kode_negara || props.form.company_kode_negara === 'ID') return '';
    return props.form.company_kode_negara;
});

const filteredNegaras = computed(() => {
    const list = (props.negaras || []).filter(n => n.nama_negara.toLowerCase() !== 'indonesia');
    if (!searchNegaraQuery.value.trim()) return list;
    const q = searchNegaraQuery.value.toLowerCase();
    return list.filter(n =>
        n.nama_negara.toLowerCase().includes(q) ||
        (n.benua && n.benua.toLowerCase().includes(q)) ||
        (n.ibu_kota && n.ibu_kota.toLowerCase().includes(q)) ||
        (n.kode_negara && n.kode_negara.toLowerCase().includes(q))
    );
});

const selectNegara = (neg) => {
    props.form.company_kode_negara = neg.kode_negara;
    props.form.perusahaan_kode_negara = neg.kode_negara;
    showNegaraDropdown.value = false;
    searchNegaraQuery.value = '';
};

const clearNegara = () => {
    props.form.company_kode_negara = '';
    props.form.perusahaan_kode_negara = '';
    showNegaraDropdown.value = false;
    searchNegaraQuery.value = '';
};

// Dropdown Perusahaan
const showCompanyDropdown = ref(false);
const searchCompanyQuery = ref('');
// Mode saring wilayah opsional (default false agar pencarian langsung ke seluruh master Head Office)
const showWilayahFilter = ref(false);

const filteredCompanies = computed(() => {
    let list = localCompanies.value || [];

    // Jika filter wilayah diaktifkan secara opsional
    if (showWilayahFilter.value) {
        if (props.form.company_jenis_lokasi) {
            list = list.filter(c => (c.jenis_lokasi || 'Dalam Negeri') === props.form.company_jenis_lokasi);
        }

        if (props.form.company_jenis_lokasi === 'Luar Negeri') {
            if (props.form.company_kode_negara && props.form.company_kode_negara !== 'ID') {
                const qNegara = props.form.company_kode_negara.toLowerCase();
                list = list.filter(c => c.kode_negara && c.kode_negara.toLowerCase() === qNegara);
            }
        } else {
            if (props.form.company_kode_provinsi) {
                list = list.filter(c => c.kode_provinsi == props.form.company_kode_provinsi);
            }
            if (props.form.company_kode_kabupaten) {
                list = list.filter(c => c.kode_kabupaten == props.form.company_kode_kabupaten);
            }
        }
    }

    if (!searchCompanyQuery.value.trim()) return list;
    const q = searchCompanyQuery.value.toLowerCase();
    return list.filter(c => {
        const matchNama = (c.nama_perusahaan || '').toLowerCase().includes(q);
        const matchAlamat = (c.alamat || '').toLowerCase().includes(q);
        const matchNegara = (c.kode_negara || '').toLowerCase().includes(q);
        const provName = props.provinces?.find(p => p.kode_provinsi == c.kode_provinsi)?.nama_provinsi?.toLowerCase() || '';
        const kabName = props.kabupatens?.find(k => k.kode_kabupaten == c.kode_kabupaten)?.nama_kabupaten?.toLowerCase() || '';
        const countryName = props.negaras?.find(n => n.kode_negara == c.kode_negara)?.nama_negara?.toLowerCase() || '';

        return matchNama || matchAlamat || matchNegara || provName.includes(q) || kabName.includes(q) || countryName.includes(q);
    });
});

const companyWilayahDisplay = computed(() => {
    if (props.form.company_jenis_lokasi === 'Luar Negeri') {
        const neg = (props.negaras || []).find(n => n.kode_negara === props.form.company_kode_negara);
        return `Luar Negeri · ${neg ? neg.nama_negara : (props.form.company_kode_negara || 'Negara Asing')}`;
    }
    const prov = (props.provinces || []).find(p => p.kode_provinsi == props.form.company_kode_provinsi)?.nama_provinsi;
    const kab = (props.kabupatens || []).find(k => k.kode_kabupaten == props.form.company_kode_kabupaten)?.nama_kabupaten;
    if (prov && kab) {
        return `Dalam Negeri · ${prov} - ${kab}`;
    }
    if (prov) {
        return `Dalam Negeri · ${prov}`;
    }
    return 'Dalam Negeri';
});

// Styling konsisten untuk input, select, dan textarea yang berstatus disabled/terkunci
const lockedInputClass = 'block w-full border border-gray-300 bg-gray-100 text-gray-800 rounded-xl px-4 py-2.5 text-sm font-medium cursor-not-allowed select-none shadow-2xs';

const selectCompany = (company) => {
    props.form.nama_perusahaan = company.nama_perusahaan;

    if (company.jenis_lokasi) {
        props.form.company_jenis_lokasi = company.jenis_lokasi;
        props.form.perusahaan_jenis_lokasi = company.jenis_lokasi;
    }
    if (company.kode_negara) {
        props.form.company_kode_negara = company.kode_negara;
        props.form.perusahaan_kode_negara = company.kode_negara;
    }
    if (company.kode_provinsi) {
        props.form.company_kode_provinsi = company.kode_provinsi;
        props.form.perusahaan_kode_provinsi = company.kode_provinsi;
    }
    if (company.kode_kabupaten) {
        props.form.company_kode_kabupaten = company.kode_kabupaten;
        props.form.perusahaan_kode_kabupaten = company.kode_kabupaten;
    }
    if (company.alamat) {
        props.form.company_alamat = company.alamat;
        props.form.perusahaan_alamat = company.alamat;
    }
    if (company.skala) {
        props.form.company_skala = company.skala;
        props.form.perusahaan_skala = company.skala;
    }
    if (company.bentuk_perusahaan) {
        props.form.company_bentuk = company.bentuk_perusahaan;
        props.form.perusahaan_bentuk = company.bentuk_perusahaan;
    }
    if (company.no_telp_fax) {
        props.form.company_no_telp_fax = company.no_telp_fax;
        props.form.perusahaan_no_telp_fax = company.no_telp_fax;
    }
    if (company.homepage) {
        props.form.company_homepage = company.homepage;
        props.form.perusahaan_homepage = company.homepage;
    }
    if (company.kode_pos) {
        props.form.zipcode = company.kode_pos;
        props.form.company_kode_pos = company.kode_pos;
        props.form.perusahaan_kode_pos = company.kode_pos;
    }
    if (company.jenis_perusahaan) {
        props.form.company_jenis_perusahaan = company.jenis_perusahaan;
        props.form.perusahaan_jenis_perusahaan = company.jenis_perusahaan;
    }
    if (company.jenis_perusahaan_lainnya) {
        props.form.company_jenis_perusahaan_lainnya = company.jenis_perusahaan_lainnya;
        props.form.perusahaan_jenis_perusahaan_lainnya = company.jenis_perusahaan_lainnya;
    }
    if (company.status_verifikasi) {
        props.form.company_status_verifikasi = company.status_verifikasi;
        props.form.perusahaan_status_verifikasi = company.status_verifikasi;
    }

    showCompanyDropdown.value = false;
    searchCompanyQuery.value = '';
};

// Fungsi Mengosongkan Pilihan Perusahaan
const clearCompany = () => {
    props.form.nama_perusahaan = '';
    props.form.company_alamat = '';
    props.form.perusahaan_alamat = '';
    props.form.company_skala = '';
    props.form.perusahaan_skala = '';
    props.form.company_bentuk = '';
    props.form.perusahaan_bentuk = '';
    props.form.company_no_telp_fax = '';
    props.form.perusahaan_no_telp_fax = '';
    props.form.company_homepage = '';
    props.form.perusahaan_homepage = '';
    props.form.zipcode = '';
    props.form.company_kode_pos = '';
    props.form.perusahaan_kode_pos = '';
    props.form.company_jenis_perusahaan = '';
    props.form.perusahaan_jenis_perusahaan = '';
    props.form.company_jenis_perusahaan_lainnya = '';
    props.form.perusahaan_jenis_perusahaan_lainnya = '';
    props.form.company_status_verifikasi = '';
    props.form.perusahaan_status_verifikasi = '';
    showCompanyDropdown.value = false;
    searchCompanyQuery.value = '';
};

// ============================================================================
// ============================================================================
// 6. MODAL TAMBAH PERUSAHAAN BARU (VUE MODAL TELEPORT)
// ============================================================================

// ref() digunakan untuk membuat data reaktif di Vue.
// Ketika nilai variabel ref berubah (diubah melalui .value), template HTML
// yang memakainya akan otomatis ter-update tanpa perlu reload halaman.

// Mengontrol apakah dialog modal tampil atau tertutup (true = terbuka, false = tertutup)
const isAddCompanyModalOpen = ref(false);

// Menandai apakah sistem sedang dalam proses mengirim data ke server
// Digunakan untuk menampilkan animasi putar (spinner) dan menonaktifkan tombol simpan
const isSubmittingCompany = ref(false);

// Menyimpan teks pesan galat (error message) jika validasi gagal atau server menolak request
const companyModalError = ref('');

// Objek form reaktif untuk menampung seluruh nilai input dari formulir modal
const newCompanyForm = ref({
    nama_perusahaan: '',
    bentuk_perusahaan: 'Perusahaan Terbatas',
    jenis_lokasi: 'Dalam Negeri',
    kode_negara: 'ID',
    kode_provinsi: '',
    kode_kabupaten: '',
    kode_pos: '',
    skala: 'Regional/Lokal',
    jenis_perusahaan: 'Perusahaan swasta',
    jenis_perusahaan_lainnya: '',
    alamat: '',
    no_telp_fax: '',
    homepage: '',
});

// computed() menghasilkan nilai turunan (kalkulasi otomatis).
// Sifat utamanya: hanya dihitung ulang bila data aslinya berubah (memiliki fungsi caching pintar).
// Di sini kita memfilter daftar negara agar tidak memasukkan "Indonesia" (karena khusus untuk Luar Negeri).
const foreignCountries = computed(() => {
    return (props.negaras || []).filter(n => (n.nama_negara || '').toLowerCase() !== 'indonesia');
});

// computed() ini menyaring daftar kabupaten/kota yang ada di database props
// agar HANYA menampilkan kabupaten yang berada di bawah provinsi yang dipilih pengguna saat ini.
const modalFilteredKabupatens = computed(() => {
    // Jika provinsi belum dipilih, kosongkan opsi kabupaten
    if (!newCompanyForm.value.kode_provinsi) {
        return [];
    }
    // Filter array kabupaten berdasarkan kecocokan ID provinsi
    return (props.kabupatens || []).filter(k => String(k.kode_provinsi) === String(newCompanyForm.value.kode_provinsi));
});

// watch() adalah pemantau (listener) yang berjalan setiap kali variabel tertentu berubah nilai.
// Di bawah ini, jika pengguna mengganti provinsi (kode_provinsi), watch akan memeriksa:
// apakah kabupaten yang dipilih sebelumnya masih ada di provinsi baru? Jika tidak, reset jadi kosong.
watch(() => newCompanyForm.value.kode_provinsi, (newProvId) => {
    const isKabupatenStillValid = modalFilteredKabupatens.value.some(k => String(k.id) === String(newCompanyForm.value.kode_kabupaten));
    if (!newProvId || !isKabupatenStillValid) {
        newCompanyForm.value.kode_kabupaten = '';
    }
});

// watch() ini memantau pilihan radio button 'jenis_lokasi' (Dalam Negeri vs Luar Negeri).
// Saat pengguna beralih opsi, kita reset field yang tidak relevan agar data tidak tercampur:
watch(() => newCompanyForm.value.jenis_lokasi, (newLokasi) => {
    if (newLokasi === 'Luar Negeri') {
        // Jika Luar Negeri, kita tidak butuh provinsi & kabupaten Indonesia
        newCompanyForm.value.kode_provinsi = '';
        newCompanyForm.value.kode_kabupaten = '';
        if (newCompanyForm.value.skala === 'Regional/Lokal') {
            newCompanyForm.value.skala = 'Internasional';
        }
    } else {
        // Jika Dalam Negeri, otomatis atur nama negara ke Indonesia
        newCompanyForm.value.kode_negara = 'ID';
        if (newCompanyForm.value.skala === 'Internasional') {
            newCompanyForm.value.skala = 'Nasional';
        }
    }
});

// Fungsi untuk membuka modal:
// 1. Menutup dropdown pencarian perusahaan agar tidak menutupi tampilan
// 2. Mengosongkan pesan error lama
// 3. Mengisi nilai awal form sesuai data terakhir dari profil alumni
// 4. Mengubah isAddCompanyModalOpen menjadi true
const openAddCompanyModal = () => {
    showCompanyDropdown.value = false;
    companyModalError.value = '';

    const initialJenisLokasi = props.form.company_jenis_lokasi || 'Dalam Negeri';
    const isLuar = initialJenisLokasi === 'Luar Negeri';

    newCompanyForm.value = {
        nama_perusahaan: '',
        bentuk_perusahaan: props.form.company_bentuk || props.form.perusahaan_bentuk || 'Perusahaan Terbatas',
        jenis_lokasi: initialJenisLokasi,
        kode_negara: isLuar ? (props.form.company_kode_negara || '') : 'ID',
        kode_provinsi: isLuar ? '' : (props.form.company_kode_provinsi || ''),
        kode_kabupaten: isLuar ? '' : (props.form.company_kode_kabupaten || ''),
        kode_pos: props.form.zipcode || props.form.company_kode_pos || '',
        skala: props.form.company_skala || (isLuar ? 'Internasional' : 'Regional/Lokal'),
        jenis_perusahaan: props.form.company_jenis_perusahaan || 'Perusahaan swasta',
        jenis_perusahaan_lainnya: props.form.company_jenis_perusahaan_lainnya || '',
        alamat: props.form.company_alamat || '',
        no_telp_fax: props.form.company_no_telp_fax || props.form.perusahaan_no_telp_fax || '',
        homepage: props.form.company_homepage || props.form.perusahaan_homepage || '',
    };

    isAddCompanyModalOpen.value = true;
};

// Fungsi untuk menutup modal:
// Mencegah penutupan jika proses simpan sedang berlangsung (isSubmittingCompany = true)
const closeAddCompanyModal = () => {
    if (isSubmittingCompany.value) {
        return;
    }
    isAddCompanyModalOpen.value = false;
    companyModalError.value = '';
};

// async/await: Sintaks JavaScript modern untuk menjalankan proses asinkron (seperti panggilan jaringan).
// Kata kunci async menandakan fungsi ini mengembalikan Promise, dan await menunda eksekusi
// hingga respons server tiba, tanpa memblokir antarmuka pengguna (UI tetap responsif).
const submitNewCompany = async () => {
    const formVal = newCompanyForm.value;
    const nama = formVal.nama_perusahaan ? formVal.nama_perusahaan.trim() : '';
    const isLuar = formVal.jenis_lokasi === 'Luar Negeri';

    // 1. Validasi input nama perusahaan
    if (!nama) {
        companyModalError.value = 'Nama Perusahaan / Instansi wajib diisi.';
        return;
    }

    // 2. Validasi wilayah berdasarkan jenis lokasi
    if (isLuar) {
        if (!formVal.kode_negara || formVal.kode_negara === 'ID' || !foreignCountries.value.some(n => n.kode_negara === formVal.kode_negara)) {
            companyModalError.value = 'Silakan pilih salah satu negara di luar negeri dari daftar master resmi.';
            return;
        }
    } else {
        if (!formVal.kode_provinsi) {
            companyModalError.value = 'Provinsi Perusahaan wajib dipilih.';
            return;
        }
        if (!formVal.kode_kabupaten) {
            companyModalError.value = 'Kabupaten / Kota Perusahaan wajib dipilih.';
            return;
        }
    }

    // Mengaktifkan status loading dan menghapus error sebelumnya
    isSubmittingCompany.value = true;
    companyModalError.value = '';

    try {
        // Mengambil token CSRF Laravel dari tag <meta> agar request POST diizinkan oleh server
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Melakukan HTTP POST request menggunakan browser fetch API
        const res = await fetch('/alumni/company', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                nama_perusahaan: nama,
                bentuk_perusahaan: formVal.bentuk_perusahaan || null,
                jenis_lokasi: formVal.jenis_lokasi,
                kode_negara: isLuar ? formVal.kode_negara : 'ID',
                kode_provinsi: isLuar ? null : formVal.kode_provinsi,
                kode_kabupaten: isLuar ? null : formVal.kode_kabupaten,
                kode_pos: formVal.kode_pos ? formVal.kode_pos.trim() : null,
                skala: formVal.skala || (isLuar ? 'Internasional' : 'Regional/Lokal'),
                jenis_perusahaan: formVal.jenis_perusahaan || 'Perusahaan swasta',
                jenis_perusahaan_lainnya: formVal.jenis_perusahaan === 'Lainnya' ? (formVal.jenis_perusahaan_lainnya ? formVal.jenis_perusahaan_lainnya.trim() : null) : null,
                alamat: formVal.alamat ? formVal.alamat.trim() : '',
                no_telp_fax: formVal.no_telp_fax ? formVal.no_telp_fax.trim() : null,
                homepage: formVal.homepage ? formVal.homepage.trim() : null,
            }),
        });

        // Mengurai respons server dari format JSON menjadi objek JavaScript
        const data = await res.json();
        if (!res.ok || !data.success || !data.company) {
            companyModalError.value = data.message || 'Gagal menyimpan data perusahaan.';
            return;
        }

        // Jika berhasil, tambahkan perusahaan baru ke daftar lokal dropdown
        const newCompany = data.company;
        localCompanies.value.unshift(newCompany);

        props.form.nama_perusahaan = newCompany.nama_perusahaan;
        props.form.company_bentuk = newCompany.bentuk_perusahaan || '';
        props.form.perusahaan_bentuk = props.form.company_bentuk;
        props.form.company_jenis_lokasi = newCompany.jenis_lokasi || (newCompany.kode_negara && newCompany.kode_negara !== 'ID' ? 'Luar Negeri' : 'Dalam Negeri');
        props.form.perusahaan_jenis_lokasi = props.form.company_jenis_lokasi;
        props.form.company_kode_negara = newCompany.kode_negara || (props.form.company_jenis_lokasi === 'Luar Negeri' ? '' : 'ID');
        props.form.perusahaan_kode_negara = props.form.company_kode_negara;
        props.form.company_kode_provinsi = newCompany.kode_provinsi;
        props.form.perusahaan_kode_provinsi = newCompany.kode_provinsi;
        props.form.company_kode_kabupaten = newCompany.kode_kabupaten;
        props.form.perusahaan_kode_kabupaten = newCompany.kode_kabupaten;
        props.form.company_alamat = newCompany.alamat || '';
        props.form.company_skala = newCompany.skala === 'Lokal' ? 'Regional/Lokal' : (newCompany.skala || 'Regional/Lokal');
        props.form.perusahaan_skala = props.form.company_skala;
        props.form.zipcode = newCompany.kode_pos || '';
        props.form.company_kode_pos = newCompany.kode_pos || '';
        props.form.perusahaan_kode_pos = newCompany.kode_pos || '';
        props.form.company_jenis_perusahaan = newCompany.jenis_perusahaan || '';
        props.form.perusahaan_jenis_perusahaan = props.form.company_jenis_perusahaan;
        props.form.company_jenis_perusahaan_lainnya = newCompany.jenis_perusahaan_lainnya || '';
        props.form.perusahaan_jenis_perusahaan_lainnya = props.form.company_jenis_perusahaan_lainnya;
        props.form.company_no_telp_fax = newCompany.no_telp_fax || '';
        props.form.perusahaan_no_telp_fax = props.form.company_no_telp_fax;
        props.form.company_homepage = newCompany.homepage || '';
        props.form.perusahaan_homepage = props.form.company_homepage;
        props.form.company_status_verifikasi = 'Menunggu Verifikasi';

        isAddCompanyModalOpen.value = false;

        Swal.fire({
            icon: 'success',
            title: 'Berhasil Ditambahkan',
            text: `Perusahaan "${newCompany.nama_perusahaan}" berhasil ditambahkan dan dipilih.`,
            confirmButtonColor: '#005B3C',
            timer: 2500,
            timerProgressBar: true,
        });
    } catch (error) {
        companyModalError.value = 'Gagal menghubungi server. Silakan coba lagi.';
    } finally {
        isSubmittingCompany.value = false;
    }
};

// ============================================================================
// 6B. MODAL USULAN PEMBARUAN DATA KANTOR PUSAT (REQUEST UPDATE HEAD OFFICE)
// ============================================================================
const isRequestUpdateModalOpen = ref(false);
const isSubmittingRequestUpdate = ref(false);
const requestUpdateError = ref('');
const requestUpdateForm = ref({
    nama_perusahaan: '',
    jenis_lokasi: 'Dalam Negeri',
    kode_negara: 'ID',
    kode_provinsi: '',
    kode_kabupaten: '',
    jenis_perusahaan: 'Perusahaan swasta',
    jenis_perusahaan_lainnya: '',
    bentuk_perusahaan: 'Perusahaan Terbatas',
    skala: 'Regional/Lokal',
    alamat: '',
    kode_pos: '',
    no_telp_fax: '',
    homepage: '',
});

// Filter kabupaten untuk modal usulan pembaruan
const requestUpdateFilteredKabupatens = computed(() => {
    if (!requestUpdateForm.value.kode_provinsi) {
        return [];
    }
    return (props.kabupatens || []).filter(k => String(k.kode_provinsi) === String(requestUpdateForm.value.kode_provinsi));
});

watch(() => requestUpdateForm.value.kode_provinsi, (newProvId) => {
    const isKabValid = requestUpdateFilteredKabupatens.value.some(k => String(k.kode_kabupaten) === String(requestUpdateForm.value.kode_kabupaten));
    if (!newProvId || !isKabValid) {
        requestUpdateForm.value.kode_kabupaten = '';
    }
});

watch(() => requestUpdateForm.value.jenis_lokasi, (newLokasi) => {
    if (newLokasi === 'Luar Negeri') {
        requestUpdateForm.value.kode_provinsi = '';
        requestUpdateForm.value.kode_kabupaten = '';
        if (!requestUpdateForm.value.kode_negara || requestUpdateForm.value.kode_negara === 'ID') {
            requestUpdateForm.value.kode_negara = '';
        }
    } else {
        requestUpdateForm.value.kode_negara = 'ID';
    }
});

const openRequestUpdateModal = () => {
    requestUpdateError.value = '';
    const initialJenisLokasi = props.form.company_jenis_lokasi || 'Dalam Negeri';
    const isLuar = initialJenisLokasi === 'Luar Negeri';

    requestUpdateForm.value = {
        nama_perusahaan: props.form.nama_perusahaan || '',
        jenis_lokasi: initialJenisLokasi,
        kode_negara: isLuar ? (props.form.company_kode_negara || '') : 'ID',
        kode_provinsi: isLuar ? '' : (props.form.company_kode_provinsi || ''),
        kode_kabupaten: isLuar ? '' : (props.form.company_kode_kabupaten || ''),
        jenis_perusahaan: props.form.company_jenis_perusahaan || props.form.perusahaan_jenis_perusahaan || 'Perusahaan swasta',
        jenis_perusahaan_lainnya: props.form.company_jenis_perusahaan_lainnya || props.form.perusahaan_jenis_perusahaan_lainnya || '',
        bentuk_perusahaan: props.form.company_bentuk || props.form.perusahaan_bentuk || 'Perusahaan Terbatas',
        skala: props.form.company_skala || props.form.perusahaan_skala || 'Regional/Lokal',
        alamat: props.form.company_alamat || props.form.perusahaan_alamat || '',
        kode_pos: props.form.company_kode_pos || props.form.perusahaan_kode_pos || props.form.zipcode || '',
        no_telp_fax: props.form.company_no_telp_fax || props.form.perusahaan_no_telp_fax || '',
        homepage: props.form.company_homepage || props.form.perusahaan_homepage || '',
    };
    isRequestUpdateModalOpen.value = true;
};

const closeRequestUpdateModal = () => {
    if (isSubmittingRequestUpdate.value) return;
    isRequestUpdateModalOpen.value = false;
    requestUpdateError.value = '';
};

const submitRequestUpdate = async () => {
    const selectedCompany = localCompanies.value.find(c => c.nama_perusahaan === props.form.nama_perusahaan);
    const companyId = selectedCompany?.id || props.form?.perusahaan_id;

    if (!companyId) {
        requestUpdateError.value = 'Data perusahaan kantor pusat belum dipilih atau belum tersimpan di master.';
        return;
    }

    isSubmittingRequestUpdate.value = true;
    requestUpdateError.value = '';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/alumni/company/request-update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                perusahaan_id: companyId,
                nama_perusahaan: requestUpdateForm.value.nama_perusahaan?.trim() || null,
                jenis_lokasi: requestUpdateForm.value.jenis_lokasi,
                kode_negara: requestUpdateForm.value.kode_negara || null,
                kode_provinsi: requestUpdateForm.value.kode_provinsi || null,
                kode_kabupaten: requestUpdateForm.value.kode_kabupaten || null,
                jenis_perusahaan: requestUpdateForm.value.jenis_perusahaan || null,
                jenis_perusahaan_lainnya: requestUpdateForm.value.jenis_perusahaan_lainnya?.trim() || null,
                bentuk_perusahaan: requestUpdateForm.value.bentuk_perusahaan || null,
                skala: requestUpdateForm.value.skala || null,
                alamat: requestUpdateForm.value.alamat?.trim() || null,
                kode_pos: requestUpdateForm.value.kode_pos?.trim() || null,
                no_telp_fax: requestUpdateForm.value.no_telp_fax?.trim() || null,
                homepage: requestUpdateForm.value.homepage?.trim() || null,
            }),
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            requestUpdateError.value = data.message || 'Gagal mengajukan usulan pembaruan.';
            return;
        }

        isRequestUpdateModalOpen.value = false;
        Swal.fire({
            icon: 'success',
            title: 'Usulan Berhasil Diajukan',
            text: data.message || 'Usulan pembaruan data kantor pusat telah dicatat dan akan ditinjau oleh administrator.',
            confirmButtonColor: '#0D542B',
        });
    } catch (err) {
        requestUpdateError.value = 'Terjadi kesalahan sistem saat mengirim usulan.';
    } finally {
        isSubmittingRequestUpdate.value = false;
    }
};

// Click outside handler untuk menutup dropdown
const handleClickOutside = (event) => {
    if (provinsiContainerRef.value && !provinsiContainerRef.value.contains(event.target)) {
        showProvinsiDropdown.value = false;
    }
    if (kabupatenContainerRef.value && !kabupatenContainerRef.value.contains(event.target)) {
        showKabupatenDropdown.value = false;
    }
    if (negaraContainerRef.value && !negaraContainerRef.value.contains(event.target)) {
        showNegaraDropdown.value = false;
    }
    if (companyContainerRef.value && !companyContainerRef.value.contains(event.target)) {
        showCompanyDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="space-y-8">

        <!-- ================================================================= -->
        <!-- CARD 1: MEDIA SOSIAL, KEAHLIAN & STATUS AKTIVITAS SAAT INI       -->
        <!-- ================================================================= -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-xs border border-gray-100">
            <div class="flex items-center justify-between mb-6 pb-3 border-b border-gray-100">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-[#0D542B] rounded-full inline-block"></span>
                    Media Sosial & Profil Profesional
                </h2>
                <span class="text-xs text-gray-400 font-medium">Jejaring alumni & karier</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Input: LinkedIn URL -->
                <div v-if="isQuestionVisible('BIO_LINKEDIN_URL')">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        {{ getQuestionLabel('BIO_LINKEDIN_URL', 'LinkedIn Profil URL') }} <span v-if="isQuestionRequired('BIO_LINKEDIN_URL')" class="text-rose-500 font-bold">*</span>
                    </label>
                    <input
                        type="url"
                        v-model="form.linkedin_url"
                        autocomplete="off"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        placeholder="https://linkedin.com/in/username"
                    />
                </div>

                <!-- Input: LinkedIn Username -->
                <div v-if="isQuestionVisible('BIO_LINKEDIN_USER')">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        {{ getQuestionLabel('BIO_LINKEDIN_USER', 'LinkedIn Username') }} <span v-if="isQuestionRequired('BIO_LINKEDIN_USER')" class="text-rose-500 font-bold">*</span>
                    </label>
                    <input
                        type="text"
                        v-model="form.linkedin_username"
                        autocomplete="off"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        placeholder="username_linkedin"
                    />
                </div>

                <!-- Input: Instagram URL -->
                <div v-if="isQuestionVisible('BIO_INSTAGRAM_URL')">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        {{ getQuestionLabel('BIO_INSTAGRAM_URL', 'Instagram Profil URL') }} <span v-if="isQuestionRequired('BIO_INSTAGRAM_URL')" class="text-rose-500 font-bold">*</span>
                    </label>
                    <input
                        type="url"
                        v-model="form.instagram_url"
                        autocomplete="off"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        placeholder="https://instagram.com/username"
                    />
                </div>

                <!-- Input: Facebook URL -->
                <div v-if="isQuestionVisible('BIO_FACEBOOK_URL')">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        {{ getQuestionLabel('BIO_FACEBOOK_URL', 'Facebook Profil URL') }} <span v-if="isQuestionRequired('BIO_FACEBOOK_URL')" class="text-rose-500 font-bold">*</span>
                    </label>
                    <input
                        type="url"
                        v-model="form.facebook_url"
                        autocomplete="off"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        placeholder="https://facebook.com/username"
                    />
                </div>

                <!-- Input: Keahlian (Skills) -->
                <div v-if="isQuestionVisible('BIO_SKILLS')" class="md:col-span-2">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5 flex items-center justify-between">
                        <span>{{ getQuestionLabel('BIO_SKILLS', 'Keahlian (Skills)') }} <span v-if="isQuestionRequired('BIO_SKILLS')" class="text-rose-500 font-bold">*</span></span>
                        <span class="text-[11px] font-normal text-gray-500">Sinkron dengan LinkedIn Skills</span>
                    </label>
                    <input
                        type="text"
                        v-model="form.skills"
                        autocomplete="off"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        placeholder="Contoh: Software Engineering, Data Science, Python, Project Management..."
                    />
                </div>

                <!-- Input: Pengalaman Kerja (Experience) -->
                <div v-if="isQuestionVisible('BIO_EXPERIENCE')" class="md:col-span-2">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5 flex items-center justify-between">
                        <span>{{ getQuestionLabel('BIO_EXPERIENCE', 'Pengalaman Kerja (Experience)') }} <span v-if="isQuestionRequired('BIO_EXPERIENCE')" class="text-rose-500 font-bold">*</span></span>
                        <span class="text-[11px] font-normal text-gray-500">Sinkron dengan LinkedIn Experiences</span>
                    </label>
                    <input
                        type="text"
                        v-model="form.experience"
                        autocomplete="off"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        placeholder="Contoh: Software Engineer di PT Telkom Indonesia (2022 - Saat Ini)..."
                    />
                </div>

                <!-- Pilihan Kategori Peran / Aktivitas Pekerjaan Saat Ini -->
                <div class="md:col-span-2 pt-2">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700">
                            {{ getQuestionLabel('F8', 'Status Pekerjaan / Aktivitas Utama') }} <span v-if="isQuestionRequired('F8')" class="text-rose-500 font-bold">*</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-2">
                        <!-- Opsi 1: Pekerja / Karyawan -->
                        <div
                            @click="selectRole('Pekerja')"
                            class="p-4 rounded-2xl border cursor-pointer transition-all flex flex-col justify-between relative group"
                            :class="form.kategori_pekerjaan === 'Pekerja' ? 'border-[#0D542B] bg-slate-50 text-gray-900 shadow-xs ring-1 ring-[#0D542B]' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                        >
                            <div class="flex items-center justify-between">
                                <div class="font-bold text-sm" :class="form.kategori_pekerjaan === 'Pekerja' ? 'text-[#0D542B]' : 'text-gray-800'">
                                    Pekerja / Karyawan
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1.5 leading-relaxed">Bekerja di instansi pemerintah, swasta, BUMN, LSM, dsb.</div>
                        </div>

                        <!-- Opsi 2: Wirausaha / Founder -->
                        <div
                            @click="selectRole('Wiraswasta')"
                            class="p-4 rounded-2xl border cursor-pointer transition-all flex flex-col justify-between relative group"
                            :class="form.kategori_pekerjaan === 'Wiraswasta' ? 'border-[#0D542B] bg-slate-50 text-gray-900 shadow-xs ring-1 ring-[#0D542B]' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                        >
                            <div class="flex items-center justify-between">
                                <div class="font-bold text-sm" :class="form.kategori_pekerjaan === 'Wiraswasta' ? 'text-[#0D542B]' : 'text-gray-800'">
                                    Wirausaha / Founder
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1.5 leading-relaxed">Mendirikan bisnis sendiri, startup, atau freelance</div>
                        </div>

                        <!-- Opsi 3: Tidak Bekerja / Mencari Kerja -->
                        <div
                            @click="selectRole('Tidak Bekerja')"
                            class="p-4 rounded-2xl border cursor-pointer transition-all flex flex-col justify-between relative group"
                            :class="['Tidak Bekerja', 'Mencari Kerja', 'Belum Bekerja', 'Belum Memungkinkan Bekerja'].includes(form.kategori_pekerjaan) ? 'border-[#0D542B] bg-slate-50 text-gray-900 shadow-xs ring-1 ring-[#0D542B]' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                        >
                            <div class="flex items-center justify-between">
                                <div class="font-bold text-sm" :class="['Tidak Bekerja', 'Mencari Kerja', 'Belum Bekerja', 'Belum Memungkinkan Bekerja'].includes(form.kategori_pekerjaan) ? 'text-[#0D542B]' : 'text-gray-800'">
                                    Tidak Bekerja / Mencari Kerja
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1.5 leading-relaxed">Belum memungkinkan bekerja atau sedang mencari kerja</div>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2 space-y-2">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700">
                        {{ getQuestionLabel('BIO_BIDANG_DIVISI', 'Bidang Pekerjaan') }} <span v-if="isQuestionRequired('BIO_BIDANG_DIVISI', false)" class="text-rose-500 font-bold">*</span>
                    </label>
                    <select
                        v-model="bidangPekerjaanSelection"
                        @change="syncBidangPekerjaan"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                    >
                        <option value="">-- Pilih Bidang Pekerjaan / Divisi --</option>
                        <option v-for="opt in bidangPekerjaanOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <input
                        v-if="isOtherOptionValue(bidangPekerjaanSelection)"
                        v-model="form.bidang_pekerjaan_lainnya"
                        @input="syncBidangPekerjaan"
                        type="text"
                        placeholder="Tuliskan bidang pekerjaan atau divisi"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                    />
                    <p class="text-[11px] text-gray-400">
                        {{ getQuestionHint('BIO_BIDANG_DIVISI', 'Pilih rumpun bidang pekerjaan. Jika belum tersedia, pilih Lainnya dan tuliskan sendiri.') }}
                    </p>
                </div>

                <!-- Input divisi terpisah dari rumpun bidang pekerjaan untuk kebutuhan direktori dan analitik. -->
                <div class="md:col-span-2 space-y-2">
                    <label for="divisi" class="block text-xs sm:text-sm font-bold text-gray-700">
                        Divisi / Unit Kerja
                    </label>
                    <input
                        id="divisi"
                        v-model="form.divisi"
                        type="text"
                        autocomplete="organization"
                        maxlength="150"
                        placeholder="Contoh: Divisi Keuangan, Human Capital, Product Development"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                    />
                    <p class="text-[11px] text-gray-400">
                        Isi unit atau divisi tempat bekerja saat ini. Lokasi cabang tetap dicatat pada domisili alumni.
                    </p>
                </div>

                <!-- Jika Karyawan/Pekerja: Posisi Jabatan Struktural (F2G) -->
                <div v-if="form.kategori_pekerjaan === 'Pekerja'" class="md:col-span-2 space-y-2">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700">
                        {{ getQuestionLabel('F2G', 'Posisi Jabatan Struktural (F2G)') }} <span v-if="isQuestionRequired('F2G')" class="text-rose-500 font-bold">*</span>
                    </label>
                    <div v-if="posisiJabatanOptions.length > 0" class="space-y-2">
                        <select
                            v-model="posisiJabatanSelection"
                            @change="syncPosisiJabatan"
                            class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        >
                            <option value="">-- Pilih Posisi / Jabatan --</option>
                            <option v-for="opt in posisiJabatanOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                        <input
                            v-if="isOtherOptionValue(posisiJabatanSelection)"
                            type="text"
                            v-model="posisiJabatanKustom"
                            @input="syncPosisiJabatan"
                            placeholder="Tuliskan posisi jabatan struktural spesifik Anda..."
                            class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        />
                    </div>
                    <div v-else>
                        <input
                            type="text"
                            v-model="form.posisi_jabatan"
                            placeholder="Contoh: Dosen, Software Engineer, Supervisor, Manager, Staff..."
                            class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        />
                    </div>
                    <p class="text-[11px] text-gray-400">
                        {{ getQuestionHint('F2G', 'Tuliskan posisi jabatan struktural atau fungsional Anda di instansi/perusahaan tempat bekerja.') }}
                    </p>
                </div>

                <!-- Jika Wiraswasta: Posisi / Jabatan Wiraswasta (F5C) -->
                <div v-else-if="form.kategori_pekerjaan === 'Wiraswasta'" class="md:col-span-2 space-y-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                            {{ getQuestionLabel('F5C', 'Posisi / Jabatan Wiraswasta & Startup (F5C)') }} <span v-if="isQuestionRequired('F5C')" class="text-rose-500 font-bold">*</span>
                        </label>
                        <select
                            v-model="form.posisi_wiraswasta"
                            class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        >
                            <option value="">-- Pilih Posisi / Jabatan Wiraswasta --</option>
                            <option v-for="opt in posisiWiraswastaOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Input Tambahan jika memilih Lainnya -->
                    <div v-if="isOtherOptionValue(form.posisi_wiraswasta)">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">Sebutkan Jabatan Wiraswasta Lainnya</label>
                        <input
                            type="text"
                            v-model="form.posisi_wiraswasta_lainnya"
                            class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                            placeholder="Contoh: Managing Partner, Content Creator..."
                        />
                    </div>
                </div>

                <!-- Opsi Tambahan / Toggle: Sedang Melanjutkan Studi -->
                <div class="md:col-span-2 pt-1">
                    <div
                        @click="toggleMelanjutkanStudi"
                        class="p-4 rounded-2xl border cursor-pointer transition-all flex items-center justify-between group"
                        :class="isMelanjutkanStudi ? 'border-[#0D542B] bg-slate-50 shadow-xs' : 'border-gray-200 bg-gray-50/60 hover:border-gray-300'"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-xl flex items-center justify-center transition-colors"
                                :class="isMelanjutkanStudi ? 'bg-[#0D542B] text-white shadow-2xs' : 'bg-gray-200 text-gray-500'"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"></path></svg>
                            </div>
                            <div>
                                <div class="font-bold text-sm" :class="isMelanjutkanStudi ? 'text-[#0D542B]' : 'text-gray-800'">
                                    Sedang Melanjutkan Studi (S1 / S2 / S3 / Profesi / Spesialis)
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    Aktifkan jika Anda saat ini sedang menempuh pendidikan lanjut (bisa sambil bekerja atau berwirausaha)
                                </div>
                            </div>
                        </div>

                        <!-- Toggle Switch Visual -->
                        <div class="relative inline-flex items-center shrink-0 ml-4">
                            <div
                                class="w-11 h-6 rounded-full transition-colors duration-200 ease-in-out relative"
                                :class="isMelanjutkanStudi ? 'bg-[#0D542B]' : 'bg-gray-300'"
                            >
                                <div
                                    class="w-5 h-5 bg-white rounded-full shadow-md transform transition-transform duration-200 ease-in-out absolute top-0.5 left-0.5"
                                    :class="isMelanjutkanStudi ? 'translate-x-5' : 'translate-x-0'"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail Studi Lanjut (F18 Standar Dikti) Jika Toggle Aktif -->
                <div v-if="isMelanjutkanStudi" class="md:col-span-2 space-y-4 bg-gray-50/60 p-5 rounded-2xl border border-gray-200">
                    <div class="pb-2 border-b border-gray-200 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Informasi Studi Lanjut</h3>
                            <p class="text-xs text-gray-500">Lengkapi data jenjang, perguruan tinggi, dan program studi yang sedang Anda tempuh</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                                {{ getQuestionLabel('BIO_PEND_TINGKAT', 'Jenjang Pendidikan') }} <span v-if="isQuestionRequired('BIO_PEND_TINGKAT')" class="text-rose-500 font-bold">*</span>
                            </label>
                            <select
                                v-model="pendidikanTingkatSelection"
                                @change="syncPendidikanTingkat"
                                class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                            >
                                <option value="">-- Pilih Tingkat / Jenjang --</option>
                                <option v-for="option in pendidikanTingkatOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <input
                                v-if="isOtherOptionValue(pendidikanTingkatSelection)"
                                v-model="pendidikanTingkatLainnya"
                                @input="syncPendidikanTingkat"
                                type="text"
                                placeholder="Tuliskan jenjang pendidikan dari sumber eksternal"
                                class="block w-full mt-2 border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                                {{ getQuestionLabel('BIO_PEND_KAMPUS', 'Perguruan Tinggi') }} <span v-if="isQuestionRequired('BIO_PEND_KAMPUS', false)" class="text-rose-500 font-bold">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="form.perguruan_tinggi"
                                class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                                placeholder="Contoh: Universitas Gadjah Mada"
                            />
                        </div>

                        <div>
                            <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                                {{ getQuestionLabel('BIO_PEND_PRODI', 'Program Studi') }} <span v-if="isQuestionRequired('BIO_PEND_PRODI', false)" class="text-rose-500 font-bold">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="form.pendidikan_prodi"
                                class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                                placeholder="Contoh: Magister Informatika"
                            />
                        </div>
                    </div>
                </div>

                <!-- Input Penghasilan / Take Home Pay (Format Nominal Bersih Tanpa Pengali Otomatis) -->
                <div v-if="isQuestionVisible('F505')" class="md:col-span-2">
                    <div class="flex items-center justify-between mb-1.5 flex-wrap gap-1">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700 !mb-0">
                            {{ getQuestionLabel('F505', 'Rata-rata Pendapatan per Bulan (Take Home Pay)') }} <span v-if="isQuestionRequired('F505')" class="text-rose-500 font-bold">*</span>
                        </label>
                        <span v-if="form.gaji && form.gaji >= 1000" class="text-xs text-[#0D542B] font-bold inline-flex items-center gap-1">
                            Terbaca: <strong>{{ formatRupiah(form.gaji) }}</strong> / bulan
                        </span>
                        <span v-else-if="form.gaji && form.gaji > 0 && form.gaji < 1000" class="text-xs text-rose-600 font-bold inline-flex items-center gap-1">
                            Minimal Rp 1.000 (tidak bisa disimpan jika di bawah 1.000)
                        </span>
                    </div>
                    <div
                        class="relative flex items-center rounded-xl border border-gray-300 bg-white text-gray-900 shadow-xs focus-within:border-[#0D542B] focus-within:ring-1 focus-within:ring-[#0D542B] transition-colors overflow-hidden"
                    >
                        <span class="pl-4 pr-1 font-bold text-gray-400 select-none text-base">Rp</span>
                        <input
                            type="text"
                            v-model="formattedGaji"
                            placeholder="Contoh: 5.000.000"
                            class="w-full py-3.5 px-3 bg-transparent font-medium font-mono border-0 focus:ring-0 text-base sm:text-lg text-gray-900 placeholder-gray-300 tracking-wide outline-none"
                        />
                        <span class="pr-4 text-xs font-semibold text-gray-400 select-none">/ bulan</span>
                    </div>
                    <p v-if="form.gaji && form.gaji > 0 && form.gaji < 1000" class="text-[11px] text-rose-500 mt-1 font-semibold">
                        Nominal gaji/pendapatan tidak dapat disimpan di bawah Rp 1.000 (minimal ribuan). Silakan lengkapi nominal.
                    </p>
                    <p v-else class="text-[11px] text-gray-400 mt-1">
                        Format titik ribuan otomatis. Wajib diisi (minimal Rp 1.000).
                    </p>
                </div>

                <!-- Khusus Alumni Teologi / Filsafat Keilahian -->
                <div v-if="alumniData?.prodi?.kode_prodi === '31' || alumniData?.nim?.startsWith('31')" class="md:col-span-2 bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">Jenis Pekerjaan (Khusus Alumni Filsafat Keilahian)</label>
                    <div class="flex gap-6 mt-2">
                        <label class="flex items-center space-x-2 cursor-pointer text-sm font-medium text-gray-700">
                            <input type="radio" v-model="form.jenis_pekerjaan" value="Gerejawi" class="text-[#0D542B] focus:ring-[#0D542B]">
                            <span>Gerejawi</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer text-sm font-medium text-gray-700">
                            <input type="radio" v-model="form.jenis_pekerjaan" value="Non Gerejawi" class="text-[#0D542B] focus:ring-[#0D542B]">
                            <span>Non Gerejawi</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- CARD TAMBAHAN: PERTANYAAN PROFIL DARI SUPERADMIN                 -->
        <!-- ================================================================= -->
        <div v-if="profileQuestions.length > 0" class="bg-white rounded-2xl p-6 sm:p-8 shadow-xs border border-gray-100">
            <div class="flex items-center justify-between mb-6 pb-3 border-b border-gray-100">
                <div>
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-1.5 h-5 bg-[#0D542B] rounded-full inline-block"></span>
                        Informasi Tambahan Profil
                    </h2>
                    <p class="text-xs text-gray-400 mt-1">Pertanyaan tambahan yang dikonfigurasi oleh Superadmin.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div v-for="question in profileQuestions" :key="question.id" class="space-y-2">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700">
                        {{ question.subpertanyaan }}
                        <span v-if="question.wajib" class="text-rose-500 font-bold">*</span>
                    </label>

                    <select
                        v-if="isProfileQuestionSelect(question)"
                        v-model="form.profile_answers[question.id]"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                    >
                        <option value="">-- Pilih jawaban --</option>
                        <option v-for="option in question.detils" :key="option.id" :value="option.option_text">
                            {{ option.option_text }}
                        </option>
                    </select>

                    <input
                        v-else
                        v-model="form.profile_answers[question.id]"
                        type="text"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        placeholder="Tulis jawaban"
                    />

                    <input
                        v-if="needsProfileQuestionCustomText(question)"
                        v-model="form.profile_answer_custom[question.id]"
                        type="text"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        placeholder="Tuliskan jawaban lainnya"
                    />

                    <p v-if="question.keterangan" class="text-[11px] text-gray-400">{{ question.keterangan }}</p>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- CARD 2: DATA PERUSAHAAN / INSTANSI TEMPAT BEKERJA (MASTER HEAD OFFICE) -->
        <!-- ================================================================= -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-xs border border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 mb-6 border-b border-gray-100 gap-3">
                <div>
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-1.5 h-5 bg-[#0D542B] rounded-full inline-block"></span>
                        Data {{ form.kategori_pekerjaan === 'Wiraswasta' ? 'Usaha / Bisnis' : 'Perusahaan / Tempat Bekerja' }} (Master Head Office)
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">Cari langsung perusahaan kantor pusat terdaftar atau ajukan pendaftaran baru</p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="showWilayahFilter = !showWilayahFilter"
                        class="px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer flex items-center gap-1.5 shadow-2xs"
                        :class="showWilayahFilter ? 'bg-emerald-50 border-emerald-300 text-[#0D542B]' : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100'"
                    >
                        <span>{{ showWilayahFilter ? 'Tutup Filter Wilayah' : 'Filter Wilayah Kantor Pusat' }}</span>
                    </button>
                </div>
            </div>

            <div class="space-y-5">
                <!-- ============================================================= -->
                <!-- 1. PENCARIAN LANGSUNG NAMA PERUSAHAAN (PRIORITAS UTAMA)       -->
                <!-- ============================================================= -->
                <div class="relative" ref="companyContainerRef">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700">
                            Nama {{ form.kategori_pekerjaan === 'Wiraswasta' ? 'Usaha / Perusahaan' : 'Perusahaan / Instansi' }} (Master Head Office) <span class="text-rose-500 font-bold">*</span>
                        </label>
                        <span v-if="form.nama_perusahaan" class="text-xs font-semibold text-[#0D542B]">
                            Perusahaan Terpilih
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <div
                            @click="showCompanyDropdown = !showCompanyDropdown; if (showCompanyDropdown) searchCompanyQuery = '';"
                            class="flex-1 rounded-xl border border-gray-300 bg-white text-gray-900 px-4 py-3 text-sm font-medium cursor-pointer flex justify-between items-center shadow-xs hover:border-[#0D542B] transition-colors"
                        >
                            <span v-if="form.nama_perusahaan" class="text-gray-900 font-semibold truncate">{{ form.nama_perusahaan }}</span>
                            <span v-else class="text-gray-400">
                                Cari nama perusahaan kantor pusat (contoh: Dinas Komunikasi dan Informatika DIY, Bank Mandiri, Google)...
                            </span>
                            <svg class="w-4 h-4 text-gray-400 shrink-0 ml-2 transition-transform duration-200" :class="{'rotate-180': showCompanyDropdown}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>

                        <!-- Tombol Kosongkan Pilihan (Jika Sudah Terpilih) -->
                        <button
                            v-if="form.nama_perusahaan"
                            type="button"
                            @click="clearCompany"
                            class="px-3.5 py-3 bg-gray-100 hover:bg-rose-50 text-gray-600 hover:text-rose-600 rounded-xl transition-colors shrink-0 text-xs font-bold border border-gray-200 cursor-pointer flex items-center gap-1 shadow-2xs"
                            title="Kosongkan Pilihan Perusahaan"
                        >
                            <span>Kosongkan</span>
                        </button>

                        <!-- Tombol Tambah Perusahaan Baru -->
                        <button
                            type="button"
                            @click="openAddCompanyModal"
                            class="px-4 py-3 bg-[#0D542B] hover:bg-[#093d1f] text-white rounded-xl transition-colors shrink-0 text-xs font-bold flex items-center gap-1 shadow-xs cursor-pointer"
                            title="Tambah Perusahaan Baru"
                        >
                            <span>+ Tambah Baru</span>
                        </button>
                    </div>

                    <!-- Dropdown List Perusahaan -->
                    <div v-if="showCompanyDropdown" class="absolute z-50 w-full mt-1.5 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden">
                        <div class="p-2.5 border-b border-gray-100 bg-gray-50/80">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </div>
                                <input
                                    type="text"
                                    v-model="searchCompanyQuery"
                                    class="w-full pl-9 pr-3 py-1.5 border border-gray-200 rounded-lg text-sm bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] outline-none"
                                    placeholder="Ketik untuk mencari nama perusahaan, kota, atau negara..."
                                    autofocus
                                >
                            </div>
                        </div>

                        <ul class="max-h-64 overflow-y-auto divide-y divide-gray-50">
                            <!-- Opsi Kosongkan di Dalam List Dropdown -->
                            <li
                                v-if="form.nama_perusahaan"
                                @click="clearCompany"
                                class="px-4 py-2.5 hover:bg-rose-50 text-rose-600 cursor-pointer text-xs font-bold flex items-center gap-1.5 transition-colors"
                            >
                                <span>Kosongkan Pilihan Perusahaan</span>
                            </li>

                            <li
                                v-for="company in filteredCompanies"
                                :key="company.id"
                                @click="selectCompany(company)"
                                class="px-4 py-3 hover:bg-slate-50 hover:text-[#0D542B] cursor-pointer text-sm font-medium transition-colors flex justify-between items-center"
                                :class="{'bg-slate-100 text-[#0D542B] font-semibold': form.nama_perusahaan === company.nama_perusahaan}"
                            >
                                <div>
                                    <span class="block text-gray-800" :class="{'text-[#0D542B] font-bold': form.nama_perusahaan === company.nama_perusahaan}">
                                        {{ company.nama_perusahaan }}
                                    </span>
                                    <span class="text-xs text-gray-400" v-if="company.jenis_lokasi === 'Luar Negeri' || (company.kode_negara && company.kode_negara !== 'ID')">
                                        Luar Negeri · {{ negaras?.find(n => n.kode_negara === company.kode_negara)?.nama_negara || company.kode_negara || 'Luar Negeri' }}
                                    </span>
                                    <span class="text-xs text-gray-400" v-else-if="company.kode_provinsi">
                                        Dalam Negeri · {{ provinces?.find(p => p.kode_provinsi == company.kode_provinsi)?.nama_provinsi || '' }}
                                        {{ company.kode_kabupaten ? ' - ' + (kabupatens?.find(k => k.kode_kabupaten == company.kode_kabupaten)?.nama_kabupaten || '') : '' }}
                                    </span>
                                </div>
                                <span v-if="company.status_verifikasi === 'Terverifikasi'" class="text-xs text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded font-semibold shrink-0 ml-2 border border-emerald-200">
                                    Terverifikasi Resmi
                                </span>
                                <span v-else class="text-xs text-amber-700 bg-amber-50 px-2 py-0.5 rounded font-semibold shrink-0 ml-2 border border-amber-200">
                                    Menunggu Verifikasi
                                </span>
                            </li>

                            <!-- State Jika Tidak Ada Perusahaan Ditemukan -->
                            <li v-if="filteredCompanies.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">
                                <div class="font-medium text-gray-600">Perusahaan tidak ditemukan dalam master data.</div>
                                <button type="button" @click="openAddCompanyModal" class="mt-2 text-xs font-bold text-[#0D542B] hover:underline cursor-pointer">
                                    + Tambah Perusahaan Baru Sekarang
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- ============================================================= -->
                <!-- 2. FILTER WILAYAH TAMBAHAN (OPSIONAL - COLLAPSIBLE)           -->
                <!-- ============================================================= -->
                <div v-if="showWilayahFilter" class="p-4 rounded-xl border border-gray-200 bg-gray-50/70 space-y-3 animate-in fade-in duration-150">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-700">Filter Tambahan Wilayah Kantor Pusat (Head Office):</span>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="form.company_jenis_lokasi = 'Dalam Negeri'"
                                class="px-2.5 py-1 rounded-md text-xs font-bold transition-all cursor-pointer"
                                :class="form.company_jenis_lokasi === 'Dalam Negeri' ? 'bg-[#0D542B] text-white' : 'bg-white border text-gray-600'"
                            >
                                Dalam Negeri
                            </button>
                            <button
                                type="button"
                                @click="form.company_jenis_lokasi = 'Luar Negeri'"
                                class="px-2.5 py-1 rounded-md text-xs font-bold transition-all cursor-pointer"
                                :class="form.company_jenis_lokasi === 'Luar Negeri' ? 'bg-[#0D542B] text-white' : 'bg-white border text-gray-600'"
                            >
                                Luar Negeri
                            </button>
                        </div>
                    </div>

                    <!-- Filter Luar Negeri -->
                    <div v-if="form.company_jenis_lokasi === 'Luar Negeri'" class="relative" ref="negaraContainerRef">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-gray-600">Negara Kantor Pusat:</span>
                            <button v-if="selectedNegaraName" type="button" @click="clearNegara" class="text-[11px] text-rose-600 font-bold">Reset</button>
                        </div>
                        <div
                            @click="showNegaraDropdown = !showNegaraDropdown; if (showNegaraDropdown) searchNegaraQuery = '';"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs cursor-pointer flex justify-between items-center"
                        >
                            <span v-if="selectedNegaraName" class="text-gray-900 font-semibold">{{ selectedNegaraName }}</span>
                            <span v-else class="text-gray-400">Pilih Negara di Luar Negeri...</span>
                            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                        <div v-if="showNegaraDropdown" class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden">
                            <div class="p-2 border-b bg-gray-50">
                                <input type="text" v-model="searchNegaraQuery" class="w-full px-2 py-1 text-xs border rounded bg-white outline-none" placeholder="Cari negara...">
                            </div>
                            <ul class="max-h-48 overflow-y-auto divide-y text-xs">
                                <li v-for="neg in filteredNegaras" :key="neg.id" @click="selectNegara(neg)" class="px-3 py-2 hover:bg-slate-50 cursor-pointer">
                                    {{ neg.nama_negara }} ({{ neg.kode_negara }})
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Filter Dalam Negeri -->
                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="relative" ref="provinsiContainerRef">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-semibold text-gray-600">Provinsi Kantor Pusat:</span>
                                <button v-if="form.company_kode_provinsi" type="button" @click="clearProvinsi" class="text-[11px] text-rose-600 font-bold">Reset</button>
                            </div>
                            <div
                                @click="showProvinsiDropdown = !showProvinsiDropdown; if (showProvinsiDropdown) searchProvinsiQuery = '';"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs cursor-pointer flex justify-between items-center"
                            >
                                <span v-if="selectedProvinceName" class="text-gray-900 font-semibold">{{ selectedProvinceName }}</span>
                                <span v-else class="text-gray-400">Semua Provinsi...</span>
                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                            <div v-if="showProvinsiDropdown" class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden">
                                <div class="p-2 border-b bg-gray-50">
                                    <input type="text" v-model="searchProvinsiQuery" class="w-full px-2 py-1 text-xs border rounded bg-white outline-none" placeholder="Cari provinsi...">
                                </div>
                                <ul class="max-h-48 overflow-y-auto divide-y text-xs">
                                    <li v-for="prov in filteredProvinces" :key="prov.kode_provinsi" @click="selectProvinsi(prov)" class="px-3 py-2 hover:bg-slate-50 cursor-pointer">
                                        {{ prov.nama_provinsi }}
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="relative" ref="kabupatenContainerRef">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-semibold text-gray-600">Kabupaten/Kota Kantor Pusat:</span>
                                <button v-if="form.company_kode_kabupaten" type="button" @click="clearKabupaten" class="text-[11px] text-rose-600 font-bold">Reset</button>
                            </div>
                            <div
                                @click="if (form.company_kode_provinsi) { showKabupatenDropdown = !showKabupatenDropdown; if (showKabupatenDropdown) searchKabupatenQuery = ''; }"
                                class="w-full rounded-lg border px-3 py-2 text-xs cursor-pointer flex justify-between items-center"
                                :class="!form.company_kode_provinsi ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-900 border-gray-300'"
                            >
                                <span v-if="selectedKabupatenName" class="text-gray-900 font-semibold">{{ selectedKabupatenName }}</span>
                                <span v-else class="text-gray-400">{{ form.company_kode_provinsi ? 'Semua Kabupaten/Kota...' : 'Pilih provinsi terlebih dahulu' }}</span>
                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                            <div v-if="showKabupatenDropdown && form.company_kode_provinsi" class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden">
                                <div class="p-2 border-b bg-gray-50">
                                    <input type="text" v-model="searchKabupatenQuery" class="w-full px-2 py-1 text-xs border rounded bg-white outline-none" placeholder="Cari kota...">
                                </div>
                                <ul class="max-h-48 overflow-y-auto divide-y text-xs">
                                    <li v-for="kab in filteredKabupatens" :key="kab.kode_kabupaten" @click="selectKabupaten(kab)" class="px-3 py-2 hover:bg-slate-50 cursor-pointer">
                                        {{ kab.nama_kabupaten }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================= -->
                <!-- 3. DETAIL MASTER HEAD OFFICE TERPILIH (TERKUNCI / DISABLED)   -->
                <!-- ============================================================= -->
                <div v-if="form.nama_perusahaan?.trim()" class="space-y-4 pt-2">
                    <!-- Banner Informasi Master Head Office -->
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 text-xs text-emerald-950 leading-relaxed shadow-2xs">
                        <div class="flex items-center justify-between mb-1.5 flex-wrap gap-2">
                            <span class="font-extrabold text-sm text-[#0D542B] flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-[#0D542B]"></span>
                                Identitas Resmi Kantor Pusat (Master Head Office)
                            </span>
                            <span v-if="form.company_status_verifikasi === 'Terverifikasi'" class="px-2.5 py-0.5 rounded-md bg-emerald-100 text-[#0D542B] font-bold text-[10px] border border-emerald-300">
                                Terverifikasi Resmi
                            </span>
                            <span v-else class="px-2.5 py-0.5 rounded-md bg-amber-100 text-amber-800 font-bold text-[10px] border border-amber-300">
                                Menunggu Verifikasi
                            </span>
                        </div>
                        Data di bawah ini merupakan identitas kantor pusat (Head Office) resmi dari pangkalan data universitas dan dikunci secara otomatis demi konsistensi data institusi. Jika Anda bertugas di kantor cabang atau bekerja secara remote, wilayah penugasan operasional Anda dicatat pada bagian <strong>Kontak & Alamat Domisili</strong>.
                    </div>

                    <!-- Form Controls Lengkap dalam Status Disabled / Terkunci -->
                    <div class="space-y-4 bg-gray-50/80 p-5 sm:p-6 rounded-2xl border border-gray-200 shadow-2xs">
                        <!-- Jenis Lokasi Kantor Pusat -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">
                                Jenis Lokasi Kantor Pusat
                            </label>
                            <div class="flex items-center gap-6">
                                <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-not-allowed select-none">
                                    <input
                                        type="radio"
                                        disabled
                                        :checked="(form.company_jenis_lokasi || 'Dalam Negeri') === 'Dalam Negeri'"
                                        class="text-[#0D542B] border-gray-300 cursor-not-allowed"
                                    />
                                    <span>Dalam Negeri (Indonesia)</span>
                                </label>
                                <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-not-allowed select-none">
                                    <input
                                        type="radio"
                                        disabled
                                        :checked="form.company_jenis_lokasi === 'Luar Negeri'"
                                        class="text-[#0D542B] border-gray-300 cursor-not-allowed"
                                    />
                                    <span>Luar Negeri (Abroad)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Wilayah Kantor Pusat (Luar Negeri vs Dalam Negeri) -->
                        <div v-if="form.company_jenis_lokasi === 'Luar Negeri'">
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                Negara Kantor Pusat
                            </label>
                            <input
                                type="text"
                                disabled
                                :value="selectedNegaraName || form.company_kode_negara || '-'"
                                :class="lockedInputClass"
                            />
                        </div>
                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">
                                    Provinsi Kantor Pusat
                                </label>
                                <input
                                    type="text"
                                    disabled
                                    :value="selectedProvinceName || (provinces?.find(p => p.kode_provinsi == form.company_kode_provinsi)?.nama_provinsi) || '-'"
                                    :class="lockedInputClass"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">
                                    Kabupaten / Kota Kantor Pusat
                                </label>
                                <input
                                    type="text"
                                    disabled
                                    :value="selectedKabupatenName || (kabupatens?.find(k => k.kode_kabupaten == form.company_kode_kabupaten)?.nama_kabupaten) || '-'"
                                    :class="lockedInputClass"
                                />
                            </div>
                        </div>

                        <!-- Nama Perusahaan / Instansi (Master Head Office) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                Nama Perusahaan / Instansi (Master Head Office)
                            </label>
                            <input
                                type="text"
                                disabled
                                :value="form.nama_perusahaan"
                                :class="lockedInputClass"
                            />
                        </div>

                        <!-- Jenis Perusahaan / Instansi (F11) & Bentuk Lembaga / Perusahaan -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">
                                    Apa jenis perusahaan/instansi/institusi tempat Anda bekerja sekarang? (F11)
                                </label>
                                <input
                                    type="text"
                                    disabled
                                    :value="form.company_jenis_perusahaan || form.perusahaan_jenis_perusahaan || 'Perusahaan swasta'"
                                    :class="lockedInputClass"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">
                                    Bentuk Lembaga / Perusahaan
                                </label>
                                <input
                                    type="text"
                                    disabled
                                    :value="form.company_bentuk || form.perusahaan_bentuk || 'Perusahaan Terbatas'"
                                    :class="lockedInputClass"
                                />
                            </div>
                        </div>

                        <!-- Skala Perusahaan / Instansi / Institusi (F2H / F5D) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                Skala Perusahaan / Instansi / Institusi (F2H / F5D)
                            </label>
                            <input
                                type="text"
                                disabled
                                :value="form.company_skala || form.perusahaan_skala || 'Regional/Lokal'"
                                :class="lockedInputClass"
                            />
                        </div>

                        <!-- Alamat Kantor Pusat (F2F) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                Alamat Kantor Pusat (Head Office)
                            </label>
                            <textarea
                                disabled
                                rows="2"
                                :value="form.company_alamat || form.perusahaan_alamat || '-'"
                                :class="lockedInputClass"
                                class="resize-none"
                            ></textarea>
                        </div>

                        <!-- Kode Pos & No. Telp / Fax Perusahaan -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">
                                    Kode Pos Kantor / Lokasi Usaha
                                </label>
                                <input
                                    type="text"
                                    disabled
                                    :value="form.company_kode_pos || form.perusahaan_kode_pos || form.zipcode || '-'"
                                    :class="lockedInputClass"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">
                                    No. Telp / Fax Perusahaan
                                </label>
                                <input
                                    type="text"
                                    disabled
                                    :value="form.company_no_telp_fax || form.perusahaan_no_telp_fax || '-'"
                                    :class="lockedInputClass"
                                />
                            </div>
                        </div>

                        <!-- Website / Homepage Perusahaan -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                Website / Homepage Perusahaan
                            </label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    disabled
                                    :value="form.company_homepage || form.perusahaan_homepage || '-'"
                                    :class="lockedInputClass"
                                    class="flex-1"
                                />
                                <a
                                    v-if="form.company_homepage || form.perusahaan_homepage"
                                    :href="form.company_homepage || form.perusahaan_homepage"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="px-3.5 py-2.5 rounded-xl bg-white border border-gray-300 hover:border-[#0D542B] text-xs font-bold text-[#0D542B] shadow-2xs hover:bg-slate-50 transition-colors shrink-0"
                                    title="Kunjungi website resmi perusahaan"
                                >
                                    Buka Situs
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Ajukan Usulan Pembaruan Data Kantor Pusat -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 rounded-xl border border-dashed border-gray-300 bg-white">
                        <div class="text-xs text-gray-500">
                            Ada data kantor pusat yang belum lengkap atau berpindah (misal: website/telepon belum tercatat)?
                        </div>
                        <button
                            type="button"
                            @click="openRequestUpdateModal"
                            class="px-3.5 py-2 rounded-xl border border-[#0D542B]/30 bg-emerald-50 hover:bg-emerald-100 text-[#0D542B] text-xs font-bold transition-colors cursor-pointer shrink-0"
                        >
                            + Usulkan Pembaruan Data Kantor Pusat
                        </button>
                    </div>
                </div>

                <!-- State Petunjuk jika belum memilih perusahaan -->
                <div v-else class="p-6 bg-gray-50 rounded-2xl border border-dashed border-gray-200 text-center">
                    <div class="text-xs text-gray-500 font-medium max-w-md mx-auto">
                        Ketik nama perusahaan kantor pusat (Head Office) pada kolom pencarian di atas untuk memilih instansi resmi atau klik <strong>+ Tambah Baru</strong> jika perusahaan belum terdaftar.
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- CARD 3: DATA ATASAN LANGSUNG / PIMPINAN                           -->
        <!-- (Hanya untuk Pekerja & Wiraswasta)                                -->
        <!-- ================================================================= -->
        <div v-if="form.kategori_pekerjaan !== 'Melanjutkan Pendidikan' && (isQuestionVisible('F2E1') || isQuestionVisible('F2E2') || isQuestionVisible('F2E3'))" class="bg-white rounded-2xl p-6 sm:p-8 shadow-xs border border-gray-100">
            <div class="flex items-center justify-between mb-2 pb-3 border-b border-gray-100">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-[#0D542B] rounded-full inline-block"></span>
                    Data Atasan Langsung
                </h2>
                <span class="text-xs text-gray-400 font-medium">Survei Pengguna Lulusan</span>
            </div>

            <p class="text-xs text-gray-400 mb-6">
                Data pimpinan/atasan digunakan untuk keperluan survei evaluasi kepuasan pengguna lulusan oleh universitas.
            </p>

            <!-- Banner Otomatis: Owner / Founder / Wiraswasta (Netral Gray) -->
            <div v-if="isOwner" class="bg-gray-50 border border-gray-200 text-gray-600 rounded-xl p-3.5 text-xs flex items-center gap-2.5 mb-5 font-medium shadow-2xs">
                <span>Karena Anda memilih posisi <strong>Owner / Wiraswasta</strong>, kolom Data Atasan Langsung di bawah ini otomatis diisi dengan data diri Anda.</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div v-if="isQuestionVisible('F2E1')" class="md:col-span-2">
                    <label for="input_nama_atasan_langsung" class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        {{ getQuestionLabel('F2E1', 'Nama Lengkap Atasan') }} <span v-if="isQuestionRequired('F2E1')" class="text-rose-500 font-bold">*</span>
                    </label>
                    <input
                        id="input_nama_atasan_langsung"
                        name="nama_atasan"
                        type="text"
                        v-model="form.nama_atasan"
                        autocomplete="off"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        placeholder="Contoh: Ir. Bambang Trihatmojo"
                    />
                </div>

                <div v-if="isQuestionVisible('F2E3')">
                    <label for="input_email_atasan_langsung" class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        {{ getQuestionLabel('F2E3', 'Email Atasan') }} <span v-if="isQuestionRequired('F2E3')" class="text-rose-500 font-bold">*</span>
                    </label>
                    <input
                        id="input_email_atasan_langsung"
                        name="email_atasan"
                        type="email"
                        v-model="form.email_atasan"
                        autocomplete="off"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        placeholder="atasan@perusahaan.co.id"
                    />
                </div>

                <div v-if="isQuestionVisible('F2E2')">
                    <label for="input_telepon_atasan_langsung" class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        {{ getQuestionLabel('F2E2', 'Nomor Telepon Atasan') }} <span v-if="isQuestionRequired('F2E2')" class="text-rose-500 font-bold">*</span>
                    </label>
                    <input
                        id="input_telepon_atasan_langsung"
                        name="telepon_atasan"
                        type="tel"
                        v-model="form.telepon_atasan"
                        autocomplete="off"
                        class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-mono font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        placeholder="081234567890"
                    />
                </div>
            </div>

            <!-- Panel Aksi: Tombol Kirim Email ke Atasan (Manual On-Demand) -->
            <div class="mt-6 pt-5 border-t border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-gray-50/80 p-4 sm:p-5 rounded-2xl border border-gray-200">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs sm:text-sm font-bold text-gray-900">Undangan Evaluasi Atasan</span>
                        <span
                            v-if="evaluasiAtasan?.is_submitted"
                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#0D542B] text-white"
                        >
                            Atasan Sudah Mengisi
                        </span>
                        <span
                            v-else-if="evaluasiAtasan?.has_invitation"
                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-[#0D542B] border border-[#0D542B]/30"
                        >
                            Tautan Siap / Terkirim
                        </span>
                        <span
                            v-else
                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600 border border-gray-200"
                        >
                            Belum Terkirim
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 max-w-xl leading-relaxed">
                        Email survei evaluasi tidak dikirim secara otomatis saat menyimpan profil untuk mencegah kesalahan penulisan email. Klik tombol di samping setelah Anda memastikan alamat email atasan sudah benar.
                    </p>
                </div>

                <button
                    type="button"
                    @click="handleKirimEmailAtasan"
                    :disabled="sendingEmail || Boolean(evaluasiAtasan?.is_submitted)"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-[#0D542B] hover:bg-[#093d1f] transition-all shadow-xs shrink-0 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <svg v-if="sendingEmail" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span>{{ sendingEmail ? 'Mengirim...' : (evaluasiAtasan?.has_invitation ? 'Kirim Ulang Email ke Atasan' : 'Kirim Email ke Atasan') }}</span>
                </button>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL TAMBAH PERUSAHAAN BARU (VUE DIALOG)                      -->
        <!-- ============================================================= -->
        <Teleport to="body">
            <div
                v-if="isAddCompanyModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-add-company-title"
            >
                <!-- Backdrop -->
                <div
                    class="fixed inset-0 bg-slate-900/60 transition-opacity"
                    @click="closeAddCompanyModal"
                ></div>

                <!-- Card Modal -->
                <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] flex flex-col overflow-hidden border border-slate-200 z-10 animate-in fade-in zoom-in-95 duration-150">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
                        <div>
                            <h3 id="modal-add-company-title" class="text-base font-bold text-slate-800">
                                Tambah Perusahaan Baru
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Daftarkan perusahaan atau instansi baru ke referensi profil karier
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="closeAddCompanyModal"
                            :disabled="isSubmittingCompany"
                            class="text-slate-400 hover:text-slate-600 rounded-lg p-1.5 transition-colors cursor-pointer disabled:opacity-50"
                            title="Tutup dialog"
                        >
                            <span class="sr-only">Tutup</span>
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body / Form Fields -->
                    <form @submit.prevent="submitNewCompany" class="p-6 space-y-4 overflow-y-auto flex-1">
                        <!-- Pesan Kesalahan Validasi / Server -->
                        <div v-if="companyModalError" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 flex items-start gap-2">
                            <svg class="w-4 h-4 text-rose-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ companyModalError }}</span>
                        </div>

                        <!-- Jenis Lokasi Perusahaan -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Jenis Lokasi Perusahaan <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex items-center gap-6 mt-1">
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                    <input
                                        type="radio"
                                        value="Dalam Negeri"
                                        v-model="newCompanyForm.jenis_lokasi"
                                        class="text-[#0D542B] focus:ring-[#0D542B] border-slate-300"
                                    />
                                    <span>Dalam Negeri (Indonesia)</span>
                                </label>
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                    <input
                                        type="radio"
                                        value="Luar Negeri"
                                        v-model="newCompanyForm.jenis_lokasi"
                                        class="text-[#0D542B] focus:ring-[#0D542B] border-slate-300"
                                    />
                                    <span>Luar Negeri (Abroad)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Nama Perusahaan -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Nama Perusahaan / Instansi <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="newCompanyForm.nama_perusahaan"
                                class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                placeholder="Contoh: PT Teknologi Nusantara / Google Singapore"
                                required
                            />
                        </div>

                        <!-- Bentuk Perusahaan & Jenis Perusahaan (F11) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Bentuk Perusahaan / Institusi
                                </label>
                                <select
                                    v-model="newCompanyForm.bentuk_perusahaan"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                >
                                    <option
                                        v-for="opt in bentukPerusahaanOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Jenis Perusahaan / Instansi (F11)
                                </label>
                                <select
                                    v-model="newCompanyForm.jenis_perusahaan"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                >
                                    <option
                                        v-for="opt in jenisPerusahaanOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Input Tambahan jika Jenis Perusahaan 'Lainnya' -->
                        <div v-if="newCompanyForm.jenis_perusahaan === 'Lainnya'">
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Sebutkan Jenis Instansi Lainnya
                            </label>
                            <input
                                type="text"
                                v-model="newCompanyForm.jenis_perusahaan_lainnya"
                                class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                placeholder="Contoh: Startup Komunitas, Lembaga Riset..."
                            />
                        </div>

                        <!-- Bidang Wilayah Luar Negeri: Negara -->
                        <div v-if="newCompanyForm.jenis_lokasi === 'Luar Negeri'">
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Negara Tempat Bekerja <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="newCompanyForm.kode_negara"
                                class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                            >
                                <option value="">-- Pilih Negara di Luar Negeri --</option>
                                <option
                                    v-for="n in foreignCountries"
                                    :key="n.kode_negara"
                                    :value="n.kode_negara"
                                >
                                    {{ n.nama_negara }} ({{ n.benua || 'Dunia' }})
                                </option>
                            </select>
                        </div>

                        <!-- Bidang Wilayah Dalam Negeri: Provinsi & Kabupaten -->
                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Provinsi <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    v-model="newCompanyForm.kode_provinsi"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                >
                                    <option value="">-- Pilih Provinsi --</option>
                                    <option
                                        v-for="prov in props.provinces || []"
                                        :key="prov.kode_provinsi"
                                        :value="prov.kode_provinsi"
                                    >
                                        {{ prov.nama_provinsi }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Kabupaten / Kota <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    v-model="newCompanyForm.kode_kabupaten"
                                    :disabled="!newCompanyForm.kode_provinsi"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all disabled:bg-slate-100 disabled:text-slate-400"
                                >
                                    <option value="">-- Pilih Kabupaten --</option>
                                    <option
                                        v-for="kab in modalFilteredKabupatens"
                                        :key="kab.kode_kabupaten"
                                        :value="kab.kode_kabupaten"
                                    >
                                        {{ kab.nama_kabupaten }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Kode Pos & Skala Perusahaan -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Kode Pos Perusahaan
                                </label>
                                <input
                                    type="text"
                                    v-model="newCompanyForm.kode_pos"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                    placeholder="Contoh: 55281 / 117371"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Skala Perusahaan
                                </label>
                                <select
                                    v-model="newCompanyForm.skala"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                >
                                    <option
                                        v-for="s in skalaOptions"
                                        :key="s.value"
                                        :value="s.value"
                                    >
                                        {{ s.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Alamat Perusahaan -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Alamat Jalan / Gedung Perusahaan
                            </label>
                            <textarea
                                v-model="newCompanyForm.alamat"
                                rows="2"
                                class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all resize-y"
                                placeholder="Nama Jalan, Gedung, Nomor..."
                            ></textarea>
                        </div>

                        <!-- No. Telp / Fax & Website / Homepage -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    No. Telp / Fax Perusahaan
                                </label>
                                <input
                                    type="text"
                                    v-model="newCompanyForm.no_telp_fax"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                    placeholder="Contoh: 0274-563929"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Website / Homepage Perusahaan
                                </label>
                                <input
                                    type="text"
                                    v-model="newCompanyForm.homepage"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                    placeholder="Contoh: https://perusahaan.com"
                                />
                            </div>
                        </div>

                        <!-- Footer / Tombol Aksi -->
                        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                            <button
                                type="button"
                                @click="closeAddCompanyModal"
                                :disabled="isSubmittingCompany"
                                class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer disabled:opacity-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSubmittingCompany"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-[#0D542B] hover:bg-[#093d1f] transition-all shadow-xs shrink-0 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg v-if="isSubmittingCompany" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ isSubmittingCompany ? 'Menyimpan...' : 'Simpan Perusahaan' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ============================================================= -->
        <!-- MODAL USULAN PEMBARUAN DATA KANTOR PUSAT (REQUEST UPDATE)     -->
        <!-- ============================================================= -->
        <Teleport to="body">
            <div
                v-if="isRequestUpdateModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-request-update-title"
            >
                <!-- Backdrop -->
                <div
                    class="fixed inset-0 bg-slate-900/60 transition-opacity"
                    @click="closeRequestUpdateModal"
                ></div>

                <!-- Card Modal -->
                <div class="relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden border border-slate-200 z-10 animate-in fade-in zoom-in-95 duration-150">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
                        <div>
                            <h3 id="modal-request-update-title" class="text-base font-bold text-slate-800">
                                Usulkan Pembaruan Data Kantor Pusat
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ form.nama_perusahaan }} (Master Head Office)
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="closeRequestUpdateModal"
                            :disabled="isSubmittingRequestUpdate"
                            class="text-slate-400 hover:text-slate-600 rounded-lg p-1.5 transition-colors cursor-pointer disabled:opacity-50"
                            title="Tutup dialog"
                        >
                            <span class="sr-only">Tutup</span>
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body / Form Fields -->
                    <form @submit.prevent="submitRequestUpdate" class="p-6 space-y-4 overflow-y-auto flex-1">
                        <div class="p-3 bg-emerald-50/60 border border-emerald-200 rounded-xl text-xs text-emerald-950 leading-relaxed">
                            Usulan pembaruan akan dicatat dan ditinjau oleh administrator universitas tanpa langsung mengubah master data terverifikasi.
                        </div>

                        <!-- Pesan Error jika ada -->
                        <div v-if="requestUpdateError" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 flex items-start gap-2">
                            <svg class="w-4 h-4 text-rose-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ requestUpdateError }}</span>
                        </div>

                        <!-- Jenis Lokasi Perusahaan -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Jenis Lokasi Perusahaan <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex items-center gap-6 mt-1">
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                    <input
                                        type="radio"
                                        value="Dalam Negeri"
                                        v-model="requestUpdateForm.jenis_lokasi"
                                        class="text-[#0D542B] focus:ring-[#0D542B] border-slate-300"
                                    />
                                    <span>Dalam Negeri (Indonesia)</span>
                                </label>
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                    <input
                                        type="radio"
                                        value="Luar Negeri"
                                        v-model="requestUpdateForm.jenis_lokasi"
                                        class="text-[#0D542B] focus:ring-[#0D542B] border-slate-300"
                                    />
                                    <span>Luar Negeri (Abroad)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Wilayah: Luar Negeri (Negara) vs Dalam Negeri (Provinsi & Kabupaten) -->
                        <div v-if="requestUpdateForm.jenis_lokasi === 'Luar Negeri'">
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Negara Tempat Bekerja
                            </label>
                            <select
                                v-model="requestUpdateForm.kode_negara"
                                class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                            >
                                <option value="">-- Pilih Negara di Luar Negeri --</option>
                                <option
                                    v-for="n in foreignCountries"
                                    :key="n.kode_negara"
                                    :value="n.kode_negara"
                                >
                                    {{ n.nama_negara }} ({{ n.benua || 'Dunia' }})
                                </option>
                            </select>
                        </div>
                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Provinsi Kantor Pusat
                                </label>
                                <select
                                    v-model="requestUpdateForm.kode_provinsi"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                >
                                    <option value="">-- Pilih Provinsi --</option>
                                    <option
                                        v-for="prov in props.provinces || []"
                                        :key="prov.kode_provinsi"
                                        :value="prov.kode_provinsi"
                                    >
                                        {{ prov.nama_provinsi }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Kabupaten / Kota Kantor Pusat
                                </label>
                                <select
                                    v-model="requestUpdateForm.kode_kabupaten"
                                    :disabled="!requestUpdateForm.kode_provinsi"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all disabled:bg-slate-100 disabled:text-slate-400"
                                >
                                    <option value="">-- Pilih Kabupaten --</option>
                                    <option
                                        v-for="kab in requestUpdateFilteredKabupatens"
                                        :key="kab.kode_kabupaten"
                                        :value="kab.kode_kabupaten"
                                    >
                                        {{ kab.nama_kabupaten }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Nama Perusahaan / Instansi -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Nama Perusahaan / Instansi (Master Head Office)
                            </label>
                            <input
                                type="text"
                                v-model="requestUpdateForm.nama_perusahaan"
                                class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                placeholder="Nama resmi kantor pusat perusahaan..."
                            />
                        </div>

                        <!-- Bentuk Perusahaan & Jenis Perusahaan (F11) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Bentuk Perusahaan / Institusi
                                </label>
                                <select
                                    v-model="requestUpdateForm.bentuk_perusahaan"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                >
                                    <option
                                        v-for="opt in bentukPerusahaanOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Jenis Perusahaan / Instansi (F11)
                                </label>
                                <select
                                    v-model="requestUpdateForm.jenis_perusahaan"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                >
                                    <option
                                        v-for="opt in jenisPerusahaanOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Input Tambahan jika Jenis Perusahaan 'Lainnya' -->
                        <div v-if="requestUpdateForm.jenis_perusahaan === 'Lainnya'">
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Sebutkan Jenis Instansi Lainnya
                            </label>
                            <input
                                type="text"
                                v-model="requestUpdateForm.jenis_perusahaan_lainnya"
                                class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                placeholder="Contoh: Startup Komunitas, Lembaga Riset..."
                            />
                        </div>

                        <!-- Skala Perusahaan (F2H / F5D) -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Skala Perusahaan / Instansi / Institusi (F2H / F5D)
                            </label>
                            <select
                                v-model="requestUpdateForm.skala"
                                class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                            >
                                <option
                                    v-for="s in skalaOptions"
                                    :key="s.value"
                                    :value="s.value"
                                >
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Alamat Kantor Pusat (F2F) -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Alamat Kantor Pusat (Head Office)
                            </label>
                            <textarea
                                v-model="requestUpdateForm.alamat"
                                rows="2"
                                class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all resize-y"
                                placeholder="Nama Jalan, Gedung, Nomor..."
                            ></textarea>
                        </div>

                        <!-- Kode Pos & No. Telp / Fax Perusahaan -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Kode Pos Kantor / Lokasi Usaha
                                </label>
                                <input
                                    type="text"
                                    v-model="requestUpdateForm.kode_pos"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                    placeholder="Contoh: 55281 / 117371"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    No. Telp / Fax Perusahaan
                                </label>
                                <input
                                    type="text"
                                    v-model="requestUpdateForm.no_telp_fax"
                                    class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                    placeholder="Contoh: 0274-563929"
                                />
                            </div>
                        </div>

                        <!-- Website / Homepage Perusahaan -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Website / Homepage Perusahaan
                            </label>
                            <input
                                type="text"
                                v-model="requestUpdateForm.homepage"
                                class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                placeholder="Contoh: https://perusahaan.co.id"
                            />
                        </div>

                        <!-- Footer / Tombol Aksi -->
                        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                            <button
                                type="button"
                                @click="closeRequestUpdateModal"
                                :disabled="isSubmittingRequestUpdate"
                                class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer disabled:opacity-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSubmittingRequestUpdate"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-[#0D542B] hover:bg-[#093d1f] transition-all shadow-xs shrink-0 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg v-if="isSubmittingRequestUpdate" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ isSubmittingRequestUpdate ? 'Mengirim...' : 'Kirim Usulan' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
