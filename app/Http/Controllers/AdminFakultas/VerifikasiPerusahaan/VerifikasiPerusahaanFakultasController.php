<?php

namespace App\Http\Controllers\AdminFakultas\VerifikasiPerusahaan;

use App\Http\Controllers\Controller;
use App\Models\Kabupaten;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Services\Perusahaan\PerusahaanVerificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller Verifikasi & Approval Perusahaan untuk Admin Fakultas.
 */
class VerifikasiPerusahaanFakultasController extends Controller
{
    public function __construct(
        protected PerusahaanVerificationService $verificationService
    ) {}

    private function findCompanyInScope(int $id): Perusahaan
    {
        $fakultasId = Auth::user()?->fakultas_id;
        if (! $fakultasId) {
            abort(403, 'Akun Anda belum terhubung dengan fakultas.');
        }

        $prodiIds = Prodi::where('fakultas_id', $fakultasId)->pluck('id')->all();

        return $this->companiesInScopeQuery($prodiIds)->whereKey($id)->firstOrFail();
    }

    /**
     * @param  array<int, int>  $prodiIds
     */
    private function companiesInScopeQuery(array $prodiIds): Builder
    {
        return Perusahaan::query()->where(function (Builder $query) use ($prodiIds) {
            $query->whereIn('created_by_prodi_id', $prodiIds)
                ->orWhereHas('biodata', fn (Builder $biodataQuery) => $biodataQuery->whereIn('prodi_id', $prodiIds))
                ->orWhereHas('creator', fn (Builder $creatorQuery) => $creatorQuery->whereIn('prodi_id', $prodiIds));
        });
    }

    /**
     * Menampilkan daftar perusahaan tempat alumni bekerja yang menunggu verifikasi di lingkup Fakultas.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user()->load('fakultas');
        $fakultasId = $user->fakultas_id;

        $fakultas = $user->fakultas;

        if (! $fakultasId) {
            abort(403, 'Akun Anda belum terhubung dengan fakultas.');
        }

        $prodisFakultas = Prodi::where('fakultas_id', $fakultasId)->orderBy('nama_prodi')->get();

        $filters = [
            'search' => $request->query('search', ''),
            'prodi_id' => $request->query('prodi_id', ''),
            'status' => $request->query('status', 'all'),
            'per_page' => $request->query('per_page', 10),
        ];

        $companies = $this->verificationService->getPendingForFakultas($fakultasId, $filters);

        // Lampirkan data rekomendasi kemiripan perusahaan terverifikasi untuk membantu konsolidasi/penggabungan data
        $companies->getCollection()->transform(function ($company) {
            $company->recommendations = $this->verificationService->getSimilarRecommendations($company, 5);

            return $company;
        });

        $propinsis = Propinsi::select('id', 'nama_provinsi')->orderBy('nama_provinsi')->get();
        $kabupatens = Kabupaten::select('id', 'propinsi_id', 'nama_kabupaten')->orderBy('nama_kabupaten')->get();

        $prodiIds = $prodisFakultas->pluck('id')->all();

        return Inertia::render('SuperAdmin/Perusahaan/Index', [
            'user' => $user,
            'role' => 'admin_fakultas',
            'basePath' => '/fakultas/perusahaan',
            'dashboardPath' => '/fakultas/dashboard',
            'canCreate' => false,
            'fakultas' => $fakultas,
            'prodis' => $prodisFakultas,
            'companies' => $companies,
            'propinsis' => $propinsis,
            'kabupatens' => $kabupatens,
            'negaras' => [],
            'filters' => $filters,
            'stats' => [
                'total_all' => $this->companiesInScopeQuery($prodiIds)->count(),
                'total_pending_all' => $this->companiesInScopeQuery($prodiIds)->where('status_verifikasi', 'Menunggu Verifikasi')->count(),
                'total_verified' => $this->companiesInScopeQuery($prodiIds)->where('status_verifikasi', 'Terverifikasi')->count(),
                'total_rejected' => $this->companiesInScopeQuery($prodiIds)->where('status_verifikasi', 'Ditolak')->count(),
            ],
        ]);
    }

    /**
     * Setujui perusahaan baru yang diinputkan oleh alumni (Verifikasi Langsung).
     */
    public function verify(int $id): RedirectResponse
    {
        $company = $this->findCompanyInScope($id);

        $this->verificationService->verifyDirectly($company);

        return redirect()->back()->with('success', "Perusahaan \"{$company->nama_perusahaan}\" berhasil diverifikasi menjadi master data.");
    }

    /**
     * Mengganti perusahaan pengajuan alumni dengan master perusahaan terverifikasi (Auto Replace).
     */
    public function replace(Request $request, int $id): RedirectResponse
    {
        $pendingCompany = $this->findCompanyInScope($id);

        $validated = $request->validate([
            'target_company_id' => 'required|exists:perusahaan,id',
        ]);

        $verifiedCompany = Perusahaan::where('id', $validated['target_company_id'])
            ->where('status_verifikasi', 'Terverifikasi')
            ->firstOrFail();

        $result = $this->verificationService->replaceCompany($pendingCompany, $verifiedCompany);

        return redirect()->back()->with('success', "Perusahaan berhasil disetujui & digantikan dengan \"{$result['replaced_name']}\". Data {$result['affected_count']} alumni otomatis disesuaikan.");
    }

    /**
     * Mengedit detail atribut perusahaan oleh admin lalu langsung diverifikasi.
     */
    public function updateAndVerify(Request $request, int $id): RedirectResponse
    {
        $company = $this->findCompanyInScope($id);

        $validated = $request->validate([
            'nama_perusahaan' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'propinsi_id' => 'nullable|exists:propinsi,id',
            'kabupaten_id' => 'nullable|exists:kabupaten,id',
            'kode_pos' => 'nullable|string|max:15',
            'sektor' => 'nullable|string|max:100',
            'skala' => 'nullable|string|in:Lokal,Nasional,Internasional',
            'jenis_perusahaan' => 'nullable|string|max:100',
            'jenis_perusahaan_lainnya' => 'nullable|string|max:150',
            'jenis_lokasi' => 'required|string|in:Dalam Negeri,Luar Negeri',
            'negara' => 'nullable|string|max:100',
        ]);

        $this->verificationService->updateAndVerify($company, $validated, true);

        return redirect()->back()->with('success', "Data perusahaan \"{$company->nama_perusahaan}\" berhasil diperbarui dan diverifikasi.");
    }

    /**
     * Menolak pengajuan perusahaan.
     */
    public function reject(Request $request, int $id): RedirectResponse
    {
        $company = $this->findCompanyInScope($id);

        $this->verificationService->rejectCompany($company);

        return redirect()->back()->with('success', "Pengajuan perusahaan \"{$company->nama_perusahaan}\" telah ditolak.");
    }

    /**
     * Endpoint API pencarian master perusahaan terverifikasi untuk modal penggantian.
     */
    public function searchVerified(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $results = $this->verificationService->searchVerified($query, 12);

        return response()->json($results);
    }
}
