<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\AlumniLinkedinPlot;
use App\Models\Biodata;
use App\Models\LinkedinSyncResult;
use App\Models\LogActivity;
use App\Models\Prodi;
use App\Services\LinkedIn\Exceptions\LinkedInProfileNotFoundException;
use App\Services\LinkedIn\Exceptions\LinkedInSyncException;
use App\Services\LinkedIn\LinkedInProfileService;
use App\Services\LinkedIn\Mappers\LinkedInProfileMapper;
use App\Services\LinkedIn\Providers\ApifyLinkedInProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Class LinkedInSyncController
 *
 * Mengelola fitur Sinkronisasi Profil LinkedIn terpadu untuk Super Admin, Admin Fakultas, dan Admin Prodi:
 * - Menampilkan direktori alumni berdasarkan filter Tahun Kelulusan, Target Periode, Semester, Prodi, dan Status Sinkronisasi.
 * - Menyesuaikan batasan data berdasarkan peran yang sedang login:
 *   1. Super Admin: Seluruh fakultas dan program studi UKDW.
 *   2. Admin Fakultas: Alumni dari program studi dalam naungan fakultasnya.
 *   3. Admin Prodi: Khusus alumni program studinya sendiri.
 * - Mendukung 2 provider resmi: Official Provider dan Apify Third-Party Scraper (Staging & Review).
 * - Menyediakan fitur sinkronisasi langsung (in-place) per alumni maupun massal.
 */
class LinkedInSyncController extends Controller
{
    /**
     * Mendapatkan lingkup akses (scope) dan base route berdasarkan peran user yang sedang login.
     *
     * @return array{role: string, baseRoute: string, prodiId: int|null, fakultasId: int|null}
     */
    private function getUserScope(Request $request): array
    {
        $user = Auth::user();

        // 1. Otoritas Admin Program Studi
        if ($user?->role === 'admin_prodi' || $request->is('prodi/*')) {
            return [
                'role' => 'admin_prodi',
                'baseRoute' => '/prodi/linkedin-sync',
                'prodiId' => $user?->prodi_id,
                'fakultasId' => null,
            ];
        }

        // 2. Otoritas Admin Fakultas (Dekanat / GKM)
        if ($user?->role === 'admin_fakultas' || $request->is('fakultas/*')) {
            return [
                'role' => 'admin_fakultas',
                'baseRoute' => '/fakultas/linkedin-sync',
                'prodiId' => null,
                'fakultasId' => $user?->fakultas_id,
            ];
        }

        // 3. Otoritas Super Admin (Pusat / Universitas)
        return [
            'role' => 'superadmin',
            'baseRoute' => '/superadmin/linkedin-sync',
            'prodiId' => null,
            'fakultasId' => null,
        ];
    }

    /**
     * Memvalidasi apakah alumni berada dalam wewenang akses akun yang sedang login.
     */
    private function validateAlumniScope(Biodata $biodata, array $scope): void
    {
        if ($scope['role'] === 'admin_prodi') {
            if ($biodata->prodi_id != $scope['prodiId']) {
                abort(403, 'Anda tidak memiliki wewenang untuk mengelola data alumni di luar program studi Anda.');
            }
        } elseif ($scope['role'] === 'admin_fakultas') {
            $prodiIdsFakultas = Prodi::where('fakultas_id', $scope['fakultasId'])->pluck('id')->all();
            if (! in_array($biodata->prodi_id, $prodiIdsFakultas)) {
                abort(403, 'Anda tidak memiliki wewenang untuk mengelola data alumni di luar lingkungan fakultas Anda.');
            }
        }
    }

    /**
     * Menampilkan halaman utama Sinkronisasi LinkedIn (Super Admin, Fakultas, Prodi).
     */
    public function index(Request $request): Response
    {
        $provider = config('services.linkedin.provider', 'official');
        $scope = $this->getUserScope($request);

        // 1. Ambil daftar Program Studi yang diizinkan untuk akun ini
        if ($scope['role'] === 'admin_prodi') {
            $daftarProdi = Prodi::where('id', $scope['prodiId'])->get();
            $prodiIdsScope = [$scope['prodiId']];
        } elseif ($scope['role'] === 'admin_fakultas') {
            $daftarProdi = Prodi::where('fakultas_id', $scope['fakultasId'])->orderBy('kode_prodi', 'asc')->get();
            $prodiIdsScope = $daftarProdi->pluck('id')->all();
        } else {
            $daftarProdi = Prodi::orderBy('kode_prodi', 'asc')->get();
            $prodiIdsScope = null;
        }

        // 2. Ambil daftar tahun kelulusan unik dari view v_alumni_audit_rekap sesuai scope
        $tahunQuery = DB::table('v_alumni_audit_rekap');
        if ($prodiIdsScope !== null) {
            $tahunQuery->whereIn('prodi_id', $prodiIdsScope);
        }
        $daftarTahun = $tahunQuery
            ->where(function ($q) {
                $q->whereNotNull('tahun_lulus')
                    ->orWhereNotNull('tahun_akademik_lulus');
            })
            ->pluck('tahun_lulus')
            ->filter()
            ->map(function ($item) {
                if (preg_match('/(\d{4})/', (string) $item, $matches)) {
                    return $matches[1];
                }

                return trim($item);
            })->unique()->sortDesc()->values()->all();

        if (empty($daftarTahun)) {
            $daftarTahun = [(string) date('Y')];
        }

        // 3. Ambil daftar target periode kelulusan unik (Semester & Tahun Lulus)
        $targetQuery = DB::table('v_alumni_audit_rekap');
        if ($prodiIdsScope !== null) {
            $targetQuery->whereIn('prodi_id', $prodiIdsScope);
        }
        $daftarTarget = $targetQuery
            ->whereNotNull('tahun_akademik_lulus')
            ->where('tahun_akademik_lulus', '!=', '')
            ->pluck('tahun_akademik_lulus')
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        // 4. Parameter Filter Request (Selaras persis dengan Halaman superadmin/alumni)
        $searchQuery = trim((string) $request->input('search', $request->input('nim', '')));
        $tahunTerbaru = $daftarTahun[0] ?? (string) date('Y');
        $tahunTerpilih = (string) $request->input('tahun', $tahunTerbaru);
        if ($tahunTerpilih === 'all' || empty($tahunTerpilih)) {
            $tahunTerpilih = $tahunTerbaru;
        }

        $semesterTerpilih = (string) $request->input('semester', 'all');
        $targetTerpilih = (string) $request->input('target', 'all');
        $statusTerpilih = (string) $request->input('status', 'all');
        $statusPlotTerpilih = (string) $request->input('status_plot', 'all');
        $prodiIdTerpilih = (string) $request->input('prodi_id', 'all');

        // Untuk Admin Prodi, kunci filter prodi ke ID prodinya sendiri
        if ($scope['role'] === 'admin_prodi') {
            $prodiIdTerpilih = (string) $scope['prodiId'];
        }

        // 5. Target alumni spesifik jika dialihkan dari detail alumni
        $targetAlumniId = $request->input('alumni_id');
        if ($targetAlumniId) {
            $targetAlumni = Biodata::with('dataAkademik')->find($targetAlumniId);
            if ($targetAlumni) {
                if (empty($searchQuery)) {
                    $searchQuery = (string) ($targetAlumni->nim ?: $targetAlumni->nama);
                }
            }
        }

        // 6. Kueri data alumni berbasis Database View `v_alumni_linkedin_plot` (AlumniLinkedinPlot)
        $queryAlumni = AlumniLinkedinPlot::query()->with([
            'biodata.latestLinkedinSyncLog',
        ]);

        // Terapkan batasan wewenang (Role Scope)
        if ($scope['role'] === 'admin_prodi') {
            $queryAlumni->where('prodi_id', $scope['prodiId']);
        } elseif ($scope['role'] === 'admin_fakultas') {
            if ($prodiIdTerpilih !== 'all' && in_array((int) $prodiIdTerpilih, $prodiIdsScope, true)) {
                $queryAlumni->where('prodi_id', (int) $prodiIdTerpilih);
            } else {
                $queryAlumni->whereIn('prodi_id', $prodiIdsScope);
            }
        } elseif ($prodiIdTerpilih !== 'all') {
            $queryAlumni->where('prodi_id', (int) $prodiIdTerpilih);
        }

        // Filter Tahun Kelulusan
        if ($tahunTerpilih && $tahunTerpilih !== 'all') {
            $queryAlumni->where(function ($q) use ($tahunTerpilih, $targetAlumniId) {
                $q->where('tahun_lulus', $tahunTerpilih);
                if ($targetAlumniId) {
                    $q->orWhere('biodata_id', $targetAlumniId);
                }
            });
        }

        // Filter Target Periode Kelulusan (Semester + Tahun)
        if ($targetTerpilih && $targetTerpilih !== 'all') {
            $queryAlumni->where('tahun_akademik_lulus', $targetTerpilih);
        }

        // Filter Semester Kelulusan (Gasal / Genap)
        if ($semesterTerpilih && $semesterTerpilih !== 'all') {
            $queryAlumni->where('tahun_akademik_lulus', 'like', "%{$semesterTerpilih}%");
        }

        // Filter Pencarian Teks
        if ($searchQuery) {
            $queryAlumni->where(function ($w) use ($searchQuery) {
                $w->where('nim', 'like', "%{$searchQuery}%")
                    ->orWhere('nama_alumni', 'like', "%{$searchQuery}%")
                    ->orWhere('linkedin_url_biodata', 'like', "%{$searchQuery}%")
                    ->orWhere('linkedin_username_biodata', 'like', "%{$searchQuery}%")
                    ->orWhere('perusahaan_biodata', 'like', "%{$searchQuery}%")
                    ->orWhere('posisi_biodata', 'like', "%{$searchQuery}%")
                    ->orWhere('nama_linkedin', 'like', "%{$searchQuery}%")
                    ->orWhere('perusahaan_linkedin', 'like', "%{$searchQuery}%")
                    ->orWhere('posisi_linkedin', 'like', "%{$searchQuery}%");
            });
        }

        // Filter Tambahan: Status Plotting View
        if ($statusPlotTerpilih && $statusPlotTerpilih !== 'all') {
            if ($statusPlotTerpilih === 'ready') {
                $queryAlumni->readyToPlot();
            } elseif ($statusPlotTerpilih === 'nama_sesuai') {
                $queryAlumni->namaSesuai();
            } elseif ($statusPlotTerpilih === 'nama_berbeda') {
                $queryAlumni->namaBerbeda();
            } elseif ($statusPlotTerpilih === 'terplot') {
                $queryAlumni->terplot();
            }
        }

        $semuaPlot = $queryAlumni->orderBy('nim', 'asc')->get();

        // 7. Hitung metrik analitik dan format data reaktif
        $totalAlumni = $semuaPlot->count();
        $denganLinkedin = 0;
        $berhasilSinkron = 0;
        $gagalSinkron = 0;
        $dilewati = 0;
        $pendingReview = 0;
        $siapDiplot = 0;
        $namaSesuai = 0;
        $namaBerbeda = 0;
        $terplotBiodata = 0;

        $alumniList = [];

        foreach ($semuaPlot as $b) {
            $hasUrl = ! empty(trim((string) ($b->linkedin_url_biodata ?: $b->linkedin_url_scraped)));
            $hasUsername = ! empty(trim((string) ($b->linkedin_username_biodata ?: $b->linkedin_username_scraped)));

            if ($b->is_nama_sesuai) {
                $namaSesuai++;
            } elseif (! $b->is_nama_sesuai && $b->sync_result_id) {
                $namaBerbeda++;
            }

            if ($b->status_plot_label === 'Data Sama / Siap Diplot') {
                $siapDiplot++;
            }

            if ($b->is_terplot_ke_biodata) {
                $terplotBiodata++;
            }

            if ($provider === 'apify') {
                if ($hasUrl) {
                    $denganLinkedin++;
                } else {
                    $dilewati++;
                }

                $statusSync = 'Belum Disinkronkan';
                $statusColor = 'slate';

                if ($b->sync_result_id) {
                    if ($b->sync_status === 'pending') {
                        $statusSync = 'Pending Review';
                        $statusColor = 'amber';
                        $pendingReview++;
                    } elseif ($b->sync_status === 'approved') {
                        $statusSync = 'Approved';
                        $statusColor = 'emerald';
                        $berhasilSinkron++;
                    } elseif ($b->sync_status === 'rejected') {
                        $statusSync = 'Rejected';
                        $statusColor = 'rose';
                        $gagalSinkron++;
                    }
                } elseif (! $hasUrl) {
                    $statusSync = 'Belum Ada URL LinkedIn';
                    $statusColor = 'slate';
                }

                $terakhirSync = $b->scraped_at ? $b->scraped_at->format('d/m/Y H:i') : '-';
                $terakhirSyncHuman = $b->scraped_at ? $b->scraped_at->diffForHumans() : '-';

                $alumniItem = [
                    'id' => $b->biodata_id,
                    'nim' => $b->nim,
                    'nama' => $b->nama_alumni ?? 'Nama Alumni Tidak Ditemukan',
                    'prodi' => $b->nama_prodi ?? '-',
                    'prodi_kode' => $b->kode_prodi ?? '',
                    'prodi_id' => $b->prodi_id,
                    'fakultas' => $b->nama_fakultas ?? '-',
                    'tahun_lulus' => $b->tahun_lulus ?: '-',
                    'tahun_akademik_lulus' => $b->tahun_akademik_lulus ?: '-',
                    'linkedin_username' => $b->linkedin_username_biodata ?: ($b->linkedin_username_scraped ?: null),
                    'linkedin_url' => $b->linkedin_url_biodata ?: ($b->linkedin_url_scraped ?: null),
                    'perusahaan' => $b->perusahaan_biodata ?? '-',
                    'posisi' => $b->posisi_biodata ?? '-',
                    'status_sync' => $statusSync,
                    'status_color' => $statusColor,
                    'terakhir_sync' => $terakhirSync ?: '-',
                    'terakhir_sync_human' => $terakhirSyncHuman ?: '-',
                    'latest_sync_result' => $b->sync_result_id ? [
                        'id' => $b->sync_result_id,
                        'status' => $b->sync_status,
                        'scraped_at' => $b->scraped_at?->format('d/m/Y H:i'),
                        'reviewed_at' => $b->reviewed_at?->format('d/m/Y H:i'),
                        'reviewer_name' => $b->reviewer_name,
                    ] : null,
                    // Field Khusus Database View Plotting & Name Matching
                    'nama_linkedin' => $b->nama_linkedin,
                    'posisi_linkedin' => $b->posisi_linkedin,
                    'perusahaan_linkedin' => $b->perusahaan_linkedin,
                    'lokasi_linkedin' => $b->lokasi_linkedin,
                    'is_nama_sesuai' => (bool) $b->is_nama_sesuai,
                    'keterangan_nama' => $b->keterangan_nama,
                    'is_terplot_ke_biodata' => (bool) $b->is_terplot_ke_biodata,
                    'status_plot_label' => $b->status_plot_label,
                ];
            } else {
                // Official Provider Flow
                if ($hasUsername) {
                    $denganLinkedin++;
                } else {
                    $dilewati++;
                }

                $lastLog = $b->biodata?->latestLinkedinSyncLog;
                $statusSync = 'Belum Disinkronkan';
                $statusColor = 'slate';

                if ($lastLog) {
                    $newValues = $lastLog->new_values ?? [];
                    $logStatus = $newValues['status'] ?? null;

                    if ($logStatus === 'Berhasil') {
                        $statusSync = 'Berhasil';
                        $statusColor = 'emerald';
                        $berhasilSinkron++;
                    } elseif ($logStatus === 'Gagal') {
                        $statusSync = 'Gagal';
                        $statusColor = 'rose';
                        $gagalSinkron++;
                    }
                } elseif (! $hasUsername) {
                    $statusSync = 'Belum Ada Username';
                    $statusColor = 'amber';
                }

                $alumniItem = [
                    'id' => $b->biodata_id,
                    'nim' => $b->nim,
                    'nama' => $b->nama_alumni ?? 'Nama Alumni Tidak Ditemukan',
                    'prodi' => $b->nama_prodi ?? '-',
                    'prodi_kode' => $b->kode_prodi ?? '',
                    'prodi_id' => $b->prodi_id,
                    'fakultas' => $b->nama_fakultas ?? '-',
                    'tahun_lulus' => $b->tahun_lulus ?: '-',
                    'tahun_akademik_lulus' => $b->tahun_akademik_lulus ?: '-',
                    'linkedin_username' => $b->linkedin_username_biodata ?: ($b->linkedin_username_scraped ?: null),
                    'linkedin_url' => $b->linkedin_url_biodata ?: ($b->linkedin_url_scraped ?: null),
                    'perusahaan' => $b->perusahaan_biodata ?? '-',
                    'posisi' => $b->posisi_biodata ?? '-',
                    'status_sync' => $statusSync,
                    'status_color' => $statusColor,
                    'terakhir_sync' => $lastLog ? $lastLog->created_at->format('d/m/Y H:i') : '-',
                    'terakhir_sync_human' => $lastLog ? $lastLog->created_at->diffForHumans() : '-',
                    'latest_sync_result' => null,
                    // Field Khusus Database View Plotting & Name Matching
                    'nama_linkedin' => $b->nama_linkedin,
                    'posisi_linkedin' => $b->posisi_linkedin,
                    'perusahaan_linkedin' => $b->perusahaan_linkedin,
                    'lokasi_linkedin' => $b->lokasi_linkedin,
                    'is_nama_sesuai' => (bool) $b->is_nama_sesuai,
                    'keterangan_nama' => $b->keterangan_nama,
                    'is_terplot_ke_biodata' => (bool) $b->is_terplot_ke_biodata,
                    'status_plot_label' => $b->status_plot_label,
                ];
            }

            // Filter Tambahan: Status Sinkronisasi
            if ($statusTerpilih && $statusTerpilih !== 'all') {
                if ($statusTerpilih === 'approved' && $alumniItem['status_sync'] !== 'Approved' && $alumniItem['status_sync'] !== 'Berhasil') {
                    continue;
                }
                if ($statusTerpilih === 'pending' && $alumniItem['status_sync'] !== 'Pending Review') {
                    continue;
                }
                if ($statusTerpilih === 'rejected' && $alumniItem['status_sync'] !== 'Rejected' && $alumniItem['status_sync'] !== 'Gagal') {
                    continue;
                }
                if ($statusTerpilih === 'belum_sync' && ! in_array($alumniItem['status_sync'], ['Belum Disinkronkan', 'Belum Ada URL LinkedIn', 'Belum Ada Username'])) {
                    continue;
                }
                if ($statusTerpilih === 'ada_linkedin' && empty($alumniItem['linkedin_url']) && empty($alumniItem['linkedin_username'])) {
                    continue;
                }
            }

            $alumniList[] = $alumniItem;
        }

        $rasioSinkron = $totalAlumni > 0 ? (int) round(($berhasilSinkron / $totalAlumni) * 100) : 0;

        return Inertia::render('SuperAdmin/LinkedIn/Index', [
            'daftarTahun' => $daftarTahun,
            'tahunTerpilih' => $tahunTerpilih,
            'daftarTarget' => $daftarTarget,
            'prodis' => $daftarProdi,
            'provider' => $provider,
            'alumnis' => $alumniList,
            'targetAlumniId' => $targetAlumniId ? (int) $targetAlumniId : null,
            'role' => $scope['role'],
            'baseRoute' => $scope['baseRoute'],
            'filters' => [
                'search' => $searchQuery,
                'tahun' => $tahunTerpilih,
                'semester' => $semesterTerpilih,
                'target' => $targetTerpilih,
                'status' => $statusTerpilih,
                'status_plot' => $statusPlotTerpilih,
                'prodi_id' => $prodiIdTerpilih,
            ],
            'stats' => [
                'total_alumni' => $totalAlumni,
                'dengan_linkedin' => $denganLinkedin,
                'berhasil_sinkron' => $berhasilSinkron,
                'gagal_sinkron' => $gagalSinkron,
                'dilewati' => $dilewati,
                'pending_review' => $pendingReview,
                'persentase_sinkron' => $rasioSinkron,
                'siap_diplot' => $siapDiplot,
                'nama_sesuai' => $namaSesuai,
                'nama_berbeda' => $namaBerbeda,
                'terplot_biodata' => $terplotBiodata,
            ],
        ]);
    }

    /**
     * Memproses sinkronisasi profil LinkedIn untuk satu alumni.
     * Mengembalikan respons JSON agar tidak memicu reload halaman penuh.
     */
    public function syncSingle(Request $request, int $id, LinkedInProfileService $service): JsonResponse
    {
        $scope = $this->getUserScope($request);
        $biodata = Biodata::with(['perusahaan', 'dataAkademik'])->findOrFail($id);
        $this->validateAlumniScope($biodata, $scope);

        $provider = config('services.linkedin.provider', 'official');

        // =====================================================================
        // FLOW APIFY: STAGING PER-ALUMNI (STATUS PENDING)
        // =====================================================================
        if ($provider === 'apify') {
            $url = trim($biodata->linkedin_url ?? '');

            // 1. Validasi URL tidak boleh kosong
            if (empty($url)) {
                return response()->json([
                    'success' => false,
                    'status' => 'EMPTY_URL',
                    'message' => 'URL LinkedIn alumni belum diisi. Provider Apify hanya menerima input dari field linkedin_url.',
                ], 422);
            }

            // 2. Validasi format URL LinkedIn
            if (! ApifyLinkedInProvider::isValidLinkedInUrl($url)) {
                return response()->json([
                    'success' => false,
                    'status' => 'INVALID_URL',
                    'message' => "Format URL LinkedIn tidak valid: '{$url}'. Harap perbaiki URL profil LinkedIn alumni.",
                ], 422);
            }

            try {
                /** @var ApifyLinkedInProvider $apify */
                $apify = app(ApifyLinkedInProvider::class);

                // Jalankan request ke Apify Actor (tepat 1 alumni -> 1 request)
                $scrapedData = $apify->scrapeProfile($url);

                // Simpan hasil ke staging linkedin_sync_results (STATUS PENDING)
                // DATA UTAMA ALUMNI TIDAK BERUBAH SAMA SEKALI PADA TAHAP INI
                $syncResult = LinkedinSyncResult::create([
                    'biodata_id' => $biodata->id,
                    'linkedin_url' => $url,
                    'linkedin_username' => $biodata->linkedin_username,
                    'scraped_data' => $scrapedData,
                    'status' => 'pending',
                    'scraped_at' => now(),
                ]);

                // Catat log audit aktivitas
                LogActivity::record(
                    action: 'linkedin_sync_apify',
                    description: "Pengambilan data profil LinkedIn via Apify berhasil untuk alumni {$biodata->nama} ({$biodata->nim}). Menunggu tinjauan Super Admin.",
                    subject: $biodata,
                    oldValues: null,
                    newValues: [
                        'sync_result_id' => $syncResult->id,
                        'status' => 'pending',
                        'provider' => 'apify',
                    ],
                );

                return response()->json([
                    'success' => true,
                    'status' => 'PENDING',
                    'message' => 'Sinkronisasi profil via Apify berhasil. Data disimpan dalam status Pending Review.',
                    'sync_result_id' => $syncResult->id,
                    'sync_result' => [
                        'id' => $syncResult->id,
                        'status' => 'pending',
                        'scraped_at' => $syncResult->scraped_at?->format('d/m/Y H:i'),
                        'parsed' => $syncResult->getPreviewData(),
                    ],
                    'alumni' => [
                        'id' => $biodata->id,
                        'nim' => $biodata->nim,
                        'nama' => $biodata->nama,
                        'status_sync' => 'Pending Review',
                        'status_color' => 'amber',
                        'terakhir_sync' => $syncResult->scraped_at?->format('d/m/Y H:i'),
                        'terakhir_sync_human' => 'Baru saja',
                        'latest_sync_result' => [
                            'id' => $syncResult->id,
                            'status' => 'pending',
                            'scraped_at' => $syncResult->scraped_at?->format('d/m/Y H:i'),
                        ],
                        'nama_linkedin' => AlumniLinkedinPlot::where('biodata_id', $biodata->id)->value('nama_linkedin'),
                        'posisi_linkedin' => AlumniLinkedinPlot::where('biodata_id', $biodata->id)->value('posisi_linkedin'),
                        'perusahaan_linkedin' => AlumniLinkedinPlot::where('biodata_id', $biodata->id)->value('perusahaan_linkedin'),
                        'lokasi_linkedin' => AlumniLinkedinPlot::where('biodata_id', $biodata->id)->value('lokasi_linkedin'),
                        'is_nama_sesuai' => (bool) AlumniLinkedinPlot::where('biodata_id', $biodata->id)->value('is_nama_sesuai'),
                        'keterangan_nama' => AlumniLinkedinPlot::where('biodata_id', $biodata->id)->value('keterangan_nama') ?: 'Menunggu Review',
                        'is_terplot_ke_biodata' => (bool) AlumniLinkedinPlot::where('biodata_id', $biodata->id)->value('is_terplot_ke_biodata'),
                        'status_plot_label' => AlumniLinkedinPlot::where('biodata_id', $biodata->id)->value('status_plot_label') ?: 'Menunggu Review (Pending)',
                    ],
                ]);

            } catch (LinkedInProfileNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'status' => 'NOT_FOUND',
                    'message' => 'Profil LinkedIn tidak ditemukan atau bersifat privat pada dataset Apify.',
                ], 404);
            } catch (LinkedInSyncException $e) {
                return response()->json([
                    'success' => false,
                    'status' => 'ERROR',
                    'message' => $e->getMessage(),
                ], 422);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'status' => 'ERROR',
                    'message' => 'Terjadi kesalahan sistem saat menghubungi server Apify.',
                ], 500);
            }
        }

        // =====================================================================
        // FLOW OFFICIAL LINKEDIN PROVIDER (EXISTING)
        // =====================================================================
        $result = $service->syncBiodata($biodata);

        // Ambil data mutakhir setelah sinkronisasi
        $fresh = $biodata->fresh(['perusahaan', 'latestLinkedinSyncLog', 'dataAkademik']);
        $lastLog = $fresh->latestLinkedinSyncLog;

        $statusSync = match ($result['status']) {
            'SUCCESS' => 'Berhasil',
            'SKIPPED' => 'Belum Ada Username',
            default => 'Gagal',
        };

        $statusColor = match ($result['status']) {
            'SUCCESS' => 'emerald',
            'SKIPPED' => 'amber',
            default => 'rose',
        };

        return response()->json([
            'success' => in_array($result['status'], ['SUCCESS', 'SKIPPED']),
            'status' => $result['status'],
            'message' => $result['message'],
            'summary' => $result['summary'],
            'alumni' => [
                'id' => $fresh->id,
                'nim' => $fresh->nim,
                'nama' => $fresh->nama,
                'tahun_lulus' => $fresh->tahun_lulus,
                'linkedin_username' => $fresh->linkedin_username,
                'linkedin_url' => $fresh->linkedin_url,
                'perusahaan' => $fresh->perusahaan?->nama_perusahaan ?? '-',
                'posisi' => $fresh->posisi_jabatan ?? '-',
                'status_sync' => $statusSync,
                'status_color' => $statusColor,
                'terakhir_sync' => $lastLog ? $lastLog->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i'),
                'terakhir_sync_human' => 'Baru saja',
            ],
        ]);
    }

    /**
     * Menampilkan detail hasil sinkronisasi staging untuk peninjauan (Review Modal).
     */
    public function showResult(Request $request, int $id): JsonResponse
    {
        $scope = $this->getUserScope($request);
        $result = LinkedinSyncResult::with(['biodata.dataAkademik', 'biodata.perusahaan', 'reviewer'])
            ->findOrFail($id);

        $this->validateAlumniScope($result->biodata, $scope);

        return response()->json([
            'success' => true,
            'result' => [
                'id' => $result->id,
                'biodata_id' => $result->biodata_id,
                'alumni_nama' => $result->biodata->nama,
                'alumni_nim' => $result->biodata->nim,
                'linkedin_url' => $result->linkedin_url,
                'status' => $result->status,
                'scraped_at' => $result->scraped_at?->format('d/m/Y H:i'),
                'reviewed_at' => $result->reviewed_at?->format('d/m/Y H:i'),
                'reviewer_name' => $result->reviewer?->name,
                'preview' => $result->getPreviewData(),
                'raw' => $result->scraped_data,
                'current_data' => [
                    'posisi_jabatan' => $result->biodata->posisi_jabatan,
                    'nama_perusahaan' => $result->biodata->perusahaan?->nama_perusahaan,
                    'skills' => $result->biodata->skills ?? $result->biodata->expert,
                    'experience' => $result->biodata->experience ?? $result->biodata->minat,
                    'expert' => $result->biodata->skills ?? $result->biodata->expert,
                    'minat' => $result->biodata->experience ?? $result->biodata->minat,
                ],
            ],
        ]);
    }

    /**
     * Menyetujui (Approve) hasil sinkronisasi Apify dan menerapkan data ke data utama.
     */
    public function approveResult(Request $request, int $id, LinkedInProfileMapper $mapper): JsonResponse
    {
        $scope = $this->getUserScope($request);
        $isReplot = $request->boolean('plot_ulang');

        return DB::transaction(function () use ($id, $mapper, $request, $scope, $isReplot) {
            $result = LinkedinSyncResult::with(['biodata.perusahaan'])->lockForUpdate()->findOrFail($id);

            $this->validateAlumniScope($result->biodata, $scope);

            $expectedStatus = $isReplot ? 'approved' : 'pending';
            if ($result->status !== $expectedStatus) {
                return response()->json([
                    'success' => false,
                    'message' => $isReplot
                        ? "Hasil sinkronisasi ini berstatus '{$result->status}' dan hanya hasil berstatus approved yang dapat diplot ulang."
                        : "Hasil sinkronisasi ini sudah berstatus '{$result->status}' dan tidak dapat disetujui kembali.",
                ], 422);
            }

            $preview = $result->getPreviewData();
            $biodata = $result->biodata;
            $updates = [];
            $changesSummary = [];

            // Input kustom dari Super Admin (opsional jika diedit/disesuaikan di modal)
            $customJob = $request->filled('posisi_jabatan') ? trim($request->input('posisi_jabatan')) : null;
            $customWiraswasta = $request->filled('posisi_wiraswasta') ? trim($request->input('posisi_wiraswasta')) : null;
            $customCompany = $request->filled('nama_perusahaan')
                ? trim($request->input('nama_perusahaan'))
                : ($request->filled('perusahaan_nama') ? trim($request->input('perusahaan_nama')) : null);
            $customSkills = $request->filled('skills') ? trim($request->input('skills')) : null;
            $customExperience = $request->filled('experience') ? trim($request->input('experience')) : null;
            $tipePekerjaan = $request->input('tipe_pekerjaan', 'pekerja'); // 'pekerja' atau 'wirausaha'

            // 1. Pemetaan Posisi Jabatan / Posisi Wiraswasta
            if ($tipePekerjaan === 'wirausaha') {
                $targetWiraswasta = $customWiraswasta ?: $customJob ?: $preview['current_job'];
                if (! empty($targetWiraswasta)) {
                    $updates['posisi_wiraswasta'] = $targetWiraswasta;
                    $updates['kategori_pekerjaan'] = 'Wiraswasta';
                    $changesSummary[] = "Kategori diset Wiraswasta dengan posisi '{$targetWiraswasta}'";
                }
            } else {
                $targetJob = $customJob ?: $preview['current_job'];
                if (! empty($targetJob)) {
                    $oldJob = $biodata->posisi_jabatan;
                    $updates['posisi_jabatan'] = $targetJob;
                    $updates['kategori_pekerjaan'] = 'Pekerja';
                    $changesSummary[] = "Jabatan diperbarui dari '".($oldJob ?: '-')."' menjadi '{$targetJob}'";
                }
            }

            // 2. Pemetaan Perusahaan (Otomatis ditambahkan ke tabel master perusahaan dengan status Menunggu Verifikasi)
            $perusahaan = null;
            $targetCompany = $customCompany ?: $preview['current_company'];
            if (! empty($targetCompany)) {
                $companyName = trim($targetCompany);
                // Hanya gunakan lokasi pada entri pekerjaan aktif. Lokasi profil
                // umum tidak cukup spesifik untuk dipetakan ke perusahaan.
                $locationName = $preview['current_company_location'] ?? null;
                $companyUrl = $preview['current_company_url'] ?? null;

                $perusahaan = $mapper->findOrCreateCompany($companyName, $locationName);

                // Pastikan status perusahaan SELALU 'Menunggu Verifikasi' sesuai kebijakan approval ulang
                $perusahaanUpdates = [];
                if ($perusahaan->status_verifikasi !== 'Menunggu Verifikasi') {
                    $perusahaanUpdates['status_verifikasi'] = 'Menunggu Verifikasi';
                }
                if (empty($perusahaan->homepage) && ! empty($companyUrl)) {
                    $perusahaanUpdates['homepage'] = $companyUrl;
                }
                if (! empty($perusahaanUpdates)) {
                    $perusahaan->update($perusahaanUpdates);
                }

                if ($biodata->perusahaan_id !== $perusahaan->id) {
                    $oldCompany = $biodata->perusahaan?->nama_perusahaan ?? '-';
                    $updates['perusahaan_id'] = $perusahaan->id;
                    $changesSummary[] = "Perusahaan diperbarui dari '{$oldCompany}' menjadi '{$perusahaan->nama_perusahaan}' (Status: Menunggu Verifikasi)";
                }
            }

            // 3. Pemetaan Foto Profil (Diunduh ke public/uploads/profile)
            $syncFoto = $request->has('sync_foto') ? $request->boolean('sync_foto') : true;
            if ($syncFoto && ! empty($preview['profile_picture'])) {
                try {
                    $photoUrl = $preview['profile_picture'];
                    $imageResponse = Http::timeout(10)->get($photoUrl);

                    if ($imageResponse->successful() && ! empty($imageResponse->body())) {
                        $dir = public_path('uploads/profile');
                        if (! file_exists($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        $safeNim = preg_replace('/[^a-zA-Z0-9_-]/', '', $biodata->nim ?: 'alumni');
                        $filename = "profile_{$safeNim}_".time().'.jpg';
                        file_put_contents("{$dir}/{$filename}", $imageResponse->body());
                        $updates['foto'] = "/uploads/profile/{$filename}";
                        $changesSummary[] = "Foto profil diunduh dan disimpan ke /uploads/profile/{$filename}";
                    } else {
                        $updates['foto'] = $photoUrl;
                        $changesSummary[] = 'Foto profil ditautkan dari URL LinkedIn';
                    }
                } catch (\Throwable $e) {
                    $updates['foto'] = $preview['profile_picture'];
                    $changesSummary[] = 'Foto profil ditautkan dari URL LinkedIn';
                }
            }

            // 4. Pemetaan Username LinkedIn
            if (! empty($preview['handle']) && $biodata->linkedin_username !== $preview['handle']) {
                $updates['linkedin_username'] = $preview['handle'];
                $changesSummary[] = "Username LinkedIn diselaraskan: {$preview['handle']}";
            }

            // 5. Pemetaan Pendidikan Lanjutan (hanya jika tersedia dan di biodata masih kosong)
            if (! empty($preview['education'][0])) {
                $edu = $preview['education'][0];
                if (empty($biodata->pendidikan_tingkat) && ! empty($edu['degree'])) {
                    $updates['pendidikan_tingkat'] = $edu['degree'];
                    $changesSummary[] = "Pendidikan tingkat disimpan: {$edu['degree']}";
                }
                if (empty($biodata->perguruan_tinggi) && ! empty($edu['school'])) {
                    $updates['perguruan_tinggi'] = $edu['school'];
                    $changesSummary[] = "Perguruan tinggi studi lanjut: {$edu['school']}";
                }
                if (empty($biodata->pendidikan_prodi) && ! empty($edu['field_of_study'])) {
                    $updates['pendidikan_prodi'] = $edu['field_of_study'];
                    $changesSummary[] = "Program studi studi lanjut: {$edu['field_of_study']}";
                }
            }

            // 6. Pemetaan Keahlian (Skills) - Prioritas dari input modal peninjauan, fallback ke hasil scraping
            $targetSkills = $customSkills;
            if (empty($targetSkills) && ! empty($preview['skills'])) {
                $skillsArray = is_array($preview['skills']) ? $preview['skills'] : explode(',', (string) $preview['skills']);
                $skillsClean = array_map(function ($s) {
                    return is_array($s) ? ($s['name'] ?? ($s['title'] ?? ($s['skill'] ?? ''))) : trim((string) $s);
                }, $skillsArray);
                $skillsClean = array_values(array_filter($skillsClean));
                $targetSkills = implode(', ', array_slice($skillsClean, 0, 15));
            }

            if (! empty($targetSkills)) {
                $updates['skills'] = $targetSkills;
                $updates['expert'] = $targetSkills;
                $changesSummary[] = "Keahlian (Skills) disimpan: {$targetSkills}";
            }

            // 7. Pemetaan Pengalaman Kerja (Experience)
            $incomingExperience = $customExperience;
            if (empty($incomingExperience)) {
                $rawExpList = ! empty($preview['experiences']) ? $preview['experiences'] : (! empty($preview['experience']) ? $preview['experience'] : []);
                if (! empty($rawExpList)) {
                    $incomingExperience = $this->formatExperienceHistory($rawExpList);
                }
            }

            $targetExp = $this->appendExperienceHistory($biodata->experience, $incomingExperience);
            if (! empty($targetExp) && $targetExp !== (string) $biodata->experience) {
                $updates['experience'] = $targetExp;
                $updates['minat'] = $targetExp;
                $changesSummary[] = 'Pengalaman Kerja (Experience) disimpan dari profil LinkedIn';
            }

            // 7. Perbarui data utama biodata jika ada perubahan
            if (! empty($updates)) {
                $biodata->update($updates);
            }

            // Approval pertama mengubah staging menjadi approved. Plot ulang mempertahankan snapshot dan status audit yang sudah ada.
            if (! $isReplot) {
                $result->update([
                    'status' => 'approved',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            }

            $action = $isReplot ? 'linkedin_sync_replotted' : 'linkedin_sync_approved';
            $actionDescription = $isReplot
                ? "Admin memplot ulang snapshot sinkronisasi LinkedIn Apify untuk alumni {$biodata->nama} ({$biodata->nim})."
                : "Super Admin menyetujui hasil sinkronisasi LinkedIn Apify untuk alumni {$biodata->nama} ({$biodata->nim}).";

            LogActivity::record(
                action: $action,
                description: $actionDescription,
                subject: $biodata,
                oldValues: null,
                newValues: [
                    'sync_result_id' => $result->id,
                    'status' => 'approved',
                    'mode' => $isReplot ? 'plot_ulang' : 'approve',
                    'changes' => $changesSummary,
                ]
            );

            $fresh = $biodata->fresh(['perusahaan']);

            $plotRecord = AlumniLinkedinPlot::where('biodata_id', $fresh->id)->first();

            return response()->json([
                'success' => true,
                'message' => $isReplot
                    ? "Data profil LinkedIn untuk {$biodata->nama} berhasil diplot ulang tanpa menghapus data lama."
                    : "Data profil LinkedIn untuk {$biodata->nama} berhasil disetujui dan diterapkan.",
                'changes_summary' => $changesSummary,
                'alumni' => [
                    'id' => $fresh->id,
                    'perusahaan' => $fresh->perusahaan?->nama_perusahaan ?? '-',
                    'posisi' => $fresh->posisi_jabatan ?? '-',
                    'linkedin_username' => $fresh->linkedin_username,
                    'status_sync' => 'Approved',
                    'status_color' => 'emerald',
                    'latest_sync_result' => [
                        'id' => $result->id,
                        'status' => 'approved',
                        'reviewed_at' => $result->reviewed_at?->format('d/m/Y H:i'),
                    ],
                    'nama_linkedin' => $plotRecord?->nama_linkedin,
                    'posisi_linkedin' => $plotRecord?->posisi_linkedin,
                    'perusahaan_linkedin' => $plotRecord?->perusahaan_linkedin,
                    'lokasi_linkedin' => $plotRecord?->lokasi_linkedin,
                    'is_nama_sesuai' => (bool) $plotRecord?->is_nama_sesuai,
                    'keterangan_nama' => $plotRecord?->keterangan_nama,
                    'is_terplot_ke_biodata' => (bool) $plotRecord?->is_terplot_ke_biodata,
                    'status_plot_label' => $plotRecord?->status_plot_label,
                ],
            ]);
        });
    }

    /**
     * Menerapkan ulang snapshot LinkedIn yang sudah pernah disetujui.
     */
    public function plotAgain(Request $request, int $id, LinkedInProfileMapper $mapper): JsonResponse
    {
        $request->merge(['plot_ulang' => true]);

        return $this->approveResult($request, $id, $mapper);
    }

    /**
     * Normalisasi seluruh riwayat pengalaman LinkedIn menjadi teks multiline
     * untuk kolom biodata.experience.
     *
     * @param  array<int, mixed>  $experiences
     */
    private function formatExperienceHistory(array $experiences): ?string
    {
        $lines = [];

        foreach ($experiences as $experience) {
            if (! is_array($experience)) {
                continue;
            }

            $position = trim((string) ($experience['position'] ?? ($experience['title'] ?? '')));
            $company = trim((string) ($experience['company'] ?? ($experience['company_name'] ?? '')));
            $date = trim((string) ($experience['date'] ?? ''));
            $timePeriod = trim((string) ($experience['time_period'] ?? ''));
            $location = trim((string) ($experience['location'] ?? ''));

            $period = $date;
            if ($timePeriod !== '' && $timePeriod !== $date) {
                $period .= ($period !== '' ? ' ' : '')."({$timePeriod})";
            }

            $subject = trim(implode(' di ', array_filter([$position, $company], static fn (string $value): bool => $value !== '')));
            $line = trim($subject.($period !== '' ? " [{$period}]" : '').($location !== '' ? " - {$location}" : ''));

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines === [] ? null : implode(PHP_EOL, $lines);
    }

    /**
     * Tambahkan riwayat baru di bawah riwayat yang sudah ada tanpa menggandakan baris.
     */
    private function appendExperienceHistory(?string $existing, ?string $incoming): ?string
    {
        $lines = [];
        $seen = [];

        foreach ([$existing, $incoming] as $history) {
            $historyLines = preg_split('/\r\n|\r|\n/', trim((string) $history), -1, PREG_SPLIT_NO_EMPTY);

            foreach ($historyLines ?: [] as $line) {
                $cleanLine = trim((string) $line);
                $normalizedLine = strtolower((string) preg_replace('/\s+/', ' ', $cleanLine));

                if ($cleanLine === '' || isset($seen[$normalizedLine])) {
                    continue;
                }

                $seen[$normalizedLine] = true;
                $lines[] = $cleanLine;
            }
        }

        return $lines === [] ? null : implode(PHP_EOL, $lines);
    }

    /**
     * Menolak (Reject) hasil sinkronisasi Apify tanpa mengubah data utama alumni.
     */
    public function rejectResult(Request $request, int $id): JsonResponse
    {
        $scope = $this->getUserScope($request);

        return DB::transaction(function () use ($id, $scope) {
            $result = LinkedinSyncResult::with('biodata')->lockForUpdate()->findOrFail($id);

            $this->validateAlumniScope($result->biodata, $scope);

            // Validasi status wajib 'pending'
            if ($result->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => "Hasil sinkronisasi ini sudah berstatus '{$result->status}' dan tidak dapat ditolak.",
                ], 422);
            }

            // Data utama TIDAK berubah sama sekali
            $result->update([
                'status' => 'rejected',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            // Catat log audit aktivitas
            LogActivity::record(
                action: 'linkedin_sync_rejected',
                description: "Admin menolak hasil sinkronisasi LinkedIn Apify untuk alumni {$result->biodata->nama} ({$result->biodata->nim}). Data utama tidak diubah.",
                subject: $result->biodata,
                oldValues: null,
                newValues: [
                    'sync_result_id' => $result->id,
                    'status' => 'rejected',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => "Hasil sinkronisasi LinkedIn untuk {$result->biodata->nama} telah ditolak. Data utama tidak diubah.",
                'alumni' => [
                    'id' => $result->biodata->id,
                    'status_sync' => 'Rejected',
                    'status_color' => 'rose',
                    'latest_sync_result' => [
                        'id' => $result->id,
                        'status' => 'rejected',
                        'reviewed_at' => $result->reviewed_at?->format('d/m/Y H:i'),
                    ],
                ],
            ]);
        });
    }

    /**
     * Mengambil seluruh histori hasil sinkronisasi LinkedIn untuk seorang alumni.
     */
    public function history(Request $request, int $id): JsonResponse
    {
        $scope = $this->getUserScope($request);
        $biodata = Biodata::findOrFail($id);
        $this->validateAlumniScope($biodata, $scope);

        $results = LinkedinSyncResult::where('biodata_id', $id)
            ->with('reviewer')
            ->orderByDesc('id')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'status' => $r->status,
                    'linkedin_url' => $r->linkedin_url,
                    'scraped_at' => $r->scraped_at?->format('d/m/Y H:i'),
                    'reviewed_at' => $r->reviewed_at?->format('d/m/Y H:i'),
                    'reviewer_name' => $r->reviewer?->name ?? '-',
                    'preview' => $r->getPreviewData(),
                ];
            });

        return response()->json([
            'success' => true,
            'alumni' => [
                'id' => $biodata->id,
                'nim' => $biodata->nim,
                'nama' => $biodata->nama,
            ],
            'history' => $results,
        ]);
    }

    /**
     * Memproses sinkronisasi massal (Bulk Sync) untuk alumni pada tahun tertentu.
     * Mengembalikan ringkasan status eksekusi.
     */
    public function syncBatch(Request $request, LinkedInProfileService $service): JsonResponse
    {
        $validated = $request->validate([
            'tahun' => ['required', 'string', 'regex:/^\d{4}$/'],
        ]);
        $scope = $this->getUserScope($request);
        $provider = config('services.linkedin.provider', 'official');

        // Untuk provider Apify, bulk scraping dinonaktifkan demi Cost Safety
        if ($provider === 'apify') {
            return response()->json([
                'success' => false,
                'message' => 'Bulk sync dinonaktifkan untuk provider Apify demi keamanan biaya (Cost Safety). Silakan lakukan sinkronisasi per alumni.',
            ], 400);
        }

        $tahun = $validated['tahun'];

        // Cari alumni pada tahun terpilih
        $alumniQuery = Biodata::where(function ($q) use ($tahun) {
            $q->whereHas('dataAkademik', fn ($sub) => $sub->where('tahun_lulus', $tahun))
                ->orWhereHas('yudisium.dataAkademik', fn ($sub) => $sub->where('tahun_lulus', $tahun));
        });

        if ($scope['role'] === 'admin_prodi') {
            $alumniQuery->where('prodi_id', $scope['prodiId']);
        } elseif ($scope['role'] === 'admin_fakultas') {
            $prodiIds = Prodi::where('fakultas_id', $scope['fakultasId'])->pluck('id');
            $alumniQuery->whereIn('prodi_id', $prodiIds);
        }

        $biodataIds = $alumniQuery->pluck('id')->all();

        if (empty($biodataIds)) {
            return response()->json([
                'success' => false,
                'message' => "Tidak ada alumni yang terdaftar pada tahun kelulusan {$tahun}.",
                'summary' => [
                    'total' => 0,
                    'berhasil' => 0,
                    'gagal' => 0,
                    'dilewati' => 0,
                ],
                'results' => [],
            ], 422);
        }

        $batchResult = $service->syncBatch($biodataIds);

        return response()->json([
            'success' => true,
            'message' => "Sinkronisasi massal tahun {$tahun} selesai.",
            'summary' => [
                'total' => $batchResult['total'],
                'berhasil' => $batchResult['berhasil'],
                'gagal' => $batchResult['gagal'],
                'dilewati' => $batchResult['dilewati'],
            ],
            'results' => $batchResult['results'],
        ]);
    }
}
