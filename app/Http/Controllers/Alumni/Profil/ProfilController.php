<?php

namespace App\Http\Controllers\Alumni\Profil;

use App\Http\Controllers\Controller;
use App\Models\Kabupaten;
use App\Models\Perusahaan;
use App\Models\Propinsi;
use App\Models\RefNegara;
use App\Services\Alumni\AdminAlumniProfileService;
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
        $negaras = RefNegara::orderBy('nama_negara')->get(['kode_negara', 'nama_negara', 'kode_iso3', 'benua']);
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
            'tanggal_lahir' => $biodata?->tanggal_lahir ?? ($dataAkademik?->tanggal_lahir ?? ''),
            'jenis_kelamin' => $biodata?->jenis_kelamin ?? ($dataAkademik?->jenis_kelamin ?? ''),
            'warga_negara' => $biodata?->warga_negara ?? ($dataAkademik?->warga_negara ?? 'WNI'),
            'foto' => $biodata?->foto ?? '',

            // Kontak & Alamat Pribadi
            'alamat_saat_ini' => $biodata?->alamat_saat_ini ?? '',
            'kelurahan' => $biodata?->kelurahan ?? '',
            'kecamatan' => $biodata?->kecamatan ?? '',
            'kode_kabupaten' => $biodata?->kode_kabupaten ?? '',
            'kode_provinsi' => $biodata?->kode_provinsi ?? '',
            'kode_pos' => $biodata?->kode_pos ?? '',
            'nomor_telepon' => $biodata?->nomor_telepon ?? '',
            'email_pribadi' => $biodata?->email_pribadi ?? $pengguna->email,

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
            'divisi' => $biodata?->divisi ?? '',
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
            'perusahaan_bentuk' => $biodata?->perusahaan?->bentuk_perusahaan ?? '',
            'perusahaan_no_telp_fax' => $biodata?->perusahaan?->no_telp_fax ?? '',
            'perusahaan_homepage' => $biodata?->perusahaan?->homepage ?? '',
            'perusahaan_jenis_perusahaan' => $biodata?->perusahaan?->jenis_perusahaan ?? '',
            'perusahaan_jenis_perusahaan_lainnya' => $biodata?->perusahaan?->jenis_perusahaan_lainnya ?? '',
            'perusahaan_jenis_lokasi' => $biodata?->perusahaan?->jenis_lokasi ?? 'Dalam Negeri',
            'perusahaan_kode_negara' => $biodata?->perusahaan?->kode_negara ?? 'ID',
            'perusahaan_kode_provinsi' => $biodata?->perusahaan?->kode_provinsi ?? '',
            'perusahaan_kode_kabupaten' => $biodata?->perusahaan?->kode_kabupaten ?? '',
            'perusahaan_status_verifikasi' => $biodata?->perusahaan?->status_verifikasi ?? '',

            // Fallback key untuk kompatibilitas frontend
            'company_alamat' => $biodata?->perusahaan?->alamat ?? '',
            'company_skala' => $biodata?->perusahaan?->skala ?? '',
            'company_bentuk' => $biodata?->perusahaan?->bentuk_perusahaan ?? '',
            'company_no_telp_fax' => $biodata?->perusahaan?->no_telp_fax ?? '',
            'company_homepage' => $biodata?->perusahaan?->homepage ?? '',
            'company_jenis_perusahaan' => $biodata?->perusahaan?->jenis_perusahaan ?? '',
            'company_jenis_perusahaan_lainnya' => $biodata?->perusahaan?->jenis_perusahaan_lainnya ?? '',
            'company_jenis_lokasi' => $biodata?->perusahaan?->jenis_lokasi ?? 'Dalam Negeri',
            'company_kode_negara' => $biodata?->perusahaan?->kode_negara ?? 'ID',
            'company_kode_provinsi' => $biodata?->perusahaan?->kode_provinsi ?? '',
            'company_kode_kabupaten' => $biodata?->perusahaan?->kode_kabupaten ?? '',
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

        // Ambil opsi referensi kuesioner resmi dari database (F2G, F2H, F5D, F11, F8, F5C, dll)
        $refOptions = AdminAlumniProfileService::getRefOptions();

        // Konfigurasi dinamis butir pertanyaan profil dari Super Admin (label, keterangan, status wajib, tipe)
        $profileQuestionConfigs = AdminAlumniProfileService::getProfileQuestionConfigs();

        // Get all perusahaan for Autocomplete
        $perusahaans = Perusahaan::select('id', 'nama_perusahaan', 'kode_provinsi', 'kode_kabupaten', 'alamat', 'kode_pos', 'skala', 'bentuk_perusahaan', 'no_telp_fax', 'homepage', 'jenis_perusahaan', 'jenis_perusahaan_lainnya', 'jenis_lokasi', 'kode_negara', 'status_verifikasi')->get();

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
            'profileQuestionConfigs' => $profileQuestionConfigs,
        ]);
    }
}
