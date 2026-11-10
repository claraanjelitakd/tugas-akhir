<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung indikator IKU S1 dari read-model database.
 *
 * View hanya menormalisasi sumber data lulusan. Formula AEE dan bobot lulusan tetap
 * berada di service agar aturan bisnis dapat diuji tanpa menaruh logika pada
 * Vue atau query agregasi yang sulit diaudit.
 */
class IkuS1AnalyticsService
{
    private const AEE_IDEAL_S1 = 25.0;

    private const VALID_CAREER_CATEGORIES = [
        'Karyawan',
        'Founder / Co-Founder',
        'Freelancer',
        'Melanjutkan Pendidikan',
    ];

    /**
     * Kategori yang boleh ditampilkan pada grafik, termasuk usaha yang belum
     * mempunyai role terstruktur sehingga tidak boleh diberi bobot otomatis.
     */
    private const DISPLAY_CAREER_CATEGORIES = [
        'Karyawan',
        'Founder / Co-Founder',
        'Freelancer',
        'Wirausaha Lainnya',
        'Melanjutkan Pendidikan',
    ];

    /**
     * Menentukan apakah kunci grafik ditangani service IKU S1.
     *
     * @param  string  $chartKey  Kunci grafik dari route analitik.
     * @return bool True jika indikator termasuk paket IKU S1.
     */
    public function supports(string $chartKey): bool
    {
        return in_array($chartKey, ['iku_s1_aee', 'iku_s1_ump_weighted', 'iku_s1_outcomes'], true);
    }

    /**
     * Membuat payload Angka Efisiensi Edukasi perguruan tinggi untuk S1.
     *
     * Hanya mahasiswa dengan status akademik Lulus yang masuk populasi. Status
     * aktif, AR, AK, Drop Out, Pindah, dan Cuti tidak ikut dalam denominator.
     *
     * @return array<string, mixed> Payload chart, tabel, ringkasan, dan metodologi.
     */
    public function buildAeeChart(?string $selectedYear = null, ?int $prodiId = null): array
    {
        $query = DB::table('v_iku_s1_aee')->where('status_mahasiswa', 'Lulus');
        $this->applyFilters($query, $selectedYear, $prodiId);
        $records = $query->orderBy('nama_prodi')->orderBy('nim')->get();
        $series = $records->groupBy('prodi_id')->map(function (Collection $rows): array {
            $first = $rows->first();
            $population = $rows->count();
            $excluded = $rows->where('is_excluded', 1)->count();
            $eligibleRows = $rows->where('is_excluded', 0);
            $eligible = $eligibleRows->count();
            $onTime = $eligibleRows->where('is_lulus_tepat_waktu', 1)->count();
            $late = $eligibleRows
                ->where('status_mahasiswa', 'Lulus')
                ->where('is_lulus_tepat_waktu', 0)
                ->count();
            $unidentified = $eligible - $onTime - $late;
            $realization = $this->percentage($onTime, $eligible);
            $achievement = $eligible > 0
                ? round(($realization / self::AEE_IDEAL_S1) * 100, 2)
                : 0.0;

            return [
                'prodi_id' => (int) $first->prodi_id,
                'label' => $first->nama_prodi,
                'nama_prodi' => $first->nama_prodi,
                'jenjang' => $first->jenjang,
                'population' => $population,
                'excluded' => $excluded,
                'eligible' => $eligible,
                'on_time' => $onTime,
                'late' => $late,
                'unidentified' => $unidentified,
                'aee_realization' => $realization,
                'aee_ideal' => self::AEE_IDEAL_S1,
                'achievement' => $achievement,
                'count_label' => $onTime.'/'.$eligible,
            ];
        })->sortByDesc('achievement')->values();

        $eligibleRows = $records->where('is_excluded', 0);
        $eligible = $eligibleRows->count();
        $onTime = $eligibleRows->where('is_lulus_tepat_waktu', 1)->count();
        $late = $eligibleRows
            ->where('status_mahasiswa', 'Lulus')
            ->where('is_lulus_tepat_waktu', 0)
            ->count();
        $unidentified = $eligible - $onTime - $late;
        $aeeRealization = $this->percentage($onTime, $eligible);
        $programAchievements = $series->where('eligible', '>', 0)->pluck('achievement');

        return [
            'key' => 'iku_s1_aee',
            'type' => 'iku_s1_aee',
            'title' => 'Angka Efisiensi Edukasi S1',
            'subtitle' => 'Mahasiswa S1 yang lulus tepat waktu dibandingkan populasi masuk per program studi.',
            'summary' => [
                'population' => $records->count(),
                'excluded_count' => $records->where('is_excluded', 1)->count(),
                'eligible_count' => $eligible,
                'on_time_count' => $onTime,
                'late_count' => $late,
                'unidentified_count' => $unidentified,
                'aee_realization' => $aeeRealization,
                'aee_ideal' => self::AEE_IDEAL_S1,
                'aee_pt' => $programAchievements->isNotEmpty() ? round($programAchievements->avg(), 2) : 0.0,
                'status_target' => (float) config('iku.s1.aee_status_target', 60.0),
            ],
            'series' => $series->all(),
            'records' => $records->map(fn (object $row): array => [
                'academic_id' => (int) $row->academic_id,
                'nim' => $row->nim,
                'nama' => $row->nama,
                'prodi_id' => (int) $row->prodi_id,
                'nama_prodi' => $row->nama_prodi,
                'angkatan_masuk' => $row->angkatan_masuk,
                'tahun_akademik_masuk' => $row->tahun_akademik_masuk,
                'status_mahasiswa' => $row->status_mahasiswa,
                'is_excluded' => (bool) $row->is_excluded,
                'is_lulus_tepat_waktu' => (bool) $row->is_lulus_tepat_waktu,
            ])->values()->all(),
            'methodology' => [
                'formula' => 'AEE = lulusan tepat waktu / seluruh lulusan S1 dengan status akademik Lulus × 100%. AEE ideal S1 = 25%. AEE PT = rata-rata capaian AEE setiap program pendidikan.',
                'denominator' => 'Lulusan S1 dengan status akademik Lulus; status aktif, AR, AK, Drop Out, Pindah, dan Cuti tidak masuk populasi.',
                'source' => 'v_iku_s1_aee dan data_akademik.prodi_id',
            ],
        ];
    }

    /**
     * Membuat payload persentase lulusan S1 yang bekerja, berwirausaha, atau studi lanjut.
     *
     * @param  int|null  $umpYear  Tahun UMP yang dipakai sebagai ambang gaji.
     * @return array<string, mixed> Payload chart bobot karier dan rincian responden.
     */
    public function buildOutcomeChart(?int $umpYear = null, ?string $selectedYear = null, ?int $prodiId = null): array
    {
        $classified = $this->classifiedOutcomes($umpYear, $selectedYear, $prodiId);
        $valid = $classified
            ->whereIn('career_category', self::VALID_CAREER_CATEGORIES)
            ->filter(fn (array $row): bool => $row['weight'] > 0);
        $weightedScore = round($valid->sum('weight'), 4);
        $score = $this->percentage($weightedScore, $valid->count());
        $series = collect(self::DISPLAY_CAREER_CATEGORIES)
            ->map(function (string $label) use ($classified, $valid): ?array {
                $categoryRows = $classified->where('career_category', $label);
                if ($categoryRows->isEmpty()) {
                    return null;
                }

                $validRows = $categoryRows->filter(fn (array $row): bool => $row['weight'] > 0);

                return [
                    'label' => $label,
                    'count' => $categoryRows->count(),
                    'valid_count' => $validRows->count(),
                    'weighted_score' => round($validRows->sum('weight'), 4),
                    'percentage' => $this->percentage($validRows->sum('weight'), $valid->count()),
                ];
            })->filter()->values()->all();

        return [
            'key' => 'iku_s1_outcomes',
            'type' => 'iku_s1_outcomes',
            'title' => 'Lulusan S1 bekerja, berwirausaha, atau studi lanjut',
            'subtitle' => 'Persentase berbobot berdasarkan masa tunggu, 1,2× UMP, posisi usaha, dan studi lanjut maksimal satu tahun.',
            'summary' => [
                'population' => $classified->count(),
                'valid_respondents' => $valid->count(),
                'weighted_score' => $weightedScore,
                'percentage' => $score,
                'ump_year' => $this->resolveUmpYear($umpYear),
            ],
            'series' => $series,
            'records' => $classified->values()->all(),
            'methodology' => [
                'formula' => 'IKU = Σ(nᵢ × kᵢ) / t × 100%. t adalah responden lulusan S1 dengan status bekerja, berwirausaha, atau melanjutkan studi.',
                'denominator' => 'Lulusan S1 yang status kariernya dapat diklasifikasikan; data tanpa status karier tidak masuk t.',
                'source' => 'v_iku_s1_lulusan, kuesioner_jawaban F8/F504/F502/F506/F18d, biodata.gaji, dan ump.',
            ],
        ];
    }

    /**
     * Membuat payload rincian perbandingan pendapatan terhadap 1,2 kali UMP.
     *
     * Grafik ini menjadi tempat audit utama untuk gaji, masa tunggu, role,
     * status sebelum/sesudah lulus, dan bobot yang kemudian direkap oleh IKU.
     *
     * @param  int|null  $umpYear  Tahun UMP yang dipakai sebagai ambang.
     * @return array<string, mixed> Payload perbandingan UMP berbobot.
     */
    public function buildUmpWeightedChart(?int $umpYear = null, ?string $selectedYear = null, ?int $prodiId = null): array
    {
        $classified = $this->classifiedOutcomes($umpYear, $selectedYear, $prodiId);
        $valid = $classified
            ->whereIn('career_category', self::VALID_CAREER_CATEGORIES)
            ->filter(fn (array $row): bool => $row['weight'] > 0);
        $comparable = $valid->filter(fn (array $row): bool => $row['salary'] !== null && $row['threshold'] !== null);
        $above = $comparable->filter(fn (array $row): bool => $row['salary_above_threshold'])->count();
        $series = $valid->groupBy('career_category')->map(function (Collection $categoryRows, string $label) use ($valid): array {
            $comparableRows = $categoryRows->filter(fn (array $row): bool => $row['salary'] !== null && $row['threshold'] !== null);
            $aboveRows = $comparableRows->filter(fn (array $row): bool => $row['salary_above_threshold']);

            return [
                'label' => $label,
                'count' => $categoryRows->count(),
                'comparable_count' => $comparableRows->count(),
                'above_count' => $aboveRows->count(),
                'below_count' => $comparableRows->count() - $aboveRows->count(),
                'weighted_score' => round($categoryRows->sum('weight'), 4),
                'percentage' => $this->percentage($categoryRows->sum('weight'), $valid->count()),
            ];
        })->values()->all();

        return [
            'key' => 'iku_s1_ump_weighted',
            'type' => 'iku_s1_ump_weighted',
            'title' => 'Audit Pendapatan, Masa Tunggu, dan Bobot IKU S1',
            'subtitle' => 'Tiga sudut audit untuk responden yang sama: status gaji terhadap 1,2× UMP, masa tunggu, dan akumulasi bobot IKU.',
            'summary' => [
                'population' => $classified->count(),
                'valid_respondents' => $valid->count(),
                'comparable_count' => $comparable->count(),
                'above_threshold_count' => $above,
                'below_threshold_count' => $comparable->count() - $above,
                'above_threshold_percentage' => $this->percentage($above, $comparable->count()),
                'weighted_score' => round($valid->sum('weight'), 4),
                'percentage' => $this->percentage($valid->sum('weight'), $valid->count()),
                'ump_year' => $this->resolveUmpYear($umpYear),
                'weight_confirmation_status' => config('iku.s1.weight_confirmation_status'),
            ],
            'series' => $series,
            'records' => $classified->values()->all(),
            'methodology' => $this->outcomeMethodology(),
        ];
    }

    /**
     * Mengambil UMP berdasarkan tahun yang dipilih atau tahun terbaru.
     *
     * @param  int|null  $umpYear  Tahun acuan opsional.
     * @return Collection<string, float> Peta kode provinsi ke besaran UMP.
     */
    private function umpRows(?int $umpYear): Collection
    {
        $year = $this->resolveUmpYear($umpYear);

        return DB::table('ump')
            ->where('tahun', $year)
            ->pluck('besaran', 'kode_provinsi')
            ->map(fn (mixed $amount): float => (float) $amount);
    }

    /**
     * Mengambil tahun UMP aktif yang benar-benar tersedia.
     */
    private function resolveUmpYear(?int $umpYear): ?int
    {
        $availableYear = DB::table('ump')->max('tahun');

        return $umpYear ?? ($availableYear === null ? null : (int) $availableYear);
    }

    /**
     * Mengklasifikasikan seluruh lulusan S1 sekali untuk dipakai oleh dua
     * grafik IKU agar formula dan rincian individu tidak bercabang berbeda.
     */
    private function classifiedOutcomes(?int $umpYear, ?string $selectedYear = null, ?int $prodiId = null): Collection
    {
        $umpRows = $this->umpRows($umpYear);
        $query = DB::table('v_iku_s1_lulusan as graduate')
            ->leftJoin('biodata', 'biodata.id', '=', 'graduate.biodata_id')
            ->leftJoin('perusahaan as workplace', 'workplace.id', '=', 'biodata.perusahaan_id')
            ->leftJoin('atasan as supervisor', 'supervisor.id', '=', 'biodata.atasan_id')
            ->leftJoin('propinsi as workplace_province', 'workplace_province.kode_provinsi', '=', 'workplace.kode_provinsi')
            ->leftJoin('kabupaten as workplace_city', 'workplace_city.kode_kabupaten', '=', 'workplace.kode_kabupaten')
            ->select([
                'graduate.*',
                'biodata.posisi_jabatan',
                'biodata.bidang_pekerjaan',
                'biodata.divisi',
                'biodata.kategori_pekerjaan as profile_career_status',
                'biodata.skills',
                'biodata.experience',
                'biodata.posisi_wiraswasta',
                'biodata.pendidikan_tingkat',
                'biodata.perguruan_tinggi',
                'biodata.pendidikan_prodi',
                'workplace.nama_perusahaan as profile_company_name',
                'workplace.alamat as workplace_address',
                'workplace.kode_pos as workplace_postal_code',
                'workplace_province.nama_provinsi as workplace_province_name',
                'workplace_city.nama_kabupaten as workplace_city_name',
                'supervisor.nama as supervisor_name',
                'supervisor.email as supervisor_email',
                'supervisor.telepon as supervisor_phone',
            ]);
        $this->applyFilters($query, $selectedYear, $prodiId, 'graduate');

        return $query->get()
            ->map(fn (object $row): array => $this->classifyOutcome($row, $umpRows));
    }

    /**
     * Menerapkan filter periode dan program studi pada read-model IKU.
     *
     * @param  Builder  $query  Query read-model.
     * @param  string|null  $selectedYear  Tahun kelulusan atau ALL.
     * @param  int|null  $prodiId  Program studi opsional.
     * @param  string|null  $tableAlias  Alias read-model untuk kolom filter.
     */
    private function applyFilters(Builder $query, ?string $selectedYear, ?int $prodiId, ?string $tableAlias = null): void
    {
        $yearColumn = $tableAlias ? $tableAlias.'.tahun_lulus' : 'tahun_lulus';
        $prodiColumn = $tableAlias ? $tableAlias.'.prodi_id' : 'prodi_id';

        if ($selectedYear !== null && $selectedYear !== 'ALL') {
            $query->where($yearColumn, (int) $selectedYear);
        }

        if ($prodiId !== null) {
            $query->where($prodiColumn, $prodiId);
        }
    }

    /**
     * Mengubah satu baris lulusan menjadi kategori dan bobot IKU.
     *
     * @param  object  $row  Baris dari view lulusan S1.
     * @param  Collection<string, float>  $umpRows  Ambang UMP per provinsi.
     * @return array<string, mixed> Fakta klasifikasi yang dapat diaudit.
     */
    private function classifyOutcome(object $row, Collection $umpRows): array
    {
        $category = $this->careerCategory($row);
        $ump = $umpRows->get($row->kode_provinsi);
        $threshold = $ump === null ? null : $ump * (float) config('iku.s1.ump_multiplier', 1.2);
        $salary = $row->gaji === null ? null : (float) $row->gaji;
        $salaryAboveThreshold = $salary !== null && $threshold !== null
            ? $salary > $threshold
            : null;
        $beforeGraduation = match (mb_strtolower(trim((string) $row->bekerja_sebelum_lulus))) {
            'ya' => true,
            'tidak' => false,
            default => null,
        };
        $months = $this->resolveWaitMonths($row, $beforeGraduation);
        $weight = $this->resolveWeight($category, $beforeGraduation, $months, $salaryAboveThreshold, $row);

        return [
            'academic_id' => (int) $row->academic_id,
            'biodata_id' => $row->biodata_id ? (int) $row->biodata_id : null,
            'nim' => $row->nim,
            'nama' => $row->nama,
            'prodi_id' => (int) $row->prodi_id,
            'nama_prodi' => $row->nama_prodi,
            'career_category' => $category,
            'status_karier' => $row->status_karier,
            'before_graduation' => $beforeGraduation,
            'months' => $months,
            'salary' => $salary,
            'ump' => $ump,
            'threshold' => $threshold,
            'weight' => $weight,
            'qualifies' => $weight > 0,
            'study_start' => $row->tanggal_mulai_studi,
            'salary_above_threshold' => $salaryAboveThreshold,
            'career_role' => $this->careerRole($category, $row),
            'career_status_label' => $this->careerStatusLabel($category, $row),
            'nama_perusahaan' => $row->profile_company_name,
            'lokasi_kerja' => $this->workplaceLocation($row),
            'nama_atasan' => $row->supervisor_name,
            'email_atasan' => $row->supervisor_email,
            'telepon_atasan' => $row->supervisor_phone,
            'skills' => $row->skills,
            'posisi_jabatan' => $row->posisi_jabatan,
            'bidang_pekerjaan' => $row->bidang_pekerjaan,
            'divisi' => $row->divisi,
            'study_detail' => $this->studyDetail($row),
        ];
    }

    /**
     * Menampilkan status karier gabungan ketika alumni bekerja sekaligus memiliki data studi lanjut.
     *
     * @param  string  $category  Kategori karier utama yang sudah diklasifikasikan.
     * @param  object  $row  Fakta lulusan dan profil karier.
     * @return string Label status karier untuk drill-down grafik.
     */
    private function careerStatusLabel(string $category, object $row): string
    {
        $hasFurtherStudy = $this->validStudyStart($row->tanggal_mulai_studi) !== null
            || filled($row->pendidikan_tingkat)
            || filled($row->perguruan_tinggi)
            || filled($row->pendidikan_prodi);
        $hasWorkProfile = filled($row->profile_company_name)
            || filled($row->posisi_jabatan)
            || filled($row->bidang_pekerjaan)
            || filled($row->divisi);

        if ($hasFurtherStudy && $category === 'Melanjutkan Pendidikan' && $hasWorkProfile) {
            return 'Bekerja + Studi Lanjut';
        }

        if ($hasFurtherStudy && $category !== 'Melanjutkan Pendidikan') {
            return $category.' + Studi Lanjut';
        }

        return $category;
    }

    /**
     * Menggabungkan alamat cabang, kabupaten/kota, provinsi, dan kode pos tempat kerja.
     *
     * @param  object  $row  Fakta perusahaan dan wilayah kerja.
     * @return string Lokasi kerja yang siap ditampilkan.
     */
    private function workplaceLocation(object $row): string
    {
        return collect([
            $row->workplace_address,
            $row->workplace_city_name,
            $row->workplace_province_name,
            $row->workplace_postal_code,
        ])->filter(fn (mixed $value): bool => filled($value))->implode(', ');
    }

    /**
     * Merangkum jenjang, kampus, program studi, dan tanggal studi lanjut alumni.
     *
     * @param  object  $row  Fakta pendidikan lanjut dari profil alumni.
     * @return string Ringkasan studi lanjut.
     */
    private function studyDetail(object $row): string
    {
        return collect([
            $row->pendidikan_tingkat,
            $row->perguruan_tinggi,
            $row->pendidikan_prodi,
            $this->validStudyStart($row->tanggal_mulai_studi),
        ])->filter(fn (mixed $value): bool => filled($value))->implode(' | ');
    }

    /**
     * Mengambil peran wirausaha asli dari field khusus atau jabatan fixture.
     *
     * @param  string  $category  Kategori karier kanonik.
     * @param  object  $row  Fakta profil karier alumni.
     * @return string Peran yang ditampilkan pada drill-down.
     */
    private function careerRole(string $category, object $row): string
    {
        if (filled($row->posisi_wiraswasta)) {
            return (string) $row->posisi_wiraswasta;
        }

        $status = mb_strtolower((string) ($row->profile_career_status ?: $row->status_karier));
        if ((str_contains($status, 'wiraswasta') || str_contains($status, 'wirausaha')) && filled($row->posisi_jabatan)) {
            return (string) $row->posisi_jabatan;
        }

        return $category;
    }

    /**
     * Memastikan jawaban F18d benar-benar tanggal studi lanjut, bukan kode opsi.
     *
     * @param  string|null  $value  Nilai jawaban tanggal dari read-model.
     * @return string|null Tanggal ISO atau null jika jawaban bukan tanggal valid.
     */
    private function validStudyStart(?string $value): ?string
    {
        $value = trim((string) $value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Menentukan kategori karier terstruktur dari profil dan jawaban tracer.
     *
     * @param  object  $row  Baris view lulusan.
     * @return string Kategori karier kanonik.
     */
    private function careerCategory(object $row): string
    {
        $status = mb_strtolower((string) ($row->profile_career_status ?: $row->status_karier));
        $positionValue = $row->posisi_wiraswasta;
        if ($positionValue === null && (str_contains($status, 'wiraswasta') || str_contains($status, 'wirausaha'))) {
            $positionValue = $row->posisi_jabatan;
        }
        $position = mb_strtolower((string) $positionValue);
        $hasWorkProfile = filled($row->profile_company_name)
            || filled($row->posisi_jabatan)
            || filled($row->bidang_pekerjaan)
            || filled($row->divisi);

        if (str_contains($position, 'freelance') || str_contains($position, 'konsultan mandiri') || str_contains($position, 'pekerja lepas')) {
            return 'Freelancer';
        }

        if (str_contains($position, 'founder') || str_contains($position, 'co-founder')) {
            return 'Founder / Co-Founder';
        }

        if (str_contains($status, 'wiraswasta') || str_contains($status, 'wirausaha')) {
            return 'Wirausaha Lainnya';
        }

        if (str_contains($status, 'bekerja') || str_contains($status, 'pekerja') || str_contains($status, 'karyawan')) {
            return 'Karyawan';
        }

        if (str_contains($status, 'pendidikan') && $hasWorkProfile) {
            return 'Karyawan';
        }

        if (str_contains($status, 'pendidikan')) {
            return 'Melanjutkan Pendidikan';
        }

        return 'Tidak memenuhi klasifikasi';
    }

    /**
     * Mengambil masa tunggu dari kolom sebelum atau sesudah lulus.
     *
     * @param  object  $row  Baris view lulusan.
     * @param  bool|null  $beforeGraduation  Apakah pekerjaan diperoleh sebelum lulus.
     * @return float|null Masa tunggu dalam bulan.
     */
    private function resolveWaitMonths(object $row, ?bool $beforeGraduation): ?float
    {
        if ($beforeGraduation === null) {
            return null;
        }

        $value = $beforeGraduation ? $row->bulan_sebelum_lulus : $row->bulan_setelah_lulus;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Menerapkan bobot resmi untuk kategori pekerjaan dan studi lanjut.
     *
     * @param  string  $category  Kategori karier kanonik.
     * @param  bool|null  $beforeGraduation  Status pekerjaan sebelum lulus.
     * @param  float|null  $months  Masa tunggu setelah atau sebelum lulus.
     * @param  bool  $salaryAboveThreshold  Gaji di atas 1,2× UMP.
     * @param  object  $row  Baris view lulusan untuk tanggal studi lanjut.
     * @return float Bobot, nol berarti tidak memenuhi kriteria.
     */
    private function resolveWeight(string $category, ?bool $beforeGraduation, ?float $months, ?bool $salaryAboveThreshold, object $row): float
    {
        if ($category === 'Melanjutkan Pendidikan') {
            $studyStart = $this->validStudyStart($row->tanggal_mulai_studi);
            if (! $studyStart || ! $row->tanggal_lulus) {
                return 0.0;
            }

            try {
                $start = Carbon::parse($studyStart);
                $graduated = Carbon::parse($row->tanggal_lulus);
            } catch (\Throwable) {
                return 0.0;
            }

            return $start->greaterThanOrEqualTo($graduated) && $start->lessThan($graduated->copy()->addYear())
                ? (float) config('iku.s1.weights.study_continuation', 0.6)
                : 0.0;
        }

        if ($beforeGraduation === null || $months === null || $months < 0) {
            return 0.0;
        }

        if ($salaryAboveThreshold === null || $months >= 12) {
            return 0.0;
        }

        $weights = config('iku.s1.weights', []);

        if ($beforeGraduation) {
            return $salaryAboveThreshold
                ? (float) ($weights['before_graduation_above_ump'] ?? 1.0)
                : (float) ($weights['before_graduation_below_ump'] ?? 0.6);
        }

        $short = $months < 6;
        $high = $salaryAboveThreshold;

        if ($category === 'Founder / Co-Founder') {
            return $short && $high
                ? (float) ($weights['founder_after_under_6_above_ump'] ?? 1.2)
                : (! $short && $high
                    ? (float) ($weights['founder_after_6_plus_above_ump'] ?? 1.0)
                    : ($short
                        ? (float) ($weights['founder_after_under_6_below_ump'] ?? 0.8)
                        : (float) ($weights['founder_after_6_plus_below_ump'] ?? 0.6)));
        }

        if ($category === 'Freelancer') {
            return $short && $high
                ? (float) ($weights['freelancer_after_under_6_above_ump'] ?? 0.5)
                : (! $short && $high
                    ? (float) ($weights['freelancer_after_6_plus_above_ump'] ?? 0.4)
                    : ($short
                        ? (float) ($weights['freelancer_after_under_6_below_ump'] ?? 0.3)
                        : (float) ($weights['freelancer_after_6_plus_below_ump'] ?? 0.2)));
        }

        if ($category === 'Wirausaha Lainnya') {
            return 0.0;
        }

        if ($months < 6 && $salaryAboveThreshold) {
            return (float) ($weights['employee_after_under_6_above_ump'] ?? 1.0);
        }

        if ($months < 12 && $salaryAboveThreshold) {
            return (float) ($weights['employee_after_6_under_12_above_ump'] ?? 0.8);
        }

        return $months < 12 ? (float) ($weights['employee_after_under_12_below_ump'] ?? 0.6) : 0.0;
    }

    /**
     * Menjelaskan formula capaian berbobot dan status konfirmasi aturan.
     *
     * @return array<string, string>
     */
    private function outcomeMethodology(): array
    {
        return [
            'formula' => 'IKU = Σ(nᵢ × kᵢ) / t × 100%. nᵢ adalah jumlah responden kategori i, kᵢ bobot, dan t total responden valid.',
            'denominator' => 'Lulusan S1 dengan status Karyawan, Founder/Co-Founder, Freelancer, atau Melanjutkan Pendidikan yang memenuhi syarat waktu dan data minimum. Wirausaha Lainnya ditampilkan untuk audit tetapi tidak diberi bobot tanpa role terstruktur.',
            'source' => 'v_iku_s1_lulusan, kuesioner_jawaban F8/F504/F502/F506/F18d, biodata.gaji, dan ump.',
            'weight_confirmation_status' => (string) config('iku.s1.weight_confirmation_status'),
            'slovin' => 'n_min = ceil(N / (1 + N × 0,023²)).',
        ];
    }

    /**
     * Menghitung persentase tanpa pembagian nol.
     *
     * @param  float|int  $numerator  Pembilang.
     * @param  float|int  $denominator  Penyebut.
     * @return float Persentase dua angka desimal.
     */
    private function percentage(float|int $numerator, float|int $denominator): float
    {
        return $denominator > 0 ? round(((float) $numerator / (float) $denominator) * 100, 2) : 0.0;
    }
}
