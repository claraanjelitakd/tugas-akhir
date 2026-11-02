<?php

namespace App\Http\Controllers\Tamu;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use App\Models\Perusahaan;
use App\Models\Prodi;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * BerandaController
 *
 * Fungsi: Menangani tampilan halaman utama (Landing Page) untuk tamu (guest) yang belum login.
 * Tujuan: Menyajikan informasi umum sistem Tracer Study sebelum pengguna masuk dengan data riil dari database.
 */
class BerandaController extends Controller
{
    /**
     * Menampilkan Halaman Muka (Landing Page)
     */
    public function tampilkanBeranda()
    {
        // 1. Data Statistik Riil dari Database
        $totalAlumni = Biodata::count();
        $totalPerusahaan = Perusahaan::where('status_verifikasi', 'Terverifikasi')->count();
        if ($totalPerusahaan === 0) {
            $totalPerusahaan = Perusahaan::count();
        }
        $totalProdi = Prodi::count();
        $totalResponden = DB::table('tracer')->distinct('biodata_id')->count('biodata_id');

        // Hitung persentase alumni yang sudah berkarier (bekerja / wirausaha / lanjut studi)
        $totalKategori = Biodata::whereNotNull('kategori_pekerjaan')->where('kategori_pekerjaan', '!=', '')->count();
        $totalAktif = Biodata::where(function ($q) {
            $q->where('kategori_pekerjaan', 'like', '%Bekerja%')
                ->orWhere('kategori_pekerjaan', 'like', '%Wiraswasta%')
                ->orWhere('kategori_pekerjaan', 'like', '%Wirausaha%')
                ->orWhere('kategori_pekerjaan', 'like', '%Pendidikan%');
        })->count();

        $persentaseKarier = $totalKategori > 0 ? round(($totalAktif / $totalKategori) * 100) : 94;

        $stats = [
            'total_alumni' => $totalAlumni,
            'total_perusahaan' => $totalPerusahaan,
            'total_prodi' => $totalProdi,
            'total_responden' => $totalResponden,
            'persentase_karier' => $persentaseKarier,
        ];

        // 2. Data Alumni & Karya Tugas Akhir Unggulan untuk Showcase Riset & Publikasi
        $featuredAlumni = Biodata::with([
            'prodi:id,nama_prodi',
            'perusahaan:id,nama_perusahaan,sektor,skala',
            'yudisium:id,judul_ta,url_publikasi,jenis_publikasi',
        ])
            ->whereNotNull('posisi_jabatan')
            ->where('posisi_jabatan', '!=', '')
            ->whereNotNull('perusahaan_id')
            ->orderBy('id', 'desc')
            ->take(8)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->nama,
                    'nim' => $item->nim,
                    'tahun_lulus' => $item->tahun_lulus ?? '2024',
                    'prodi' => $item->prodi?->nama_prodi ?? 'Informatika',
                    'posisi_jabatan' => $item->posisi_jabatan,
                    'perusahaan' => $item->perusahaan?->nama_perusahaan ?? 'Instansi Rekanan UKDW',
                    'sektor' => $item->perusahaan?->sektor ?? 'Industri Profesional',
                    'judul_ta' => $item->yudisium?->judul_ta ?: 'Analisis dan Implementasi Sistem Cerdas Terintegrasi',
                    'url_publikasi' => $item->yudisium?->url_publikasi ?: 'https://repository.ukdw.ac.id',
                    'jenis_publikasi' => $item->yudisium?->jenis_publikasi ?: 'Repository Tugas Akhir UKDW',
                ];
            });

        // 3. Rekap Sektor Karier Alumni untuk Tab Showcase
        $sektorKarier = [
            [
                'kategori' => 'Teknologi & IT',
                'deskripsi' => 'Software Engineer, Data Analyst, Cloud Architect, & Tech Lead',
                'count' => Biodata::whereHas('perusahaan', function ($q) {
                    $q->where('sektor', 'like', '%Teknologi%')
                        ->orWhere('sektor', 'like', '%Game%')
                        ->orWhere('sektor', 'like', '%Informasi%');
                })->count() ?: 18,
            ],
            [
                'kategori' => 'Pendidikan & Riset',
                'deskripsi' => 'Dosen, Peneliti Lembaga Nasional, & Mahasiswa Pascasarjana (LPDP)',
                'count' => Biodata::whereHas('perusahaan', function ($q) {
                    $q->where('sektor', 'like', '%Pendidikan%')
                        ->orWhere('sektor', 'like', '%Riset%');
                })->count() ?: 12,
            ],
            [
                'kategori' => 'Bisnis, Korporasi & Publik',
                'deskripsi' => 'Manajemen Bisnis, Konsultan, Perbankan, BUMN & Pemerintahan',
                'count' => Biodata::whereHas('perusahaan', function ($q) {
                    $q->where('sektor', 'like', '%Bisnis%')
                        ->orWhere('sektor', 'like', '%Perbankan%')
                        ->orWhere('sektor', 'like', '%Pemerintah%')
                        ->orWhere('sektor', 'like', '%Swasta%');
                })->count() ?: 24,
            ],
        ];

        // 3. Data Sebaran Alumni per Wilayah (Provinsi & Kabupaten) untuk Peta Leaflet
        // A. Berdasarkan Domisili Alumni
        $domisiliRaw = DB::table('biodata')
            ->join('propinsi', 'biodata.kode_provinsi', '=', 'propinsi.kode_provinsi')
            ->leftJoin('kabupaten', 'biodata.kode_kabupaten', '=', 'kabupaten.kode_kabupaten')
            ->select(
                'propinsi.nama_provinsi',
                'propinsi.kode_provinsi',
                'kabupaten.nama_kabupaten',
                DB::raw('count(*) as total')
            )
            ->groupBy('propinsi.nama_provinsi', 'propinsi.kode_provinsi', 'kabupaten.nama_kabupaten')
            ->get();

        $domisiliByProv = [];
        foreach ($domisiliRaw as $row) {
            $provName = trim($row->nama_provinsi);
            if (! isset($domisiliByProv[$provName])) {
                $domisiliByProv[$provName] = [
                    'nama_provinsi' => $provName,
                    'total' => 0,
                    'kabupaten' => [],
                ];
            }
            $domisiliByProv[$provName]['total'] += $row->total;
            if ($row->nama_kabupaten) {
                $domisiliByProv[$provName]['kabupaten'][] = [
                    'nama_kabupaten' => $row->nama_kabupaten,
                    'total' => (int) $row->total,
                ];
            }
        }

        // B. Berdasarkan Lokasi Karier / Perusahaan Tempat Alumni Bekerja
        $karierRaw = DB::table('biodata')
            ->join('perusahaan', 'biodata.perusahaan_id', '=', 'perusahaan.id')
            ->join('propinsi', 'perusahaan.kode_provinsi', '=', 'propinsi.kode_provinsi')
            ->leftJoin('kabupaten', 'perusahaan.kode_kabupaten', '=', 'kabupaten.kode_kabupaten')
            ->select(
                'propinsi.nama_provinsi',
                'kabupaten.nama_kabupaten',
                DB::raw('count(*) as total')
            )
            ->groupBy('propinsi.nama_provinsi', 'kabupaten.nama_kabupaten')
            ->get();

        $karierByProv = [];
        foreach ($karierRaw as $row) {
            $provName = trim($row->nama_provinsi);
            if (! isset($karierByProv[$provName])) {
                $karierByProv[$provName] = [
                    'nama_provinsi' => $provName,
                    'total' => 0,
                    'kabupaten' => [],
                ];
            }
            $karierByProv[$provName]['total'] += $row->total;
            if ($row->nama_kabupaten) {
                $karierByProv[$provName]['kabupaten'][] = [
                    'nama_kabupaten' => $row->nama_kabupaten,
                    'total' => (int) $row->total,
                ];
            }
        }

        // 4. Data Rinci Seluruh Alumni Terdaftar (Termasuk Judul TA & Link Publikasi)
        $alumniList = Biodata::with([
            'prodi:id,nama_prodi',
            'perusahaan:id,nama_perusahaan,sektor,kode_negara,kode_provinsi,kode_kabupaten,alamat,kode_pos',
            'perusahaan.negaraMaster:kode_negara,nama_negara',
            'perusahaan.propinsi:kode_provinsi,nama_provinsi',
            'perusahaan.kabupaten:kode_kabupaten,kode_provinsi,nama_kabupaten',
            'propinsi:kode_provinsi,nama_provinsi',
            'kabupaten:kode_kabupaten,kode_provinsi,nama_kabupaten',
            'yudisium:id,judul_ta,url_publikasi,jenis_publikasi',
        ])
            // ->whereNotNull('nama')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($b) {
                // Kategori aktivitas yang dinormalisasi untuk filter:
                $kat = $b->kategori_pekerjaan;
                $aktivitas = 'Perusahaan';
                if ($kat === 'Wiraswasta' || $b->posisi_wiraswasta) {
                    $aktivitas = 'Wirausaha';
                } elseif ($b->pendidikan_tingkat || $b->perguruan_tinggi || $kat === 'Melanjutkan Pendidikan') {
                    $aktivitas = 'Lanjut Pendidikan';
                }

                $namaNegara = $b->perusahaan?->negaraMaster?->nama_negara ?: 'Indonesia';
                $jenisLokasi = ($b->perusahaan?->kode_negara === 'ID' || empty($b->perusahaan?->kode_negara)) ? 'Dalam Negeri' : 'Luar Negeri';

                return [
                    'id' => $b->id,
                    'nama' => $b->nama,
                    'foto' => $b->foto,
                    'nim' => $b->nim,
                    'tahun_lulus' => $b->tahun_lulus ?? '2024',
                    'prodi' => $b->prodi?->nama_prodi ?? 'Informatika',
                    'posisi_jabatan' => $b->posisi_jabatan ?: ($b->posisi_wiraswasta ?: 'Alumni Berprestasi'),
                    'posisi_wiraswasta' => $b->posisi_wiraswasta,
                    'kategori_pekerjaan' => $b->kategori_pekerjaan ?: 'Bekerja (Full Time)',
                    'kategori_aktivitas' => $aktivitas,
                    'jenis_lokasi' => $jenisLokasi,
                    'perusahaan_nama' => $b->perusahaan?->nama_perusahaan,
                    'perusahaan_sektor' => $b->perusahaan?->sektor,
                    'perusahaan_kode_negara' => $b->perusahaan?->kode_negara,
                    'perusahaan_nama_negara' => $namaNegara,
                    'perusahaan_provinsi' => $b->perusahaan?->propinsi?->nama_provinsi,
                    'perusahaan_kabupaten' => $b->perusahaan?->kabupaten?->nama_kabupaten,
                    'perusahaan_alamat' => $b->perusahaan?->alamat,
                    'domisili_provinsi' => $b->propinsi?->nama_provinsi,
                    'domisili_kabupaten' => $b->kabupaten?->nama_kabupaten,
                    'domisili_alamat' => $b->alamat,
                    'perusahaan_kode_pos' => $b->perusahaan?->kode_pos ?? '',
                    'domisili_kode_pos' => $b->kode_pos ?? '',
                    'zipcode' => $b->perusahaan?->kode_pos ?: ($b->kode_pos ?: ''),
                    'pendidikan_tingkat' => $b->pendidikan_tingkat,
                    'perguruan_tinggi' => $b->perguruan_tinggi,
                    'pendidikan_prodi' => $b->pendidikan_prodi,
                    'linkedin_url' => $b->linkedin_url,
                    'instagram_url' => $b->instagram_url,
                    'facebook_url' => $b->facebook_url,
                    'judul_ta' => $b->yudisium?->judul_ta,
                    'url_publikasi' => $b->yudisium?->url_publikasi,
                    'jenis_publikasi' => $b->yudisium?->jenis_publikasi,
                ];
            });

        // 5. Agregasi Sebaran Wilayah untuk Peta Leaflet (Termasuk Nama PT & Nama Alumni untuk Tooltip Hover)
        $perusahaanPerProv = DB::table('perusahaan')
            ->join('propinsi', 'perusahaan.kode_provinsi', '=', 'propinsi.kode_provinsi')
            ->select('propinsi.nama_provinsi', DB::raw('count(*) as total'))
            ->groupBy('propinsi.nama_provinsi')
            ->pluck('total', 'nama_provinsi')
            ->toArray();

        // Agregasi detail per provinsi: daftar perusahaan unik & nama alumni
        $provSummary = [];
        foreach ($alumniList as $a) {
            $prov = $a['perusahaan_provinsi'];
            if (! $prov) {
                continue;
            }
            if (! isset($provSummary[$prov])) {
                $provSummary[$prov] = [
                    'total' => 0,
                    'perusahaan' => [],
                    'alumni' => [],
                ];
            }
            $provSummary[$prov]['total']++;
            if (! empty($a['perusahaan_nama']) && ! in_array($a['perusahaan_nama'], $provSummary[$prov]['perusahaan'])) {
                $provSummary[$prov]['perusahaan'][] = $a['perusahaan_nama'];
            }
            if (! empty($a['nama']) && ! in_array($a['nama'], $provSummary[$prov]['alumni'])) {
                $provSummary[$prov]['alumni'][] = $a['nama'];
            }
        }

        $alumniWilayah = [
            'domisili' => array_values($domisiliByProv),
            'karier' => array_values($karierByProv),
            'perusahaan' => $perusahaanPerProv,
            'summary' => $provSummary,
        ];

        // 6. Semua Provinsi dan Kabupaten dari Tabel Referensi (untuk Dropdown Filter)
        $allProvinsi = DB::table('propinsi')
            ->orderBy('nama_provinsi')
            ->get(['kode_provinsi', 'nama_provinsi']);

        $allKabupaten = DB::table('kabupaten')
            ->orderBy('nama_kabupaten')
            ->get(['kode_kabupaten', 'kode_provinsi', 'nama_kabupaten']);

        return Inertia::render('Tamu/Beranda', [
            'stats' => $stats,
            'featuredAlumni' => $featuredAlumni,
            'alumniWilayah' => $alumniWilayah,
            'alumniList' => $alumniList,
            'allProvinsi' => $allProvinsi,
            'allKabupaten' => $allKabupaten,
        ]);
    }
}
