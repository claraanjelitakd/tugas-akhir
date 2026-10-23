<?php

namespace App\Http\Controllers\Alumni\Profil;

use App\Http\Controllers\Controller;
use App\Models\Kabupaten;
use App\Models\Perusahaan;
use App\Models\Propinsi;
use App\Models\RefNegara;
use App\Models\RefSubpertanyaanDetil;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Http\Request;

/**
 * ProfilController
 *
 * Fungsi: Mengelola halaman pengisian profil alumni (biodata diri dan data akademik).
 * Tujuan: Menyediakan antarmuka bagi alumni untuk memperbarui data pribadi dan pekerjaan mereka.
 */
class ProfilController extends Controller
{
    /**
     * Menampilkan Halaman Profil
     */
    public function tampilkanHalamanProfil(Request $request)
    {
        $pengguna = $request->user();
        $biodata = $pengguna->biodata()->with(['prodi.fakultas', 'perusahaan', 'perusahaan.propinsi', 'perusahaan.kabupaten', 'dataAkademik', 'yudisium', 'atasan', 'evaluasiAtasan'])->first();

        $propinsi = Propinsi::all();
        $kabupaten = Kabupaten::all();
        $negaras = RefNegara::orderBy('nama_negara')->get(['id', 'nama_negara', 'kode_iso2', 'benua']);
        $dataAkademik = $biodata?->dataAkademik;
        $yudisium = $biodata?->yudisium ?? $dataAkademik?->yudisium;
        $atasan = $biodata?->atasan;
        $profileQuestions = KuesionerSyncService::getDynamicProfileQuestions();
        $dynamicProfileAnswers = $biodata
            ? KuesionerSyncService::getDynamicProfileAnswers($biodata, $profileQuestions)
            : ['answers' => [], 'custom' => []];

        // Merakit formData murni di backend agar frontend Vue tidak perlu logika inisialisasi / pengecekan manual
        $formData = [
            // Identitas Pribadi (Prioritaskan nilai di tabel biodata, fallback ke data_akademik)
            'nim' => $biodata?->nim ?? '',
            'nama' => $biodata?->nama ?? ($dataAkademik?->nama ?? ''),
            'tempat_lahir' => $biodata?->tempat_lahir ?? ($dataAkademik?->tempat_lahir ?? ''),
            'tanggal_lahir' => $biodata?->tanggal_lahir ?? ($dataAkademik?->tanggal_lahir ?? ''),
            'agama' => $biodata?->agama ?? ($dataAkademik?->agama ?? ''),
            'jenis_kelamin' => $biodata?->jenis_kelamin ?? ($dataAkademik?->jenis_kelamin ?? ''),
            'golongan_darah' => $biodata?->golongan_darah ?? ($dataAkademik?->golongan_darah ?? ''),
            'warga_negara' => $biodata?->warga_negara ?? ($dataAkademik?->warga_negara ?? 'WNI'),
            'foto' => $biodata?->foto ?? '',

            // Kontak & Alamat Pribadi
            'alamat_saat_ini' => $biodata?->alamat_saat_ini ?? ($dataAkademik?->alamat_saat_ini ?? ''),
            'kelurahan' => $biodata?->kelurahan ?? ($dataAkademik?->kelurahan ?? ''),
            'kecamatan' => $biodata?->kecamatan ?? ($dataAkademik?->kecamatan ?? ''),
            'kabupaten_id' => $biodata?->kabupaten_id ?? ($dataAkademik?->kabupaten_id ?? ''),
            'propinsi_id' => $biodata?->propinsi_id ?? ($dataAkademik?->propinsi_id ?? ''),
            'provinsi_id' => $biodata?->propinsi_id ?? ($dataAkademik?->propinsi_id ?? ''),
            'kode_pos' => $biodata?->kode_pos ?? ($dataAkademik?->kode_pos ?? ''),
            'nomor_telepon' => $biodata?->nomor_telepon ?? ($dataAkademik?->nomor_telepon ?? ''),
            'email_pribadi' => $biodata?->email_pribadi ?? ($dataAkademik?->email_pribadi ?? $pengguna->email),

            // Data Akademik & Yudisium
            'prodi_id' => $biodata?->prodi_id,
            'kode_prodi' => $biodata?->prodi?->kode_prodi ?? '',
            'program_studi' => $biodata?->prodi?->nama_prodi ?? '',
            'fakultas' => $biodata?->prodi?->fakultas?->nama_fakultas ?? '',
            'angkatan_masuk' => $dataAkademik?->angkatan_masuk ?? '',
            'status_mahasiswa' => $dataAkademik?->status_mahasiswa ?? 'AR',
            'tahun_akademik_lulus' => $dataAkademik?->tahun_akademik_lulus ?? '',
            'tahun_lulus' => $dataAkademik?->tahun_lulus ? (string) $dataAkademik->tahun_lulus : '',
            'ipk' => $dataAkademik?->ip_kumulatif ?? '',
            'ip_kumulatif' => $dataAkademik?->ip_kumulatif ?? '',
            'total_sks' => $dataAkademik?->total_sks ?? '',
            'total_angka_kualitas' => $dataAkademik?->total_angka_kualitas ?? '',
            'asal_sekolah' => $dataAkademik?->asal_sekolah ?? '',

            // Tugas akhir dan tanggal kelulusan tetap ditampilkan sebagai data akademik non-sensitif.
            'judul_ta' => $yudisium?->judul_ta ?? '',
            'judul_ta_inggris' => $yudisium?->judul_ta_inggris ?? '',
            'tanggal_lulus' => $dataAkademik?->tanggal_lulus?->format('Y-m-d') ?? '',

            // Karier / Profil Profesional & Penghasilan
            'instagram_url' => $biodata?->instagram_url ?? '',
            'facebook_url' => $biodata?->facebook_url ?? '',
            'linkedin_url' => $biodata?->linkedin_url ?? '',
            'linkedin_username' => $biodata?->linkedin_username ?? '',

            'skills' => $biodata?->skills ?? '',
            'experience' => $biodata?->experience ?? '',
            'kategori_pekerjaan' => $biodata?->kategori_pekerjaan ?? '',
            'posisi_jabatan' => $biodata?->posisi_jabatan ?? '',
            'bidang_pekerjaan' => $biodata?->bidang_pekerjaan ?? '',
            'posisi_wiraswasta' => $biodata?->posisi_wiraswasta ?? '',
            'pendidikan_tingkat' => $biodata?->pendidikan_tingkat ?? '',
            'perguruan_tinggi' => $biodata?->perguruan_tinggi ?? '',
            'pendidikan_prodi' => $biodata?->pendidikan_prodi ?? '',
            'gaji' => $biodata?->gaji !== null ? (int) $biodata->gaji : '',
            'jenis_pekerjaan' => $biodata?->jenis_pekerjaan ?? '',
            'zipcode' => $biodata?->zipcode ?? '',

            'nama_perusahaan' => $biodata?->perusahaan?->nama_perusahaan ?? '',
            'perusahaan_alamat' => $biodata?->perusahaan?->alamat ?? '',
            'perusahaan_skala' => $biodata?->perusahaan?->skala ?? '',
            'perusahaan_jenis_perusahaan' => $biodata?->perusahaan?->jenis_perusahaan ?? '',
            'perusahaan_jenis_perusahaan_lainnya' => $biodata?->perusahaan?->jenis_perusahaan_lainnya ?? '',
            'perusahaan_jenis_lokasi' => $biodata?->perusahaan?->jenis_lokasi ?? 'Dalam Negeri',
            'perusahaan_negara' => $biodata?->perusahaan?->negara ?? 'Indonesia',
            'perusahaan_propinsi_id' => $biodata?->perusahaan?->propinsi_id ?? '',
            'perusahaan_kabupaten_id' => $biodata?->perusahaan?->kabupaten_id ?? '',
            'perusahaan_status_verifikasi' => $biodata?->perusahaan?->status_verifikasi ?? '',

            // Fallback key untuk kompatibilitas frontend
            'company_alamat' => $biodata?->perusahaan?->alamat ?? '',
            'company_skala' => $biodata?->perusahaan?->skala ?? '',
            'company_jenis_perusahaan' => $biodata?->perusahaan?->jenis_perusahaan ?? '',
            'company_jenis_perusahaan_lainnya' => $biodata?->perusahaan?->jenis_perusahaan_lainnya ?? '',
            'company_jenis_lokasi' => $biodata?->perusahaan?->jenis_lokasi ?? 'Dalam Negeri',
            'company_negara' => $biodata?->perusahaan?->negara ?? 'Indonesia',
            'company_province_id' => $biodata?->perusahaan?->propinsi_id ?? '',
            'company_kabupaten_id' => $biodata?->perusahaan?->kabupaten_id ?? '',
            'company_kode_pos' => $biodata?->perusahaan?->kode_pos ?? '',
            'perusahaan_kode_pos' => $biodata?->perusahaan?->kode_pos ?? '',
            'company_status_verifikasi' => $biodata?->perusahaan?->status_verifikasi ?? '',

            // Data Atasan
            'nama_atasan' => $atasan?->nama ?? '',
            'email_atasan' => $atasan?->email ?? '',
            'telepon_atasan' => $atasan?->telepon ?? '',

            // Pertanyaan tambahan yang dikonfigurasi Superadmin untuk halaman profil.
            'profile_answers' => $dynamicProfileAnswers['answers'],
            'profile_answer_custom' => $dynamicProfileAnswers['custom'],
        ];

        // Ambil opsi referensi kuesioner resmi dari database (F2G, F2H, F5D, F11, F8)
        $refOptions = RefSubpertanyaanDetil::whereIn('kode_pertanyaan', ['F2G', 'F2H', 'F5D', 'F11', 'F8', 'BIO_BIDANG_DIVISI', 'BIO_PEND_TINGKAT', 'f2g', 'f2h', 'f5d', 'f11', 'f8', 'bio_bidang_divisi', 'bio_pend_tingkat'])
            ->orderBy('order')
            ->get(['id', 'kode_pertanyaan', 'kode_opsi', 'option_text', 'order'])
            ->groupBy(fn ($item) => strtoupper($item->kode_pertanyaan))
            ->toArray();

        // Get all perusahaan for Autocomplete
        $perusahaans = Perusahaan::select('id', 'nama_perusahaan', 'propinsi_id', 'kabupaten_id', 'alamat', 'kode_pos', 'skala', 'jenis_perusahaan', 'jenis_perusahaan_lainnya', 'jenis_lokasi', 'negara', 'status_verifikasi')->get();

        return inertia('Alumni/Profil/Index', [
            // Hanya kirim konteks minimum yang dibutuhkan FormKarier; relasi akademik/keluarga
            // tidak diserialisasikan ke browser alumni.
            'alumniData' => [
                'id' => $biodata?->id,
                'nim' => $biodata?->nim,
                'prodi' => [
                    'kode_prodi' => $biodata?->prodi?->kode_prodi,
                ],
            ],
            'evaluasiAtasan' => $biodata?->evaluasiAtasan ? [
                'is_submitted' => (bool) $biodata->evaluasiAtasan->is_submitted,
                'has_invitation' => filled($biodata->evaluasiAtasan->token),
            ] : null,
            'formData' => $formData,
            'propinsis' => $propinsi,
            'provinces' => $propinsi,
            'kabupatens' => $kabupaten,
            'negaras' => $negaras,
            'perusahaans' => $perusahaans,
            'companies' => $perusahaans,
            'refOptions' => $refOptions,
            'profileQuestions' => $profileQuestions,
        ]);
    }
}
