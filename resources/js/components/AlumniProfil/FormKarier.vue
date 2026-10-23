<!--
  Komponen Anak (Child Component): Form Karier & Riwayat Pekerjaan
  File: resources/js/Pages/Alumni/Profil/Components/FormKarier.vue
  
  Deskripsi:
  Mengelola data karier alumni, mencakup peran/aktivitas saat ini (Pekerja / Karyawan, Wirausaha / Founder, Melanjutkan Pendidikan),
  data perusahaan/instansi (lokasi dalam/luar negeri, filter negara/provinsi/kabupaten sebelum memilih perusahaan, dan penambahan perusahaan),
  nominal penghasilan bulanan (tanpa auto-multiplier), serta data kontak atasan langsung.
-->
<script setup>
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
    evaluasiAtasan: Object,
});

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
// 2. OPSI DINAMIS DARI DATABASE (ref_subpertanyaan_detil)
// ============================================================================

// Pilihan Posisi Jabatan Struktural (F2G)
const posisiJabatanOptions = computed(() => {
    let opts = [];
    if (props.refOptions?.F2G && props.refOptions.F2G.length > 0) {
        opts = props.refOptions.F2G.map(opt => ({
            value: opt.option_text,
            label: `${opt.kode_opsi} - ${opt.option_text}`
        }));
    } else {
        opts = [
            { value: 'Direksi', label: '1 - Direksi' },
            { value: 'Top Manager', label: '2 - Top Manager' },
            { value: 'Middle Manager', label: '3 - Middle Manager' },
            { value: 'Low Manager', label: '4 - Low Manager' },
            { value: 'Supervisor', label: '5 - Supervisor' },
            { value: 'Staff', label: '6 - Staff' },
        ];
    }

    if (props.form?.posisi_jabatan && !opts.some(o => o.value.toLowerCase() === props.form.posisi_jabatan.toLowerCase())) {
        opts.unshift({
            value: props.form.posisi_jabatan,
            label: `${props.form.posisi_jabatan} (LinkedIn / Kustom)`
        });
    }

    return opts;
});

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

// Pilihan Skala Perusahaan (F2H / F5D)
const skalaOptions = computed(() => {
    if (props.refOptions?.F2H && props.refOptions.F2H.length > 0) {
        return props.refOptions.F2H.map(opt => ({
            value: opt.option_text,
            label: opt.option_text
        }));
    }
    return [
        { value: 'Regional/Lokal', label: 'Regional / Lokal' },
        { value: 'Nasional', label: 'Nasional' },
        { value: 'Internasional', label: 'Internasional' },
    ];
});

// Pilihan bidang pekerjaan / divisi (BIO_BIDANG_DIVISI) disimpan langsung ke biodata.
const bidangPekerjaanOptions = computed(() => {
    const configured = props.refOptions?.BIO_BIDANG_DIVISI || [];
    const options = configured.map(opt => ({
        value: opt.option_text,
        label: `${opt.kode_opsi} - ${opt.option_text}`,
    }));

    if (!options.some(opt => isOtherOptionValue(opt.value))) {
        options.push({ value: 'Lainnya', label: 'Lainnya' });
    }

    return options;
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

const isCustomJabatan = ref(false);

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

// Alumni hanya boleh mengedit detail perusahaan jika belum terverifikasi atau bertindak sebagai Owner
const canEditCompanyDetails = computed(() => {
    return !isVerifiedCompany.value || isOwner.value;
});

// Mengisi data atasan otomatis dengan kontak alumni jika pemilik usaha
const autoFillAtasanOwner = () => {
    props.form.nama_atasan = props.form.nama || '';
    props.form.email_atasan = props.form.email_pribadi || props.form.email || '';
    props.form.telepon_atasan = props.form.nomor_telepon || '';
};

// Pemilihan peran eksklusif Pekerja vs Wiraswasta (Single Choice)
const selectRole = (newRole) => {
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
            text: 'Harap masukkan alamat email atasan yang valid sebelum mengirimkan undangan survei.',
            icon: 'warning',
            confirmButtonColor: '#005B3C',
        });
        return;
    }

    if (props.form.isDirty) {
        Swal.fire({
            title: 'Simpan Profil Terlebih Dahulu',
            text: 'Ada perubahan data kontak atasan atau profil Anda yang belum disimpan. Silakan klik tombol Simpan Perubahan terlebih dahulu agar data terbarui.',
            icon: 'info',
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
                email_atasan: email,
            }, {
                preserveScroll: true,
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
                        text: err?.message || 'Terjadi kesalahan sistem saat mengirim email ke atasan.',
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
if (!props.form.company_negara && !props.form.perusahaan_negara) {
    props.form.company_negara = props.form.company_jenis_lokasi === 'Luar Negeri' ? '' : 'Indonesia';
    props.form.perusahaan_negara = props.form.company_negara;
}

watch(() => props.form.company_jenis_lokasi, (newVal) => {
    props.form.perusahaan_jenis_lokasi = newVal;
    if (newVal === 'Luar Negeri') {
        props.form.company_province_id = null;
        props.form.perusahaan_propinsi_id = null;
        props.form.company_kabupaten_id = null;
        props.form.perusahaan_kabupaten_id = null;
        if (!props.form.company_negara || props.form.company_negara === 'Indonesia') {
            props.form.company_negara = '';
            props.form.perusahaan_negara = '';
        }
    } else {
        props.form.company_negara = 'Indonesia';
        props.form.perusahaan_negara = 'Indonesia';
    }
});

watch(() => props.form.company_negara, (newVal) => {
    props.form.perusahaan_negara = newVal;
});

// Dropdown Provinsi
const showProvinsiDropdown = ref(false);
const searchProvinsiQuery = ref('');

const selectedProvinceName = computed(() => {
    if (!props.form.company_province_id || !props.provinces) return '';
    const prov = props.provinces.find(p => p.id == props.form.company_province_id);
    return prov ? prov.nama_provinsi : '';
});

const filteredProvinces = computed(() => {
    const list = props.provinces || [];
    if (!searchProvinsiQuery.value.trim()) return list;
    const q = searchProvinsiQuery.value.toLowerCase();
    return list.filter(p => p.nama_provinsi.toLowerCase().includes(q));
});

const selectProvinsi = (prov) => {
    props.form.company_province_id = prov.id;
    props.form.perusahaan_propinsi_id = prov.id;
    props.form.company_kabupaten_id = '';
    props.form.perusahaan_kabupaten_id = '';
    showProvinsiDropdown.value = false;
    searchProvinsiQuery.value = '';
};

const clearProvinsi = () => {
    props.form.company_province_id = null;
    props.form.perusahaan_propinsi_id = null;
    props.form.company_kabupaten_id = null;
    props.form.perusahaan_kabupaten_id = null;
    showProvinsiDropdown.value = false;
    searchProvinsiQuery.value = '';
};

// Dropdown Kabupaten
const showKabupatenDropdown = ref(false);
const searchKabupatenQuery = ref('');

const selectedKabupatenName = computed(() => {
    if (!props.form.company_kabupaten_id || !props.kabupatens) return '';
    const kab = props.kabupatens.find(k => k.id == props.form.company_kabupaten_id);
    return kab ? kab.nama_kabupaten : '';
});

const filteredKabupatens = computed(() => {
    let list = props.kabupatens || [];
    if (props.form.company_province_id) {
        list = list.filter(k => k.province_id == props.form.company_province_id);
    }
    if (!searchKabupatenQuery.value.trim()) return list;
    const q = searchKabupatenQuery.value.toLowerCase();
    return list.filter(k => k.nama_kabupaten.toLowerCase().includes(q));
});

const selectKabupaten = (kab) => {
    props.form.company_kabupaten_id = kab.id;
    props.form.perusahaan_kabupaten_id = kab.id;
    showKabupatenDropdown.value = false;
    searchKabupatenQuery.value = '';
};

const clearKabupaten = () => {
    props.form.company_kabupaten_id = null;
    props.form.perusahaan_kabupaten_id = null;
    showKabupatenDropdown.value = false;
    searchKabupatenQuery.value = '';
};

// Dropdown Negara Luar Negeri
const showNegaraDropdown = ref(false);
const searchNegaraQuery = ref('');

const selectedNegaraName = computed(() => {
    if (!props.form.company_negara || props.form.company_negara === 'Indonesia') return '';
    return props.form.company_negara;
});

const filteredNegaras = computed(() => {
    const list = (props.negaras || []).filter(n => n.nama_negara.toLowerCase() !== 'indonesia');
    if (!searchNegaraQuery.value.trim()) return list;
    const q = searchNegaraQuery.value.toLowerCase();
    return list.filter(n => 
        n.nama_negara.toLowerCase().includes(q) || 
        (n.benua && n.benua.toLowerCase().includes(q)) ||
        (n.ibu_kota && n.ibu_kota.toLowerCase().includes(q)) ||
        (n.kode_iso2 && n.kode_iso2.toLowerCase().includes(q))
    );
});

const selectNegara = (neg) => {
    props.form.company_negara = neg.nama_negara;
    props.form.perusahaan_negara = neg.nama_negara;
    showNegaraDropdown.value = false;
    searchNegaraQuery.value = '';
};

const clearNegara = () => {
    props.form.company_negara = '';
    props.form.perusahaan_negara = '';
    showNegaraDropdown.value = false;
    searchNegaraQuery.value = '';
};

// Dropdown Perusahaan
const showCompanyDropdown = ref(false);
const searchCompanyQuery = ref('');

const filteredCompanies = computed(() => {
    let list = localCompanies.value || [];

    if (props.form.company_jenis_lokasi) {
        list = list.filter(c => (c.jenis_lokasi || 'Dalam Negeri') === props.form.company_jenis_lokasi);
    }

    if (props.form.company_jenis_lokasi === 'Luar Negeri') {
        if (props.form.company_negara && props.form.company_negara !== 'Indonesia') {
            const qNegara = props.form.company_negara.toLowerCase();
            list = list.filter(c => c.negara && c.negara.toLowerCase() === qNegara);
        }
    } else {
        if (props.form.company_province_id) {
            list = list.filter(c => c.province_id == props.form.company_province_id);
        }
        if (props.form.company_kabupaten_id) {
            list = list.filter(c => c.kabupaten_id == props.form.company_kabupaten_id);
        }
    }

    if (!searchCompanyQuery.value.trim()) return list;
    const q = searchCompanyQuery.value.toLowerCase();
    return list.filter(c => 
        c.nama_perusahaan.toLowerCase().includes(q) || 
        (c.negara && c.negara.toLowerCase().includes(q)) ||
        (c.alamat && c.alamat.toLowerCase().includes(q))
    );
});

const selectCompany = (company) => {
    props.form.nama_perusahaan = company.nama_perusahaan;
    
    if (company.jenis_lokasi) {
        props.form.company_jenis_lokasi = company.jenis_lokasi;
        props.form.perusahaan_jenis_lokasi = company.jenis_lokasi;
    }
    if (company.negara) {
        props.form.company_negara = company.negara;
        props.form.perusahaan_negara = company.negara;
    }
    if (company.province_id) {
        props.form.company_province_id = company.province_id;
        props.form.perusahaan_propinsi_id = company.province_id;
    }
    if (company.kabupaten_id) {
        props.form.company_kabupaten_id = company.kabupaten_id;
        props.form.perusahaan_kabupaten_id = company.kabupaten_id;
    }
    if (company.alamat) {
        props.form.company_alamat = company.alamat;
        props.form.perusahaan_alamat = company.alamat;
    }
    if (company.skala) {
        props.form.company_skala = company.skala;
        props.form.perusahaan_skala = company.skala;
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
// 6. MODAL POP-UP TAMBAH PERUSAHAAN BARU (SWEETALERT2)
// ============================================================================
const openAddCompanyModal = () => {
    showCompanyDropdown.value = false;

    const provinceOptions = (props.provinces || [])
        .map(p => `<option value="${p.id}" ${p.id == props.form.company_province_id ? 'selected' : ''}>${p.nama_provinsi}</option>`)
        .join('');

    const initialProvId = props.form.company_province_id || '';
    const initialKabId = props.form.company_kabupaten_id || '';
    const initialKodePos = props.form.zipcode || '';
    const initialJenisLokasi = props.form.company_jenis_lokasi || 'Dalam Negeri';
    const initialNegara = props.form.company_negara || '';
    const initialJenisPerusahaan = props.form.company_jenis_perusahaan || 'Perusahaan swasta';

    const foreignCountries = (props.negaras || []).filter(n => n.nama_negara.toLowerCase() !== 'indonesia');
    const negaraSelectOptions = foreignCountries
        .map(n => `<option value="${n.nama_negara}" ${n.nama_negara === initialNegara ? 'selected' : ''}>${n.nama_negara} (${n.benua || 'Dunia'})</option>`)
        .join('');

    const jenisOptionsHtml = jenisPerusahaanOptions.value
        .map(j => `<option value="${j.value}" ${j.value === initialJenisPerusahaan ? 'selected' : ''}>${j.label}</option>`)
        .join('');

    const skalaOptionsHtml = skalaOptions.value
        .map(s => `<option value="${s.value}" ${s.value === (props.form.company_skala || 'Nasional') ? 'selected' : ''}>${s.label}</option>`)
        .join('');

    const htmlContent = `
        <div style="text-align: left; font-size: 13px; color: #374151;" class="space-y-3.5 pt-1">
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                    Jenis Lokasi Perusahaan <span style="color: #ef4444;">*</span>
                </label>
                <div style="display: flex; gap: 16px; margin-top: 4px;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; font-weight: 600; color: #1f2937;">
                        <input type="radio" id="swal-lokasi-dalam" name="swal-jenis-lokasi" value="Dalam Negeri" ${initialJenisLokasi === 'Dalam Negeri' ? 'checked' : ''} style="accent-color: #005B3C;" />
                        Dalam Negeri (Indonesia)
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; font-weight: 600; color: #1f2937;">
                        <input type="radio" id="swal-lokasi-luar" name="swal-jenis-lokasi" value="Luar Negeri" ${initialJenisLokasi === 'Luar Negeri' ? 'checked' : ''} style="accent-color: #005B3C;" />
                        Luar Negeri (Abroad)
                    </label>
                </div>
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                    Nama Perusahaan / Instansi <span style="color: #ef4444;">*</span>
                </label>
                <input id="swal-company-name" type="text" style="width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 9px 12px; font-size: 14px; box-sizing: border-box; outline: none;" placeholder="Contoh: PT Teknologi Nusantara / Google Singapore" />
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                    Jenis Perusahaan / Instansi (F11)
                </label>
                <select id="swal-company-jenis" style="width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 9px 12px; font-size: 14px; box-sizing: border-box; outline: none; background: #fff;">
                    ${jenisOptionsHtml}
                </select>
            </div>

            <div id="swal-section-jenis-lainnya" style="display: ${initialJenisPerusahaan === 'Lainnya' ? 'block' : 'none'};">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                    Sebutkan Jenis Instansi Lainnya
                </label>
                <input id="swal-company-jenis-lainnya" type="text" style="width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 9px 12px; font-size: 14px; box-sizing: border-box; outline: none;" placeholder="Contoh: Startup Komunitas, Lembaga Riset..." />
            </div>

            <div id="swal-section-luar-negeri" style="${initialJenisLokasi === 'Luar Negeri' ? 'display: block;' : 'display: none;'}">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                    Negara Tempat Bekerja <span style="color: #ef4444;">*</span>
                </label>
                <select id="swal-company-negara" style="width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 9px 12px; font-size: 14px; box-sizing: border-box; outline: none; background: #fff;">
                    <option value="">-- Pilih Negara di Luar Negeri --</option>
                    ${negaraSelectOptions}
                </select>
            </div>

            <div id="swal-section-dalam-negeri" style="${initialJenisLokasi === 'Dalam Negeri' ? 'display: grid;' : 'display: none;'} grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                        Provinsi <span style="color: #ef4444;">*</span>
                    </label>
                    <select id="swal-company-province" style="width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 9px 12px; font-size: 14px; box-sizing: border-box; outline: none; background: #fff;">
                        <option value="">-- Pilih Provinsi --</option>
                        ${provinceOptions}
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                        Kabupaten / Kota <span style="color: #ef4444;">*</span>
                    </label>
                    <select id="swal-company-kabupaten" style="width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 9px 12px; font-size: 14px; box-sizing: border-box; outline: none; background: #fff;">
                        <option value="">-- Pilih Kabupaten --</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                        Kode Pos Perusahaan
                    </label>
                    <input id="swal-company-kodepos" type="text" value="${initialKodePos}" style="width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 9px 12px; font-size: 14px; box-sizing: border-box; outline: none;" placeholder="Contoh: 55281 / 117371" />
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                        Skala Perusahaan
                    </label>
                    <select id="swal-company-skala" style="width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 9px 12px; font-size: 14px; box-sizing: border-box; outline: none; background: #fff;">
                        ${skalaOptionsHtml}
                    </select>
                </div>
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                    Alamat Jalan / Gedung Perusahaan
                </label>
                <textarea id="swal-company-alamat" rows="2" style="width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 9px 12px; font-size: 14px; box-sizing: border-box; outline: none; resize: vertical;" placeholder="Nama Jalan, Gedung, Nomor..."></textarea>
            </div>
        </div>
    `;

    Swal.fire({
        title: '<div style="font-size: 18px; font-weight: 700; color: #111827;">Tambah Perusahaan Baru</div>',
        html: htmlContent,
        showCancelButton: true,
        confirmButtonText: 'Simpan Perusahaan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#005B3C',
        cancelButtonColor: '#9CA3AF',
        focusConfirm: false,
        width: '34rem',
        customClass: { popup: 'rounded-2xl shadow-xl' },
        didOpen: () => {
            const radioDalam = document.getElementById('swal-lokasi-dalam');
            const radioLuar = document.getElementById('swal-lokasi-luar');
            const sectionDalam = document.getElementById('swal-section-dalam-negeri');
            const sectionLuar = document.getElementById('swal-section-luar-negeri');
            const provSelect = document.getElementById('swal-company-province');
            const kabSelect = document.getElementById('swal-company-kabupaten');
            const jenisSelect = document.getElementById('swal-company-jenis');
            const sectionJenisLainnya = document.getElementById('swal-section-jenis-lainnya');

            if (jenisSelect && sectionJenisLainnya) {
                jenisSelect.addEventListener('change', (e) => {
                    sectionJenisLainnya.style.display = (e.target.value === 'Lainnya') ? 'block' : 'none';
                });
            }

            const toggleLocationSections = () => {
                if (radioLuar.checked) {
                    sectionLuar.style.display = 'block';
                    sectionDalam.style.display = 'none';
                } else {
                    sectionLuar.style.display = 'none';
                    sectionDalam.style.display = 'grid';
                }
            };

            radioDalam.addEventListener('change', toggleLocationSections);
            radioLuar.addEventListener('change', toggleLocationSections);

            const updateKabupatenOptions = (provId, selectedKabId = '') => {
                if (!provId) {
                    kabSelect.innerHTML = '<option value="">-- Pilih Kabupaten --</option>';
                    kabSelect.disabled = true;
                    kabSelect.style.backgroundColor = '#f3f4f6';
                    return;
                }
                const filtered = (props.kabupatens || []).filter(k => k.province_id == provId);
                let options = '<option value="">-- Pilih Kabupaten --</option>';
                filtered.forEach(k => {
                    const sel = k.id == selectedKabId ? 'selected' : '';
                    options += `<option value="${k.id}" ${sel}>${k.nama_kabupaten}</option>`;
                });
                kabSelect.innerHTML = options;
                kabSelect.disabled = false;
                kabSelect.style.backgroundColor = '#fff';
            };

            if (initialProvId) {
                updateKabupatenOptions(initialProvId, initialKabId);
            } else {
                kabSelect.disabled = true;
                kabSelect.style.backgroundColor = '#f3f4f6';
            }

            provSelect.addEventListener('change', (e) => {
                updateKabupatenOptions(e.target.value);
            });

            const nameInput = document.getElementById('swal-company-name');
            if (nameInput) nameInput.focus();
        },
        preConfirm: async () => {
            const isLuar = document.getElementById('swal-lokasi-luar')?.checked;
            const jenisLokasi = isLuar ? 'Luar Negeri' : 'Dalam Negeri';
            const nama = document.getElementById('swal-company-name')?.value?.trim();
            const negara = document.getElementById('swal-company-negara')?.value?.trim();
            const provId = document.getElementById('swal-company-province')?.value;
            const kabId = document.getElementById('swal-company-kabupaten')?.value;
            const kodepos = document.getElementById('swal-company-kodepos')?.value?.trim();
            const skala = document.getElementById('swal-company-skala')?.value || (isLuar ? 'Internasional' : 'Regional/Lokal');
            const alamat = document.getElementById('swal-company-alamat')?.value?.trim() || '';
            const jenisPerusahaan = document.getElementById('swal-company-jenis')?.value || 'Perusahaan swasta';
            const jenisPerusahaanLainnya = document.getElementById('swal-company-jenis-lainnya')?.value?.trim() || null;

            if (!nama) {
                Swal.showValidationMessage('Nama Perusahaan / Instansi wajib diisi.');
                return false;
            }

            if (isLuar) {
                if (!negara || negara === 'Indonesia' || !foreignCountries.some(n => n.nama_negara === negara)) {
                    Swal.showValidationMessage('Silakan pilih salah satu negara di luar negeri dari daftar master resmi.');
                    return false;
                }
            } else {
                if (!provId) {
                    Swal.showValidationMessage('Provinsi Perusahaan wajib dipilih.');
                    return false;
                }
                if (!kabId) {
                    Swal.showValidationMessage('Kabupaten / Kota Perusahaan wajib dipilih.');
                    return false;
                }
            }

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const res = await fetch('/alumni/company', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        nama_perusahaan: nama,
                        jenis_lokasi: jenisLokasi,
                        negara: isLuar ? negara : 'Indonesia',
                        province_id: isLuar ? null : provId,
                        kabupaten_id: isLuar ? null : kabId,
                        kode_pos: kodepos,
                        skala: skala,
                        jenis_perusahaan: jenisPerusahaan,
                        jenis_perusahaan_lainnya: jenisPerusahaanLainnya,
                        alamat: alamat
                    })
                });

                const data = await res.json();
                if (!res.ok || !data.success || !data.company) {
                    Swal.showValidationMessage(data.message || 'Gagal menyimpan data perusahaan.');
                    return false;
                }
                return data.company;
            } catch (error) {
                Swal.showValidationMessage('Gagal menghubungi server. Silakan coba lagi.');
                return false;
            }
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const newCompany = result.value;
            localCompanies.value.unshift(newCompany);

            props.form.nama_perusahaan = newCompany.nama_perusahaan;
            props.form.company_jenis_lokasi = newCompany.jenis_lokasi || (newCompany.negara && newCompany.negara !== 'Indonesia' ? 'Luar Negeri' : 'Dalam Negeri');
            props.form.perusahaan_jenis_lokasi = props.form.company_jenis_lokasi;
            props.form.company_negara = newCompany.negara || (props.form.company_jenis_lokasi === 'Luar Negeri' ? '' : 'Indonesia');
            props.form.perusahaan_negara = props.form.company_negara;
            props.form.company_province_id = newCompany.province_id;
            props.form.perusahaan_propinsi_id = newCompany.province_id;
            props.form.company_kabupaten_id = newCompany.kabupaten_id;
            props.form.perusahaan_kabupaten_id = newCompany.kabupaten_id;
            props.form.company_alamat = newCompany.alamat || '';
            props.form.company_skala = newCompany.skala || 'Regional/Lokal';
            props.form.zipcode = newCompany.kode_pos || '';
            props.form.company_kode_pos = newCompany.kode_pos || '';
            props.form.perusahaan_kode_pos = newCompany.kode_pos || '';
            props.form.company_jenis_perusahaan = newCompany.jenis_perusahaan || '';
            props.form.perusahaan_jenis_perusahaan = props.form.company_jenis_perusahaan;
            props.form.company_jenis_perusahaan_lainnya = newCompany.jenis_perusahaan_lainnya || '';
            props.form.perusahaan_jenis_perusahaan_lainnya = props.form.company_jenis_perusahaan_lainnya;
            props.form.company_status_verifikasi = 'Menunggu Verifikasi';

            Swal.fire({
                icon: 'success',
                title: 'Berhasil Ditambahkan',
                text: `Perusahaan "${newCompany.nama_perusahaan}" berhasil ditambahkan dan dipilih.`,
                confirmButtonColor: '#005B3C',
                timer: 2500,
                timerProgressBar: true
            });
        }
    });
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
                <div>
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        LinkedIn Profil URL <span class="text-rose-500 font-bold">*</span>
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
                <div>
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        LinkedIn Username <span class="text-rose-500 font-bold">*</span>
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
                <div>
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Instagram Profil URL <span class="text-rose-500 font-bold">*</span>
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
                <div>
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Facebook Profil URL <span class="text-rose-500 font-bold">*</span>
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
                <div class="md:col-span-2">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5 flex items-center justify-between">
                        <span>Keahlian (Skills) <span class="text-rose-500 font-bold">*</span></span>
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
                <div class="md:col-span-2">
                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5 flex items-center justify-between">
                        <span>Pengalaman Kerja (Experience) <span class="text-rose-500 font-bold">*</span></span>
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
                            Status Pekerjaan / Aktivitas Utama <span class="text-rose-500 font-bold">*</span>
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
                        Bidang Pekerjaan / Divisi
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
                        Pilihan dikelola dari master Superadmin. Jika belum tersedia, pilih Lainnya dan tuliskan sendiri.
                    </p>
                </div>

                <!-- Jika Karyawan/Pekerja: Posisi Jabatan Struktural (F2G) Dinamis dari Database -->
                <div v-if="form.kategori_pekerjaan === 'Pekerja'" class="md:col-span-2 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700">
                            Posisi Jabatan Struktural (F2G) <span class="text-rose-500 font-bold">*</span>
                        </label>
                        <button 
                            type="button" 
                            @click="isCustomJabatan = !isCustomJabatan" 
                            class="text-xs font-bold text-[#0D542B] hover:underline cursor-pointer"
                        >
                            {{ isCustomJabatan ? '&larr; Pilih dari Dropdown Standar' : '+ Tulis Jabatan Kustom' }}
                        </button>
                    </div>

                    <!-- Input Bebas jika jabatan spesifik/kustom -->
                    <div v-if="isCustomJabatan">
                        <input 
                            type="text" 
                            v-model="form.posisi_jabatan" 
                            placeholder="Contoh: Wakil Dekan I, Lead Software Engineer, Konsultan..."
                            class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        />
                        <p class="text-[11px] text-gray-400 mt-1">Jabatan kustom otomatis tersimpan ke profil alumni.</p>
                    </div>

                    <!-- Dropdown Standar F2G (jika tidak memilih input kustom) -->
                    <div v-else>
                        <select 
                            v-model="form.posisi_jabatan" 
                            class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        >
                            <option value="">-- Pilih Posisi Jabatan --</option>
                            <option v-for="opt in posisiJabatanOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Jika Wiraswasta: Posisi / Jabatan Wiraswasta (F5C) -->
                <div v-else-if="form.kategori_pekerjaan === 'Wiraswasta'" class="md:col-span-2 space-y-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                            Posisi / Jabatan Wiraswasta & Startup (F5C) <span class="text-rose-500 font-bold">*</span>
                        </label>
                        <select 
                            v-model="form.posisi_wiraswasta" 
                            class="block w-full border border-gray-300 bg-white text-gray-900 rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                        >
                            <option value="">-- Pilih Posisi / Jabatan Wiraswasta --</option>
                            <option value="Owner">Owner</option>
                            <option value="Founder">Founder</option>
                            <option value="Co-Founder">Co-Founder</option>
                            <option value="Direktur Utama">Direktur Utama</option>
                            <option value="Pengelola Usaha">Pengelola Usaha</option>
                            <option value="Freelancer / Konsultan Mandiri">Freelancer / Konsultan Mandiri</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>

                    <!-- Input Tambahan jika memilih Lainnya -->
                    <div v-if="form.posisi_wiraswasta === 'Lainnya'">
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
                                Jenjang Pendidikan <span class="text-rose-500 font-bold">*</span>
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
                                Perguruan Tinggi <span class="text-rose-500 font-bold">*</span>
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
                                Program Studi <span class="text-rose-500 font-bold">*</span>
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
                <div class="md:col-span-2">
                    <div class="flex items-center justify-between mb-1.5 flex-wrap gap-1">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700 !mb-0">
                            Rata-rata Pendapatan per Bulan (Take Home Pay) <span class="text-rose-500 font-bold">*</span>
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
        <!-- CARD 2: DATA PERUSAHAAN / INSTANSI TEMPAT BEKERJA                 -->
        <!-- ================================================================= -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-xs border border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 mb-6 border-b border-gray-100 gap-3">
                <div>
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-1.5 h-5 bg-[#0D542B] rounded-full inline-block"></span>
                        Data {{ form.kategori_pekerjaan === 'Wiraswasta' ? 'Usaha / Bisnis' : 'Perusahaan / Tempat Bekerja' }}
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">Filter lokasi wilayah atau pilih perusahaan yang terdaftar</p>
                </div>

                <!-- Pilihan Lokasi Perusahaan: Dalam Negeri / Luar Negeri -->
                <div class="flex items-center gap-2 bg-gray-100/80 p-1.5 rounded-xl border border-gray-200">
                    <button 
                        type="button" 
                        @click="form.company_jenis_lokasi = 'Dalam Negeri'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="form.company_jenis_lokasi === 'Dalam Negeri' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-gray-600 hover:text-gray-900 hover:bg-white/60'"
                    >
                        Dalam Negeri
                    </button>
                    <button 
                        type="button" 
                        @click="form.company_jenis_lokasi = 'Luar Negeri'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="form.company_jenis_lokasi === 'Luar Negeri' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-gray-600 hover:text-gray-900 hover:bg-white/60'"
                    >
                        Luar Negeri
                    </button>
                </div>
            </div>

            <div class="space-y-5">
                <!-- ============================================================= -->
                <!-- 1. FILTER LOKASI WILAYAH (BISA DIPILIH SEBELUM MEMILIH PT)    -->
                <!-- ============================================================= -->
                
                <!-- Jika Luar Negeri: Dropdown Pencarian Master Negara Dunia -->
                <div v-if="form.company_jenis_lokasi === 'Luar Negeri'" class="relative" ref="negaraContainerRef">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700">
                            Negara Tempat Bekerja <span class="text-rose-500 font-bold">*</span>
                        </label>
                        <button 
                            v-if="selectedNegaraName" 
                            type="button" 
                            @click="clearNegara" 
                            class="text-[11px] font-bold text-gray-500 hover:text-rose-600 cursor-pointer"
                        >
                            Reset Negara
                        </button>
                    </div>
                    
                    <div 
                        @click="showNegaraDropdown = !showNegaraDropdown; if (showNegaraDropdown) searchNegaraQuery = '';"
                        class="w-full rounded-xl border border-gray-300 bg-white text-gray-900 px-4 py-3 text-sm font-medium cursor-pointer flex justify-between items-center shadow-xs hover:border-[#0D542B] transition-colors"
                    >
                        <span v-if="selectedNegaraName" class="text-gray-900 font-semibold">
                            {{ selectedNegaraName }}
                        </span>
                        <span v-else class="text-gray-400">Pilih Negara di Luar Negeri...</span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0 ml-2 transition-transform duration-200" :class="{'rotate-180': showNegaraDropdown}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>

                    <!-- Dropdown List Negara -->
                    <div v-if="showNegaraDropdown" class="absolute z-50 w-full mt-1.5 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden">
                        <div class="p-2.5 border-b border-gray-100 bg-gray-50/80">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </div>
                                <input 
                                    type="text" 
                                    v-model="searchNegaraQuery" 
                                    class="w-full pl-9 pr-3 py-1.5 border border-gray-200 rounded-lg text-sm bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] outline-none"
                                    placeholder="Ketik untuk mencari negara..."
                                    autofocus
                                >
                            </div>
                        </div>
                        <ul class="max-h-56 overflow-y-auto divide-y divide-gray-50">
                            <li 
                                v-for="neg in filteredNegaras" 
                                :key="neg.id" 
                                @click="selectNegara(neg)" 
                                class="px-4 py-2.5 hover:bg-slate-50 hover:text-[#0D542B] cursor-pointer text-sm font-medium transition-colors flex items-center justify-between"
                                :class="{'bg-slate-100 text-[#0D542B] font-semibold': form.company_negara === neg.nama_negara}"
                            >
                                <div class="flex items-center gap-2">
                                    <span>{{ neg.nama_negara }}</span>
                                    <span v-if="neg.kode_iso2" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 uppercase">{{ neg.kode_iso2 }}</span>
                                </div>
                                <div class="text-xs text-gray-400 font-normal">
                                    {{ neg.benua || 'Luar Negeri' }} <span v-if="neg.ibu_kota">• {{ neg.ibu_kota }}</span>
                                </div>
                            </li>
                            <li v-if="filteredNegaras.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
                                <div>Negara tidak ditemukan dalam daftar master resmi negara dunia.</div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Jika Dalam Negeri: Filter Provinsi & Kabupaten / Kota -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Dropdown Provinsi -->
                    <div class="relative" ref="provinsiContainerRef">
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs sm:text-sm font-bold text-gray-700">
                                Provinsi Perusahaan <span class="text-rose-500 font-bold">*</span>
                            </label>
                            <button 
                                v-if="form.company_province_id" 
                                type="button" 
                                @click="clearProvinsi" 
                                class="text-[11px] font-bold text-gray-500 hover:text-rose-600 cursor-pointer"
                            >
                                Reset Provinsi
                            </button>
                        </div>
                        
                        <div 
                            @click="showProvinsiDropdown = !showProvinsiDropdown; if (showProvinsiDropdown) searchProvinsiQuery = '';"
                            class="w-full rounded-xl border border-gray-300 bg-white text-gray-900 px-4 py-3 text-sm font-medium cursor-pointer flex justify-between items-center shadow-xs hover:border-[#0D542B] transition-colors"
                        >
                            <span v-if="selectedProvinceName" class="text-gray-900 font-medium">{{ selectedProvinceName }}</span>
                            <span v-else class="text-gray-400">Pilih Provinsi Perusahaan...</span>
                            <svg class="w-4 h-4 text-gray-400 shrink-0 ml-2 transition-transform duration-200" :class="{'rotate-180': showProvinsiDropdown}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>

                        <div v-if="showProvinsiDropdown" class="absolute z-50 w-full mt-1.5 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden">
                            <div class="p-2.5 border-b border-gray-100 bg-gray-50/80">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </div>
                                    <input 
                                        type="text" 
                                        v-model="searchProvinsiQuery" 
                                        class="w-full pl-9 pr-3 py-1.5 border border-gray-200 rounded-lg text-sm bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] outline-none"
                                        placeholder="Ketik untuk mencari provinsi..."
                                        autofocus
                                    >
                                </div>
                            </div>
                            <ul class="max-h-52 overflow-y-auto">
                                <li 
                                    v-for="prov in filteredProvinces" 
                                    :key="prov.id" 
                                    @click="selectProvinsi(prov)" 
                                    class="px-4 py-2.5 hover:bg-slate-50 hover:text-[#0D542B] cursor-pointer text-sm font-medium transition-colors border-b border-gray-50 last:border-b-0"
                                    :class="{'bg-slate-100 text-[#0D542B] font-semibold': form.company_province_id == prov.id}"
                                >
                                    {{ prov.nama_provinsi }}
                                </li>
                                <li v-if="filteredProvinces.length === 0" class="px-4 py-4 text-center text-sm text-gray-400">
                                    Tidak ada hasil yang cocok.
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Dropdown Kabupaten -->
                    <div class="relative" ref="kabupatenContainerRef">
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs sm:text-sm font-bold text-gray-700">
                                Kabupaten / Kota Perusahaan <span class="text-rose-500 font-bold">*</span>
                            </label>
                            <button 
                                v-if="form.company_kabupaten_id" 
                                type="button" 
                                @click="clearKabupaten" 
                                class="text-[11px] font-bold text-gray-500 hover:text-rose-600 cursor-pointer"
                            >
                                Reset Kota
                            </button>
                        </div>
                        
                        <div 
                            @click="if (form.company_province_id) { showKabupatenDropdown = !showKabupatenDropdown; if (showKabupatenDropdown) searchKabupatenQuery = ''; }"
                            class="w-full rounded-xl border px-4 py-3 text-sm font-medium cursor-pointer flex justify-between items-center shadow-xs transition-colors"
                            :class="[
                                !form.company_province_id ? 'opacity-60 cursor-not-allowed bg-gray-50 border-gray-200 text-gray-400' :
                                'border-gray-300 bg-white text-gray-900 hover:border-[#0D542B]'
                            ]"
                        >
                            <span v-if="selectedKabupatenName" class="text-gray-900 font-medium">{{ selectedKabupatenName }}</span>
                            <span v-else class="text-gray-400">
                                {{ form.company_province_id ? 'Pilih Kabupaten/Kota...' : 'Pilih Provinsi terlebih dahulu' }}
                            </span>
                            <svg class="w-4 h-4 text-gray-400 shrink-0 ml-2 transition-transform duration-200" :class="{'rotate-180': showKabupatenDropdown}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>

                        <div v-if="showKabupatenDropdown && form.company_province_id" class="absolute z-50 w-full mt-1.5 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden">
                            <div class="p-2.5 border-b border-gray-100 bg-gray-50/80">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </div>
                                    <input 
                                        type="text" 
                                        v-model="searchKabupatenQuery" 
                                        class="w-full pl-9 pr-3 py-1.5 border border-gray-200 rounded-lg text-sm bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] outline-none"
                                        placeholder="Ketik untuk mencari kabupaten/kota..."
                                        autofocus
                                    >
                                </div>
                            </div>
                            <ul class="max-h-52 overflow-y-auto">
                                <li 
                                    v-for="kab in filteredKabupatens" 
                                    :key="kab.id" 
                                    @click="selectKabupaten(kab)" 
                                    class="px-4 py-2.5 hover:bg-slate-50 hover:text-[#0D542B] cursor-pointer text-sm font-medium transition-colors border-b border-gray-50 last:border-b-0"
                                    :class="{'bg-slate-100 text-[#0D542B] font-semibold': form.company_kabupaten_id == kab.id}"
                                >
                                    {{ kab.nama_kabupaten }}
                                </li>
                                <li v-if="filteredKabupatens.length === 0" class="px-4 py-4 text-center text-sm text-gray-400">
                                    Tidak ada kabupaten/kota yang cocok.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- ============================================================= -->
                <!-- 2. PEMILIHAN / PENCARIAN NAMA PERUSAHAAN (BERDASARKAN FILTER)  -->
                <!-- ============================================================= -->
                <div class="relative pt-1" ref="companyContainerRef">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700">
                            Nama {{ form.kategori_pekerjaan === 'Wiraswasta' ? 'Usaha / Perusahaan' : 'Perusahaan / Instansi' }} <span class="text-rose-500 font-bold">*</span>
                        </label>
                    </div>

                    <div class="flex items-center gap-2">
                        <div 
                            @click="showCompanyDropdown = !showCompanyDropdown; if (showCompanyDropdown) searchCompanyQuery = '';"
                            class="flex-1 rounded-xl border border-gray-300 bg-white text-gray-900 px-4 py-3 text-sm font-medium cursor-pointer flex justify-between items-center shadow-xs hover:border-[#0D542B] transition-colors"
                        >
                            <span v-if="form.nama_perusahaan" class="text-gray-900 font-semibold truncate">{{ form.nama_perusahaan }}</span>
                            <span v-else class="text-gray-400">
                                {{ (form.company_province_id || form.company_negara) ? 'Pilih atau cari perusahaan di wilayah ini...' : 'Pilih atau cari nama perusahaan...' }}
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
                            <span>✕ Kosongkan</span>
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
                                    placeholder="Ketik untuk mencari nama perusahaan..."
                                    autofocus
                                >
                            </div>
                        </div>

                        <ul class="max-h-56 overflow-y-auto">
                            <!-- Opsi Kosongkan di Dalam List Dropdown -->
                            <li 
                                v-if="form.nama_perusahaan"
                                @click="clearCompany" 
                                class="px-4 py-2.5 hover:bg-rose-50 text-rose-600 cursor-pointer text-xs font-bold border-b border-gray-100 flex items-center gap-1.5 transition-colors"
                            >
                                <span>✕ Kosongkan Pilihan Perusahaan</span>
                            </li>

                            <li 
                                v-for="company in filteredCompanies" 
                                :key="company.id" 
                                @click="selectCompany(company)" 
                                class="px-4 py-3 hover:bg-slate-50 hover:text-[#0D542B] cursor-pointer text-sm font-medium transition-colors border-b border-gray-50 last:border-b-0 flex justify-between items-center"
                                :class="{'bg-slate-100 text-[#0D542B] font-semibold': form.nama_perusahaan === company.nama_perusahaan}"
                            >
                                <div>
                                    <span class="block text-gray-800" :class="{'text-[#0D542B] font-bold': form.nama_perusahaan === company.nama_perusahaan}">
                                        {{ company.nama_perusahaan }}
                                    </span>
                                    <span class="text-xs text-gray-400" v-if="company.jenis_lokasi === 'Luar Negeri' || (company.negara && company.negara !== 'Indonesia')">
                                        {{ company.negara || 'Luar Negeri' }}
                                    </span>
                                    <span class="text-xs text-gray-400" v-else-if="company.province_id">
                                        {{ provinces.find(p => p.id == company.province_id)?.nama_provinsi || '' }}
                                        {{ company.kabupaten_id ? ' - ' + (kabupatens.find(k => k.id == company.kabupaten_id)?.nama_kabupaten || '') : '' }}
                                    </span>
                                </div>
                                <span v-if="company.status_verifikasi === 'Terverifikasi'" class="text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded font-semibold shrink-0 ml-2">
                                    Terverifikasi
                                </span>
                                <span v-else class="text-xs text-amber-700 bg-amber-50 px-2 py-0.5 rounded font-semibold shrink-0 ml-2">
                                    Menunggu Verifikasi
                                </span>
                            </li>

                            <!-- State Jika Tidak Ada Perusahaan Ditemukan -->
                            <li v-if="filteredCompanies.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">
                                <div class="font-medium text-gray-600">Data perusahaan tidak ditemukan pada wilayah/filter yang dipilih.</div>
                                <button type="button" @click="openAddCompanyModal" class="mt-2 text-xs font-bold text-[#0D542B] hover:underline cursor-pointer">
                                    + Tambah Perusahaan Baru
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- ============================================================= -->
                <!-- 3. DETAIL PERUSAHAAN (Hanya Muncul Jika Perusahaan Sudah Dipilih) -->
                <!-- ============================================================= -->
                <div v-if="form.nama_perusahaan?.trim()" class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-4 border-t border-gray-100">
                    
                    <!-- Banner Notifikasi Status Verifikasi Perusahaan -->
                    <div v-if="!canEditCompanyDetails" class="md:col-span-2 bg-gray-50 border border-gray-200 rounded-xl p-3.5 flex items-center gap-3 text-xs text-gray-600 font-medium shadow-2xs">
                        <div class="w-2 h-2 rounded-full bg-emerald-600 shrink-0"></div>
                        <div>
                            Perusahaan ini telah <strong>Terverifikasi Resmi</strong> oleh universitas. Detail data perusahaan terkunci untuk menjaga konsistensi master data institusi.
                        </div>
                    </div>

                    <!-- Jenis Perusahaan / Instansi (F11 Standar Dikti Dinamis) -->
                    <div class="md:col-span-2">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                            Jenis Perusahaan / Instansi (F11) <span v-if="form.kategori_pekerjaan === 'Pekerja'" class="text-rose-500 font-bold">*</span>
                        </label>
                        <select 
                            v-model="form.company_jenis_perusahaan" 
                            @change="form.perusahaan_jenis_perusahaan = form.company_jenis_perusahaan" 
                            :disabled="!canEditCompanyDetails"
                            class="block w-full border rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors"
                            :class="[
                                !canEditCompanyDetails ? 'bg-gray-100/90 text-gray-500 cursor-not-allowed border-gray-200' : 
                                'border-gray-300 bg-white text-gray-900 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none'
                            ]"
                        >
                            <option value="">-- Pilih Jenis Instansi / Perusahaan --</option>
                            <option v-for="opt in jenisPerusahaanOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Input Isian Bebas jika memilih Jenis Lainnya -->
                    <div v-if="form.company_jenis_perusahaan === 'Lainnya'" class="md:col-span-2">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">Sebutkan Jenis Instansi Lainnya</label>
                        <input 
                            type="text" 
                            v-model="form.company_jenis_perusahaan_lainnya" 
                            @input="form.perusahaan_jenis_perusahaan_lainnya = form.company_jenis_perusahaan_lainnya"
                            :disabled="!canEditCompanyDetails"
                            class="block w-full border rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors"
                            :class="[
                                !canEditCompanyDetails ? 'bg-gray-100/90 text-gray-500 cursor-not-allowed border-gray-200' : 
                                'border-gray-300 bg-white text-gray-900 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none'
                            ]" 
                            placeholder="Contoh: Lembaga Riset Independen, Startup Komunitas..." 
                        />
                    </div>

                    <!-- Skala Perusahaan / Instansi (F2H / F5D Dinamis) -->
                    <div class="md:col-span-2">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                            Skala Perusahaan / Instansi (F2H / F5D) <span v-if="form.kategori_pekerjaan === 'Pekerja'" class="text-rose-500 font-bold">*</span>
                        </label>
                        <select 
                            v-model="form.company_skala" 
                            @change="form.perusahaan_skala = form.company_skala" 
                            :disabled="!canEditCompanyDetails"
                            class="block w-full border rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors"
                            :class="[
                                !canEditCompanyDetails ? 'bg-gray-100/90 text-gray-500 cursor-not-allowed border-gray-200' : 
                                'border-gray-300 bg-white text-gray-900 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none'
                            ]"
                        >
                            <option value="">-- Pilih Skala Perusahaan --</option>
                            <option v-for="opt in skalaOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Alamat Kantor / Perusahaan -->
                    <div class="md:col-span-2">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                            Alamat Perusahaan <span v-if="form.kategori_pekerjaan === 'Pekerja'" class="text-rose-500 font-bold">*</span>
                        </label>
                        <input 
                            type="text" 
                            v-model="form.company_alamat" 
                            @input="form.perusahaan_alamat = form.company_alamat"
                            :disabled="!canEditCompanyDetails"
                            class="block w-full border rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors"
                            :class="[
                                !canEditCompanyDetails ? 'bg-gray-100/90 text-gray-500 cursor-not-allowed border-gray-200' : 
                                'border-gray-300 bg-white text-gray-900 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none'
                            ]"
                            placeholder="Contoh: Pacific Building, Jl. Laksda Adisucipto No. 157, Sleman" 
                        />
                    </div>

                    <!-- Kode Pos Perusahaan -->
                    <div class="md:col-span-2">
                        <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                            Kode Pos Kantor / Perusahaan <span class="text-rose-500 font-bold">*</span>
                        </label>
                        <input 
                            type="text" 
                            v-model="form.zipcode" 
                            @input="form.company_kode_pos = form.zipcode; form.perusahaan_kode_pos = form.zipcode"
                            autocomplete="off"
                            :disabled="!canEditCompanyDetails"
                            class="block w-full border rounded-xl shadow-xs px-4 py-3 text-sm font-medium transition-colors"
                            :class="[
                                !canEditCompanyDetails ? 'bg-gray-100/90 text-gray-500 cursor-not-allowed border-gray-200' : 
                                'border-gray-300 bg-white text-gray-900 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none'
                            ]" 
                            placeholder="Kode Pos Kantor (Cth: 55281)" 
                        />
                    </div>
                </div>

                <!-- Petunjuk jika belum memilih nama perusahaan (Netral Abu-Abu) -->
                <div v-else class="p-5 bg-gray-50 rounded-2xl border border-dashed border-gray-200 text-center">
                    <div class="text-xs text-gray-500 font-medium">
                        Silakan pilih atau tambahkan nama {{ form.kategori_pekerjaan === 'Wiraswasta' ? 'usaha' : 'perusahaan' }} pada lokasi di atas untuk melengkapi jenis, skala, dan alamat kantor.
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- CARD 3: DATA ATASAN LANGSUNG / PIMPINAN                           -->
        <!-- (Hanya untuk Pekerja & Wiraswasta)                                -->
        <!-- ================================================================= -->
        <div v-if="form.kategori_pekerjaan !== 'Melanjutkan Pendidikan'" class="bg-white rounded-2xl p-6 sm:p-8 shadow-xs border border-gray-100">
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
                <div class="md:col-span-2">
                    <label for="input_nama_atasan_langsung" class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Nama Lengkap Atasan <span class="text-rose-500 font-bold">*</span>
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
                
                <div>
                    <label for="input_email_atasan_langsung" class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Email Atasan <span class="text-rose-500 font-bold">*</span>
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

                <div>
                    <label for="input_telepon_atasan_langsung" class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5">
                        Nomor Telepon Atasan <span class="text-rose-500 font-bold">*</span>
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
                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"
                        >
                            Atasan Sudah Mengisi
                        </span>
                        <span 
                            v-else-if="evaluasiAtasan?.has_invitation"
                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-800 border border-blue-200"
                        >
                            Tautan Siap / Terkirim
                        </span>
                        <span 
                            v-else 
                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200"
                        >
                            Belum Terkirim
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 max-w-xl leading-relaxed">
                        Email survei evaluasi tidak dikirim secara otomatis saat menyimpan profil untuk mencegah kesalahan penulisan email. Klik tombol di samping setelah Anda yakin data email atasan sudah benar.
                    </p>
                </div>

                <button 
                    type="button" 
                    @click="handleKirimEmailAtasan"
                    :disabled="sendingEmail || !form.email_atasan || !isValidEmail(form.email_atasan)"
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

    </div>
</template>
