<?php

namespace App\Http\Controllers\SuperAdmin\MasterWilayah;

use App\Http\Controllers\Controller;
use App\Models\Kabupaten;
use App\Models\LogActivity;
use App\Models\Perusahaan;
use App\Models\Propinsi;
use App\Models\RefNegara;
use App\Models\Ump;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Class MasterWilayahController
 *
 * Mengelola Master Data Wilayah Administrasi (Provinsi & Kabupaten/Kota)
 * serta ketetapan Upah Minimum Provinsi (UMP) per daerah dan tahun.
 */
class MasterWilayahController extends Controller
{
    /**
     * Tampilkan Halaman Master Data Wilayah & UMP
     */
    public function index(Request $request): Response
    {
        $activeTab = $request->input('tab', 'provinsi');
        $search = $request->input('search');
        $selectedYear = (int) $request->input('tahun', 2026);
        $propinsiFilterId = $request->input('propinsi_id');
        $benuaFilter = $request->input('benua');
        $perPage = (int) $request->input('per_page', 15);

        // Ambil daftar tahun unik yang tersedia di tabel UMP
        $availableYears = Ump::select('tahun')
            ->distinct()
            ->orderBy('tahun', 'desc')
            ->pluck('tahun')
            ->toArray();

        if (empty($availableYears)) {
            $availableYears = [2026];
        }

        if (! in_array($selectedYear, $availableYears, true)) {
            $availableYears[] = $selectedYear;
            rsort($availableYears);
        }

        // Data Ringkasan (Statistik)
        $totalProvinsi = Propinsi::count();
        $totalKabupaten = Kabupaten::count();
        $umpByYear = Ump::where('tahun', $selectedYear)->get();
        $totalUmpConfigured = $umpByYear->count();
        $avgUmp = $umpByYear->avg('besaran') ?: 0;

        $minUmpRecord = $umpByYear->sortBy('besaran')->first();
        $maxUmpRecord = $umpByYear->sortByDesc('besaran')->first();

        $minUmpProvince = $minUmpRecord ? Propinsi::where('kode_provinsi', $minUmpRecord->kode_provinsi)->value('nama_provinsi') : '-';
        $maxUmpProvince = $maxUmpRecord ? Propinsi::where('kode_provinsi', $maxUmpRecord->kode_provinsi)->value('nama_provinsi') : '-';

        $stats = [
            'total_provinsi' => $totalProvinsi,
            'total_kabupaten' => $totalKabupaten,
            'total_negara' => RefNegara::count(),
            'total_ump_configured' => $totalUmpConfigured,
            'avg_ump' => round($avgUmp, 2),
            'min_ump' => [
                'besaran' => $minUmpRecord ? (float) $minUmpRecord->besaran : 0,
                'provinsi' => $minUmpProvince,
            ],
            'max_ump' => [
                'besaran' => $maxUmpRecord ? (float) $maxUmpRecord->besaran : 0,
                'provinsi' => $maxUmpProvince,
            ],
            'selected_year' => $selectedYear,
        ];

        // Daftar seluruh provinsi untuk dropdown modal/filter
        $allProvinces = Propinsi::select('id', 'kode_provinsi', 'nama_provinsi')
            ->orderBy('nama_provinsi', 'asc')
            ->get();

        // Query Provinsi murni untuk master data & jumlah kabupaten
        $provinsiQuery = Propinsi::withCount('kabupaten');

        if ($search && $activeTab === 'provinsi') {
            $provinsiQuery->where(function ($q) use ($search) {
                $q->where('nama_provinsi', 'like', "%{$search}%")
                    ->orWhere('kode_provinsi', 'like', "%{$search}%");
            });
        }

        $provinces = $provinsiQuery->orderBy('kode_provinsi', 'asc')->get()->map(function ($prov) {
            return [
                'id' => $prov->id,
                'kode_provinsi' => $prov->kode_provinsi,
                'nama_provinsi' => $prov->nama_provinsi,
                'total_kabupaten' => $prov->kabupaten_count,
            ];
        });

        // Query Kabupaten murni untuk master data dengan relasi Provinsi Induk
        $kabupatenQuery = Kabupaten::with('propinsi');

        if ($propinsiFilterId) {
            $kabupatenQuery->where('propinsi_id', $propinsiFilterId);
        }

        if ($search && $activeTab === 'kabupaten') {
            $kabupatenQuery->where(function ($q) use ($search) {
                $q->where('nama_kabupaten', 'like', "%{$search}%")
                    ->orWhere('kode_kabupaten', 'like', "%{$search}%")
                    ->orWhereHas('propinsi', function ($pq) use ($search) {
                        $pq->where('nama_provinsi', 'like', "%{$search}%");
                    });
            });
        }

        $kabupatens = $kabupatenQuery->orderBy('kode_kabupaten', 'asc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function ($kab) {
                return [
                    'id' => $kab->id,
                    'propinsi_id' => $kab->propinsi_id,
                    'kode_kabupaten' => $kab->kode_kabupaten,
                    'nama_kabupaten' => $kab->nama_kabupaten,
                    'nama_provinsi' => $kab->propinsi?->nama_provinsi ?? '-',
                    'kode_provinsi' => $kab->propinsi?->kode_provinsi ?? '-',
                ];
            });

        // Pengelolaan UMP khusus per Provinsi untuk tab 'ump'
        $allUmps = [];
        if ($activeTab === 'ump') {
            $propinsiUmpQuery = Propinsi::with(['umps' => function ($q) use ($selectedYear) {
                $q->where('tahun', $selectedYear);
            }]);

            if ($search) {
                $propinsiUmpQuery->where(function ($q) use ($search) {
                    $q->where('nama_provinsi', 'like', "%{$search}%")
                        ->orWhere('kode_provinsi', 'like', "%{$search}%");
                });
            }

            $allUmps = $propinsiUmpQuery->orderBy('kode_provinsi', 'asc')
                ->get()
                ->map(function ($item) use ($selectedYear) {
                    $activeUmp = $item->umps->first();

                    return [
                        'id' => $activeUmp?->id,
                        'provinsi_id' => $item->id,
                        'kode_provinsi' => $item->kode_provinsi,
                        'nama_provinsi' => $item->nama_provinsi,
                        'tahun' => $activeUmp ? $activeUmp->tahun : $selectedYear,
                        'besaran' => $activeUmp ? (float) $activeUmp->besaran : null,
                        'catatan' => $activeUmp?->catatan,
                        'has_ump' => (bool) $activeUmp,
                        'updated_at' => $activeUmp?->updated_at ? $activeUmp->updated_at->format('d/m/Y H:i') : '-',
                    ];
                });
        }

        // Query Master Negara untuk tab 'negara'
        $negaras = null;
        if ($activeTab === 'negara') {
            $negaraQuery = RefNegara::query();

            if ($benuaFilter) {
                $negaraQuery->where('benua', $benuaFilter);
            }

            if ($search) {
                $negaraQuery->where(function ($q) use ($search) {
                    $q->where('nama_negara', 'like', "%{$search}%")
                        ->orWhere('kode_iso2', 'like', "%{$search}%")
                        ->orWhere('kode_iso3', 'like', "%{$search}%")
                        ->orWhere('nama_resmi', 'like', "%{$search}%")
                        ->orWhere('ibu_kota', 'like', "%{$search}%")
                        ->orWhere('benua', 'like', "%{$search}%");
                });
            }

            $negaras = $negaraQuery->orderBy('nama_negara', 'asc')
                ->paginate($perPage)
                ->withQueryString();
        }

        // Daftar unik benua untuk opsi filter dropdown
        $allContinents = RefNegara::whereNotNull('benua')
            ->where('benua', '!=', '')
            ->distinct()
            ->orderBy('benua', 'asc')
            ->pluck('benua')
            ->toArray();

        return Inertia::render('SuperAdmin/MasterWilayah/Index', [
            'active_tab' => $activeTab,
            'stats' => $stats,
            'available_years' => $availableYears,
            'all_provinces' => $allProvinces,
            'all_continents' => $allContinents,
            'provinces' => $provinces,
            'kabupatens' => $kabupatens,
            'negaras' => $negaras ?: ['data' => [], 'links' => []],
            'umps' => $allUmps,
            'filters' => [
                'search' => $search ?: '',
                'tahun' => $selectedYear,
                'propinsi_id' => $propinsiFilterId ?: '',
                'benua' => $benuaFilter ?: '',
                'per_page' => $perPage,
                'tab' => $activeTab,
            ],
        ]);
    }

    /**
     * Tambah Provinsi Baru
     */
    public function storeProvinsi(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_provinsi' => 'required|string|max:10|unique:propinsi,kode_provinsi',
            'nama_provinsi' => 'required|string|max:255',
            'besaran_ump' => 'nullable|numeric|min:0',
            'tahun_ump' => 'nullable|integer|min:2000|max:2100',
            'catatan_ump' => 'nullable|string|max:255',
        ]);

        $provinsi = Propinsi::create([
            'kode_provinsi' => $validated['kode_provinsi'],
            'nama_provinsi' => strtoupper(trim($validated['nama_provinsi'])),
        ]);

        if (! empty($validated['besaran_ump'])) {
            Ump::create([
                'kode_provinsi' => $provinsi->kode_provinsi,
                'tahun' => $validated['tahun_ump'] ?: 2026,
                'besaran' => $validated['besaran_ump'],
                'catatan' => $validated['catatan_ump'] ?? null,
            ]);
        }

        LogActivity::record(
            'CREATE_PROVINSI',
            "Super Admin menambahkan provinsi baru: {$provinsi->nama_provinsi} (Kode: {$provinsi->kode_provinsi})",
            $provinsi,
            null,
            $provinsi->toArray()
        );

        return back()->with('success', "Provinsi {$provinsi->nama_provinsi} berhasil ditambahkan.");
    }

    /**
     * Perbarui Data Provinsi
     */
    public function updateProvinsi(Request $request, int $id): RedirectResponse
    {
        $provinsi = Propinsi::findOrFail($id);

        $validated = $request->validate([
            'kode_provinsi' => 'required|string|max:10|unique:propinsi,kode_provinsi,'.$provinsi->id,
            'nama_provinsi' => 'required|string|max:255',
        ]);

        $oldValues = $provinsi->only(['kode_provinsi', 'nama_provinsi']);
        $oldKode = $provinsi->kode_provinsi;
        $newKode = $validated['kode_provinsi'];

        // Jika kode_provinsi berubah, sesuaikan data UMP
        if ($oldKode !== $newKode) {
            Ump::where('kode_provinsi', $oldKode)->update(['kode_provinsi' => $newKode]);
        }

        $provinsi->update([
            'kode_provinsi' => $newKode,
            'nama_provinsi' => strtoupper(trim($validated['nama_provinsi'])),
        ]);

        LogActivity::record(
            'UPDATE_PROVINSI',
            "Super Admin memperbarui provinsi {$provinsi->nama_provinsi} (Kode: {$provinsi->kode_provinsi})",
            $provinsi,
            $oldValues,
            $provinsi->only(['kode_provinsi', 'nama_provinsi'])
        );

        return back()->with('success', "Data provinsi {$provinsi->nama_provinsi} berhasil diperbarui.");
    }

    /**
     * Hapus Provinsi
     */
    public function destroyProvinsi(int $id): RedirectResponse
    {
        $provinsi = Propinsi::findOrFail($id);

        // Cek keterkaitan dengan kabupaten
        $kabupatenCount = Kabupaten::where('propinsi_id', $provinsi->id)->count();
        if ($kabupatenCount > 0) {
            return back()->withErrors([
                'error' => "Provinsi {$provinsi->nama_provinsi} tidak dapat dihapus karena masih memuat {$kabupatenCount} kabupaten/kota.",
            ]);
        }

        // Cek keterkaitan dengan perusahaan
        if ($provinsi->perusahaan()->exists()) {
            return back()->withErrors([
                'error' => "Provinsi {$provinsi->nama_provinsi} tidak dapat dihapus karena terdaftar pada data perusahaan.",
            ]);
        }

        $oldData = $provinsi->toArray();

        // Hapus data UMP terkait
        Ump::where('kode_provinsi', $provinsi->kode_provinsi)->delete();
        $provinsi->delete();

        LogActivity::record(
            'DELETE_PROVINSI',
            "Super Admin menghapus provinsi: {$provinsi->nama_provinsi} (Kode: {$provinsi->kode_provinsi})",
            null,
            $oldData,
            null
        );

        return back()->with('success', "Provinsi {$provinsi->nama_provinsi} berhasil dihapus.");
    }

    /**
     * Tambah Kabupaten/Kota Baru
     */
    public function storeKabupaten(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'propinsi_id' => 'required|exists:propinsi,id',
            'kode_kabupaten' => 'required|string|max:20|unique:kabupaten,kode_kabupaten',
            'nama_kabupaten' => 'required|string|max:255',
        ]);

        $kabupaten = Kabupaten::create([
            'propinsi_id' => $validated['propinsi_id'],
            'kode_kabupaten' => $validated['kode_kabupaten'],
            'nama_kabupaten' => strtoupper(trim($validated['nama_kabupaten'])),
        ]);

        // Bersihkan cache kabupaten per provinsi agar dropdown selalu mutakhir
        Cache::forget("wilayah:kabupaten:prov_{$validated['propinsi_id']}");

        LogActivity::record(
            'CREATE_KABUPATEN',
            "Super Admin menambahkan kabupaten/kota baru: {$kabupaten->nama_kabupaten} (Kode: {$kabupaten->kode_kabupaten})",
            $kabupaten,
            null,
            $kabupaten->toArray()
        );

        return back()->with('success', "Kabupaten/Kota {$kabupaten->nama_kabupaten} berhasil ditambahkan.");
    }

    /**
     * Perbarui Data Kabupaten/Kota
     */
    public function updateKabupaten(Request $request, int $id): RedirectResponse
    {
        $kabupaten = Kabupaten::findOrFail($id);

        $validated = $request->validate([
            'propinsi_id' => 'required|exists:propinsi,id',
            'kode_kabupaten' => 'required|string|max:20|unique:kabupaten,kode_kabupaten,'.$kabupaten->id,
            'nama_kabupaten' => 'required|string|max:255',
        ]);

        $oldValues = $kabupaten->only(['propinsi_id', 'kode_kabupaten', 'nama_kabupaten']);

        $kabupaten->update([
            'propinsi_id' => $validated['propinsi_id'],
            'kode_kabupaten' => $validated['kode_kabupaten'],
            'nama_kabupaten' => strtoupper(trim($validated['nama_kabupaten'])),
        ]);

        // Bersihkan cache kabupaten untuk provinsi lama dan provinsi baru
        Cache::forget("wilayah:kabupaten:prov_{$oldValues['propinsi_id']}");
        Cache::forget("wilayah:kabupaten:prov_{$validated['propinsi_id']}");

        LogActivity::record(
            'UPDATE_KABUPATEN',
            "Super Admin memperbarui kabupaten/kota: {$kabupaten->nama_kabupaten} (Kode: {$kabupaten->kode_kabupaten})",
            $kabupaten,
            $oldValues,
            $kabupaten->only(['propinsi_id', 'kode_kabupaten', 'nama_kabupaten'])
        );

        return back()->with('success', "Data kabupaten/kota {$kabupaten->nama_kabupaten} berhasil diperbarui.");
    }

    /**
     * Hapus Kabupaten/Kota
     */
    public function destroyKabupaten(int $id): RedirectResponse
    {
        $kabupaten = Kabupaten::findOrFail($id);

        if ($kabupaten->perusahaan()->exists()) {
            return back()->withErrors([
                'error' => "Kabupaten/Kota {$kabupaten->nama_kabupaten} tidak dapat dihapus karena tercatat pada master data perusahaan.",
            ]);
        }

        $oldData = $kabupaten->toArray();
        $propinsiId = $kabupaten->propinsi_id;
        $kabupaten->delete();

        // Bersihkan cache kabupaten per provinsi
        Cache::forget("wilayah:kabupaten:prov_{$propinsiId}");

        LogActivity::record(
            'DELETE_KABUPATEN',
            "Super Admin menghapus kabupaten/kota: {$kabupaten->nama_kabupaten} (Kode: {$kabupaten->kode_kabupaten})",
            null,
            $oldData,
            null
        );

        return back()->with('success', "Kabupaten/Kota {$kabupaten->nama_kabupaten} berhasil dihapus.");
    }

    /**
     * Endpoint API asinkron untuk mengambil daftar kabupaten berdasarkan ID provinsi (cascading dropdown).
     */
    public function getKabupatenByProvinsi(int $propinsiId): JsonResponse
    {
        $kabupatens = Cache::remember("wilayah:kabupaten:prov_{$propinsiId}", 86400, function () use ($propinsiId) {
            return Kabupaten::select('id', 'propinsi_id', 'nama_kabupaten')
                ->where('propinsi_id', $propinsiId)
                ->orderBy('nama_kabupaten')
                ->get();
        });

        return response()->json($kabupatens);
    }

    /**
     * Simpan / Perbarui Ketetapan UMP per Provinsi & Tahun
     */
    public function storeOrUpdateUmp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_provinsi' => 'required|string|exists:propinsi,kode_provinsi',
            'tahun' => 'required|integer|min:2000|max:2100',
            'besaran' => 'required|numeric|min:0',
            'catatan' => 'nullable|string|max:255',
        ]);

        $ump = Ump::updateOrCreate(
            [
                'kode_provinsi' => $validated['kode_provinsi'],
                'tahun' => $validated['tahun'],
            ],
            [
                'besaran' => $validated['besaran'],
                'catatan' => $validated['catatan'] ?? null,
            ]
        );

        $provinsi = Propinsi::where('kode_provinsi', $validated['kode_provinsi'])->first();
        $provNama = $provinsi ? $provinsi->nama_provinsi : $validated['kode_provinsi'];
        $besaranFormatted = number_format($validated['besaran'], 0, ',', '.');

        LogActivity::record(
            'SET_UMP',
            "Super Admin mengatur UMP {$provNama} tahun {$validated['tahun']} menjadi Rp {$besaranFormatted}",
            $ump,
            null,
            $ump->toArray()
        );

        return back()->with('success', "Besaran UMP {$provNama} tahun {$validated['tahun']} berhasil disimpan: Rp {$besaranFormatted}.");
    }

    /**
     * Hapus Data UMP
     */
    public function destroyUmp(int $id): RedirectResponse
    {
        $ump = Ump::findOrFail($id);
        $oldData = $ump->toArray();
        $provNama = $ump->propinsi ? $ump->propinsi->nama_provinsi : $ump->kode_provinsi;
        $tahun = $ump->tahun;

        $ump->delete();

        LogActivity::record(
            'DELETE_UMP',
            "Super Admin menghapus ketetapan UMP {$provNama} tahun {$tahun}",
            null,
            $oldData,
            null
        );

        return back()->with('success', "Ketetapan UMP {$provNama} tahun {$tahun} berhasil dihapus.");
    }

    /**
     * Salin Ketetapan UMP dari Tahun Sumber ke Tahun Target (dengan opsional penyesuaian persentase)
     */
    public function copyYearUmp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_year' => 'required|integer|min:2000|max:2100',
            'target_year' => 'required|integer|min:2000|max:2100|different:source_year',
            'percentage_increase' => 'nullable|numeric|min:-50|max:100',
        ]);

        $sourceYear = $validated['source_year'];
        $targetYear = $validated['target_year'];
        $percentage = (float) ($validated['percentage_increase'] ?? 0);
        $multiplier = 1 + ($percentage / 100);

        $sourceUmps = Ump::where('tahun', $sourceYear)->get();

        if ($sourceUmps->isEmpty()) {
            return back()->withErrors([
                'source_year' => "Tidak ada data UMP pada tahun {$sourceYear} untuk disalin.",
            ]);
        }

        $copiedCount = 0;
        foreach ($sourceUmps as $src) {
            $newBesaran = round($src->besaran * $multiplier);

            Ump::updateOrCreate(
                [
                    'kode_provinsi' => $src->kode_provinsi,
                    'tahun' => $targetYear,
                ],
                [
                    'besaran' => $newBesaran,
                    'catatan' => $percentage != 0
                        ? "Penyesuaian {$percentage}% dari tahun {$sourceYear}"
                        : "Disalin dari tahun {$sourceYear}",
                ]
            );
            $copiedCount++;
        }

        LogActivity::record(
            'COPY_YEAR_UMP',
            "Super Admin menyalin {$copiedCount} data UMP dari tahun {$sourceYear} ke tahun {$targetYear} (Penyesuaian: {$percentage}%)"
        );

        return back()->with('success', "Berhasil menyalin {$copiedCount} data UMP ke tahun {$targetYear}.");
    }

    /**
     * Tambah Data Master Negara Baru
     */
    public function storeNegara(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_iso2' => 'required|string|size:2|unique:ref_negara,kode_iso2',
            'kode_iso3' => 'nullable|string|size:3|unique:ref_negara,kode_iso3',
            'nama_negara' => 'required|string|max:80|unique:ref_negara,nama_negara',
            'nama_resmi' => 'nullable|string|max:120',
            'benua' => 'nullable|string|max:50',
            'ibu_kota' => 'nullable|string|max:80',
        ]);

        $negara = RefNegara::create([
            'kode_iso2' => strtoupper(trim($validated['kode_iso2'])),
            'kode_iso3' => ! empty($validated['kode_iso3']) ? strtoupper(trim($validated['kode_iso3'])) : null,
            'nama_negara' => trim($validated['nama_negara']),
            'nama_resmi' => ! empty($validated['nama_resmi']) ? trim($validated['nama_resmi']) : null,
            'benua' => ! empty($validated['benua']) ? trim($validated['benua']) : null,
            'ibu_kota' => ! empty($validated['ibu_kota']) ? trim($validated['ibu_kota']) : null,
        ]);

        LogActivity::record(
            'CREATE_NEGARA',
            "Super Admin menambahkan negara baru: {$negara->nama_negara} (ISO-2: {$negara->kode_iso2})",
            $negara,
            null,
            $negara->toArray()
        );

        return back()->with('success', "Negara {$negara->nama_negara} berhasil ditambahkan.");
    }

    /**
     * Perbarui Data Master Negara
     */
    public function updateNegara(Request $request, int $id): RedirectResponse
    {
        $negara = RefNegara::findOrFail($id);

        $validated = $request->validate([
            'kode_iso2' => 'required|string|size:2|unique:ref_negara,kode_iso2,'.$negara->id,
            'kode_iso3' => 'nullable|string|size:3|unique:ref_negara,kode_iso3,'.$negara->id,
            'nama_negara' => 'required|string|max:80|unique:ref_negara,nama_negara,'.$negara->id,
            'nama_resmi' => 'nullable|string|max:120',
            'benua' => 'nullable|string|max:50',
            'ibu_kota' => 'nullable|string|max:80',
        ]);

        $oldValues = $negara->toArray();
        $oldNama = $negara->nama_negara;
        $newNama = trim($validated['nama_negara']);

        // Jika nama negara berubah, sinkronkan relasi string pada tabel perusahaan
        if ($oldNama !== $newNama) {
            Perusahaan::where('negara', $oldNama)->update(['negara' => $newNama]);
        }

        $negara->update([
            'kode_iso2' => strtoupper(trim($validated['kode_iso2'])),
            'kode_iso3' => ! empty($validated['kode_iso3']) ? strtoupper(trim($validated['kode_iso3'])) : null,
            'nama_negara' => $newNama,
            'nama_resmi' => ! empty($validated['nama_resmi']) ? trim($validated['nama_resmi']) : null,
            'benua' => ! empty($validated['benua']) ? trim($validated['benua']) : null,
            'ibu_kota' => ! empty($validated['ibu_kota']) ? trim($validated['ibu_kota']) : null,
        ]);

        LogActivity::record(
            'UPDATE_NEGARA',
            "Super Admin memperbarui data negara: {$negara->nama_negara} (ISO-2: {$negara->kode_iso2})",
            $negara,
            $oldValues,
            $negara->toArray()
        );

        return back()->with('success', "Data negara {$negara->nama_negara} berhasil diperbarui.");
    }

    /**
     * Hapus Data Master Negara
     */
    public function destroyNegara(int $id): RedirectResponse
    {
        $negara = RefNegara::findOrFail($id);

        $perusahaanCount = Perusahaan::where('negara', $negara->nama_negara)->count();
        if ($perusahaanCount > 0) {
            return back()->withErrors([
                'error' => "Negara {$negara->nama_negara} tidak dapat dihapus karena masih digunakan oleh {$perusahaanCount} data perusahaan.",
            ]);
        }

        $oldData = $negara->toArray();
        $negara->delete();

        LogActivity::record(
            'DELETE_NEGARA',
            "Super Admin menghapus data negara: {$negara->nama_negara} (ISO-2: {$negara->kode_iso2})",
            null,
            $oldData,
            null
        );

        return back()->with('success', "Negara {$negara->nama_negara} berhasil dihapus.");
    }
}
