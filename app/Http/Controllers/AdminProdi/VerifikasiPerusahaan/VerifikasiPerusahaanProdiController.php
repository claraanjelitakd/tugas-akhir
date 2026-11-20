<?php

namespace App\Http\Controllers\AdminProdi\VerifikasiPerusahaan;

use App\Http\Controllers\Controller;
use App\Models\Kabupaten;
use App\Models\LogActivity;
use App\Models\Perusahaan;
use App\Models\Propinsi;
use App\Models\RefNegara;
use App\Models\User;
use App\Services\Perusahaan\PerusahaanVerificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller Verifikasi & Approval Perusahaan untuk Admin Program Studi.
 */
class VerifikasiPerusahaanProdiController extends Controller
{
    public function __construct(
        protected PerusahaanVerificationService $verificationService
    ) {}

    private function findCompanyInScope(int $id): Perusahaan
    {
        $prodiId = Auth::user()?->kode_prodi;
        if (! $prodiId) {
            abort(403, 'Akun Anda belum terhubung dengan program studi.');
        }

        return $this->companiesInScopeQuery($prodiId)->whereKey($id)->firstOrFail();
    }

    private function companiesInScopeQuery(string $prodiId): Builder
    {
        return Perusahaan::query()->where(function (Builder $query) use ($prodiId) {
            $query->where('created_by_kode_prodi', $prodiId)
                ->orWhereHas('biodata', fn (Builder $biodataQuery) => $biodataQuery->where('kode_prodi', $prodiId))
                ->orWhereHas('creator', fn (Builder $creatorQuery) => $creatorQuery->where('kode_prodi', $prodiId));
        });
    }

    /**
     * Menampilkan daftar perusahaan tempat alumni bekerja yang menunggu verifikasi.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $user->load('prodi');
        $prodi = $user->prodi;
        $prodiId = $user->kode_prodi;

        if (! $prodiId) {
            abort(403, 'Akun Anda belum terhubung dengan program studi.');
        }

        $filters = [
            'search' => $request->query('search', ''),
            'scope' => $request->query('scope', 'prodi'), // 'prodi' atau 'all'
            'status' => $request->query('status', 'all'),
            'kode_negara' => $request->query('kode_negara', ''),
            'kode_provinsi' => $request->query('kode_provinsi', ''),
            'kode_kabupaten' => $request->query('kode_kabupaten', ''),
            'per_page' => (int) $request->query('per_page', 10),
        ];

        $companies = $this->verificationService->getPendingForProdi($prodiId, $filters);

        $propinsis = Propinsi::select('kode_provinsi', 'nama_provinsi')->orderBy('nama_provinsi')->get();
        $kabupatens = ! empty($filters['kode_provinsi'])
            ? Kabupaten::where('kode_provinsi', $filters['kode_provinsi'])->select('kode_kabupaten', 'kode_provinsi', 'nama_kabupaten')->orderBy('nama_kabupaten')->get()
            : [];
        $negaras = RefNegara::select('kode_negara', 'nama_negara')->orderBy('nama_negara')->get();
        // Fix: filterNegaras sebelumnya tidak dikirim, menyebabkan dropdown filter negara kosong
        $distinctNegaras = $this->companiesInScopeQuery($prodiId)
            ->whereNotNull('kode_negara')
            ->where('kode_negara', '!=', '')
            ->distinct()
            ->pluck('kode_negara')
            ->filter()
            ->sort()
            ->values();

        return Inertia::render('SuperAdmin/Perusahaan/Index', [
            'user' => $user,
            'role' => 'admin_prodi',
            'basePath' => '/prodi/perusahaan',
            'dashboardPath' => '/prodi/dashboard',
            'canCreate' => true,
            'prodi' => $prodi,
            'prodis' => [$prodi],
            'companies' => $companies,
            'propinsis' => $propinsis,
            'kabupatens' => $kabupatens,
            'negaras' => $negaras,
            'filterNegaras' => $distinctNegaras,
            'filters' => $filters,
            'stats' => $this->buildScopedStats($prodiId),
        ]);
    }

    /**
     * Menambahkan institusi / perusahaan baru langsung ke master data terverifikasi program studi.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $prodiId = $user->kode_prodi;

        if (! $prodiId) {
            abort(403, 'Akun Anda belum terhubung dengan program studi.');
        }

        $jenisLokasi = $request->input('jenis_lokasi', 'Dalam Negeri');

        $rules = [
            'nama_perusahaan' => 'required|string|max:255',
            'jenis_lokasi' => 'required|string|in:Dalam Negeri,Luar Negeri',
            'kode_negara' => $jenisLokasi === 'Luar Negeri' ? 'required|string|size:2|not_in:ID|exists:ref_negara,kode_negara' : 'nullable|size:2|exists:ref_negara,kode_negara',
            'kode_provinsi' => $jenisLokasi === 'Dalam Negeri' ? 'nullable|exists:propinsi,kode_provinsi' : 'nullable',
            'kode_kabupaten' => $jenisLokasi === 'Dalam Negeri' ? 'nullable|exists:kabupaten,kode_kabupaten' : 'nullable',
            'alamat' => 'nullable|string|max:500',
            'kode_pos' => 'nullable|string|max:15',
            'skala' => 'nullable|string|in:Lokal,Nasional,Internasional',
            'jenis_perusahaan' => 'nullable|string|max:100',
            'jenis_perusahaan_lainnya' => 'nullable|string|max:150',
            'sektor' => 'nullable|string|max:100',
            'bentuk_perusahaan' => 'nullable|string|max:60',
            'jumlah_pegawai' => 'nullable|string|max:60',
            'homepage' => 'nullable|string|max:255',
            'no_telp_fax' => 'nullable|string|max:100',
        ];

        $messages = [
            'nama_perusahaan.required' => 'Nama perusahaan / institusi wajib diisi.',
            'kode_negara.required' => 'Kode negara wajib dipilih untuk instansi luar negeri.',
        ];

        $validated = $request->validate($rules, $messages);

        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => $validated['nama_perusahaan'],
            'jenis_lokasi' => $jenisLokasi,
            'kode_negara' => $jenisLokasi === 'Luar Negeri' ? strtoupper($validated['kode_negara']) : 'ID',
            'kode_provinsi' => $jenisLokasi === 'Dalam Negeri' ? ($validated['kode_provinsi'] ?? null) : null,
            'kode_kabupaten' => $jenisLokasi === 'Dalam Negeri' ? ($validated['kode_kabupaten'] ?? null) : null,
            'alamat' => $validated['alamat'] ?? null,
            'kode_pos' => $validated['kode_pos'] ?? null,
            'skala' => $validated['skala'] ?? ($jenisLokasi === 'Luar Negeri' ? 'Internasional' : 'Nasional'),
            'jenis_perusahaan' => $validated['jenis_perusahaan'] ?? null,
            'jenis_perusahaan_lainnya' => $validated['jenis_perusahaan_lainnya'] ?? null,
            'sektor' => $validated['sektor'] ?? null,
            'bentuk_perusahaan' => $validated['bentuk_perusahaan'] ?? null,
            'jumlah_pegawai' => $validated['jumlah_pegawai'] ?? null,
            'homepage' => $validated['homepage'] ?? null,
            'no_telp_fax' => $validated['no_telp_fax'] ?? null,
            'status_verifikasi' => 'Terverifikasi',
            'created_by_user_id' => $user->id,
            'created_by_kode_prodi' => $prodiId,
        ]);

        LogActivity::record(
            'CREATE_PERUSAHAAN_PRODI',
            "Admin Program Studi menambahkan perusahaan terverifikasi: {$perusahaan->nama_perusahaan} (ID: {$perusahaan->id})",
            null,
            null,
            $perusahaan->toArray()
        );

        return redirect()->back()->with('success', "Institusi \"{$perusahaan->nama_perusahaan}\" berhasil ditambahkan ke master data terverifikasi.");
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
            'kode_provinsi' => 'nullable|exists:propinsi,kode_provinsi',
            'kode_kabupaten' => 'nullable|exists:kabupaten,kode_kabupaten',
            'kode_pos' => 'nullable|string|max:15',
            'sektor' => 'nullable|string|max:100',
            'skala' => 'nullable|string|in:Lokal,Nasional,Internasional',
            'jenis_perusahaan' => 'nullable|string|max:100',
            'jenis_perusahaan_lainnya' => 'nullable|string|max:150',
            // Fix: 4 field berikut sebelumnya tidak divalidasi, menyebabkan data hilang saat edit perusahaan
            'bentuk_perusahaan' => 'nullable|string|max:60',
            'jumlah_pegawai' => 'nullable|string|max:60',
            'homepage' => 'nullable|string|max:255',
            'no_telp_fax' => 'nullable|string|max:100',
            'jenis_lokasi' => 'required|string|in:Dalam Negeri,Luar Negeri',
            'kode_negara' => 'nullable|string|size:2|exists:ref_negara,kode_negara',
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

    /**
     * Endpoint API asinkron untuk mengambil rekomendasi kemiripan master perusahaan terverifikasi on-demand.
     */
    public function recommendations(int $id): JsonResponse
    {
        $company = $this->findCompanyInScope($id);
        $results = $this->verificationService->getSimilarRecommendations($company, 5);

        return response()->json($results);
    }

    /**
     * Menghapus data perusahaan dalam lingkup program studi yang tidak memiliki relasi alumni.
     */
    public function destroy(int $id): RedirectResponse
    {
        $company = $this->findCompanyInScope($id);
        $company->loadCount(['biodata', 'evaluasiAtasan']);

        if ($company->biodata_count > 0) {
            return redirect()->back()->withErrors([
                'error' => "Perusahaan \"{$company->nama_perusahaan}\" tidak dapat dihapus karena masih terhubung dengan {$company->biodata_count} data alumni.",
            ]);
        }

        if ($company->evaluasi_atasan_count > 0) {
            return redirect()->back()->withErrors([
                'error' => "Perusahaan \"{$company->nama_perusahaan}\" tidak dapat dihapus karena tercatat pada {$company->evaluasi_atasan_count} data evaluasi atasan.",
            ]);
        }

        $oldData = $company->toArray();
        $nama = $company->nama_perusahaan;
        $company->delete();

        LogActivity::record(
            'DELETE_PERUSAHAAN_PRODI',
            "Admin Program Studi menghapus perusahaan usulan: {$nama} (ID: {$id})",
            null,
            $oldData,
            null
        );

        return redirect()->back()->with('success', "Perusahaan \"{$nama}\" berhasil dihapus.");
    }

    /**
     * Optimisasi: menggabungkan 4 query COUNT terpisah menjadi 1 query aggregate dengan CASE WHEN.
     *
     * @return array{total_all: int, total_pending_all: int, total_verified: int, total_rejected: int}
     */
    private function buildScopedStats(string $prodiId): array
    {
        $counts = $this->companiesInScopeQuery($prodiId)
            ->selectRaw('COUNT(*) as total_all')
            ->selectRaw("SUM(CASE WHEN status_verifikasi = 'Menunggu Verifikasi' THEN 1 ELSE 0 END) as total_pending_all")
            ->selectRaw("SUM(CASE WHEN status_verifikasi = 'Terverifikasi' THEN 1 ELSE 0 END) as total_verified")
            ->selectRaw("SUM(CASE WHEN status_verifikasi = 'Ditolak' THEN 1 ELSE 0 END) as total_rejected")
            ->first();

        return [
            'total_all' => (int) ($counts->total_all ?? 0),
            'total_pending_all' => (int) ($counts->total_pending_all ?? 0),
            'total_verified' => (int) ($counts->total_verified ?? 0),
            'total_rejected' => (int) ($counts->total_rejected ?? 0),
        ];
    }
}
