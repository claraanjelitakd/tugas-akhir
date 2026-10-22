<?php

namespace App\Services\Perusahaan;

use App\Models\Biodata;
use App\Models\LogActivity;
use App\Models\Perusahaan;
use App\Models\Prodi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service untuk menangani verifikasi, rekomendasi kesamaan, dan penggantian otomatis
 * data perusahaan/institusi tempat kerja alumni yang diajukan oleh alumni.
 */
class PerusahaanVerificationService
{
    /**
     * Kata-kata umum, bentuk badan usaha, dan atribut unit/cabang administratif yang diabaikan saat normalisasi nama perusahaan.
     *
     * @var array<int, string>
     */
    protected array $stopWords = [
        // Bentuk badan usaha & legalitas
        'pt', 'p.t', 'cv', 'c.v', 'tbk', 'persero', 'corp', 'corporation', 'inc', 'ltd',
        'llc', 'gmbh', 'co', 'company', 'group', 'holding', 'perum', 'yayasan',
        'ud', 'u.d', 'fa', 'firma', 'koperasi', 'pte',

        // Keterangan kantor, cabang, unit operasional, dan wilayah
        'kantor', 'pusat', 'cabang', 'branch', 'subcabang', 'kcu', 'kc', 'kcp',
        'unit', 'witel', 'regional', 'wilayah', 'area', 'divisi', 'division',
        'office', 'hq', 'head', 'representative',

        // Instansi & lembaga birokrasi pemerintahan
        'dinas', 'kementerian', 'badan', 'lembaga',

        // Kata hubung & penanda geografis umum
        'indonesia', 'dan', 'and', 'of', 'the', 'in', 'di',
    ];

    /**
     * Kata-kata klasifikasi jenis industri/sektor umum yang tidak boleh menjadi penentu kecocokan tunggal jika hanya 1 kata yang sama.
     * Mencegah "Bank Mandiri" merekomendasikan "Bank BCA", "Bank BPD", "Bank Aceh" hanya karena sama-sama ada kata "Bank".
     *
     * @var array<int, string>
     */
    protected array $genericIndustryWords = [
        'bank', 'universitas', 'univ', 'institut', 'sekolah', 'rs', 'hospital',
        'hotel', 'studio', 'restoran', 'resto', 'cafe', 'toko', 'media', 'lab', 'laboratorium',
    ];

    /**
     * Mengambil daftar perusahaan yang menunggu verifikasi untuk Program Studi tertentu.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPendingForProdi(int $prodiId, array $filters = []): LengthAwarePaginator
    {
        $query = Perusahaan::query()
            ->with([
                'propinsi',
                'kabupaten',
                'creator:id,name,role,username',
                'creator.biodata.prodi:id,nama_prodi',
                'creator.biodata.dataAkademik:nim,nama,tahun_lulus,angkatan_masuk',
                'createdProdi:id,nama_prodi',
                'latestVerificationLog.user:id,name,role,username',
                'biodata' => function ($q) {
                    $q->select('id', 'user_id', 'nim', 'prodi_id', 'posisi_jabatan', 'kategori_pekerjaan', 'perusahaan_id')
                        ->with(['dataAkademik:nim,nama,tahun_lulus,angkatan_masuk', 'prodi:id,nama_prodi']);
                },
            ])
            ->withCount('biodata');

        $status = $filters['status'] ?? 'all';
        if ($status !== 'all') {
            $query->where('status_verifikasi', $status);
        }

        // Filter apakah hanya pengajuan dari alumni prodi ini atau semua
        $scope = $filters['scope'] ?? 'prodi';
        if ($scope === 'prodi') {
            $query->where(function ($q) use ($prodiId) {
                $q->where('created_by_prodi_id', $prodiId)
                    ->orWhereHas('biodata', fn ($b) => $b->where('prodi_id', $prodiId))
                    ->orWhereHas('creator', fn ($u) => $u->where('prodi_id', $prodiId));
            });
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('nama_perusahaan', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%")
                    ->orWhereHas('propinsi', fn ($p) => $p->where('nama_provinsi', 'like', "%{$search}%"))
                    ->orWhereHas('kabupaten', fn ($k) => $k->where('nama_kabupaten', 'like', "%{$search}%"))
                    ->orWhereHas('biodata', fn ($b) => $b->where('nim', 'like', "%{$search}%")->orWhereHas('dataAkademik', fn ($da) => $da->where('nama', 'like', "%{$search}%")))
                    ->orWhereHas('creator', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 10);

        return $query->latest('updated_at')->paginate($perPage)->withQueryString();
    }

    /**
     * Mengambil daftar perusahaan yang menunggu verifikasi untuk lingkup Fakultas tertentu.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPendingForFakultas(int $fakultasId, array $filters = []): LengthAwarePaginator
    {
        $prodiIds = Prodi::where('fakultas_id', $fakultasId)->pluck('id')->all();

        $query = Perusahaan::query()
            ->with([
                'propinsi',
                'kabupaten',
                'creator:id,name,role,username',
                'creator.biodata.prodi:id,nama_prodi',
                'createdProdi:id,nama_prodi',
                'latestVerificationLog.user:id,name,role,username',
                'biodata' => function ($q) {
                    $q->select('id', 'user_id', 'nim', 'prodi_id', 'posisi_jabatan', 'kategori_pekerjaan', 'perusahaan_id')
                        ->with(['dataAkademik:nim,nama,tahun_lulus', 'prodi:id,nama_prodi']);
                },
            ]);

        $status = $filters['status'] ?? 'all';
        if ($status !== 'all') {
            $query->where('status_verifikasi', $status);
        }

        if (! empty($filters['prodi_id']) && in_array((int) $filters['prodi_id'], $prodiIds)) {
            $targetProdiId = (int) $filters['prodi_id'];
            $query->where(function ($q) use ($targetProdiId) {
                $q->where('created_by_prodi_id', $targetProdiId)
                    ->orWhereHas('biodata', fn ($b) => $b->where('prodi_id', $targetProdiId))
                    ->orWhereHas('creator', fn ($u) => $u->where('prodi_id', $targetProdiId));
            });
        } else {
            $query->where(function ($q) use ($prodiIds) {
                $q->whereIn('created_by_prodi_id', $prodiIds)
                    ->orWhereHas('biodata', fn ($b) => $b->whereIn('prodi_id', $prodiIds))
                    ->orWhereHas('creator', fn ($u) => $u->whereIn('prodi_id', $prodiIds));
            });
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('nama_perusahaan', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%")
                    ->orWhereHas('propinsi', fn ($p) => $p->where('nama_provinsi', 'like', "%{$search}%"))
                    ->orWhereHas('kabupaten', fn ($k) => $k->where('nama_kabupaten', 'like', "%{$search}%"))
                    ->orWhereHas('biodata', fn ($b) => $b->where('nim', 'like', "%{$search}%")->orWhereHas('dataAkademik', fn ($da) => $da->where('nama', 'like', "%{$search}%")))
                    ->orWhereHas('creator', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 10);

        return $query->latest('updated_at')->paginate($perPage)->withQueryString();
    }

    /**
     * Menghitung total pengajuan perusahaan menunggu verifikasi.
     */
    public function countPendingForProdi(int $prodiId): int
    {
        return Perusahaan::where('status_verifikasi', 'Menunggu Verifikasi')
            ->where(function ($q) use ($prodiId) {
                $q->where('created_by_prodi_id', $prodiId)
                    ->orWhereHas('biodata', fn ($b) => $b->where('prodi_id', $prodiId))
                    ->orWhereHas('creator', fn ($u) => $u->where('prodi_id', $prodiId));
            })
            ->count();
    }

    /**
     * Menghitung total pengajuan perusahaan menunggu verifikasi di tingkat fakultas.
     */
    public function countPendingForFakultas(int $fakultasId): int
    {
        $prodiIds = Prodi::where('fakultas_id', $fakultasId)->pluck('id')->all();

        return Perusahaan::where('status_verifikasi', 'Menunggu Verifikasi')
            ->where(function ($q) use ($prodiIds) {
                $q->whereIn('created_by_prodi_id', $prodiIds)
                    ->orWhereHas('biodata', fn ($b) => $b->whereIn('prodi_id', $prodiIds))
                    ->orWhereHas('creator', fn ($u) => $u->whereIn('prodi_id', $prodiIds));
            })
            ->count();
    }

    /**
     * Menemukan daftar rekomendasi perusahaan terverifikasi yang mirip dengan perusahaan pengajuan (pending).
     *
     * CATATAN ALGORITMA (Bukan sekadar trim() biasa):
     * Metode ini BUKAN sekadar menggunakan fungsi trim() string bawaan PHP, melainkan menerapkan
     * "Multi-level String Similarity Matching & Geographic Scoring Algorithm" dengan tahapan:
     * 1. Preprocessing: Pembersihan tanda baca & pembuangan Stop Words badan usaha (PT, CV, Corp, dsb.).
     * 2. Level 1 (Skor 95): Exact Match nama bersih (tanpa embel-embel badan usaha).
     * 3. Level 2 (Skor 88): Akronim / Singkatan Match (contoh: "BCA" cocok dengan "Bank Central Asia").
     * 4. Level 3 (Skor 75-90): Substring / Containment Match (nama satu terkandung dalam nama lainnya).
     * 5. Level 4 (Skor proporsional, max 75): Overlap Token Kata (irisan kata yang identik).
     * 6. Level 5 (Skor fuzzy): Levenshtein / similar_text (kemiripan urutan karakter jika ada typo/salah ketik).
     * 7. Bonus Lokasi: +20 poin jika Kota/Kabupaten sama, +10 poin jika Provinsi sama.
     * 8. Ambang Batas: Hanya skor >= 45 (atau >= 30 jika satu kota) yang ditampilkan sebagai rekomendasi.
     *
     * @param  Perusahaan  $pendingCompany  Perusahaan baru yang diajukan oleh alumni (status Menunggu Verifikasi)
     * @param  int  $limit  Jumlah maksimal perusahaan rekomendasi yang ditampilkan (default: 6)
     * @return array<int, array<string, mixed>>
     */
    public function getSimilarRecommendations(Perusahaan $pendingCompany, int $limit = 6): array
    {
        // [BARIS 1] Mengambil string nama mentah perusahaan dari model $pendingCompany dan memotong spasi depan/belakang dengan trim().
        // Variabel $rawPendingName (string): Nama mentah yang diketik alumni (contoh: "  PT. Bank Central Asia, Tbk  " -> "PT. Bank Central Asia, Tbk").
        $rawPendingName = trim((string) $pendingCompany->nama_perusahaan);

        // [BARIS 2] Jika nama kosong, langsung hentikan proses dan kembalikan array kosong (tidak ada rekomendasi).
        if ($rawPendingName === '') {
            return [];
        }

        // [BARIS 3] Memanggil method cleanCompanyName() untuk membuang tanda baca dan kata PT/CV/Tbk.
        // Variabel $cleanPending (string): Kalimat bersih huruf kecil tanpa stopwords (output: "bank central asia").
        $cleanPending = $this->cleanCompanyName($rawPendingName);

        // [BARIS 4] Memanggil method getTokens() yang memotong kalimat bersih menjadi array kata-kata (token).
        // Variabel $pendingTokens (array): Kumpulan kata penyusun nama (output: ["bank", "central", "asia"]).
        $pendingTokens = $this->getTokens($rawPendingName);

        // [BARIS 5] Memanggil method getAcronyms() dan makeAcronym() untuk ekstraksi singkatan/akronim.
        // Variabel $pendingAcronyms (array): Kumpulan akronim potensial (output: ["bca"]).
        $pendingAcronyms = $this->getAcronyms($rawPendingName);
        $pendingAcronym = $this->makeAcronym($rawPendingName);

        // [BARIS 6] Menjalankan query Eloquent untuk mengambil seluruh data master perusahaan yang sudah 'Terverifikasi'
        // Variabel $verifiedCompanies (Collection): Kumpulan objek model Perusahaan terverifikasi dari tabel database.
        $verifiedCompanies = Perusahaan::query()
            ->with([
                'propinsi:id,nama_provinsi',
                'kabupaten:id,nama_kabupaten',
                'createdProdi:id,nama_prodi',
                'creator:id,name,role,username',
            ])
            ->where('status_verifikasi', 'Terverifikasi')
            ->where('id', '!=', $pendingCompany->id)
            ->get();

        // [BARIS 7] Inisialisasi wadah penampung array hasil perhitungan skor kemiripan
        // Variabel $scored (array): Tempat menyimpan daftar calon perusahaan rekomendasi yang lolos seleksi.
        $scored = [];

        // [BARIS 8] Perulangan (looping) untuk membandingkan perusahaan pengajuan alumni dengan SETIAP master perusahaan terverifikasi
        foreach ($verifiedCompanies as $verified) {
            // [BARIS 8.1] Mengambil nama mentah master perusahaan dan memotong spasi pinggir
            $rawVerifiedName = trim((string) $verified->nama_perusahaan);

            // [BARIS 8.2] Membersihkan nama master dari stop words (PT/CV) -> output string: misal "bank central asia"
            $cleanVerified = $this->cleanCompanyName($rawVerifiedName);

            // [BARIS 8.3] Memotong nama master menjadi array kata (token) -> output array: misal ["bank", "central", "asia"]
            $verifiedTokens = $this->getTokens($rawVerifiedName);

            // [BARIS 8.4] Membuat akronim dari nama master -> output array: misal ["bca"]
            $verifiedAcronyms = $this->getAcronyms($rawVerifiedName);
            $verifiedAcronym = $this->makeAcronym($rawVerifiedName);

            // [BARIS 8.5] Variabel penampung nilai skor nama (default 0)
            $nameScore = 0;

            // [BARIS 8.6] Variabel penampung teks alasan mengapa cocok (untuk ditampilkan di badge UI)
            $reasons = [];

            // =========================================================================
            // TAHAP 1: PENILAIAN NAMA (MULTI-LEVEL NAME SIMILARITY MATCHING)
            // =========================================================================
            // Penilaian dilakukan bertingkat (hierarki prioritas) dari tingkat kesamaan
            // paling pasti (identik) hingga deteksi kemiripan kata dan salah ketik (typo).

            // -------------------------------------------------------------------------
            // LEVEL 1: EXACT MATCH NAMA BERSIH (SKOR: 95 POIN)
            // -------------------------------------------------------------------------
            // Logika: Jika setelah dibersihkan dari "PT", "CV", "Tbk", dll., kedua nama sama 100%.
            // Contoh nyata:
            //   - Input Alumni   : "PT. Tokopedia"         -> bersih: "tokopedia"
            //   - Master Database: "Tokopedia Indonesia"   (jika Indonesia masuk stopwords) -> bersih: "tokopedia"
            //   - Hasil          : Sama persis! Diberi skor 95 (sangat direkomendasikan).
            if ($cleanPending !== '' && $cleanPending === $cleanVerified) {
                $nameScore = 95;
                $reasons[] = 'Nama Identik (Tanpa PT/CV)';
            }

            // -------------------------------------------------------------------------
            // LEVEL 2: PENCOCOKAN AKRONIM / SINGKATAN (SKOR: 88 POIN)
            // -------------------------------------------------------------------------
            // Logika: Mencocokkan singkatan inisial huruf depan dengan nama panjangnya.
            // Contoh nyata:
            //   - Kasus A: Alumni mengetik "PT BCA INDONESIA", bersih jadi "bca".
            //              Master "PT Bank Central Asia Tbk (Kantor Pusat)", akronim jadi "bca". Cocok!
            //   - Kasus B: Alumni mengetik "Universitas Kristen Duta Wacana", master tersimpan "(UKDW)".
            //   - Kasus C: Kata singkatan ada di dalam daftar token master (misal "Shopee" di "PT Shopee Express").
            elseif (
                (! empty($verifiedAcronyms) && in_array(strtolower($cleanPending), $verifiedAcronyms, true)) ||
                (! empty($pendingAcronyms) && in_array(strtolower($cleanVerified), $pendingAcronyms, true)) ||
                (! empty($verifiedAcronyms) && ! empty($pendingAcronyms) && count(array_intersect($pendingAcronyms, $verifiedAcronyms)) > 0) ||
                (! empty($verifiedAcronym) && strtolower($cleanPending) === strtolower($verifiedAcronym)) ||
                (! empty($pendingAcronym) && strtolower($cleanVerified) === strtolower($pendingAcronym)) ||
                (in_array(strtolower($cleanPending), $verifiedTokens, true)) ||
                (in_array(strtolower($cleanVerified), $pendingTokens, true))
            ) {
                $nameScore = 88;
                $reasons[] = 'Akronim / Singkatan Cocok';
            }

            // -------------------------------------------------------------------------
            // LEVEL 3: SUBSTRING / FRASA TERKANDUNG (SKOR: 75 s/d 90 POIN)
            // -------------------------------------------------------------------------
            // Logika: Salah satu nama menjadi bagian dari nama lainnya.
            // Contoh nyata:
            //   - Input Alumni   : "Gojek"
            //   - Master Database: "Gojek Super App"
            //   -> Kata "Gojek" terkandung utuh di dalam "Gojek Super App".
            //   Perhitungan skor:
            //     - Skor dasar: 75 poin
            //     - Tambahan skor: Berdasarkan rasio panjang teks (minLen / maxLen * 15).
            //       Semakin mirip panjang kedua teksnya, semakin tinggi skornya mendekati 90 poin.
            elseif (
                ($cleanPending !== '' && str_contains($cleanVerified, $cleanPending)) ||
                ($cleanVerified !== '' && str_contains($cleanPending, $cleanVerified))
            ) {
                $minLen = min(strlen($cleanPending), strlen($cleanVerified));
                $maxLen = max(strlen($cleanPending), strlen($cleanVerified));
                $ratio = $maxLen > 0 ? ($minLen / $maxLen) : 0;
                $nameScore = 75 + (int) ($ratio * 15);
                $reasons[] = 'Nama Mengandung Frasa Yang Sama';
            }

            // -------------------------------------------------------------------------
            // LEVEL 3B: PENCOCOKAN AKRONIM PARSIAL / COMPOUND ACRONYM MATCH (SKOR: 70 - 80 POIN)
            // -------------------------------------------------------------------------
            // Logika: Salah satu kata (token) merupakan akronim dari nama entitas lainnya.
            // Contoh nyata:
            //   - Kasus A: "BCA Digital" memiliki token ["bca", "digital"].
            //              Kata "bca" adalah akronim dari "Bank Central Asia" -> Terdeteksi kemiripan entitas terkait!
            //   - Kasus B: "BCA Finance", "BCA Syariah", "Mandiri Sekuritas".
            elseif (
                (! empty($verifiedAcronyms) && count($matchedAcrTokens = array_intersect($pendingTokens, $verifiedAcronyms)) > 0) ||
                (! empty($pendingAcronyms) && count($matchedAcrTokens = array_intersect($verifiedTokens, $pendingAcronyms)) > 0)
            ) {
                $matchedWord = strtoupper(implode(', ', $matchedAcrTokens));
                $totalTokens = max(count($pendingTokens), count($verifiedTokens));
                $ratio = $totalTokens > 0 ? (count($matchedAcrTokens) / $totalTokens) : 0.5;
                $nameScore = 70 + (int) ($ratio * 15);
                $reasons[] = "Mengandung Akronim Entitas Terkait ({$matchedWord})";
            }

            // -------------------------------------------------------------------------
            // LEVEL 4 & 5: IRISAN KATA (TOKEN INTERSECT) & DETEKSI TYPO (FUZZY SIMILARITY)
            // -------------------------------------------------------------------------
            // Jika nama tidak identik, bukan singkatan, dan tidak saling terkandung utuh,
            // maka kita analisis kata per kata (Level 4) dan susunan hurufnya jika typo (Level 5).
            else {
                // [LANGKAH 1] array_intersect(): Fungsi bawaan PHP untuk mencari IRISAN kata yang ada di kedua array.
                // Variabel $intersect: Menyimpan array kata-kata yang sama persis antara pengajuan ($pendingTokens) dan master ($verifiedTokens).
                // Contoh: pending=['bank','mandiri','syariah'], verified=['bank','mandiri','taspen'] -> $intersect=['bank','mandiri'].
                $intersect = array_intersect($pendingTokens, $verifiedTokens);

                // [LANGKAH 2] count(): Menghitung jumlah elemen/kata di dalam array $intersect.
                // Variabel $overlapCount: Angka integer berapa kata yang cocok (tumpang tindih / overlap). Contoh: 2.
                $overlapCount = count($intersect);

                // [LANGKAH 3] Mengecek apakah ada minimal 1 kata yang sama.
                if ($overlapCount > 0) {
                    // [LANGKAH 4] max(): Mengambil jumlah kata terbanyak di antara nama pengajuan atau master.
                    $totalTokens = max(count($pendingTokens), count($verifiedTokens));
                    $overlapRatio = $totalTokens > 0 ? ($overlapCount / $totalTokens) : 0;

                    // Cek apakah kecocokan tunggal (hanya 1 kata) merupakan kata jenis industri/sektor umum (misal "bank")
                    $isSingleGenericMatch = ($overlapCount === 1 && in_array(reset($intersect), $this->genericIndustryWords, true));

                    // [LANGKAH 5] Syarat ambang batas rasio kata: Minimal 33% (sepertiga bagian nama) harus identik,
                    // dan bukan hanya kata jenis sektor umum tunggal.
                    if ($overlapRatio >= 0.33 && ! $isSingleGenericMatch) {
                        $tokenScore = 50 + (int) ($overlapRatio * 25);

                        // [LANGKAH 6] Jika nilai irisan kata ini lebih besar dari nilai nama saat ini, perbarui nilainya.
                        if ($tokenScore > $nameScore) {
                            $nameScore = $tokenScore;
                            $reasons[] = "Memiliki {$overlapCount} Kata Yang Sama (".implode(', ', $intersect).')';
                        }
                    }
                }

                // [LANGKAH 7] similar_text(): Fungsi bawaan PHP untuk menghitung kemiripan karakter huruf (Ratcliff/Obershelp).
                // Parameter ke-3 ($percent) adalah pass-by-reference, otomatis diisi nilai persentase (float 0 - 100) oleh PHP.
                // Variabel $percent: Menampung angka persentase kemiripan huruf jika ada salah ketik/typo (misal "centrl" vs "central" -> 96.9).
                similar_text($cleanPending, $cleanVerified, $percent);

                // Cek apakah ada kata klasifikasi sektor industri generik yang mendistorsi kemiripan huruf (misal sama-sama kata "bank")
                $pendingCore = trim(implode(' ', array_diff($pendingTokens, $this->genericIndustryWords)));
                $verifiedCore = trim(implode(' ', array_diff($verifiedTokens, $this->genericIndustryWords)));
                $isDistortedByGeneric = false;
                if ($pendingCore !== '' && $verifiedCore !== '') {
                    similar_text($pendingCore, $verifiedCore, $percentCore);
                    // Jika kata inti di luar sektor generik berbeda (< 60%) dan satu-satunya kata yang sama adalah kata sektor generik, jangan anggap typo
                    if ($percentCore < 60 && count($intersect) === 1 && in_array(reset($intersect), $this->genericIndustryWords, true)) {
                        $isDistortedByGeneric = true;
                    }
                }

                // [LANGKAH 8] Cek apakah persentase >= 60% dan lebih tinggi dari skor irisan kata sebelumnya.
                if ($percent >= 60 && ! $isDistortedByGeneric && (int) $percent > $nameScore) {
                    // Konversi nilai persen ke integer dan simpan sebagai skor nama utama
                    $nameScore = (int) $percent;

                    // Tambahkan alasan ke array $reasons untuk UI admin
                    $reasons[] = 'Struktur Huruf Mirip ('.round($percent).'%)';
                }

            }

            // =========================================================================
            // TAHAP 2: PENILAIAN LOKASI GEOGRAFIS (GEOGRAPHIC LOCATION BONUS)
            // =========================================================================
            // Menambahkan bobot nilai jika pengajuan berada di area yang sama
            $isSameProvince = false;
            $isSameCity = false;

            if ($pendingCompany->propinsi_id && $pendingCompany->propinsi_id === $verified->propinsi_id) {
                $isSameProvince = true;
                $reasons[] = 'Provinsi Sama ('.$verified->propinsi?->nama_provinsi.')';
            }

            if ($pendingCompany->kabupaten_id && $pendingCompany->kabupaten_id === $verified->kabupaten_id) {
                $isSameCity = true;
                $reasons[] = 'Kota/Kabupaten Sama ('.$verified->kabupaten?->nama_kabupaten.')';
            }

            // Bonus bobot jika lokasi sama (hanya jika nama sudah memiliki kesamaan minimum >= 35)
            $totalScore = $nameScore;
            if ($isSameCity && $nameScore >= 35) {
                $totalScore += 20; // Bonus +20 poin jika Kota/Kabupaten identik
            } elseif ($isSameProvince && $nameScore >= 35) {
                $totalScore += 10; // Bonus +10 poin jika Provinsi identik
            }

            // Cap nilai maksimal di 100
            $totalScore = min(100, $totalScore);

            // =========================================================================
            // TAHAP 3: FILTER AMBANG BATAS (THRESHOLD FILTERING)
            // =========================================================================
            // Syarat masuk rekomendasi: Skor Total >= 45 ATAU (Satu Kota dan Skor Nama >= 30)
            if ($totalScore >= 45 || ($isSameCity && $nameScore >= 30)) {
                $scored[] = [
                    'id' => $verified->id,
                    'nama_perusahaan' => $verified->nama_perusahaan,
                    'alamat' => $verified->alamat,
                    'propinsi_id' => $verified->propinsi_id,
                    'kabupaten_id' => $verified->kabupaten_id,
                    'nama_provinsi' => $verified->propinsi?->nama_provinsi ?? '-',
                    'nama_kabupaten' => $verified->kabupaten?->nama_kabupaten ?? '-',
                    'homepage' => $verified->homepage,
                    'no_telp_fax' => $verified->no_telp_fax,
                    'kode_pos' => $verified->kode_pos,
                    'sektor' => $verified->sektor ?? '-',
                    'skala' => $verified->skala ?? '-',
                    'bentuk_perusahaan' => $verified->bentuk_perusahaan,
                    'jumlah_pegawai' => $verified->jumlah_pegawai,
                    'jenis_perusahaan' => $verified->jenis_perusahaan ?? '-',
                    'jenis_perusahaan_lainnya' => $verified->jenis_perusahaan_lainnya,
                    'jenis_lokasi' => $verified->jenis_lokasi,
                    'negara' => $verified->negara,
                    'status_verifikasi' => $verified->status_verifikasi,
                    'created_by_user_id' => $verified->created_by_user_id,
                    'created_by_prodi_id' => $verified->created_by_prodi_id,
                    'creator_name' => $verified->creator?->name,
                    'creator_role' => $verified->creator?->role,
                    'created_prodi_name' => $verified->createdProdi?->nama_prodi,
                    'created_at' => $verified->created_at,
                    'updated_at' => $verified->updated_at,
                    'similarity_score' => $totalScore,
                    'is_same_city' => $isSameCity,
                    'is_same_province' => $isSameProvince,
                    'match_reasons' => $reasons,
                ];
            }
        }

        // =========================================================================
        // TAHAP 4: PENGURUTAN (RANKING) & LIMIT HASIL REKOMENDASI
        // =========================================================================
        // Urutkan berdasarkan similarity_score tertinggi secara descending
        usort($scored, fn ($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Mencari master data perusahaan terverifikasi dengan kata kunci fleksibel.
     *
     * @return Collection<int, Perusahaan>
     */
    public function searchVerified(string $keyword, int $limit = 10): Collection
    {
        $term = trim($keyword);
        if ($term === '') {
            return new Collection;
        }

        return Perusahaan::query()
            ->with(['propinsi:id,nama_provinsi', 'kabupaten:id,nama_kabupaten'])
            ->where('status_verifikasi', 'Terverifikasi')
            ->where(function ($q) use ($term) {
                $q->where('nama_perusahaan', 'like', "%{$term}%")
                    ->orWhere('alamat', 'like', "%{$term}%")
                    ->orWhereHas('kabupaten', fn ($k) => $k->where('nama_kabupaten', 'like', "%{$term}%"))
                    ->orWhereHas('propinsi', fn ($p) => $p->where('nama_provinsi', 'like', "%{$term}%"));
            })
            ->take($limit)
            ->get();
    }

    /**
     * Mengganti perusahaan yang diajukan dengan master perusahaan terverifikasi (Auto Replace).
     * Seluruh alumni yang bekerja di perusahaan pending ini dialihkan ke perusahaan terverifikasi,
     * lalu record pending yang redundan dibersihkan.
     *
     * @return array<string, mixed>
     */
    public function replaceCompany(Perusahaan $pendingCompany, Perusahaan $verifiedCompany): array
    {
        return DB::transaction(function () use ($pendingCompany, $verifiedCompany) {
            $biodatas = Biodata::where('perusahaan_id', $pendingCompany->id)->get();
            $affectedCount = $biodatas->count();
            $affectedBiodataIds = $biodatas->pluck('id')->toArray();
            $pendingName = trim($pendingCompany->nama_perusahaan);
            $verifiedName = trim($verifiedCompany->nama_perusahaan);

            // 1. Alihkan relasi biodata profil alumni ke perusahaan terverifikasi
            Biodata::where('perusahaan_id', $pendingCompany->id)->update([
                'perusahaan_id' => $verifiedCompany->id,
            ]);

            // 2. Alihkan relasi transaksi Evaluasi Atasan ke master terverifikasi
            if (Schema::hasTable('evaluasi_atasan')) {
                DB::table('evaluasi_atasan')
                    ->where('perusahaan_id', $pendingCompany->id)
                    ->update(['perusahaan_id' => $verifiedCompany->id]);
            }

            // 3. Alihkan jawaban kuesioner Tracer Study (tabel tracer) yang terhubung dengan perusahaan usulan
            if (Schema::hasTable('tracer') && ! empty($affectedBiodataIds)) {
                // Alihkan jawaban eksplisit bernilai nama usulan alumni
                DB::table('tracer')
                    ->whereIn('biodata_id', $affectedBiodataIds)
                    ->where('answer', $pendingName)
                    ->update(['answer' => $verifiedName]);

                // Alihkan butir kuesioner penanda instansi/perusahaan tempat kerja (F5B / F2E)
                DB::table('tracer')
                    ->whereIn('biodata_id', $affectedBiodataIds)
                    ->where(function ($q) {
                        $q->where('kode_pertanyaan', 'LIKE', 'F5B%')
                            ->orWhere('kode_pertanyaan', 'LIKE', 'F2E%');
                    })
                    ->update(['answer' => $verifiedName]);
            }

            // 4. Hapus record usulan perusahaan yang sudah berhasil digantikan dan dikonsolidasikan
            $pendingCompany->delete();

            // 5. Catat audit trail ke log_activities
            LogActivity::record(
                'GANTI_PERUSAHAAN_MASTER',
                "Mengganti perusahaan pengajuan '{$pendingName}' ke master terverifikasi '{$verifiedName}' untuk {$affectedCount} alumni.",
                $verifiedCompany,
                ['pending_id' => $pendingCompany->id, 'pending_name' => $pendingName],
                ['verified_id' => $verifiedCompany->id, 'verified_name' => $verifiedName, 'alumni_dialihkan' => $affectedCount]
            );

            return [
                'status' => 'success',
                'affected_count' => $affectedCount,
                'replaced_name' => $verifiedCompany->nama_perusahaan,
            ];
        });
    }

    /**
     * Memverifikasi perusahaan yang diajukan langsung tanpa penggantian.
     */
    public function verifyDirectly(Perusahaan $company): bool
    {
        $res = $company->update([
            'status_verifikasi' => 'Terverifikasi',
        ]);

        // Catat ke Audit Trail log_activities
        LogActivity::record(
            'ACC_VERIFIKASI_PERUSAHAAN',
            "Menyetujui (ACC) perusahaan '{$company->nama_perusahaan}' menjadi berstatus Terverifikasi.",
            $company
        );

        return $res;
    }

    /**
     * Mengupdate atribut perusahaan oleh admin (memperbaiki typo dsb.) dan memverifikasinya.
     *
     * @param  array<string, mixed>  $validatedData
     */
    public function updateAndVerify(Perusahaan $company, array $validatedData, bool $verifyNow = true): Perusahaan
    {
        $oldValues = $company->only(array_keys($validatedData));

        if ($verifyNow) {
            $validatedData['status_verifikasi'] = 'Terverifikasi';
        }

        $company->update($validatedData);

        // Catat ke Audit Trail log_activities
        LogActivity::record(
            'UPDATE_VERIFIKASI_PERUSAHAAN',
            "Memperbarui data dan memverifikasi perusahaan '{$company->nama_perusahaan}'.",
            $company,
            $oldValues,
            $company->only(array_keys($validatedData))
        );

        return $company->fresh();
    }

    /**
     * Menolak pengajuan perusahaan.
     */
    public function rejectCompany(Perusahaan $company, ?string $alasan = null): bool
    {
        $res = $company->update([
            'status_verifikasi' => 'Ditolak',
        ]);

        // Catat ke Audit Trail log_activities
        LogActivity::record(
            'REJECT_PERUSAHAAN',
            "Menolak pengajuan perusahaan '{$company->nama_perusahaan}'.".($alasan ? " Alasan: {$alasan}" : ''),
            $company
        );

        return $res;
    }

    /**
     * Membersihkan nama perusahaan dari tanda kurung keterangan cabang/lokasi,
     * tanda baca, dan kata umum / bentuk badan usaha (Stop Words).
     *
     * Alur Pembersihan (Cleaning Pipeline):
     * 1. Menghilangkan teks keterangan cabang/organisasi di dalam kurung (...) seperti '(Kantor Pusat)', '(Surabaya Office)', '(Persero)'
     *    agar menyisakan entitas pokok perusahaan. Jika nama menjadi kosong, fallback ke nama utuh.
     * 2. Menghapus tanda baca, simbol, dan karakter non-alfanumerik.
     * 3. Memecah string menjadi token kata dan menyaring seluruh kata yang terdaftar di Stop Words.
     * 4. Menggabungkan kembali kata-kata esensial menjadi string nama bersih huruf kecil (lowercase).
     *
     * Contoh: "PT Bank Central Asia Tbk (Kantor Pusat)" -> "bank central asia"
     * Contoh: "PT. BCA INDONESIA" -> "bca"
     *
     * @param  string  $name  Nama mentah perusahaan
     * @return string Nama yang dinormalisasi huruf kecil, tanpa tanda baca, dan tanpa stop words
     */
    protected function cleanCompanyName(string $name): string
    {
        // Alur Tahap 1: Pisahkan/abaikan keterangan di dalam tanda kurung jika ada (misal '(Kantor Pusat)')
        $nameWithoutParentheses = preg_replace('/\([^)]*\)/', ' ', $name);
        $cleanMain = $this->cleanString((string) $nameWithoutParentheses);

        // Jika setelah membuang kurung hasilnya tidak kosong, gunakan nama utama yang bersih
        if ($cleanMain !== '') {
            return $cleanMain;
        }

        // Fallback jika seluruh nama aslinya berada di dalam tanda kurung (misal: "(PT BCA)")
        return $this->cleanString($name);
    }

    /**
     * Fungsi pembantu pembersihan string dari tanda baca dan stop words.
     */
    protected function cleanString(string $str): string
    {
        $lower = strtolower(trim($str));
        $clean = preg_replace('/[.,\-_()\[\]\/\\\&+]+/', ' ', $lower);
        $words = preg_split('/\s+/', (string) $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $filtered = array_filter($words, fn ($w) => ! in_array($w, $this->stopWords, true));

        return trim(implode(' ', $filtered));
    }

    /**
     * Menghasilkan array token kata bersih setelah proses pembersihan stop words.
     * Contoh: "PT Bank Central Asia Tbk (Kantor Pusat)" -> ["bank", "central", "asia"]
     *
     * @param  string  $name  Nama mentah perusahaan
     * @return array<int, string> Kumpulan kata-kata penyusun nama
     */
    protected function getTokens(string $name): array
    {
        // [LANGKAH 1] Ambil string bersih tanpa PT/CV dan tanpa keterangan kantor/cabang
        $clean = $this->cleanCompanyName($name);

        // [LANGKAH 2] Jika string kosong, langsung kembalikan array kosong []
        if ($clean === '') {
            return [];
        }

        // [LANGKAH 3] Potong string berdasarkan spasi menjadi elemen array kata-kata inti
        return preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Menghasilkan akronim/singkatan otomatis utama dari huruf depan setiap kata penting.
     * Contoh: "PT Bank Central Asia Tbk (Kantor Pusat)" -> "bca"
     *
     * @param  string  $name  Nama perusahaan
     * @return string Akronim huruf kecil (contoh: "bca"), atau string kosong jika hanya 1 kata
     */
    protected function makeAcronym(string $name): string
    {
        $acronyms = $this->getAcronyms($name);

        return $acronyms[0] ?? '';
    }

    /**
     * Mengekstrak seluruh variasi akronim potensial dari nama perusahaan.
     * Mencakup:
     * 1. Akronim dari huruf depan token nama utama (contoh: "Bank Central Asia" -> "bca").
     * 2. Alias / singkatan eksplisit yang ditulis di dalam tanda kurung (contoh: "Universitas Kristen Duta Wacana (UKDW)" -> "ukdw").
     *
     * @param  string  $name  Nama perusahaan
     * @return array<int, string> Daftar akronim/singkatan dalam huruf kecil
     */
    protected function getAcronyms(string $name): array
    {
        $acronyms = [];

        // 1. Akronim dari huruf depan setiap token kata nama utama
        $tokens = $this->getTokens($name);
        if (count($tokens) > 1) {
            $acr = '';
            foreach ($tokens as $token) {
                $acr .= $token[0] ?? '';
            }
            if ($acr !== '') {
                $acronyms[] = strtolower($acr);
            }
        }

        // 1b. Akronim inklusif: menghitung akronim alami dari seluruh kata penting termasuk kata entitas negara/geografis (misal 'indonesia')
        // Menangani kasus natural seperti "Bank Nasional Indonesia" -> "bni", "Kereta Api Indonesia" -> "kai", "Bank Rakyat Indonesia" -> "bri"
        $nameWithoutParentheses = preg_replace('/\([^)]*\)/', ' ', $name);
        $rawLower = strtolower(trim((string) $nameWithoutParentheses));
        $rawClean = preg_replace('/[.,\-_()\[\]\/\\\&+]+/', ' ', $rawLower);
        $rawWords = preg_split('/\s+/', (string) $rawClean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $legalStopWords = [
            'pt', 'p.t', 'cv', 'c.v', 'tbk', 'persero', 'corp', 'corporation', 'inc', 'ltd',
            'llc', 'gmbh', 'co', 'company', 'group', 'holding', 'perum', 'yayasan',
            'ud', 'u.d', 'fa', 'firma', 'koperasi', 'pte',
        ];
        $meaningfulWords = array_values(array_filter($rawWords, fn ($w) => ! in_array($w, $legalStopWords, true)));
        if (count($meaningfulWords) > 1) {
            $inclusiveAcr = '';
            foreach ($meaningfulWords as $w) {
                $inclusiveAcr .= $w[0] ?? '';
            }
            if ($inclusiveAcr !== '') {
                $acronyms[] = strtolower($inclusiveAcr);
            }
        }

        // 2. Ekstrak alias eksplisit dari dalam tanda kurung (...) jika ada
        if (preg_match_all('/\(([^)]+)\)/', $name, $matches)) {
            foreach ($matches[1] as $content) {
                $aliasClean = $this->cleanString($content);
                if ($aliasClean !== '') {
                    $aliasTokens = preg_split('/\s+/', $aliasClean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    if (count($aliasTokens) === 1 && strlen($aliasTokens[0]) <= 8) {
                        $acronyms[] = strtolower($aliasTokens[0]);
                    } elseif (count($aliasTokens) > 1) {
                        $aliasAcr = '';
                        foreach ($aliasTokens as $t) {
                            $aliasAcr .= $t[0] ?? '';
                        }
                        if ($aliasAcr !== '') {
                            $acronyms[] = strtolower($aliasAcr);
                        }
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($acronyms)));
    }
}
