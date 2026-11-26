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
use App\Services\LinkedIn\Providers\MockApifyLinkedInProvider;
use Database\Seeders\LinkedinSyncResultSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

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
 * - Memetakan perusahaan secara otomatis jika kecocokan master sangat kuat.
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
                'prodiId' => $user?->kode_prodi,
                'fakultasId' => null,
            ];
        }

        // 2. Otoritas Admin Fakultas (Dekanat / GKM)
        if ($user?->role === 'admin_fakultas' || $request->is('fakultas/*')) {
            return [
                'role' => 'admin_fakultas',
                'baseRoute' => '/fakultas/linkedin-sync',
                'prodiId' => null,
                'fakultasId' => $user?->kode_fakultas,
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
            if ($biodata->kode_prodi != $scope['prodiId']) {
                abort(403, 'Anda tidak memiliki wewenang untuk mengelola data alumni di luar program studi Anda.');
            }
        } elseif ($scope['role'] === 'admin_fakultas') {
            $prodiIdsFakultas = Prodi::where('kode_fakultas', $scope['fakultasId'])->pluck('kode_prodi')->all();
            if (! in_array($biodata->kode_prodi, $prodiIdsFakultas)) {
                abort(403, 'Anda tidak memiliki wewenang untuk mengelola data alumni di luar lingkungan fakultas Anda.');
            }
        }
    }

    /**
     * Mengembalikan JSON untuk fetch API dan redirect valid untuk request Inertia.
     *
     * @param  array<string, mixed>  $payload
     */
    private function respondToMutation(Request $request, array $payload, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            if (($payload['success'] ?? false) === false) {
                return redirect()->back()->withErrors([
                    'linkedin' => $payload['message'] ?? 'Permintaan sinkronisasi tidak dapat diproses.',
                ]);
            }

            return redirect()->back()->with('success', $payload['message'] ?? 'Permintaan sinkronisasi berhasil diproses.');
        }

        return response()->json($payload, $status);
    }

    /**
     * Mengalihkan ke direktori Data Alumni sesuai peran pengguna
     * karena seluruh fitur sinkronisasi LinkedIn kini telah diintegrasikan langsung ke Data Alumni.
     *
     * @param  Request  $request  Request HTTP terautentikasi.
     * @return RedirectResponse Pengalihan ke rute direktori alumni.
     */
    public function index(Request $request): RedirectResponse
    {
        $scope = $this->getUserScope($request);
        $routePrefix = match ($scope['role']) {
            'admin_prodi' => 'prodi',
            'admin_fakultas' => 'fakultas',
            default => 'superadmin',
        };

        return redirect()->route("{$routePrefix}.alumni.index");
    }

    /**
     * Memperbarui URL profil LinkedIn sebelum proses sinkronisasi dijalankan.
     *
     * @param  Request  $request  Request JSON yang membawa URL profil baru
     * @param  int  $id  ID biodata alumni yang URL-nya diperbarui
     */
    public function updateUrl(Request $request, int $id): JsonResponse
    {
        $scope = $this->getUserScope($request);
        $biodata = Biodata::findOrFail($id);
        $this->validateAlumniScope($biodata, $scope);

        $validated = $request->validate([
            'linkedin_url' => ['required', 'string', 'max:500'],
        ]);
        $linkedinUrl = trim($validated['linkedin_url']);

        if (! ApifyLinkedInProvider::isValidLinkedInUrl($linkedinUrl)) {
            return response()->json([
                'success' => false,
                'message' => 'Format URL LinkedIn tidak valid. Gunakan URL profil dengan pola linkedin.com/in/.',
            ], 422);
        }

        $oldUrl = $biodata->linkedin_url;
        $biodata->update([
            'linkedin_url' => $linkedinUrl,
        ]);

        if ($oldUrl !== $linkedinUrl) {
            LogActivity::record(
                action: 'linkedin_url_updated',
                description: "URL profil LinkedIn diperbarui untuk alumni {$biodata->nama} ({$biodata->nim}).",
                subject: $biodata,
                oldValues: ['linkedin_url' => $oldUrl],
                newValues: ['linkedin_url' => $linkedinUrl],
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'URL profil LinkedIn berhasil diperbarui.',
            'linkedin_url' => $linkedinUrl,
        ]);
    }

    /**
     * Memproses sinkronisasi profil LinkedIn untuk satu alumni.
     * Mengembalikan respons JSON agar tidak memicu reload halaman penuh.
     */
    public function syncSingle(Request $request, int $id, LinkedInProfileService $service, LinkedInProfileMapper $mapper): JsonResponse
    {
        $scope = $this->getUserScope($request);
        $biodata = Biodata::with(['perusahaan', 'dataAkademik'])->findOrFail($id);
        $this->validateAlumniScope($biodata, $scope);

        $provider = config('services.linkedin.provider', 'official');

        // =====================================================================
        // FLOW APIFY: STAGING PER-ALUMNI (STATUS PENDING)
        // =====================================================================
        if (in_array($provider, ['apify', 'mock_apify'], true)) {
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
                /** @var ApifyLinkedInProvider|MockApifyLinkedInProvider $apify */
                $apify = $provider === 'mock_apify'
                    ? app(MockApifyLinkedInProvider::class)
                    : app(ApifyLinkedInProvider::class);

                // Jalankan request ke Apify Actor (tepat 1 alumni -> 1 request)
                $scrapedData = $apify->scrapeProfile($url);

                // Simpan hasil ke staging; field profil selain mapping perusahaan tetap menunggu approval.
                $syncResult = LinkedinSyncResult::create([
                    'biodata_id' => $biodata->id,
                    'linkedin_url' => $url,
                    'linkedin_username' => $biodata->linkedin_username,
                    'scraped_data' => $scrapedData,
                    'status' => 'pending',
                    'scraped_at' => now(),
                ]);

                $preview = $syncResult->getPreviewData();
                $companyResolution = null;

                if (filled($preview['current_company'] ?? null)) {
                    $companyResolution = $mapper->resolveCompany(
                        (string) $preview['current_company'],
                        $preview['current_company_location'] ?? null,
                        $biodata,
                    );

                    // Sinkronisasi hanya menyiapkan rekomendasi. Relasi biodata baru
                    // diubah ketika admin menekan Approve/Plot.
                    if (
                        $companyResolution['status'] === 'matched'
                        && $biodata->perusahaan_id
                        && $biodata->perusahaan_id !== $companyResolution['company']->id
                    ) {
                        $companyResolution['status'] = 'needs_review';
                    }
                }

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
                        'company_resolution' => $companyResolution ? [
                            'company_id' => $companyResolution['company']->id,
                            'status' => $companyResolution['status'],
                            'match_type' => $companyResolution['match_type'],
                            'similarity_score' => $companyResolution['similarity_score'],
                        ] : null,
                    ],
                );

                // Cadangkan snapshot scraping aktif ke disk agar aman dari migrate:fresh
                LinkedinSyncResultSeeder::backupSyncResults();

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
                        'company_resolution' => $this->formatCompanyResolution($companyResolution),
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
                            'company_resolution' => $this->formatCompanyResolution($companyResolution),
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
     *
     * @param  Request  $request  HTTP request dengan scope peran pengguna
     * @param  int  $id  ID hasil sinkronisasi staging (linkedin_sync_results.id)
     * @return JsonResponse Data payload staging, preview ekstraksi, dan data aktual alumni saat ini
     */
    public function showResult(Request $request, int $id): JsonResponse
    {
        $scope = $this->getUserScope($request);
        $result = LinkedinSyncResult::with([
            'biodata.dataAkademik',
            'biodata.prodi.fakultas',
            'biodata.propinsi',
            'biodata.kabupaten',
            'biodata.perusahaan.propinsi',
            'biodata.perusahaan.kabupaten',
            'reviewer',
        ])
            ->findOrFail($id);

        $this->validateAlumniScope($result->biodata, $scope);

        $biodata = $result->biodata;
        $perusahaan = $biodata->perusahaan;
        $domisili = collect([
            $biodata->kabupaten?->nama_kabupaten,
            $biodata->propinsi?->nama_provinsi,
        ])->filter()->implode(', ');
        $headOfficeLocation = collect([
            $perusahaan?->kabupaten?->nama_kabupaten,
            $perusahaan?->propinsi?->nama_provinsi,
        ])->filter()->implode(', ');

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
                    'foto' => $biodata->foto,
                    'linkedin_url' => $biodata->linkedin_url ?: $result->linkedin_url,
                    'posisi_jabatan' => $biodata->posisi_jabatan,
                    'nama_perusahaan' => $perusahaan?->nama_perusahaan,
                    'bidang_pekerjaan' => $biodata->bidang_pekerjaan,
                    'kategori_pekerjaan' => $biodata->kategori_pekerjaan,
                    'divisi' => $biodata->divisi,
                    'pendidikan_tingkat' => $biodata->pendidikan_tingkat,
                    'perguruan_tinggi' => $biodata->perguruan_tinggi,
                    'pendidikan_prodi' => $biodata->pendidikan_prodi,
                    'skills' => $biodata->skills,
                    'experience' => $biodata->experience,
                    'prodi' => $biodata->prodi?->nama_prodi,
                    'fakultas' => $biodata->prodi?->fakultas?->nama_fakultas,
                    'domisili' => [
                        'label' => $domisili ?: null,
                        'alamat' => $biodata->alamat_saat_ini,
                        'kode_pos' => $biodata->kode_pos,
                    ],
                    'head_office' => [
                        'nama' => $perusahaan?->nama_perusahaan,
                        'status_verifikasi' => $perusahaan?->status_verifikasi,
                        'lokasi' => $headOfficeLocation ?: null,
                        'alamat' => $perusahaan?->alamat,
                        'kode_pos' => $perusahaan?->kode_pos,
                        'no_telp_fax' => $perusahaan?->no_telp_fax,
                        'homepage' => $perusahaan?->homepage,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Menyetujui (Approve) hasil sinkronisasi Apify dan menerapkan data ke data utama.
     * Mendukung pemetaan jabatan/wirausaha, perusahaan (status Menunggu Verifikasi),
     * bidang pekerjaan (industri LinkedIn), keahlian (top skills), foto profil lokal,
     * serta riwayat studi lanjut (jenjang, perguruan tinggi, prodi).
     *
     * @param  Request  $request  HTTP request berisi data usulan modifikasi dari modal review
     * @param  int  $id  ID hasil sinkronisasi staging yang ditinjau
     * @param  LinkedInProfileMapper  $mapper  Mapper untuk pencocokan atau pembuatan data master perusahaan
     * @return JsonResponse|RedirectResponse Respons JSON hasil approval beserta ringkasan perubahan
     */
    public function approveResult(Request $request, int $id, LinkedInProfileMapper $mapper): JsonResponse|RedirectResponse
    {
        $scope = $this->getUserScope($request);
        $isReplot = $request->boolean('plot_ulang');

        return DB::transaction(function () use ($id, $mapper, $request, $scope, $isReplot) {
            $result = LinkedinSyncResult::with(['biodata.perusahaan'])->lockForUpdate()->findOrFail($id);

            $this->validateAlumniScope($result->biodata, $scope);

            $expectedStatus = $isReplot ? 'approved' : 'pending';
            if ($result->status !== $expectedStatus) {
                return $this->respondToMutation($request, [
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
            $companyResolution = null;

            // Input kustom dari Super Admin (opsional jika diedit/disesuaikan di modal)
            $customJob = $request->filled('posisi_jabatan') ? trim($request->input('posisi_jabatan')) : null;
            $customWiraswasta = $request->filled('posisi_wiraswasta') ? trim($request->input('posisi_wiraswasta')) : null;
            $customCompany = $request->filled('nama_perusahaan')
                ? trim($request->input('nama_perusahaan'))
                : ($request->filled('perusahaan_nama') ? trim($request->input('perusahaan_nama')) : null);
            $customBidang = $request->filled('bidang_pekerjaan') ? trim($request->input('bidang_pekerjaan')) : null;
            $customDivisi = $request->filled('divisi') ? trim($request->input('divisi')) : null;
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

            // 1b. Pemetaan Bidang Pekerjaan / Divisi
            $targetBidang = $customBidang ?: ($preview['bidang_pekerjaan'] ?? null);
            if (! empty($targetBidang) && $biodata->bidang_pekerjaan !== $targetBidang) {
                $updates['bidang_pekerjaan'] = $targetBidang;
                $changesSummary[] = "Bidang pekerjaan diselaraskan: {$targetBidang}";
            }

            // Divisi/unit kerja berasal dari metadata posisi LinkedIn jika tersedia.
            $targetDivisi = $customDivisi ?: ($preview['current_divisi'] ?? null);
            if (! empty($targetDivisi) && $biodata->divisi !== $targetDivisi) {
                $updates['divisi'] = $targetDivisi;
                $changesSummary[] = "Divisi/unit kerja diselaraskan: {$targetDivisi}";
            }

            // 2. Pemetaan Perusahaan: master terverifikasi dipakai langsung, sedangkan
            //    nama yang benar-benar baru tetap menjadi kandidat Menunggu Verifikasi.
            $perusahaan = null;
            $targetCompany = $customCompany ?: $preview['current_company'];
            if (! empty($targetCompany)) {
                $companyName = trim($targetCompany);
                // Hanya gunakan lokasi pada entri pekerjaan aktif. Lokasi profil
                // umum tidak cukup spesifik untuk dipetakan ke perusahaan.
                $locationName = $preview['current_company_location'] ?? null;

                $companyResolution = $mapper->resolveCompany($companyName, $locationName, $biodata);
                $perusahaan = $companyResolution['company'];

                if ($biodata->perusahaan_id !== $perusahaan->id) {
                    $oldCompany = $biodata->perusahaan?->nama_perusahaan ?? '-';
                    $updates['perusahaan_id'] = $perusahaan->id;
                    $statusLabel = $perusahaan->status_verifikasi === 'Terverifikasi'
                        ? 'Master terverifikasi'
                        : 'Menunggu Verifikasi';
                    $changesSummary[] = "Perusahaan diperbarui dari '{$oldCompany}' menjadi '{$perusahaan->nama_perusahaan}' (Status: {$statusLabel})";
                }
            }

            // 3. Pemetaan Foto Profil (Diunduh ke public/uploads/profile)
            $syncFoto = $request->has('sync_foto') ? $request->boolean('sync_foto') : true;
            if ($syncFoto && ! empty($preview['profile_picture'])) {
                // Fixture mock tidak boleh memicu request jaringan saat approval.
                if (config('services.linkedin.provider') === 'mock_apify') {
                    $updates['foto'] = $preview['profile_picture'];
                    $changesSummary[] = 'Foto profil mock ditautkan tanpa mengunduh aset eksternal';
                } else {
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
            }

            // 4. Pemetaan Username LinkedIn
            if (! empty($preview['handle']) && $biodata->linkedin_username !== $preview['handle']) {
                $updates['linkedin_username'] = $preview['handle'];
                $changesSummary[] = "Username LinkedIn diselaraskan: {$preview['handle']}";
            }

            // 5. Pemetaan Pendidikan Lanjutan (Studi Lanjut)
            $syncStudi = $request->has('sync_studi_lanjut') ? $request->boolean('sync_studi_lanjut') : true;
            $customTingkat = $request->filled('pendidikan_tingkat') ? trim($request->input('pendidikan_tingkat')) : null;
            $customKampus = $request->filled('perguruan_tinggi') ? trim($request->input('perguruan_tinggi')) : null;
            $customProdi = $request->filled('pendidikan_prodi') ? trim($request->input('pendidikan_prodi')) : null;

            if ($syncStudi) {
                $studyCandidate = $preview['further_study'] ?? null;
                $tingkatToSet = $customTingkat ?: ($studyCandidate['pendidikan_tingkat'] ?? null);
                $kampusToSet = $customKampus ?: ($studyCandidate['perguruan_tinggi'] ?? null);
                $prodiToSet = $customProdi ?: ($studyCandidate['pendidikan_prodi'] ?? null);

                // Fallback ke entri pendidikan pertama jika studyCandidate belum terisi
                if (empty($kampusToSet) && ! empty($preview['education'][0])) {
                    $edu = $preview['education'][0];
                    $tingkatToSet = $tingkatToSet ?: ($edu['degree'] ?? null);
                    $kampusToSet = $edu['school'] ?? null;
                    $prodiToSet = $prodiToSet ?: ($edu['field_of_study'] ?? null);
                }

                if (! empty($kampusToSet) || ! empty($prodiToSet) || ! empty($tingkatToSet)) {
                    if (! empty($tingkatToSet) && $biodata->pendidikan_tingkat !== $tingkatToSet) {
                        $updates['pendidikan_tingkat'] = $tingkatToSet;
                        $changesSummary[] = "Jenjang studi lanjut diperbarui: {$tingkatToSet}";
                    }
                    if (! empty($kampusToSet) && $biodata->perguruan_tinggi !== $kampusToSet) {
                        $updates['perguruan_tinggi'] = $kampusToSet;
                        $changesSummary[] = "Perguruan tinggi studi lanjut: {$kampusToSet}";
                    }
                    if (! empty($prodiToSet) && $biodata->pendidikan_prodi !== $prodiToSet) {
                        $updates['pendidikan_prodi'] = $prodiToSet;
                        $changesSummary[] = "Program studi studi lanjut: {$prodiToSet}";
                    }
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
                    'user_id' => Auth::id(),
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

            // Cadangkan pembaruan status approved ke snapshot disk
            LinkedinSyncResultSeeder::backupSyncResults();

            return $this->respondToMutation($request, [
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
                    'company_resolution' => $this->formatCompanyResolution($companyResolution),
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
     * Menerapkan ulang snapshot LinkedIn yang sudah pernah disetujui (Approved).
     * Mempertahankan snapshot staging dan status audit yang sudah ada, sambil
     * memperbarui data biodata, bidang pekerjaan, dan riwayat studi lanjut.
     *
     * @param  Request  $request  HTTP request
     * @param  int  $id  ID hasil sinkronisasi yang disetujui
     * @param  LinkedInProfileMapper  $mapper  Mapper master perusahaan
     */
    public function plotAgain(Request $request, int $id, LinkedInProfileMapper $mapper): JsonResponse|RedirectResponse
    {
        $request->merge(['plot_ulang' => true]);

        return $this->approveResult($request, $id, $mapper);
    }

    /**
     * Menyederhanakan hasil resolusi perusahaan untuk payload frontend.
     *
     * @param  array<string, mixed>|null  $resolution  Hasil resolver perusahaan.
     * @return array<string, mixed>|null Data status mapping yang aman dikirim ke Vue.
     */
    private function formatCompanyResolution(?array $resolution): ?array
    {
        if (! $resolution || ! isset($resolution['company'])) {
            return null;
        }

        return [
            'company_id' => $resolution['company']->id,
            'company_name' => $resolution['company']->nama_perusahaan,
            'company_status' => $resolution['company']->status_verifikasi,
            'status' => $resolution['status'],
            'match_type' => $resolution['match_type'],
            'similarity_score' => $resolution['similarity_score'],
            'recommendation' => isset($resolution['recommendation']) && is_array($resolution['recommendation'])
                ? [
                    'company_id' => $resolution['recommendation']['id'] ?? null,
                    'company_name' => $resolution['recommendation']['nama_perusahaan'] ?? null,
                    'similarity_score' => $resolution['recommendation']['similarity_score'] ?? null,
                    'match_reasons' => $resolution['recommendation']['match_reasons'] ?? [],
                ]
                : null,
        ];
    }

    /**
     * Menyusun status mapping perusahaan untuk tabel direktori tanpa membuat record baru.
     *
     * @param  AlumniLinkedinPlot  $plot  Read-model alumni dan hasil scraping terbaru.
     * @return array<string, mixed>|null Status mapping untuk kebutuhan UI review cepat.
     */
    private function formatDirectoryCompanyMapping(AlumniLinkedinPlot $plot): ?array
    {
        $scrapedCompany = trim((string) ($plot->perusahaan_linkedin ?? ''));
        $currentCompany = trim((string) ($plot->perusahaan_biodata ?? ''));

        if ($scrapedCompany === '') {
            return null;
        }

        $normalize = static function (string $value): string {
            $value = strtolower($value);
            $value = (string) preg_replace('/\b(pt|cv|tbk|persero|corp|corporation|inc|ltd|company|indonesia|official)\b/i', ' ', $value);
            $value = (string) preg_replace('/[^a-z0-9]+/i', ' ', $value);

            return trim((string) preg_replace('/\s+/', ' ', $value));
        };

        $sameCompany = $currentCompany !== '' && $normalize($currentCompany) === $normalize($scrapedCompany);
        $masterStatus = $plot->perusahaan?->status_verifikasi;

        return [
            'scraped_name' => $scrapedCompany,
            'master_name' => $currentCompany !== '' ? $currentCompany : null,
            'master_status' => $masterStatus,
            'status' => $sameCompany
                ? ($masterStatus === 'Terverifikasi' ? 'auto_mapped' : 'pending_verification')
                : ($currentCompany !== '' ? 'needs_review' : 'pending_verification'),
            'label' => $sameCompany
                ? ($masterStatus === 'Terverifikasi' ? 'Terpetakan otomatis' : 'Menunggu verifikasi perusahaan')
                : ($currentCompany !== '' ? 'Perlu review perusahaan' : 'Perusahaan baru menunggu verifikasi'),
        ];
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
    public function rejectResult(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $scope = $this->getUserScope($request);

        return DB::transaction(function () use ($id, $scope, $request) {
            $result = LinkedinSyncResult::with('biodata')->lockForUpdate()->findOrFail($id);

            $this->validateAlumniScope($result->biodata, $scope);

            // Validasi status wajib 'pending'
            if ($result->status !== 'pending') {
                return $this->respondToMutation($request, [
                    'success' => false,
                    'message' => "Hasil sinkronisasi ini sudah berstatus '{$result->status}' dan tidak dapat ditolak.",
                ], 422);
            }

            // Data utama TIDAK berubah sama sekali
            $result->update([
                'status' => 'rejected',
                'user_id' => Auth::id(),
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

            // Cadangkan pembaruan status rejected ke snapshot disk
            LinkedinSyncResultSeeder::backupSyncResults();

            return $this->respondToMutation($request, [
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

        // Alur Apify, termasuk mock lokalnya, hanya mendukung staging per alumni.
        if (in_array($provider, ['apify', 'mock_apify'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk sync dinonaktifkan untuk alur Apify demi keamanan biaya dan konsistensi staging. Silakan lakukan sinkronisasi per alumni.',
            ], 400);
        }

        $tahun = $validated['tahun'];

        // Cari alumni pada tahun terpilih
        $alumniQuery = Biodata::where(function ($q) use ($tahun) {
            $q->whereHas('dataAkademik', fn ($sub) => $sub->where('tahun_lulus', $tahun))
                ->orWhereHas('yudisium.dataAkademik', fn ($sub) => $sub->where('tahun_lulus', $tahun));
        });

        if ($scope['role'] === 'admin_prodi') {
            $alumniQuery->where('kode_prodi', $scope['prodiId']);
        } elseif ($scope['role'] === 'admin_fakultas') {
            $prodiIds = Prodi::where('kode_fakultas', $scope['fakultasId'])->pluck('kode_prodi');
            $alumniQuery->whereIn('kode_prodi', $prodiIds);
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
