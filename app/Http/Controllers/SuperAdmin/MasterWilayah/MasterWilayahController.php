<?php

namespace App\Http\Controllers\SuperAdmin\MasterWilayah;

use App\Http\Controllers\Controller;
use App\Models\Kabupaten;
use App\Models\LogActivity;
use App\Models\Perusahaan;
use App\Models\Propinsi;
use App\Models\RefNegara;
use App\Models\Ump;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $currentYear = (int) now()->year;
        $selectedYear = (int) $request->input('tahun', $currentYear);
        $kodeProvinsiFilter = $request->input('kode_provinsi');
        $benuaFilter = $request->input('benua');
        $perPage = (int) $request->input('per_page', 15);

        // 1. Ambil daftar tahun unik UMP dengan cache ringan
        $availableYears = Cache::remember('ump_available_years', 300, function () use ($currentYear): array {
            $years = Ump::select('tahun')
                ->distinct()
                ->orderBy('tahun', 'desc')
                ->pluck('tahun')
                ->toArray();

            return ! empty($years) ? $years : [$currentYear];
        });

        if (! in_array($selectedYear, $availableYears, true)) {
            $availableYears[] = $selectedYear;
            rsort($availableYears);
        }

        // 2. Data Ringkasan (Statistik) dioptimalkan dengan agregasi tunggal & cache
        $stats = Cache::remember("master_wilayah_stats_{$selectedYear}", 60, function () use ($selectedYear): array {
            $totalProvinsi = DB::table('propinsi')->count();
            $totalKabupaten = DB::table('kabupaten')->count();
            $totalNegara = DB::table('ref_negara')->count();

            $umpStats = DB::table('ump')
                ->where('tahun', $selectedYear)
                ->whereNotNull('besaran')
                ->selectRaw('COUNT(*) as total_configured, AVG(besaran) as avg_besaran, MIN(besaran) as min_besaran, MAX(besaran) as max_besaran')
                ->first();

            $minUmpProvince = '-';
            $maxUmpProvince = '-';
            $minBesaran = 0.0;
            $maxBesaran = 0.0;
            $totalUmpConfigured = 0;
            $avgUmp = 0.0;

            if ($umpStats && $umpStats->total_configured > 0) {
                $totalUmpConfigured = (int) $umpStats->total_configured;
                $avgUmp = round((float) $umpStats->avg_besaran, 2);
                $minBesaran = (float) $umpStats->min_besaran;
                $maxBesaran = (float) $umpStats->max_besaran;

                $minUmpProvince = DB::table('ump as u')
                    ->join('propinsi as p', 'p.kode_provinsi', '=', 'u.kode_provinsi')
                    ->where('u.tahun', $selectedYear)
                    ->where('u.besaran', $umpStats->min_besaran)
                    ->value('p.nama_provinsi') ?? '-';

                $maxUmpProvince = DB::table('ump as u')
                    ->join('propinsi as p', 'p.kode_provinsi', '=', 'u.kode_provinsi')
                    ->where('u.tahun', $selectedYear)
                    ->where('u.besaran', $umpStats->max_besaran)
                    ->value('p.nama_provinsi') ?? '-';
            }

            return [
                'total_provinsi' => $totalProvinsi,
                'total_kabupaten' => $totalKabupaten,
                'total_negara' => $totalNegara,
                'total_ump_configured' => $totalUmpConfigured,
                'avg_ump' => $avgUmp,
                'min_ump' => [
                    'besaran' => $minBesaran,
                    'provinsi' => $minUmpProvince,
                ],
                'max_ump' => [
                    'besaran' => $maxBesaran,
                    'provinsi' => $maxUmpProvince,
                ],
                'selected_year' => $selectedYear,
            ];
        });

        // 3. Lookup daftar provinsi dan benua dengan cache (jarang berubah)
        $allProvinces = Cache::remember('all_provinces_lookup', 3600, function (): array {
            return Propinsi::select('kode_provinsi', 'nama_provinsi')
                ->orderBy('nama_provinsi', 'asc')
                ->get()
                ->toArray();
        });

        if (! is_iterable($allProvinces) || is_a($allProvinces, '__PHP_Incomplete_Class')) {
            Cache::forget('all_provinces_lookup');
            $allProvinces = Propinsi::select('kode_provinsi', 'nama_provinsi')
                ->orderBy('nama_provinsi', 'asc')
                ->get()
                ->toArray();
        }

        $allContinents = Cache::remember('all_continents_lookup', 86400, function (): array {
            return RefNegara::whereNotNull('benua')
                ->where('benua', '!=', '')
                ->distinct()
                ->orderBy('benua', 'asc')
                ->pluck('benua')
                ->toArray();
        });

        if (! is_array($allContinents) || is_a($allContinents, '__PHP_Incomplete_Class')) {
            Cache::forget('all_continents_lookup');
            $allContinents = RefNegara::whereNotNull('benua')
                ->where('benua', '!=', '')
                ->distinct()
                ->orderBy('benua', 'asc')
                ->pluck('benua')
                ->toArray();
        }

        // 4. Pemuatan Data Cepat (Provinsi & UMP selalu siap di memori klien untuk transisi tab instan)
        $kabCounts = Cache::remember('kabupaten_counts_by_provinsi', 3600, function (): array {
            return DB::table('kabupaten')
                ->select('kode_provinsi', DB::raw('COUNT(*) as total'))
                ->groupBy('kode_provinsi')
                ->pluck('total', 'kode_provinsi')
                ->toArray();
        });

        if (! is_array($kabCounts) || is_a($kabCounts, '__PHP_Incomplete_Class')) {
            Cache::forget('kabupaten_counts_by_provinsi');
            $kabCounts = DB::table('kabupaten')
                ->select('kode_provinsi', DB::raw('COUNT(*) as total'))
                ->groupBy('kode_provinsi')
                ->pluck('total', 'kode_provinsi')
                ->toArray();
        }

        $provQuery = Propinsi::query();
        if ($search && $activeTab === 'provinsi') {
            $provQuery->where(function ($q) use ($search): void {
                $q->where('nama_provinsi', 'like', "%{$search}%")
                    ->orWhere('kode_provinsi', 'like', "%{$search}%");
            });
        }

        $provinces = $provQuery->orderBy('kode_provinsi', 'asc')
            ->get(['kode_provinsi', 'nama_provinsi'])
            ->map(fn ($prov): array => [
                'kode_provinsi' => $prov->kode_provinsi,
                'nama_provinsi' => $prov->nama_provinsi,
                'total_kabupaten' => (int) ($kabCounts[$prov->kode_provinsi] ?? 0),
            ])
            ->all();

        $allUmps = DB::table('propinsi as p')
            ->leftJoin('ump as u', function ($join) use ($selectedYear): void {
                $join->on('u.kode_provinsi', '=', 'p.kode_provinsi')
                    ->where('u.tahun', '=', $selectedYear);
            })
            ->select([
                'u.id',
                'p.kode_provinsi',
                'p.nama_provinsi',
                'u.besaran',
                'u.catatan',
                'u.updated_at',
            ])
            ->when($search && $activeTab === 'ump', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('p.nama_provinsi', 'like', "%{$search}%")
                        ->orWhere('p.kode_provinsi', 'like', "%{$search}%");
                });
            })
            ->orderBy('p.kode_provinsi', 'asc')
            ->get()
            ->map(fn ($item): array => [
                'id' => $item->id,
                'kode_provinsi' => $item->kode_provinsi,
                'nama_provinsi' => $item->nama_provinsi,
                'tahun' => $selectedYear,
                'besaran' => $item->besaran !== null ? (float) $item->besaran : null,
                'catatan' => $item->catatan,
                'has_ump' => $item->besaran !== null,
                'updated_at' => $item->updated_at ? Carbon::parse($item->updated_at)->format('d/m/Y H:i') : '-',
            ])
            ->all();

        $kabupatenQuery = Kabupaten::with('propinsi:kode_provinsi,nama_provinsi');
        if ($kodeProvinsiFilter) {
            $kabupatenQuery->where('kode_provinsi', $kodeProvinsiFilter);
        }
        if ($search && $activeTab === 'kabupaten') {
            $kabupatenQuery->where(function ($q) use ($search): void {
                $q->where('nama_kabupaten', 'like', "%{$search}%")
                    ->orWhere('kode_kabupaten', 'like', "%{$search}%")
                    ->orWhereHas('propinsi', function ($pq) use ($search): void {
                        $pq->where('nama_provinsi', 'like', "%{$search}%");
                    });
            });
        }
        $kabupatens = $kabupatenQuery->orderBy('kode_kabupaten', 'asc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($kab): array => [
                'kode_kabupaten' => $kab->kode_kabupaten,
                'kode_provinsi' => $kab->kode_provinsi,
                'nama_kabupaten' => $kab->nama_kabupaten,
                'nama_provinsi' => $kab->propinsi?->nama_provinsi ?? '-',
            ]);

        $negaras = ['data' => [], 'links' => [], 'total' => (int) $stats['total_negara']];
        if ($activeTab === 'negara') {
            $negaraQuery = RefNegara::query();
            if ($benuaFilter) {
                $negaraQuery->where('benua', $benuaFilter);
            }
            if ($search) {
                $negaraQuery->where(function ($q) use ($search): void {
                    $q->where('nama_negara', 'like', "%{$search}%")
                        ->orWhere('kode_negara', 'like', "%{$search}%")
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
                'kode_provinsi' => $kodeProvinsiFilter ?: '',
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
                'tahun' => $validated['tahun_ump'] ?: (int) now()->year,
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

        $this->clearWilayahCache();

        return back()->with('success', "Provinsi {$provinsi->nama_provinsi} berhasil ditambahkan.");
    }

    /**
     * Perbarui Data Provinsi
     */
    public function updateProvinsi(Request $request, string $kodeProvinsi): RedirectResponse
    {
        $provinsi = Propinsi::findOrFail($kodeProvinsi);

        $validated = $request->validate([
            'kode_provinsi' => 'required|string|max:10|unique:propinsi,kode_provinsi,'.$provinsi->kode_provinsi.',kode_provinsi',
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

        $this->clearWilayahCache();

        return back()->with('success', "Data provinsi {$provinsi->nama_provinsi} berhasil diperbarui.");
    }

    /**
     * Hapus Provinsi
     */
    public function destroyProvinsi(string $kodeProvinsi): RedirectResponse
    {
        $provinsi = Propinsi::findOrFail($kodeProvinsi);

        // Cek keterkaitan dengan kabupaten
        $kabupatenCount = Kabupaten::where('kode_provinsi', $provinsi->kode_provinsi)->count();
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

        $this->clearWilayahCache();

        return back()->with('success', "Provinsi {$provinsi->nama_provinsi} berhasil dihapus.");
    }

    /**
     * Tambah Kabupaten/Kota Baru
     */
    public function storeKabupaten(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_provinsi' => 'required|exists:propinsi,kode_provinsi',
            'kode_kabupaten' => 'required|string|max:20|unique:kabupaten,kode_kabupaten',
            'nama_kabupaten' => 'required|string|max:255',
        ]);

        $kabupaten = Kabupaten::create([
            'kode_provinsi' => $validated['kode_provinsi'],
            'kode_kabupaten' => $validated['kode_kabupaten'],
            'nama_kabupaten' => strtoupper(trim($validated['nama_kabupaten'])),
        ]);

        // Bersihkan cache kabupaten per provinsi agar dropdown selalu mutakhir
        Cache::forget("wilayah:kabupaten:prov_{$validated['kode_provinsi']}");

        LogActivity::record(
            'CREATE_KABUPATEN',
            "Super Admin menambahkan kabupaten/kota baru: {$kabupaten->nama_kabupaten} (Kode: {$kabupaten->kode_kabupaten})",
            $kabupaten,
            null,
            $kabupaten->toArray()
        );

        $this->clearWilayahCache();

        return back()->with('success', "Kabupaten/Kota {$kabupaten->nama_kabupaten} berhasil ditambahkan.");
    }

    /**
     * Perbarui Data Kabupaten/Kota
     */
    public function updateKabupaten(Request $request, string $kodeKabupaten): RedirectResponse
    {
        $kabupaten = Kabupaten::findOrFail($kodeKabupaten);

        $validated = $request->validate([
            'kode_provinsi' => 'required|exists:propinsi,kode_provinsi',
            'kode_kabupaten' => 'required|string|max:20|unique:kabupaten,kode_kabupaten,'.$kabupaten->kode_kabupaten.',kode_kabupaten',
            'nama_kabupaten' => 'required|string|max:255',
        ]);

        $oldValues = $kabupaten->only(['kode_provinsi', 'kode_kabupaten', 'nama_kabupaten']);

        $kabupaten->update([
            'kode_provinsi' => $validated['kode_provinsi'],
            'kode_kabupaten' => $validated['kode_kabupaten'],
            'nama_kabupaten' => strtoupper(trim($validated['nama_kabupaten'])),
        ]);

        // Bersihkan cache kabupaten untuk provinsi lama dan provinsi baru
        Cache::forget("wilayah:kabupaten:prov_{$oldValues['kode_provinsi']}");
        Cache::forget("wilayah:kabupaten:prov_{$validated['kode_provinsi']}");

        LogActivity::record(
            'UPDATE_KABUPATEN',
            "Super Admin memperbarui kabupaten/kota: {$kabupaten->nama_kabupaten} (Kode: {$kabupaten->kode_kabupaten})",
            $kabupaten,
            $oldValues,
            $kabupaten->only(['kode_provinsi', 'kode_kabupaten', 'nama_kabupaten'])
        );

        $this->clearWilayahCache();

        return back()->with('success', "Data kabupaten/kota {$kabupaten->nama_kabupaten} berhasil diperbarui.");
    }

    /**
     * Hapus Kabupaten/Kota
     */
    public function destroyKabupaten(string $kodeKabupaten): RedirectResponse
    {
        $kabupaten = Kabupaten::findOrFail($kodeKabupaten);

        if ($kabupaten->perusahaan()->exists()) {
            return back()->withErrors([
                'error' => "Kabupaten/Kota {$kabupaten->nama_kabupaten} tidak dapat dihapus karena tercatat pada master data perusahaan.",
            ]);
        }

        $oldData = $kabupaten->toArray();
        $kodeProvinsi = $kabupaten->kode_provinsi;
        $kabupaten->delete();

        // Bersihkan cache kabupaten per provinsi
        Cache::forget("wilayah:kabupaten:prov_{$kodeProvinsi}");

        LogActivity::record(
            'DELETE_KABUPATEN',
            "Super Admin menghapus kabupaten/kota: {$kabupaten->nama_kabupaten} (Kode: {$kabupaten->kode_kabupaten})",
            null,
            $oldData,
            null
        );

        $this->clearWilayahCache();

        return back()->with('success', "Kabupaten/Kota {$kabupaten->nama_kabupaten} berhasil dihapus.");
    }

    /**
     * Endpoint API asinkron untuk mengambil daftar kabupaten berdasarkan ID provinsi (cascading dropdown).
     */
    public function getKabupatenByProvinsi(string $kodeProvinsi): JsonResponse
    {
        $cacheKey = "wilayah:kabupaten:prov_{$kodeProvinsi}";

        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response()->json($cached);
            }
        } catch (\Throwable) {
            Cache::forget($cacheKey);
        }

        $kabupatens = Kabupaten::select('kode_kabupaten', 'kode_provinsi', 'nama_kabupaten')
            ->where('kode_provinsi', $kodeProvinsi)
            ->orderBy('nama_kabupaten')
            ->get()
            ->toArray();

        try {
            Cache::put($cacheKey, $kabupatens, 86400);
        } catch (\Throwable) {
            // Abaikan error penyimpanan cache jika terjadi kendala driver
        }

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

        $this->clearWilayahCache((int) $validated['tahun']);

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

        $this->clearWilayahCache((int) $tahun);

        return back()->with('success', "Ketetapan UMP {$provNama} tahun {$tahun} berhasil dihapus.");
    }

    /**
     * Hapus / Reset Seluruh Ketetapan UMP pada Tahun Tertentu (Rollback Salin UMP).
     */
    public function resetYearUmp(int $year): RedirectResponse
    {
        $deletedCount = Ump::where('tahun', $year)->delete();

        LogActivity::record(
            'RESET_YEAR_UMP',
            "Super Admin menghapus seluruh ({$deletedCount}) ketetapan UMP untuk tahun {$year}"
        );

        $this->clearWilayahCache($year);

        return back()->with('success', "Seluruh data UMP tahun {$year} ({$deletedCount} provinsi) berhasil dihapus.");
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

        $this->clearWilayahCache((int) $targetYear);

        return back()->with('success', "Berhasil menyalin {$copiedCount} data UMP ke tahun {$targetYear}.");
    }

    /**
     * Tambah Data Master Negara Baru
     */
    public function storeNegara(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_negara' => 'required|string|size:2|unique:ref_negara,kode_negara',
            'kode_iso3' => 'nullable|string|size:3|unique:ref_negara,kode_iso3',
            'nama_negara' => 'required|string|max:80|unique:ref_negara,nama_negara',
            'nama_resmi' => 'nullable|string|max:120',
            'benua' => 'nullable|string|max:50',
            'ibu_kota' => 'nullable|string|max:80',
        ]);

        $negara = RefNegara::create([
            'kode_negara' => strtoupper(trim($validated['kode_negara'])),
            'kode_iso3' => ! empty($validated['kode_iso3']) ? strtoupper(trim($validated['kode_iso3'])) : null,
            'nama_negara' => trim($validated['nama_negara']),
            'nama_resmi' => ! empty($validated['nama_resmi']) ? trim($validated['nama_resmi']) : null,
            'benua' => ! empty($validated['benua']) ? trim($validated['benua']) : null,
            'ibu_kota' => ! empty($validated['ibu_kota']) ? trim($validated['ibu_kota']) : null,
        ]);

        LogActivity::record(
            'CREATE_NEGARA',
            "Super Admin menambahkan negara baru: {$negara->nama_negara} (kode: {$negara->kode_negara})",
            $negara,
            null,
            $negara->toArray()
        );

        return back()->with('success', "Negara {$negara->nama_negara} berhasil ditambahkan.");
    }

    /**
     * Perbarui Data Master Negara
     */
    public function updateNegara(Request $request, string $kodeNegara): RedirectResponse
    {
        $negara = RefNegara::findOrFail($kodeNegara);

        $validated = $request->validate([
            'kode_negara' => 'required|string|size:2|unique:ref_negara,kode_negara,'.$negara->kode_negara.',kode_negara',
            'kode_iso3' => 'nullable|string|size:3|unique:ref_negara,kode_iso3,'.$negara->kode_negara.',kode_negara',
            'nama_negara' => 'required|string|max:80|unique:ref_negara,nama_negara,'.$negara->kode_negara.',kode_negara',
            'nama_resmi' => 'nullable|string|max:120',
            'benua' => 'nullable|string|max:50',
            'ibu_kota' => 'nullable|string|max:80',
        ]);

        $oldValues = $negara->toArray();
        $newNama = trim($validated['nama_negara']);

        $negara->update([
            'kode_negara' => strtoupper(trim($validated['kode_negara'])),
            'kode_iso3' => ! empty($validated['kode_iso3']) ? strtoupper(trim($validated['kode_iso3'])) : null,
            'nama_negara' => $newNama,
            'nama_resmi' => ! empty($validated['nama_resmi']) ? trim($validated['nama_resmi']) : null,
            'benua' => ! empty($validated['benua']) ? trim($validated['benua']) : null,
            'ibu_kota' => ! empty($validated['ibu_kota']) ? trim($validated['ibu_kota']) : null,
        ]);

        LogActivity::record(
            'UPDATE_NEGARA',
            "Super Admin memperbarui data negara: {$negara->nama_negara} (kode: {$negara->kode_negara})",
            $negara,
            $oldValues,
            $negara->toArray()
        );

        return back()->with('success', "Data negara {$negara->nama_negara} berhasil diperbarui.");
    }

    /**
     * Hapus Data Master Negara
     */
    public function destroyNegara(string $kodeNegara): RedirectResponse
    {
        $negara = RefNegara::findOrFail($kodeNegara);

        $perusahaanCount = Perusahaan::where('kode_negara', $negara->kode_negara)->count();
        if ($perusahaanCount > 0) {
            return back()->withErrors([
                'error' => "Negara {$negara->nama_negara} tidak dapat dihapus karena masih digunakan oleh {$perusahaanCount} data perusahaan.",
            ]);
        }

        $oldData = $negara->toArray();
        $negara->delete();

        LogActivity::record(
            'DELETE_NEGARA',
            "Super Admin menghapus data negara: {$negara->nama_negara} (kode: {$negara->kode_negara})",
            null,
            $oldData,
            null
        );

        $this->clearWilayahCache();

        return back()->with('success', "Negara {$negara->nama_negara} berhasil dihapus.");
    }

    /**
     * Bersihkan cache referensi wilayah & UMP setelah aksi mutasi data.
     */
    private function clearWilayahCache(?int $year = null): void
    {
        Cache::forget('all_provinces_lookup');
        Cache::forget('ump_available_years');
        Cache::forget('all_continents_lookup');
        Cache::forget('kabupaten_counts_by_provinsi');
        if ($year) {
            Cache::forget("master_wilayah_stats_{$year}");
        }
        Cache::forget('master_wilayah_stats_'.now()->year);
    }
}
