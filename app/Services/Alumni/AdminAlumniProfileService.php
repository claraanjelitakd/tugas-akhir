<?php

namespace App\Services\Alumni;

use App\Models\AlumniProfileSummary;
use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\LogActivity;
use App\Models\Perusahaan;
use App\Models\RefSubpertanyaanDetil;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * AdminAlumniProfileService
 *
 * Layanan terpadu (Single Source of Truth) untuk:
 * 1. Menyusun array formData lengkap untuk dikirim ke frontend Vue (buildFormData).
 * 2. Menyimpan seluruh entitas profil alumni secara atomik (updateProfile).
 *
 * Digunakan oleh seluruh role administrator:
 * - Super Admin: DetailAlumniSuperAdminController
 * - Biro 3   : DetailAlumniController (AdminBiroTiga)
 * - Fakultas  : DetailAlumniFakultasController
 * - Prodi     : DaftarAlumniProdiController
 *
 * Alasan sentralisasi:
 * Mengoptimalkan pembacaan data profil menggunakan Database View `v_alumni_profile_summary`
 * sehingga data profil teragregasi instan
 * tanpa overhead N+1 query.
 */
class AdminAlumniProfileService
{
    /**
     * Susun array formData lengkap untuk dikirim ke frontend Vue.
     *
     * Diutamakan membaca dari Database View `v_alumni_profile_summary` (AlumniProfileSummary)
     * dengan fallback berjenjang ke relasi Eloquent.
     *
     * @param  Biodata  $alumni  Model biodata alumni.
     * @return array<string, mixed> Array flat key-value siap dikirim ke Inertia.
     */
    public static function buildFormData(Biodata $alumni): array
    {
        $summary = $alumni->profileSummary ?? AlumniProfileSummary::where('biodata_id', $alumni->id)->first();
        $dataAkademik = $alumni->dataAkademik;
        $yudisium = $alumni->yudisium ?? $dataAkademik?->yudisium;
        $atasan = $alumni->atasan;
        $perusahaan = $alumni->perusahaan;

        return [
            // 1. Identitas Pribadi (Diutamakan dari view v_alumni_profile_summary)
            'nim' => $summary?->nim ?? ($alumni->nim ?? ($dataAkademik?->nim ?? '')),
            'nama' => $summary?->nama ?? ($alumni->nama ?? ($dataAkademik?->nama ?? '')),
            'tempat_lahir' => $summary?->tempat_lahir ?? ($alumni->tempat_lahir ?? ($dataAkademik?->tempat_lahir ?? '')),
            'tanggal_lahir' => $summary?->tanggal_lahir ?? ($alumni->tanggal_lahir ?? ($dataAkademik?->tanggal_lahir ?? '')),
            'agama' => $summary?->agama ?? ($alumni->agama ?? ($dataAkademik?->agama ?? '')),
            'jenis_kelamin' => $summary?->jenis_kelamin ?? ($alumni->jenis_kelamin ?? ($dataAkademik?->jenis_kelamin ?? '')),
            'golongan_darah' => $summary?->golongan_darah ?? ($alumni->golongan_darah ?? ($dataAkademik?->golongan_darah ?? '')),
            'warga_negara' => $summary?->warga_negara ?? ($alumni->warga_negara ?? ($dataAkademik?->warga_negara ?? 'WNI')),
            'foto' => $alumni->foto ?? '',

            // Kontak & Alamat Pribadi
            'alamat_saat_ini' => $summary?->alamat ?? ($alumni->alamat_saat_ini ?? ($dataAkademik?->alamat_saat_ini ?? '')),
            'kelurahan' => $summary?->kelurahan ?? ($alumni->kelurahan ?? ($dataAkademik?->kelurahan ?? '')),
            'kecamatan' => $summary?->kecamatan ?? ($alumni->kecamatan ?? ($dataAkademik?->kecamatan ?? '')),
            'kabupaten_id' => $summary?->kabupaten_id ?? ($alumni->kabupaten_id ?? ($dataAkademik?->kabupaten_id ?? '')),
            'propinsi_id' => $summary?->propinsi_id ?? ($alumni->propinsi_id ?? ($dataAkademik?->propinsi_id ?? '')),
            'provinsi_id' => $summary?->propinsi_id ?? ($alumni->propinsi_id ?? ($dataAkademik?->propinsi_id ?? '')),
            'kode_pos' => $summary?->kode_pos ?? ($alumni->kode_pos ?? ($dataAkademik?->kode_pos ?? '')),
            'nomor_telepon' => $summary?->nomor_telepon ?? ($alumni->nomor_telepon ?? ($dataAkademik?->nomor_telepon ?? '')),
            'email_pribadi' => $summary?->email_pribadi ?? ($alumni->email_pribadi ?? ($dataAkademik?->email_pribadi ?? '')),
            'email' => $summary?->email ?? ($alumni->email_pribadi ?? ($dataAkademik?->email_pribadi ?? '')),

            // 2. Data Akademik
            'prodi_id' => $summary?->prodi_id ?? ($alumni->prodi_id ?? ($dataAkademik?->prodi_id ?? '')),
            'kode_prodi' => $summary?->kode_prodi ?? ($alumni->prodi?->kode_prodi ?? ''),
            'program_studi' => $summary?->nama_prodi ?? ($alumni->prodi?->nama_prodi ?? ''),
            'fakultas' => $summary?->nama_fakultas ?? ($alumni->prodi?->fakultas?->nama_fakultas ?? ''),
            'angkatan_masuk' => $summary?->angkatan_masuk ?? ($dataAkademik?->angkatan_masuk ?? ''),
            'status_mahasiswa' => $summary?->status_mahasiswa ?? ($dataAkademik?->status_mahasiswa ?? 'AR'),
            'tahun_akademik_lulus' => $summary?->tahun_akademik_lulus ?? ($dataAkademik?->tahun_akademik_lulus ?? ''),
            'tahun_lulus' => $summary?->tahun_lulus ? (string) $summary->tahun_lulus : ($dataAkademik?->tahun_lulus ? (string) $dataAkademik->tahun_lulus : ''),
            'ipk' => $summary?->ipk ?? ($dataAkademik?->ip_kumulatif ?? ''),
            'ip_kumulatif' => $summary?->ipk ?? ($dataAkademik?->ip_kumulatif ?? ''),
            'total_sks' => $summary?->total_sks ?? ($dataAkademik?->total_sks ?? ''),
            'total_angka_kualitas' => $summary?->total_angka_kualitas ?? ($dataAkademik?->total_angka_kualitas ?? ''),
            'asal_sekolah' => $summary?->asal_sekolah ?? ($dataAkademik?->asal_sekolah ?? ''),

            // Tugas akhir dan tanggal kelulusan tetap tersedia; data sensitif lain tetap diminimalkan.
            'judul_ta' => $yudisium?->judul_ta ?? '',
            'judul_ta_inggris' => $yudisium?->judul_ta_inggris ?? '',
            'tanggal_lulus' => $dataAkademik?->tanggal_lulus?->format('Y-m-d') ?? '',

            // 2. Karier, Media Sosial, Keahlian & Penghasilan
            'instagram_url' => $summary?->instagram_url ?? ($alumni->instagram_url ?? ''),
            'facebook_url' => $summary?->facebook_url ?? ($alumni->facebook_url ?? ''),
            'linkedin_url' => $summary?->linkedin_url ?? ($alumni->linkedin_url ?? ''),
            'linkedin_username' => $summary?->linkedin_username ?? ($alumni->linkedin_username ?? ''),
            'skills' => $summary?->skills ?? ($alumni->skills ?? ''),
            'experience' => $summary?->experience ?? ($alumni->experience ?? ''),
            'kategori_pekerjaan' => $summary?->kategori_pekerjaan ?? ($alumni->kategori_pekerjaan ?? ''),
            'posisi_jabatan' => $summary?->posisi_jabatan ?? ($alumni->posisi_jabatan ?? ''),
            'bidang_pekerjaan' => $summary?->bidang_pekerjaan ?? ($alumni->bidang_pekerjaan ?? ''),
            'posisi_wiraswasta' => $summary?->posisi_wiraswasta ?? ($alumni->posisi_wiraswasta ?? ''),
            'pendidikan_tingkat' => $summary?->pendidikan_tingkat ?? ($alumni->pendidikan_tingkat ?? ''),
            'perguruan_tinggi' => $summary?->perguruan_tinggi ?? ($alumni->perguruan_tinggi ?? ''),
            'pendidikan_prodi' => $summary?->pendidikan_prodi ?? ($alumni->pendidikan_prodi ?? ''),
            'gaji' => ($summary?->gaji !== null) ? (int) $summary->gaji : (($alumni->gaji !== null) ? (int) $alumni->gaji : ''),
            'jenis_pekerjaan' => $summary?->jenis_pekerjaan ?? ($alumni->jenis_pekerjaan ?? ''),
            'zipcode' => $summary?->kode_pos ?? ($alumni->zipcode ?? ''),
            'is_profile_complete' => (int) ($summary?->is_profile_complete ?? 0),

            // Perusahaan
            'nama_perusahaan' => $perusahaan?->nama_perusahaan ?? '',
            'perusahaan_alamat' => $perusahaan?->alamat ?? '',
            'perusahaan_skala' => $perusahaan?->skala ?? '',
            'perusahaan_jenis_perusahaan' => $perusahaan?->jenis_perusahaan ?? '',
            'perusahaan_jenis_perusahaan_lainnya' => $perusahaan?->jenis_perusahaan_lainnya ?? '',
            'perusahaan_jenis_lokasi' => $perusahaan?->jenis_lokasi ?? 'Dalam Negeri',
            'perusahaan_negara' => $perusahaan?->negara ?? 'Indonesia',
            'perusahaan_propinsi_id' => $perusahaan?->propinsi_id ?? '',
            'perusahaan_kabupaten_id' => $perusahaan?->kabupaten_id ?? '',
            'perusahaan_status_verifikasi' => $perusahaan?->status_verifikasi ?? '',

            // Alias Company untuk kompatibilitas frontend
            'company_alamat' => $perusahaan?->alamat ?? '',
            'company_skala' => $perusahaan?->skala ?? '',
            'company_jenis_perusahaan' => $perusahaan?->jenis_perusahaan ?? '',
            'company_jenis_perusahaan_lainnya' => $perusahaan?->jenis_perusahaan_lainnya ?? '',
            'company_jenis_lokasi' => $perusahaan?->jenis_lokasi ?? 'Dalam Negeri',
            'company_negara' => $perusahaan?->negara ?? 'Indonesia',
            'company_province_id' => $perusahaan?->propinsi_id ?? '',
            'company_kabupaten_id' => $perusahaan?->kabupaten_id ?? '',
            'company_kode_pos' => $perusahaan?->kode_pos ?? '',
            'perusahaan_kode_pos' => $perusahaan?->kode_pos ?? '',
            'company_status_verifikasi' => $perusahaan?->status_verifikasi ?? '',

            // Atasan
            'nama_atasan' => $atasan?->nama ?? '',
            'email_atasan' => $atasan?->email ?? '',
            'telepon_atasan' => $atasan?->telepon ?? '',
        ];
    }

    /**
     * Ambil opsi referensi kuesioner resmi dari database.
     *
     * Mengambil daftar pilihan dari `ref_subpertanyaan_detil` untuk kode:
     * - F2G  : Posisi jabatan struktural (Pekerja)
     * - F2H  : Jenis pekerjaan / bidang usaha
     * - F5D  : Skala instansi/perusahaan
     * - F11  : Sumber biaya kuliah
     * - F8   : Status/kategori pekerjaan saat ini
     *
     * Hasil dikembalikan sebagai array ter-group by kode pertanyaan (uppercase)
     * untuk kemudahan akses di frontend Vue (misal: `refOptions['F2G']`).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function getRefOptions(): array
    {
        return RefSubpertanyaanDetil::whereIn('kode_pertanyaan', ['F2G', 'F2H', 'F5D', 'F11', 'F8', 'BIO_BIDANG_DIVISI', 'BIO_PEND_TINGKAT', 'f2g', 'f2h', 'f5d', 'f11', 'f8', 'bio_bidang_divisi', 'bio_pend_tingkat'])
            ->orderBy('order')
            ->get(['id', 'kode_pertanyaan', 'kode_opsi', 'option_text', 'order'])
            ->groupBy(fn ($item) => strtoupper($item->kode_pertanyaan))
            ->toArray();
    }

    /**
     * Simpan seluruh pembaruan profil alumni oleh Admin secara atomik.
     *
     * Data akademik dan yudisium hanya digunakan sebagai referensi.
     * Perubahan disimpan pada data transaksi tracer study seperti perusahaan,
     * atasan, biodata, dan jawaban kuesioner.
     *
     * Aturan eksklusivitas posisi (mencegah bug F5C terisi saat bukan wiraswasta):
     * - kategori_pekerjaan = 'Pekerja'              → posisi_wiraswasta = null
     * - kategori_pekerjaan = 'Wiraswasta'           → posisi_jabatan = null
     * - kategori_pekerjaan = 'Melanjutkan Pendidikan' → keduanya null
     *
     * @param  Biodata  $biodata  Model biodata yang akan diperbarui.
     * @param  array<string, mixed>  $data  Data request dari form frontend.
     */
    public static function updateProfile(Biodata $biodata, array $data): void
    {
        $fotoPath = self::storeUploadedPhoto($biodata, $data['foto'] ?? null);

        // Tangani data perusahaan tracer.
        $perusahaanId = $biodata->perusahaan_id;
        $namaPerusahaan = ! empty($data['nama_perusahaan']) ? trim($data['nama_perusahaan']) : null;
        if ($namaPerusahaan) {
            $jenisLokasi = ! empty($data['company_jenis_lokasi']) ? $data['company_jenis_lokasi'] : (! empty($data['perusahaan_jenis_lokasi']) ? $data['perusahaan_jenis_lokasi'] : 'Dalam Negeri');
            $negara = ($jenisLokasi === 'Luar Negeri') ? (! empty($data['company_negara']) ? $data['company_negara'] : (! empty($data['perusahaan_negara']) ? $data['perusahaan_negara'] : '')) : 'Indonesia';
            $provId = ($jenisLokasi === 'Luar Negeri') ? null : (! empty($data['company_province_id']) ? $data['company_province_id'] : (! empty($data['perusahaan_propinsi_id']) ? $data['perusahaan_propinsi_id'] : null));
            $kabId = ($jenisLokasi === 'Luar Negeri') ? null : (! empty($data['company_kabupaten_id']) ? $data['company_kabupaten_id'] : (! empty($data['perusahaan_kabupaten_id']) ? $data['perusahaan_kabupaten_id'] : null));
            $alamat = ! empty($data['company_alamat']) ? $data['company_alamat'] : (! empty($data['perusahaan_alamat']) ? $data['perusahaan_alamat'] : null);
            $skala = ! empty($data['company_skala']) ? $data['company_skala'] : (! empty($data['perusahaan_skala']) ? $data['perusahaan_skala'] : null);
            $jenisPerusahaan = ! empty($data['company_jenis_perusahaan']) ? $data['company_jenis_perusahaan'] : (! empty($data['perusahaan_jenis_perusahaan']) ? $data['perusahaan_jenis_perusahaan'] : null);
            $jenisPerusahaanLainnya = ! empty($data['company_jenis_perusahaan_lainnya']) ? $data['company_jenis_perusahaan_lainnya'] : (! empty($data['perusahaan_jenis_perusahaan_lainnya']) ? $data['perusahaan_jenis_perusahaan_lainnya'] : null);
            $kodePosKantor = ! empty($data['company_kode_pos']) ? $data['company_kode_pos'] : (! empty($data['perusahaan_kode_pos']) ? $data['perusahaan_kode_pos'] : (! empty($data['zipcode']) ? $data['zipcode'] : null));

            $perusahaan = Perusahaan::firstOrCreate(
                ['nama_perusahaan' => $namaPerusahaan],
                [
                    'jenis_lokasi' => $jenisLokasi,
                    'negara' => $negara,
                    'propinsi_id' => $provId,
                    'kabupaten_id' => $kabId,
                    'alamat' => $alamat,
                    'kode_pos' => $kodePosKantor,
                    'skala' => $skala,
                    'jenis_perusahaan' => $jenisPerusahaan,
                    'jenis_perusahaan_lainnya' => $jenisPerusahaanLainnya,
                    'status_verifikasi' => 'Terverifikasi',
                ]
            );

            $perusahaan->update([
                'jenis_lokasi' => $jenisLokasi,
                'negara' => $negara,
                'propinsi_id' => $provId,
                'kabupaten_id' => $kabId,
                'alamat' => $alamat ?: $perusahaan->alamat,
                'kode_pos' => $kodePosKantor ?: $perusahaan->kode_pos,
                'skala' => $skala ?: $perusahaan->skala,
                'jenis_perusahaan' => $jenisPerusahaan ?: $perusahaan->jenis_perusahaan,
                'jenis_perusahaan_lainnya' => $jenisPerusahaanLainnya ?: $perusahaan->jenis_perusahaan_lainnya,
            ]);

            $perusahaanId = $perusahaan->id;
        }

        // 4. Tangani Data Atasan & Peran
        $kategoriPekerjaan = ! empty($data['kategori_pekerjaan']) ? $data['kategori_pekerjaan'] : null;
        $posisiJabatan = ! empty($data['posisi_jabatan']) ? $data['posisi_jabatan'] : null;
        $posisiWiraswasta = ! empty($data['posisi_wiraswasta']) ? $data['posisi_wiraswasta'] : null;
        if ($posisiWiraswasta === 'Lainnya' && ! empty($data['posisi_wiraswasta_lainnya'])) {
            $posisiWiraswasta = $data['posisi_wiraswasta_lainnya'];
        }
        $bidangPekerjaan = ! empty($data['bidang_pekerjaan']) ? trim((string) $data['bidang_pekerjaan']) : null;
        if ($bidangPekerjaan && strtolower($bidangPekerjaan) === 'lainnya') {
            $bidangPekerjaan = ! empty($data['bidang_pekerjaan_lainnya'])
                ? trim((string) $data['bidang_pekerjaan_lainnya'])
                : null;
        }

        if ($kategoriPekerjaan === 'Pekerja') {
            $posisiWiraswasta = null;
        } elseif ($kategoriPekerjaan === 'Wiraswasta') {
            $posisiJabatan = null;
        } elseif ($kategoriPekerjaan === 'Melanjutkan Pendidikan') {
            $posisiJabatan = null;
            $posisiWiraswasta = null;
        }

        $atasanId = $biodata->atasan_id;
        $namaAtasan = ! empty($data['nama_atasan']) ? $data['nama_atasan'] : null;
        $emailAtasan = ! empty($data['email_atasan']) ? $data['email_atasan'] : null;
        $teleponAtasan = ! empty($data['telepon_atasan']) ? $data['telepon_atasan'] : null;

        if ($namaAtasan || $emailAtasan || $teleponAtasan) {
            $atasan = ! empty($biodata->atasan_id) ? Atasan::find($biodata->atasan_id) : null;
            if ($atasan) {
                $atasan->update([
                    'nama' => $namaAtasan ?: $atasan->nama,
                    'email' => $emailAtasan,
                    'telepon' => $teleponAtasan,
                ]);
            } else {
                $atasan = Atasan::create([
                    'nama' => $namaAtasan ?: 'Atasan',
                    'email' => $emailAtasan,
                    'telepon' => $teleponAtasan,
                ]);
            }
            $atasanId = $atasan->id;
        }

        // 5. Sanitasi Gaji / Penghasilan
        // Normalisasi input gaji agar tidak berlipat ganda setiap kali disimpan.
        // Kasus yang ditangani:
        //   - Format desimal bersih (misal "5000000.00") → round ke integer
        //   - Format ribuan dengan separator (misal "5.000.000" atau "5,000,000") →
        //     hapus pola desimal 2 digit di akhir terlebih dahulu, lalu strip semua non-digit
        $gajiNominal = null;
        if (isset($data['gaji']) && $data['gaji'] !== '' && $data['gaji'] !== null) {
            $rawGaji = (string) $data['gaji'];
            if (preg_match('/^\d+(\.\d{1,2})?$/', $rawGaji)) {
                // Angka desimal murni (misal dari DB atau input numerik langsung)
                $gajiInt = (int) round((float) $rawGaji);
            } else {
                // Format ribuan: buang dulu pola "xxx.00" atau "xxx,00" di akhir,
                // kemudian strip semua karakter non-numerik
                $rawGaji = preg_replace('/[.,]\d{2}$/', '', $rawGaji);
                $cleanGaji = preg_replace('/[^0-9]/', '', $rawGaji);
                $gajiInt = (int) $cleanGaji;
            }
            $gajiNominal = $gajiInt > 0 ? $gajiInt : null;
        }

        // Update biodata dengan kontak, domisili, dan data tracer yang bersifat transaksional.
        $biodataUpdates = [
            'nomor_telepon' => ! empty($data['nomor_telepon']) ? $data['nomor_telepon'] : null,
            'email_pribadi' => ! empty($data['email_pribadi']) ? $data['email_pribadi'] : (! empty($data['email']) ? $data['email'] : $biodata->email_pribadi),
            'alamat_saat_ini' => ! empty($data['alamat_saat_ini']) ? $data['alamat_saat_ini'] : null,
            'kabupaten_id' => ! empty($data['kabupaten_id']) ? $data['kabupaten_id'] : null,
            'propinsi_id' => ! empty($data['propinsi_id']) ? $data['propinsi_id'] : (! empty($data['provinsi_id']) ? $data['provinsi_id'] : null),
            'kode_pos' => ! empty($data['kode_pos']) ? $data['kode_pos'] : null,
            'instagram_url' => ! empty($data['instagram_url']) ? $data['instagram_url'] : null,
            'facebook_url' => ! empty($data['facebook_url']) ? $data['facebook_url'] : null,
            'linkedin_url' => ! empty($data['linkedin_url']) ? $data['linkedin_url'] : null,
            'linkedin_username' => ! empty($data['linkedin_username']) ? $data['linkedin_username'] : null,
            'skills' => ! empty($data['skills']) ? $data['skills'] : null,
            'experience' => ! empty($data['experience']) ? $data['experience'] : null,
            'kategori_pekerjaan' => $kategoriPekerjaan,
            'posisi_jabatan' => $posisiJabatan,
            'bidang_pekerjaan' => $bidangPekerjaan,
            'posisi_wiraswasta' => $posisiWiraswasta,
            'pendidikan_tingkat' => ! empty($data['pendidikan_tingkat']) ? $data['pendidikan_tingkat'] : null,
            'perguruan_tinggi' => ! empty($data['perguruan_tinggi']) ? $data['perguruan_tinggi'] : null,
            'pendidikan_prodi' => ! empty($data['pendidikan_prodi']) ? $data['pendidikan_prodi'] : null,
            'gaji' => $gajiNominal !== null ? $gajiNominal : null,
            'jenis_pekerjaan' => ! empty($data['jenis_pekerjaan']) ? $data['jenis_pekerjaan'] : (! empty($data['company_jenis_perusahaan']) ? $data['company_jenis_perusahaan'] : (! empty($data['perusahaan_jenis_perusahaan']) ? $data['perusahaan_jenis_perusahaan'] : null)),
            'perusahaan_id' => $perusahaanId,
            'atasan_id' => $atasanId,
        ];

        if ($fotoPath !== null) {
            $biodataUpdates['foto'] = $fotoPath;
        }

        $biodata->update($biodataUpdates);

        // 8. Sinkronisasi email_pribadi ke users.email
        if (! empty($biodataUpdates['email_pribadi']) && $biodata->user) {
            $biodata->user->update(['email' => $biodataUpdates['email_pribadi']]);
        }

        // 9. Catat Log Activity Audit Trail
        LogActivity::record(
            action: 'update_profil_alumni_oleh_admin',
            model: 'Biodata',
            modelId: $biodata->id,
            details: [
                'nim' => $biodata->nim,
                'diperbarui_oleh' => auth()->user()?->name ?? 'Admin',
                'role_admin' => auth()->user()?->role ?? 'admin',
            ],
            description: "Admin memperbarui profil alumni NIM {$biodata->nim}"
        );

        $biodata->refresh();

        // 10. Sinkronisasi Otomatis ke Jawaban Kuesioner Tracer
        KuesionerSyncService::syncProfileResponses($biodata);
    }

    /**
     * Simpan foto baru jika admin memang mengunggah file.
     *
     * String URL foto lama sengaja diabaikan agar tidak diperlakukan sebagai file baru.
     */
    private static function storeUploadedPhoto(Biodata $biodata, mixed $photo): ?string
    {
        if (! $photo instanceof UploadedFile) {
            return null;
        }

        if (! $photo->isValid()) {
            throw ValidationException::withMessages([
                'foto' => 'Foto gagal diunggah. Silakan pilih file lain.',
            ]);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($photo->getMimeType(), $allowedMimeTypes, true)) {
            throw ValidationException::withMessages([
                'foto' => 'Format foto harus JPG, PNG, atau WebP.',
            ]);
        }

        if (($photo->getSize() ?? 0) > 2 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'foto' => 'Ukuran foto maksimal 2MB.',
            ]);
        }

        $directory = public_path('uploads/profile');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $safeNim = preg_replace('/[^a-zA-Z0-9_-]/', '', $biodata->nim ?: 'alumni');
        $filename = "profile_{$safeNim}_".Str::uuid().'.'.$photo->extension();
        $photo->move($directory, $filename);

        return "/uploads/profile/{$filename}";
    }
}
