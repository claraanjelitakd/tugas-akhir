<?php

namespace App\Services\Alumni;

use App\Models\EvaluasiAtasan;
use App\Models\LinkedinSyncResult;
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

        // Prodis terpilih dari filter input (jika tidak discoped ketat oleh peran Prodi)
        $prodiIdTerpilih = $scopedProdiId ?: $request->input('prodi_id');

        // 2. Kueri daftar tahun kelulusan unik untuk dropdown filter melalui view profil resmi v_alumni_profile_summary
        $tahunQuery = DB::table('v_alumni_profile_summary')
            ->where(function ($q) {
                $q->whereNotNull('tahun_lulus')
                    ->orWhereNotNull('tahun_akademik_lulus');
            });

        $this->applyScope($tahunQuery, $scopedProdiId, $scopedFakultasId, $scopedProdiIds);

        $daftarTahun = $tahunQuery->pluck('tahun_lulus')
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

        // 3. Kueri daftar target periode kelulusan unik melalui view profil resmi v_alumni_profile_summary
        $targetQuery = DB::table('v_alumni_profile_summary')
            ->whereNotNull('tahun_akademik_lulus')
            ->where('tahun_akademik_lulus', '!=', '');

        $this->applyScope($targetQuery, $scopedProdiId, $scopedFakultasId, $scopedProdiIds);

        $daftarTarget = $targetQuery->distinct()
            ->pluck('tahun_akademik_lulus')
            ->filter()
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

        $semuaAlumni = $query->orderBy('nim', 'asc')->get();

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

        // 6. Mapping data reaktif untuk Frontend
        $alumniList = [];
        $totalSelesai = 0;
        $totalBelumSelesai = 0;
        $totalLinkedinSynced = 0;

        foreach ($semuaAlumni as $item) {
            $isComplete = (bool) $item->is_complete_total;
            if ($isComplete) {
                $totalSelesai++;
            } else {
                $totalBelumSelesai++;
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

            if ($currentLinkedinStatus === 'approved') {
                $totalLinkedinSynced++;
            }

            // Filter status LinkedIn di level memori
            if ($linkedinStatusTerpilih && $linkedinStatusTerpilih !== 'all') {
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
                    $reviewerName = 'Sistem (Scraper)';
                }
            }

            // Format tanggal audit terakhir
            $waktuTerakhir = null;
            if ($latestSync) {
                if ($latestSync->reviewed_at) {
                    $waktuTerakhir = $latestSync->reviewed_at->format('d/m/Y H:i');
                } elseif ($latestSync->scraped_at) {
                    $waktuTerakhir = $latestSync->scraped_at->format('d/m/Y H:i');
                } elseif ($latestSync->updated_at) {
                    $waktuTerakhir = $latestSync->updated_at->format('d/m/Y H:i');
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
                    'scraped_at' => $latestSync?->scraped_at ? $latestSync->scraped_at->format('d/m/Y H:i') : null,
                    'reviewed_at' => $latestSync?->reviewed_at ? $latestSync->reviewed_at->format('d/m/Y H:i') : null,
                    'waktu_terakhir' => $waktuTerakhir,
                    'reviewer_name' => $reviewerName,
                    'last_job' => $latestSync?->preview['current_job'] ?? null,
                    'last_company' => $latestSync?->preview['current_company'] ?? null,
                    'sync_id' => $latestSync?->id,
                ],
            ];
        }

        // 7. Hitung rasio & kartu statistik
        $totalSemua = count($semuaAlumni);
        $rasioSelesai = $totalSemua > 0 ? (int) round(($totalSelesai / $totalSemua) * 100) : 0;
        $rasioLinkedin = $totalSemua > 0 ? (int) round(($totalLinkedinSynced / $totalSemua) * 100) : 0;

        return [
            'alumnis' => $alumniList,
            'biodatas' => $alumniList, // kompatibilitas balik
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
