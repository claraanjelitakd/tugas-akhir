<?php

namespace App\Services\Alumni;

use App\Models\AlumniProfileSummary;
use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\LogActivity;
use App\Models\Perusahaan;
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
            'tanggal_lahir' => $summary?->tanggal_lahir ?? ($alumni->tanggal_lahir ?? ($dataAkademik?->tanggal_lahir ?? '')),
            'jenis_kelamin' => $summary?->jenis_kelamin ?? ($alumni->jenis_kelamin ?? ($dataAkademik?->jenis_kelamin ?? '')),
            'warga_negara' => $summary?->warga_negara ?? ($alumni->warga_negara ?? ($dataAkademik?->warga_negara ?? 'WNI')),
            'foto' => $alumni->foto ?? '',

            // Kontak & Alamat Pribadi
            'alamat_saat_ini' => $summary?->alamat ?? ($alumni->alamat_saat_ini ?? ''),
            'kelurahan' => $summary?->kelurahan ?? ($alumni->kelurahan ?? ''),
            'kecamatan' => $summary?->kecamatan ?? ($alumni->kecamatan ?? ''),
            'kode_kabupaten' => $summary?->kode_kabupaten ?? ($alumni->kode_kabupaten ?? ''),
            'kode_provinsi' => $summary?->kode_provinsi ?? ($alumni->kode_provinsi ?? ''),
            'kode_pos' => $summary?->kode_pos ?? ($alumni->kode_pos ?? ''),
            'nomor_telepon' => $summary?->nomor_telepon ?? ($alumni->nomor_telepon ?? ''),
            'email_pribadi' => $summary?->email_pribadi ?? ($alumni->email_pribadi ?? ''),
            'email' => $summary?->email ?? ($alumni->email_pribadi ?? ''),

            // 2. Data Akademik
            'prodi_id' => $summary?->prodi_id ?? ($alumni->prodi_id ?? ''),
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
            'divisi' => $summary?->divisi ?? ($alumni->divisi ?? ''),
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
            'perusahaan_kode_negara' => $perusahaan?->kode_negara ?? 'ID',
            'perusahaan_kode_provinsi' => $perusahaan?->kode_provinsi ?? '',
            'perusahaan_kode_kabupaten' => $perusahaan?->kode_kabupaten ?? '',
            'perusahaan_kode_pos' => $perusahaan?->kode_pos ?? '',
            'perusahaan_homepage' => $perusahaan?->homepage ?? '',
            'perusahaan_no_telp_fax' => $perusahaan?->no_telp_fax ?? '',
            'perusahaan_status_verifikasi' => $perusahaan?->status_verifikasi ?? '',

            // Alias Company untuk kompatibilitas frontend
            'company_alamat' => $perusahaan?->alamat ?? '',
            'company_skala' => $perusahaan?->skala ?? '',
            'company_jenis_perusahaan' => $perusahaan?->jenis_perusahaan ?? '',
            'company_jenis_perusahaan_lainnya' => $perusahaan?->jenis_perusahaan_lainnya ?? '',
            'company_jenis_lokasi' => $perusahaan?->jenis_lokasi ?? 'Dalam Negeri',
            'company_kode_negara' => $perusahaan?->kode_negara ?? 'ID',
            'company_kode_provinsi' => $perusahaan?->kode_provinsi ?? '',
            'company_kode_kabupaten' => $perusahaan?->kode_kabupaten ?? '',
            'company_kode_pos' => $perusahaan?->kode_pos ?? '',
            'company_homepage' => $perusahaan?->homepage ?? '',
            'company_no_telp_fax' => $perusahaan?->no_telp_fax ?? '',
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
     * Mengambil daftar pilihan dari `kuesioner_opsi` untuk kode:
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
        return KuesionerOpsi::join('kuesioner_pertanyaan', 'kuesioner_pertanyaan.id', '=', 'kuesioner_opsi.pertanyaan_id')
            ->whereIn('kuesioner_pertanyaan.kode_pertanyaan', [
                'F2G', 'F2H', 'F5D', 'F11', 'F8', 'F5C', 'BIO_BIDANG_DIVISI', 'BIO_PEND_TINGKAT',
                'BIO_WARGA_NEGARA', 'BIO_INST_BENTUK', 'BIO_INST_LOKASI',
                'f2g', 'f2h', 'f5d', 'f11', 'f8', 'f5c', 'bio_bidang_divisi', 'bio_pend_tingkat',
                'bio_warga_negara', 'bio_inst_bentuk', 'bio_inst_lokasi',
            ])
            ->orderBy('kuesioner_opsi.order')
            ->select([
                'kuesioner_opsi.id',
                'kuesioner_pertanyaan.kode_pertanyaan',
                'kuesioner_opsi.kode_opsi',
                'kuesioner_opsi.label as option_text',
                'kuesioner_opsi.order',
            ])
            ->get()
            ->groupBy(fn ($item) => strtoupper($item->kode_pertanyaan))
            ->toArray();
    }

    /**
     * Mengambil seluruh konfigurasi pertanyaan profil alumni dari kuesioner terpadu.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getProfileQuestionConfigs(): array
    {
        return KuesionerPertanyaan::whereHas('sections', function ($q) {
            $q->whereIn('kuesioner_section_pertanyaan.tampil_di', ['profil', 'profile', 'both'])
                ->where('kuesioner_section_pertanyaan.is_active', true);
        })
            ->orWhereIn('kuesioner_pertanyaan.kode_pertanyaan', ['F8', 'F11', 'F5D', 'F2G', 'F2H'])
            ->get(['id', 'kode_pertanyaan', 'teks_pertanyaan as subpertanyaan', 'keterangan', 'tipe as type'])
            ->keyBy(fn ($item) => strtoupper($item->kode_pertanyaan))
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
        $namaPerusahaan = self::nullableInput($data, 'nama_perusahaan', null);
        if ($namaPerusahaan) {
            [$hasJenisLokasi, $jenisLokasiInput] = self::firstProvidedInput($data, ['company_jenis_lokasi', 'perusahaan_jenis_lokasi']);
            [$hasKodeNegara, $kodeNegaraInput] = self::firstProvidedInput($data, ['company_kode_negara', 'perusahaan_kode_negara']);
            [$hasKodeProvinsi, $kodeProvinsiInput] = self::firstProvidedInput($data, ['company_kode_provinsi', 'perusahaan_kode_provinsi']);
            [$hasKodeKabupaten, $kodeKabupatenInput] = self::firstProvidedInput($data, ['company_kode_kabupaten', 'perusahaan_kode_kabupaten']);
            [$hasAlamat, $alamatInput] = self::firstProvidedInput($data, ['company_alamat', 'perusahaan_alamat']);
            [$hasSkala, $skalaInput] = self::firstProvidedInput($data, ['company_skala', 'perusahaan_skala']);
            [$hasJenisPerusahaan, $jenisPerusahaanInput] = self::firstProvidedInput($data, ['company_jenis_perusahaan', 'perusahaan_jenis_perusahaan']);
            [$hasJenisPerusahaanLainnya, $jenisPerusahaanLainnyaInput] = self::firstProvidedInput($data, ['company_jenis_perusahaan_lainnya', 'perusahaan_jenis_perusahaan_lainnya']);
            [$hasKodePosKantor, $kodePosKantorInput] = self::firstProvidedInput($data, ['company_kode_pos', 'perusahaan_kode_pos', 'zipcode']);
            [$hasHomepage, $homepageInput] = self::firstProvidedInput($data, ['company_homepage', 'perusahaan_homepage']);
            [$hasNoTelpFax, $noTelpFaxInput] = self::firstProvidedInput($data, ['company_no_telp_fax', 'perusahaan_no_telp_fax']);

            $jenisLokasi = $hasJenisLokasi ? ($jenisLokasiInput ?: 'Dalam Negeri') : 'Dalam Negeri';
            $kodeNegara = $hasKodeNegara ? $kodeNegaraInput : ($jenisLokasi === 'Luar Negeri' ? '' : 'ID');
            $kodeProvinsi = $hasKodeProvinsi ? $kodeProvinsiInput : null;
            $kodeKabupaten = $hasKodeKabupaten ? $kodeKabupatenInput : null;
            $alamat = $hasAlamat ? $alamatInput : null;
            $skala = $hasSkala ? $skalaInput : null;
            $jenisPerusahaan = $hasJenisPerusahaan ? $jenisPerusahaanInput : null;
            $jenisPerusahaanLainnya = $hasJenisPerusahaanLainnya ? $jenisPerusahaanLainnyaInput : null;
            $kodePosKantor = $hasKodePosKantor ? $kodePosKantorInput : null;
            $homepage = $hasHomepage ? $homepageInput : null;
            $noTelpFax = $hasNoTelpFax ? $noTelpFaxInput : null;

            $perusahaan = Perusahaan::firstOrCreate(
                ['nama_perusahaan' => $namaPerusahaan],
                [
                    'jenis_lokasi' => $jenisLokasi,
                    'kode_negara' => $kodeNegara,
                    'kode_provinsi' => $kodeProvinsi,
                    'kode_kabupaten' => $kodeKabupaten,
                    'alamat' => $alamat,
                    'kode_pos' => $kodePosKantor,
                    'skala' => $skala,
                    'jenis_perusahaan' => $jenisPerusahaan,
                    'jenis_perusahaan_lainnya' => $jenisPerusahaanLainnya,
                    'homepage' => $homepage,
                    'no_telp_fax' => $noTelpFax,
                    'status_verifikasi' => 'Terverifikasi',
                ]
            );

            if (! $perusahaan->wasRecentlyCreated) {
                $perusahaanUpdates = [];

                if ($hasJenisLokasi) {
                    $perusahaanUpdates['jenis_lokasi'] = $jenisLokasi;
                }
                if ($hasKodeNegara) {
                    $perusahaanUpdates['kode_negara'] = $kodeNegaraInput;
                }
                if ($hasKodeProvinsi) {
                    $perusahaanUpdates['kode_provinsi'] = $kodeProvinsiInput;
                }
                if ($hasKodeKabupaten) {
                    $perusahaanUpdates['kode_kabupaten'] = $kodeKabupatenInput;
                }
                if ($hasAlamat) {
                    $perusahaanUpdates['alamat'] = $alamatInput;
                }
                if ($hasKodePosKantor) {
                    $perusahaanUpdates['kode_pos'] = $kodePosKantorInput;
                }
                if ($hasSkala) {
                    $perusahaanUpdates['skala'] = $skalaInput;
                }
                if ($hasJenisPerusahaan) {
                    $perusahaanUpdates['jenis_perusahaan'] = $jenisPerusahaanInput;
                }
                if ($hasJenisPerusahaanLainnya) {
                    $perusahaanUpdates['jenis_perusahaan_lainnya'] = $jenisPerusahaanLainnyaInput;
                }
                if ($hasHomepage) {
                    $perusahaanUpdates['homepage'] = $homepageInput;
                }
                if ($hasNoTelpFax) {
                    $perusahaanUpdates['no_telp_fax'] = $noTelpFaxInput;
                }

                if ($perusahaanUpdates !== []) {
                    $perusahaan->update($perusahaanUpdates);
                }
            }

            $perusahaanId = $perusahaan->id;
        }

        // 4. Tangani Data Atasan & Peran
        $kategoriPekerjaanProvided = array_key_exists('kategori_pekerjaan', $data);
        $kategoriPekerjaan = self::nullableInput($data, 'kategori_pekerjaan', $biodata->kategori_pekerjaan);
        $posisiJabatan = self::nullableInput($data, 'posisi_jabatan', $biodata->posisi_jabatan);
        $posisiWiraswasta = self::nullableInput($data, 'posisi_wiraswasta', $biodata->posisi_wiraswasta);
        if ($posisiWiraswasta && str_contains(strtolower($posisiWiraswasta), 'lainnya') && ! empty($data['posisi_wiraswasta_lainnya'])) {
            $posisiWiraswasta = $data['posisi_wiraswasta_lainnya'];
        }
        $bidangPekerjaan = self::nullableInput($data, 'bidang_pekerjaan', $biodata->bidang_pekerjaan);
        if ($bidangPekerjaan && str_contains(strtolower($bidangPekerjaan), 'lainnya')) {
            $bidangPekerjaan = ! empty($data['bidang_pekerjaan_lainnya'])
                ? trim((string) $data['bidang_pekerjaan_lainnya'])
                : null;
        }

        $isNonWorkingCategory = $kategoriPekerjaanProvided && in_array($kategoriPekerjaan, ['Tidak Bekerja', 'Mencari Kerja', 'Belum Bekerja', 'Belum Memungkinkan Bekerja'], true);

        if ($kategoriPekerjaanProvided && $kategoriPekerjaan === 'Pekerja') {
            $posisiWiraswasta = null;
        } elseif ($kategoriPekerjaanProvided && $kategoriPekerjaan === 'Wiraswasta') {
            $posisiJabatan = null;
        } elseif ($kategoriPekerjaanProvided && $kategoriPekerjaan === 'Melanjutkan Pendidikan') {
            $posisiJabatan = null;
            $posisiWiraswasta = null;
        } elseif ($isNonWorkingCategory) {
            $posisiJabatan = null;
            $posisiWiraswasta = null;
            $bidangPekerjaan = null;
        }

        $divisi = self::nullableInput($data, 'divisi', $biodata->divisi);
        if ($isNonWorkingCategory) {
            $divisi = null;
        }

        $atasanId = $biodata->atasan_id;
        $namaAtasanProvided = array_key_exists('nama_atasan', $data);
        $emailAtasanProvided = array_key_exists('email_atasan', $data);
        $teleponAtasanProvided = array_key_exists('telepon_atasan', $data);
        $namaAtasan = self::nullableInput($data, 'nama_atasan', null);
        $emailAtasan = self::nullableInput($data, 'email_atasan', null);
        $teleponAtasan = self::nullableInput($data, 'telepon_atasan', null);

        if ($namaAtasanProvided || $emailAtasanProvided || $teleponAtasanProvided) {
            $atasan = ! empty($biodata->atasan_id) ? Atasan::find($biodata->atasan_id) : null;
            if ($atasan) {
                $atasanUpdates = [];
                if ($perusahaanId && $perusahaanId !== $atasan->perusahaan_id) {
                    $atasanUpdates['perusahaan_id'] = $perusahaanId;
                }
                if ($namaAtasanProvided) {
                    $atasanUpdates['nama'] = $namaAtasan;
                }
                if ($emailAtasanProvided) {
                    $atasanUpdates['email'] = $emailAtasan;
                }
                if ($teleponAtasanProvided) {
                    $atasanUpdates['telepon'] = $teleponAtasan;
                }
                $atasan->update($atasanUpdates);
            } else {
                $atasan = Atasan::create([
                    'perusahaan_id' => $perusahaanId,
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
        $gajiProvided = array_key_exists('gaji', $data);
        $gajiNominal = $biodata->gaji;
        if ($gajiProvided && $data['gaji'] !== '' && $data['gaji'] !== null) {
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
        $emailPribadi = array_key_exists('email_pribadi', $data)
            ? self::nullableInput($data, 'email_pribadi', null)
            : (array_key_exists('email', $data) ? self::nullableInput($data, 'email', null) : $biodata->email_pribadi);
        $jenisPekerjaan = array_key_exists('jenis_pekerjaan', $data)
            ? self::nullableInput($data, 'jenis_pekerjaan', null)
            : (array_key_exists('company_jenis_perusahaan', $data)
                ? self::nullableInput($data, 'company_jenis_perusahaan', null)
                : (array_key_exists('perusahaan_jenis_perusahaan', $data)
                    ? self::nullableInput($data, 'perusahaan_jenis_perusahaan', null)
                    : $biodata->jenis_pekerjaan));

        $biodataUpdates = [
            'nomor_telepon' => self::nullableInput($data, 'nomor_telepon', $biodata->nomor_telepon),
            'email_pribadi' => $emailPribadi,
            'alamat_saat_ini' => self::nullableInput($data, 'alamat_saat_ini', $biodata->alamat_saat_ini),
            'kode_kabupaten' => self::nullableInput($data, 'kode_kabupaten', $biodata->kode_kabupaten),
            'kode_provinsi' => self::nullableInput($data, 'kode_provinsi', $biodata->kode_provinsi),
            'kode_pos' => self::nullableInput($data, 'kode_pos', $biodata->kode_pos),
            'instagram_url' => self::nullableInput($data, 'instagram_url', $biodata->instagram_url),
            'facebook_url' => self::nullableInput($data, 'facebook_url', $biodata->facebook_url),
            'linkedin_url' => self::nullableInput($data, 'linkedin_url', $biodata->linkedin_url),
            'linkedin_username' => self::nullableInput($data, 'linkedin_username', $biodata->linkedin_username),
            'skills' => self::nullableInput($data, 'skills', $biodata->skills),
            'experience' => self::nullableInput($data, 'experience', $biodata->experience),
            'kategori_pekerjaan' => $kategoriPekerjaan,
            'posisi_jabatan' => $posisiJabatan,
            'bidang_pekerjaan' => $bidangPekerjaan,
            'divisi' => $divisi,
            'posisi_wiraswasta' => $posisiWiraswasta,
            'pendidikan_tingkat' => self::nullableInput($data, 'pendidikan_tingkat', $biodata->pendidikan_tingkat),
            'perguruan_tinggi' => self::nullableInput($data, 'perguruan_tinggi', $biodata->perguruan_tinggi),
            'pendidikan_prodi' => self::nullableInput($data, 'pendidikan_prodi', $biodata->pendidikan_prodi),
            'gaji' => $gajiNominal,
            'jenis_pekerjaan' => $jenisPekerjaan,
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
     * Mengambil input nullable tanpa menghapus nilai lama saat key tidak dikirim.
     *
     * @param  array<string, mixed>  $data  Payload pembaruan profil.
     * @param  string  $key  Nama field yang dibaca.
     * @param  mixed  $fallback  Nilai lama jika field tidak ada pada payload.
     * @return mixed Nilai input yang sudah dinormalisasi atau nilai lama.
     */
    private static function nullableInput(array $data, string $key, mixed $fallback): mixed
    {
        if (! array_key_exists($key, $data)) {
            return $fallback;
        }

        $value = $data[$key];

        return is_string($value) ? (trim($value) !== '' ? trim($value) : null) : $value;
    }

    /**
     * Mengambil nilai pertama dari beberapa alias field dan membedakan antara
     * field yang tidak dikirim dengan field yang sengaja dikosongkan.
     *
     * @param  array<string, mixed>  $data  Payload pembaruan profil.
     * @param  array<int, string>  $keys  Alias field yang diterima.
     * @return array{0: bool, 1: mixed} Status keberadaan field dan nilainya.
     */
    private static function firstProvidedInput(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return [true, self::nullableInput($data, $key, null)];
            }
        }

        return [false, null];
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
