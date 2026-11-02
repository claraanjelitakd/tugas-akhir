<?php

namespace App\Services\Alumni;

use App\Models\EvaluasiAtasan;
use App\Models\LinkedinSyncResult;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AlumniDirectoryQueryService
 *
 * Layanan terpusat (Single Source of Truth) untuk membaca, memfilter, dan mengagregasi
 * data direktori alumni beserta status audit tracer study dan sinkronisasi LinkedIn.
 *
 * Digunakan oleh seluruh peran:
 * - Super Administrator (SuperAdmin\KelolaAlumni\DaftarAlumniSuperAdminController)
 * - Admin Fakultas (AdminFakultas\KelolaAlumni\DaftarAlumniFakultasController)
 * - Admin Program Studi (AdminProdi\KelolaAlumni\DaftarAlumniProdiController)
 * - Biro 3 Kemahasiswaan & Alumni (AdminBiroTiga\KelolaAlumni\DaftarAlumniController)
 *
 * Arsitektur: Zero-Cross Boundary & DRY (Don't Repeat Yourself).
 */
class AlumniDirectoryQueryService
{
    /**
     * Ambil data direktori alumni lengkap beserta statistik ringkasan dan metadata filter.
     *
     * @param  Request  $request  HTTP Request berisi query string filter
     * @param  array<string, mixed>  $options  Opsi pembatasan scope (prodi_id, fakultas_id, atau prodi_ids)
     * @return array<string, mixed> Data direktori, opsi dropdown tahun/target, dan kartu statistik
     */
    public function getDirectoryData(Request $request, array $options = []): array
    {
        // 1. Ekstraksi parameter filter dari HTTP Request
        $pencarian = $request->input('search');
        $semesterTerpilih = $request->input('semester');
        $statusTerpilih = $request->input('status');
        $targetTerpilih = $request->input('target');
        $linkedinStatusTerpilih = $request->input('linkedin_status', 'all');

        // Scope prodi / fakultas
        $scopedProdiId = $options['prodi_id'] ?? null;
        $scopedFakultasId = $options['fakultas_id'] ?? null;
        $scopedProdiIds = $options['prodi_ids'] ?? null;
        $serverPagination = (bool) ($options['server_pagination'] ?? false);
        $perPage = min(max((int) $request->input('per_page', 10), 5), 50);

        // Prodis terpilih dari filter input (jika tidak discoped ketat oleh peran Prodi)
        $prodiIdTerpilih = $scopedProdiId ?: $request->input('prodi_id');

        // 2. Ambil metadata tahun dan target dalam satu pembacaan ringan ke view profil.
        $profileOptionsQuery = DB::table('v_alumni_profile_summary')
            ->select(['tahun_lulus', 'tahun_akademik_lulus'])
            ->where(function ($q) {
                $q->whereNotNull('tahun_lulus')
                    ->orWhereNotNull('tahun_akademik_lulus');
            });

        $this->applyScope($profileOptionsQuery, $scopedProdiId, $scopedFakultasId, $scopedProdiIds);

        $profileOptions = $profileOptionsQuery->get();

        $daftarTahun = $profileOptions->pluck('tahun_lulus')
            ->filter()
            ->map(function ($item) {
                if (preg_match('/(\d{4})/', (string) $item, $matches)) {
                    return $matches[1];
                }

                return trim((string) $item);
            })
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        // Tentukan default tahun kelulusan (tahun terbaru yang tersedia)
        $tahunTerbaru = $daftarTahun[0] ?? (string) date('Y');
        $tahunTerpilih = $request->input('tahun');

        if (empty($tahunTerpilih) || $tahunTerpilih === 'all') {
            $tahunTerpilih = $tahunTerbaru;
        }

        // 3. Bentuk daftar target dari hasil metadata yang sama tanpa scan view kedua.
        $daftarTarget = $profileOptions->pluck('tahun_akademik_lulus')
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        // 4. Bangun kueri utama dari database view v_alumni_audit_rekap
        $query = DB::table('v_alumni_audit_rekap');
        $this->applyScope($query, $scopedProdiId, $scopedFakultasId, $scopedProdiIds);

        // Filter Program Studi (hanya berlaku jika bukan scoped prodi tunggal)
        if (! $scopedProdiId && $prodiIdTerpilih && $prodiIdTerpilih !== 'all') {
            $query->where('prodi_id', $prodiIdTerpilih);
        }

        // Filter Target Kelulusan (Semester + Tahun Lulus)
        if ($targetTerpilih && $targetTerpilih !== 'all') {
            $query->where('tahun_akademik_lulus', $targetTerpilih);
        }

        // Filter Pencarian Universal (NIM, Nama, Email)
        if ($pencarian) {
            $query->where(function ($w) use ($pencarian) {
                $w->where('nim', 'like', "%{$pencarian}%")
                    ->orWhere('nama', 'like', "%{$pencarian}%")
                    ->orWhere('email', 'like', "%{$pencarian}%");
            });
        }

        // Filter Tahun Kelulusan
        if ($tahunTerpilih && $tahunTerpilih !== 'all') {
            $tahunNormalized = preg_match('/(\d{4})/', (string) $tahunTerpilih, $m) ? $m[1] : $tahunTerpilih;
            $query->where(function ($q) use ($tahunTerpilih, $tahunNormalized) {
                $q->where('tahun_lulus', $tahunNormalized)
                    ->orWhere('tahun_lulus', $tahunTerpilih);
            });
        }

        // Filter Semester Kelulusan (Gasal / Genap)
        if ($semesterTerpilih && $semesterTerpilih !== 'all') {
            $query->where('tahun_akademik_lulus', 'like', "%{$semesterTerpilih}%");
        }

        // Filter Status Kelengkapan Kuesioner (Selesai vs Belum Selesai)
        if ($statusTerpilih === 'selesai') {
            $query->where('is_complete_total', 1);
        } elseif ($statusTerpilih === 'belum_selesai') {
            $query->where('is_complete_total', 0);
        }

        // Filter LinkedIn diterapkan di database agar pagination tidak menyisakan halaman kosong.
        if ($serverPagination) {
            $this->applyLinkedinStatusFilter($query, $linkedinStatusTerpilih);
        }

        $pagination = null;
        $totalSemua = 0;
        $totalSelesai = 0;
        $totalBelumSelesai = 0;
        $totalLinkedinSynced = 0;

        if ($serverPagination) {
            $summary = (clone $query)
                ->selectRaw('COUNT(*) AS total_alumni, SUM(CASE WHEN is_complete_total = 1 THEN 1 ELSE 0 END) AS total_selesai')
                ->first();

            $totalSemua = (int) ($summary->total_alumni ?? 0);
            $totalSelesai = (int) ($summary->total_selesai ?? 0);
            $totalBelumSelesai = $totalSemua - $totalSelesai;

            $matchingBiodataIds = (clone $query)->select('biodata_id');
            $latestSyncIds = DB::table('linkedin_sync_results as latest_sync')
                ->selectRaw('MAX(latest_sync.id)')
                ->whereIn('latest_sync.biodata_id', $matchingBiodataIds)
                ->groupBy('latest_sync.biodata_id');

            $totalLinkedinSynced = (int) DB::table('linkedin_sync_results')
                ->whereIn('id', $latestSyncIds)
                ->where('status', 'approved')
                ->count();

            $requestedPage = max((int) $request->input('page', 1), 1);
            $lastPage = max((int) ceil($totalSemua / $perPage), 1);
            $currentPage = min($requestedPage, $lastPage);

            $semuaAlumni = $query
                ->orderBy('nim', 'asc')
                ->forPage($currentPage, $perPage)
                ->get();
            $pagination = [
                'current_page' => $currentPage,
                'from' => $totalSemua > 0 ? (($currentPage - 1) * $perPage) + 1 : null,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'to' => $totalSemua > 0 ? min($currentPage * $perPage, $totalSemua) : null,
                'total' => $totalSemua,
            ];
        } else {
            $semuaAlumni = $query->orderBy('nim', 'asc')->get();
        }

        // 5. Muat data relasi eksternal (Evaluasi Atasan & Hasil Sinkronisasi LinkedIn)
        $biodataIds = $semuaAlumni->pluck('biodata_id')->filter()->all();

        $evaluasiMap = EvaluasiAtasan::whereIn('biodata_id', $biodataIds)
            ->get()
            ->keyBy('biodata_id');

        $syncMap = LinkedinSyncResult::whereIn('biodata_id', $biodataIds)
            ->with('reviewer:id,name,email')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('biodata_id');

        // Ambil akun yang memulai sinkronisasi dari audit log, bukan dari reviewer hasilnya.
        $syncInitiatorMap = DB::table('log_activities as sync_log')
            ->leftJoin('users as sync_user', 'sync_user.id', '=', 'sync_log.user_id')
            ->where('sync_log.action', 'linkedin_sync_apify')
            ->whereIn('sync_log.model_id', $biodataIds)
            ->orderByDesc('sync_log.id')
            ->get([
                'sync_log.model_id',
                'sync_user.name as actor_name',
                'sync_user.username as actor_username',
            ])
            ->unique('model_id')
            ->keyBy('model_id');

        // 6. Mapping data reaktif untuk Frontend
        $alumniList = [];

        foreach ($semuaAlumni as $item) {
            $isComplete = (bool) $item->is_complete_total;
            if (! $serverPagination) {
                if ($isComplete) {
                    $totalSelesai++;
                } else {
                    $totalBelumSelesai++;
                }
            }

            // Parsing semester kelulusan
            $rawSemesterLulus = $item->tahun_akademik_lulus ?? '-';
            $semesterLabel = 'Gasal';
            if (stripos($rawSemesterLulus, 'genap') !== false) {
                $semesterLabel = 'Genap';
            } elseif (stripos($rawSemesterLulus, 'gasal') !== false) {
                $semesterLabel = 'Gasal';
            }

            // Data evaluasi atasan
            $ev = $evaluasiMap->get($item->biodata_id);
            $evaluasiStatus = 'none';
            if ($ev) {
                $evaluasiStatus = $ev->is_submitted ? 'submitted' : 'pending';
            }

            // Data sinkronisasi LinkedIn
            $syncList = $syncMap->get($item->biodata_id);
            $latestSync = $syncList ? $syncList->first() : null;
            $syncCount = $syncList ? $syncList->count() : 0;
            $currentLinkedinStatus = $latestSync?->status ?? 'belum_sinkron';
            $syncInitiator = $syncInitiatorMap->get($item->biodata_id);
            $syncInitiatorName = $syncInitiator?->actor_name ?: ($syncInitiator?->actor_username ?: null);

            if (! $serverPagination && $currentLinkedinStatus === 'approved') {
                $totalLinkedinSynced++;
            }

            // Filter status LinkedIn di level memori
            if (! $serverPagination && $linkedinStatusTerpilih && $linkedinStatusTerpilih !== 'all') {
                if ($linkedinStatusTerpilih === 'sudah_sinkron' && $currentLinkedinStatus !== 'approved') {
                    continue;
                }
                if ($linkedinStatusTerpilih === 'pending' && $currentLinkedinStatus !== 'pending') {
                    continue;
                }
                if ($linkedinStatusTerpilih === 'belum_sinkron' && $currentLinkedinStatus !== 'belum_sinkron') {
                    continue;
                }
                if ($linkedinStatusTerpilih === 'ditolak' && $currentLinkedinStatus !== 'rejected') {
                    continue;
                }
            }

            // Nama reviewer sinkronisasi
            $reviewerName = null;
            if ($latestSync) {
                if ($latestSync->reviewer) {
                    $reviewerName = $latestSync->reviewer->name;
                } elseif ($latestSync->status === 'approved') {
                    $reviewerName = 'Administrator';
                } elseif ($latestSync->status === 'pending') {
                    $reviewerName = $syncInitiatorName ?: 'Sistem (Scraper)';
                }
            }

            // Format tanggal audit terakhir
            $waktuTerakhir = null;
            if ($latestSync) {
                if ($latestSync->getRawOriginal('reviewed_at')) {
                    $waktuTerakhir = $this->formatStoredTimestamp($latestSync->getRawOriginal('reviewed_at'));
                } elseif ($latestSync->getRawOriginal('scraped_at')) {
                    $waktuTerakhir = $this->formatStoredTimestamp($latestSync->getRawOriginal('scraped_at'));
                } elseif ($latestSync->getRawOriginal('updated_at')) {
                    $waktuTerakhir = $this->formatStoredTimestamp($latestSync->getRawOriginal('updated_at'));
                }
            }

            $alumniList[] = [
                'id' => $item->biodata_id,
                'biodata_id' => $item->biodata_id,
                'nim' => $item->nim,
                'nama' => $item->nama ?? 'Mahasiswa UKDW',
                'email' => $item->email_pribadi ?: ($item->email ?: null),
                'nomor_telepon' => $item->nomor_telepon ?: null,
                'linkedin_url' => $item->linkedin_url ?? null,
                'linkedin_username' => $item->linkedin_username ?? null,
                'prodi' => $item->nama_prodi ?? '-',
                'prodi_kode' => $item->kode_prodi ?? '',
                'prodi_id' => $item->prodi_id,
                'fakultas' => $item->nama_fakultas ?? '-',
                'fakultas_id' => $item->fakultas_id,
                'tahun_akademik_lulus' => $rawSemesterLulus,
                'semester' => $semesterLabel,
                'tahun_lulus' => $item->tahun_lulus ?? '-',
                'ipk' => $item->ipk ?? '-',
                'status_yudisium' => $item->status_yudisium ?? ($item->status_mahasiswa ?? 'Lulus'),
                'perusahaan' => $item->nama_perusahaan ?? '-',
                'posisi_jabatan' => $item->posisi_jabatan ?? '-',
                'status_pengisian' => $item->status_tracer_label ?? ($isComplete ? 'Selesai' : 'Belum Selesai'),
                'is_complete' => $isComplete,
                'progress' => [
                    'profile' => [
                        'is_complete' => (bool) $item->is_profile_complete,
                        'percentage' => $item->is_profile_complete ? 100 : 50,
                    ],
                    'questionnaire' => [
                        'is_complete' => (bool) $item->is_complete_univ,
                        'percentage' => (int) $item->univ_percentage,
                        'answered_count' => (int) $item->answered_mandatory_univ,
                        'total_mandatory' => (int) $item->total_mandatory_univ,
                    ],
                    'prodi' => [
                        'is_complete' => (bool) $item->is_complete_prodi,
                        'percentage' => (int) $item->prodi_percentage,
                        'answered_count' => (int) $item->answered_prodi_questions,
                        'total_questions' => (int) $item->total_prodi_questions,
                    ],
                ],
                'kelengkapan' => [
                    'is_complete' => $isComplete,
                    'status' => $item->status_tracer_label ?? ($isComplete ? 'Selesai' : 'Belum Selesai'),
                    'percentage' => (int) $item->univ_percentage,
                    'profile' => [
                        'is_complete' => (bool) $item->is_profile_complete,
                        'percentage' => $item->is_profile_complete ? 100 : 50,
                    ],
                    'questionnaire' => [
                        'is_complete' => (bool) $item->is_complete_univ,
                        'percentage' => (int) $item->univ_percentage,
                        'answered_count' => (int) $item->answered_mandatory_univ,
                        'total_mandatory' => (int) $item->total_mandatory_univ,
                    ],
                    'prodi' => [
                        'is_complete' => (bool) $item->is_complete_prodi,
                        'percentage' => (int) $item->prodi_percentage,
                        'answered_count' => (int) $item->answered_prodi_questions,
                        'total_questions' => (int) $item->total_prodi_questions,
                    ],
                ],
                'evaluasi_atasan' => [
                    'has_evaluasi' => (bool) $ev,
                    'is_submitted' => (bool) ($ev?->is_submitted),
                    'status' => $evaluasiStatus,
                    'status_label' => $ev ? ($ev->is_submitted ? 'Sudah Diisi' : 'Menunggu Respon') : 'Belum Ada Atasan',
                    'submitted_at' => $ev?->submitted_at ? $ev->submitted_at->format('d/m/Y H:i') : null,
                    'token' => $ev?->token,
                ],
                'linkedin_sync' => [
                    'has_sync' => (bool) $latestSync,
                    'sync_count' => $syncCount,
                    'status' => $currentLinkedinStatus,
                    'status_label' => match ($currentLinkedinStatus) {
                        'approved' => 'Sudah Disinkronkan',
                        'pending' => 'Pending Review',
                        'rejected' => 'Ditolak',
                        default => 'Belum Disinkron'
                    },
                    'scraped_at' => $latestSync?->getRawOriginal('scraped_at')
                        ? $this->formatStoredTimestamp($latestSync->getRawOriginal('scraped_at'))
                        : null,
                    'reviewed_at' => $latestSync?->getRawOriginal('reviewed_at')
                        ? $this->formatStoredTimestamp($latestSync->getRawOriginal('reviewed_at'))
                        : null,
                    'waktu_terakhir' => $waktuTerakhir,
                    'initiator_name' => $syncInitiatorName,
                    'reviewer_name' => $reviewerName,
                    'last_job' => $latestSync?->preview['current_job'] ?? null,
                    'last_company' => $latestSync?->preview['current_company'] ?? null,
                    'sync_id' => $latestSync?->id,
                ],
            ];
        }

        // 7. Hitung rasio & kartu statistik
        if (! $serverPagination) {
            $totalSemua = count($semuaAlumni);
        }
        $rasioSelesai = $totalSemua > 0 ? (int) round(($totalSelesai / $totalSemua) * 100) : 0;
        $rasioLinkedin = $totalSemua > 0 ? (int) round(($totalLinkedinSynced / $totalSemua) * 100) : 0;

        return [
            'alumnis' => $alumniList,
            'pagination' => $pagination,
            'daftarTahun' => $daftarTahun,
            'daftarTarget' => $daftarTarget,
            'filters' => [
                'search' => $pencarian ?? '',
                'tahun' => $tahunTerpilih,
                'semester' => $semesterTerpilih,
                'target' => $targetTerpilih ?? 'all',
                'status' => $statusTerpilih,
                'prodi_id' => $prodiIdTerpilih,
                'linkedin_status' => $linkedinStatusTerpilih,
            ],
            'stats' => [
                'total_alumni' => $totalSemua,
                'total_selesai' => $totalSelesai,
                'total_belum_selesai' => $totalBelumSelesai,
                'persentase_selesai' => $rasioSelesai,
                'total_linkedin_synced' => $totalLinkedinSynced,
                'persentase_linkedin' => $rasioLinkedin,
            ],
        ];
    }

    /**
     * Memformat timestamp yang tersimpan dalam UTC ke timezone aplikasi.
     *
     * @param  string|null  $timestamp  Nilai timestamp mentah dari database
     * @return string|null Timestamp siap tampil atau null jika nilai kosong
     */
    protected function formatStoredTimestamp(?string $timestamp): ?string
    {
        if (blank($timestamp)) {
            return null;
        }

        return Carbon::parse($timestamp, 'UTC')
            ->setTimezone(config('app.timezone'))
            ->format('d/m/Y H:i');
    }

    /**
     * Terapkan filter status LinkedIn pada query utama sebelum pagination server.
     *
     * @param  Builder  $query  Query direktori alumni yang sedang dibangun
     * @param  string  $status  Kode status LinkedIn dari filter pengguna
     */
    protected function applyLinkedinStatusFilter(Builder $query, string $status): void
    {
        if ($status === '' || $status === 'all') {
            return;
        }

        $latestSyncIds = DB::table('linkedin_sync_results as latest_sync')
            ->selectRaw('MAX(latest_sync.id)')
            ->groupBy('latest_sync.biodata_id');

        if ($status === 'belum_sinkron') {
            $query->whereNotIn(
                'biodata_id',
                DB::table('linkedin_sync_results')
                    ->select('biodata_id')
                    ->whereIn('id', $latestSyncIds),
            );

            return;
        }

        $statusMap = [
            'sudah_sinkron' => 'approved',
            'pending' => 'pending',
            'ditolak' => 'rejected',
        ];

        if (isset($statusMap[$status])) {
            $query->whereIn(
                'biodata_id',
                DB::table('linkedin_sync_results')
                    ->select('biodata_id')
                    ->whereIn('id', $latestSyncIds)
                    ->where('status', $statusMap[$status]),
            );
        }
    }

    /**
     * Terapkan pembatasan hak akses / scope program studi atau fakultas pada kueri.
     *
     * @param  Builder  $query
     * @param  int|string|null  $prodiId
     * @param  int|string|null  $fakultasId
     * @param  array<int|string>|null  $prodiIds
     */
    protected function applyScope($query, $prodiId = null, $fakultasId = null, ?array $prodiIds = null): void
    {
        if ($prodiId) {
            $query->where('prodi_id', $prodiId);
        } elseif (! empty($prodiIds)) {
            $query->whereIn('prodi_id', $prodiIds);
        } elseif ($fakultasId) {
            $query->where('fakultas_id', $fakultasId);
        }
    }
}
