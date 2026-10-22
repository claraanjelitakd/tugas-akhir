<?php

namespace App\Http\Controllers\SuperAdmin\Perusahaan;

use App\Http\Controllers\Controller;
use App\Models\Kabupaten;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\RefNegara;
use App\Services\Perusahaan\PerusahaanVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller Verifikasi & Approval Perusahaan untuk Super Administrator (Otoritas Tertinggi Segenap Prodi).
 */
class VerifikasiPerusahaanSuperAdminController extends Controller
{
    public function __construct(
        protected PerusahaanVerificationService $verificationService
    ) {}

    /**
     * Menampilkan daftar pengajuan perusahaan alumni untuk disetujui/diverifikasi oleh Superadmin.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $filters = [
            'search' => $request->query('search', ''),
            'scope' => $request->query('scope', 'all'), // 'all' atau 'prodi'
            'prodi_id' => $request->query('prodi_id', ''),
            'status' => $request->query('status', 'all'),
            'per_page' => (int) $request->query('per_page', 10),
        ];

        $prodis = Prodi::select('id', 'nama_prodi', 'kode_prodi')
            ->orderBy('nama_prodi')
            ->get();

        if (! empty($filters['prodi_id'])) {
            $prodiId = (int) $filters['prodi_id'];
            $companies = $this->verificationService->getPendingForProdi($prodiId, $filters);
        } else {
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

            if ($filters['status'] !== 'all') {
                $query->where('status_verifikasi', $filters['status']);
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

            $companies = $query->latest('updated_at')->paginate($filters['per_page'])->withQueryString();
        }

        // Lampirkan data rekomendasi kemiripan perusahaan terverifikasi untuk membantu konsolidasi/penggabungan data.
        // Catatan: getSimilarRecommendations() menggunakan algoritma Multi-level String Similarity (bukan hanya trim):
        // 1) Text Preprocessing & Stopword stripping (PT/CV), 2) Exact match, 3) Acronym matching (misal BCA),
        // 4) Substring & Token overlap, 5) Levenshtein/similar_text, 6) Geographic bonus (kota & provinsi).
        $companies->getCollection()->transform(function ($company) {
            if ($company->biodata->isEmpty() && $company->creator && $company->creator->biodata) {
                $company->setRelation('biodata', collect([$company->creator->biodata]));
                if (($company->biodata_count ?? 0) === 0) {
                    $company->biodata_count = 1;
                }
            }

            $company->recommendations = $this->verificationService->getSimilarRecommendations($company, 5);

            return $company;
        });

        $propinsis = Propinsi::select('id', 'nama_provinsi')->orderBy('nama_provinsi')->get();
        $kabupatens = Kabupaten::select('id', 'propinsi_id', 'nama_kabupaten')->orderBy('nama_kabupaten')->get();
        $negaras = RefNegara::select('id', 'nama_negara')->orderBy('nama_negara')->get();

        return Inertia::render('SuperAdmin/Perusahaan/Index', [
            'user' => $user,
            'role' => 'superadmin',
            'basePath' => '/superadmin/perusahaan',
            'dashboardPath' => '/superadmin/dashboard',
            'canCreate' => true,
            'prodis' => $prodis,
            'companies' => $companies,
            'propinsis' => $propinsis,
            'kabupatens' => $kabupatens,
            'negaras' => $negaras,
            'filters' => $filters,
            'stats' => [
                'total_all' => Perusahaan::count(),
                'total_pending_all' => Perusahaan::where('status_verifikasi', 'Menunggu Verifikasi')->count(),
                'total_verified' => Perusahaan::where('status_verifikasi', 'Terverifikasi')->count(),
                'total_rejected' => Perusahaan::where('status_verifikasi', 'Ditolak')->count(),
            ],
        ]);
    }

    /**
     * Menambahkan institusi / perusahaan baru langsung ke master data terverifikasi universitas.
     */
    public function store(Request $request): RedirectResponse
    {
        $jenisLokasi = $request->input('jenis_lokasi', 'Dalam Negeri');

        $rules = [
            'nama_perusahaan' => 'required|string|max:255',
            'jenis_lokasi' => 'required|string|in:Dalam Negeri,Luar Negeri',
            'negara' => $jenisLokasi === 'Luar Negeri' ? 'required|string|max:100' : 'nullable|string|max:100',
            'propinsi_id' => $jenisLokasi === 'Dalam Negeri' ? 'nullable|exists:propinsi,id' : 'nullable',
            'kabupaten_id' => $jenisLokasi === 'Dalam Negeri' ? 'nullable|exists:kabupaten,id' : 'nullable',
            'alamat' => 'nullable|string|max:500',
            'kode_pos' => 'nullable|string|max:15',
            'skala' => 'nullable|string|in:Lokal,Nasional,Internasional',
            'jenis_perusahaan' => 'nullable|string|max:100',
            'jenis_perusahaan_lainnya' => 'nullable|string|max:150',
            'sektor' => 'nullable|string|max:100',
            'homepage' => 'nullable|string|max:255',
            'no_telp_fax' => 'nullable|string|max:100',
        ];

        $messages = [
            'nama_perusahaan.required' => 'Nama perusahaan / institusi wajib diisi.',
            'negara.required' => 'Nama negara wajib dipilih untuk instansi luar negeri.',
        ];

        $validated = $request->validate($rules, $messages);

        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => $validated['nama_perusahaan'],
            'jenis_lokasi' => $jenisLokasi,
            'negara' => $jenisLokasi === 'Luar Negeri' ? $validated['negara'] : 'Indonesia',
            'propinsi_id' => $jenisLokasi === 'Dalam Negeri' ? ($validated['propinsi_id'] ?? null) : null,
            'kabupaten_id' => $jenisLokasi === 'Dalam Negeri' ? ($validated['kabupaten_id'] ?? null) : null,
            'alamat' => $validated['alamat'] ?? null,
            'kode_pos' => $validated['kode_pos'] ?? null,
            'skala' => $validated['skala'] ?? ($jenisLokasi === 'Luar Negeri' ? 'Internasional' : 'Nasional'),
            'jenis_perusahaan' => $validated['jenis_perusahaan'] ?? null,
            'jenis_perusahaan_lainnya' => $validated['jenis_perusahaan_lainnya'] ?? null,
            'sektor' => $validated['sektor'] ?? null,
            'homepage' => $validated['homepage'] ?? null,
            'no_telp_fax' => $validated['no_telp_fax'] ?? null,
            'status_verifikasi' => 'Terverifikasi',
            'created_by_user_id' => Auth::id(),
        ]);

        return redirect()->back()->with('success', "Institusi \"{$perusahaan->nama_perusahaan}\" berhasil ditambahkan ke master data resmi universitas.");
    }

    /**
     * Setujui perusahaan baru yang diinputkan oleh alumni (Verifikasi Langsung).
     */
    public function verify(int $id): RedirectResponse
    {
        $company = Perusahaan::findOrFail($id);

        $this->verificationService->verifyDirectly($company);

        return redirect()->back()->with('success', "Perusahaan \"{$company->nama_perusahaan}\" berhasil diverifikasi menjadi master data.");
    }

    /**
     * Mengganti perusahaan pengajuan alumni dengan master perusahaan terverifikasi (Auto Replace).
     */
    public function replace(Request $request, int $id): RedirectResponse
    {
        $pendingCompany = Perusahaan::findOrFail($id);

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
     * Mengedit detail atribut perusahaan oleh Superadmin lalu langsung diverifikasi.
     */
    public function updateAndVerify(Request $request, int $id): RedirectResponse
    {
        $company = Perusahaan::findOrFail($id);

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
        $company = Perusahaan::findOrFail($id);
        $alasan = $request->input('alasan');

        $this->verificationService->rejectCompany($company, $alasan);

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
