<?php

namespace App\Http\Controllers\Alumni\Profil;

use App\Http\Controllers\Controller;
use App\Mail\UndanganEvaluasiAtasanMail;
use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use App\Models\LogActivity;
use App\Models\Perusahaan;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * SimpanProfilController
 *
 * Fungsi: Menangani penyimpanan profil tracer alumni tanpa mengubah data referensi akademik.
 * Tujuan: Memisahkan logika penyimpanan dari logika penampilan data (Single Responsibility).
 */
class SimpanProfilController extends Controller
{
    /**
     * Menyimpan Perubahan Profil
     */
    public function simpanPerubahanProfil(Request $request)
    {
        $request->validate([
            'profile_answers' => ['nullable', 'array'],
            'profile_answer_custom' => ['nullable', 'array'],
        ]);

        if ($request->hasFile('foto')) {
            $request->validate([
                'foto' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ]);
        }

        $pengguna = $request->user();
        $biodata = $pengguna->biodata;

        $dataTervalidasi = $request->all();

        // =========================================================================================
        // 2. TANGANI PERUSAHAAN (DALAM NEGERI VS LUAR NEGERI)
        // =========================================================================================
        $idPerusahaan = null;

        // Cek apakah alumni mengisi nama perusahaan pada FormKarier
        if (! empty($dataTervalidasi['nama_perusahaan'])) {
            $jenisLokasi = ! empty($dataTervalidasi['perusahaan_jenis_lokasi']) ? $dataTervalidasi['perusahaan_jenis_lokasi'] : (! empty($dataTervalidasi['company_jenis_lokasi']) ? $dataTervalidasi['company_jenis_lokasi'] : 'Dalam Negeri');
            $rawNegara = ! empty($dataTervalidasi['perusahaan_negara']) ? $dataTervalidasi['perusahaan_negara'] : (! empty($dataTervalidasi['company_negara']) ? $dataTervalidasi['company_negara'] : null);
            $negara = 'Indonesia';
            if ($jenisLokasi === 'Luar Negeri') {
                $negara = ($rawNegara && strtolower((string) $rawNegara) !== 'indonesia') ? $rawNegara : 'Singapura';
            }

            $propinsiId = $jenisLokasi === 'Luar Negeri'
                ? null
                : (! empty($dataTervalidasi['company_province_id']) ? $dataTervalidasi['company_province_id'] : (! empty($dataTervalidasi['perusahaan_propinsi_id']) ? $dataTervalidasi['perusahaan_propinsi_id'] : null));

            $kabupatenId = $jenisLokasi === 'Luar Negeri'
                ? null
                : (! empty($dataTervalidasi['company_kabupaten_id']) ? $dataTervalidasi['company_kabupaten_id'] : (! empty($dataTervalidasi['perusahaan_kabupaten_id']) ? $dataTervalidasi['perusahaan_kabupaten_id'] : null));

            $jenisPerusahaan = ! empty($dataTervalidasi['company_jenis_perusahaan']) ? $dataTervalidasi['company_jenis_perusahaan'] : (! empty($dataTervalidasi['perusahaan_jenis_perusahaan']) ? $dataTervalidasi['perusahaan_jenis_perusahaan'] : null);
            $jenisPerusahaanLainnya = ! empty($dataTervalidasi['company_jenis_perusahaan_lainnya']) ? $dataTervalidasi['company_jenis_perusahaan_lainnya'] : (! empty($dataTervalidasi['perusahaan_jenis_perusahaan_lainnya']) ? $dataTervalidasi['perusahaan_jenis_perusahaan_lainnya'] : null);
            $skala = ! empty($dataTervalidasi['company_skala']) ? $dataTervalidasi['company_skala'] : (! empty($dataTervalidasi['perusahaan_skala']) ? $dataTervalidasi['perusahaan_skala'] : null);
            $alamat = ! empty($dataTervalidasi['company_alamat']) ? $dataTervalidasi['company_alamat'] : (! empty($dataTervalidasi['perusahaan_alamat']) ? $dataTervalidasi['perusahaan_alamat'] : null);
            $kodePos = ! empty($dataTervalidasi['company_kode_pos']) ? $dataTervalidasi['company_kode_pos'] : (! empty($dataTervalidasi['perusahaan_kode_pos']) ? $dataTervalidasi['perusahaan_kode_pos'] : (! empty($dataTervalidasi['zipcode']) ? $dataTervalidasi['zipcode'] : null));

            $prodiIdPengaju = $pengguna?->prodi_id ?? $biodata?->prodi_id;
            $perusahaan = Perusahaan::firstOrCreate(
                ['nama_perusahaan' => $dataTervalidasi['nama_perusahaan']],
                [
                    'propinsi_id' => $propinsiId,
                    'kabupaten_id' => $kabupatenId,
                    'alamat' => $alamat,
                    'kode_pos' => $kodePos,
                    'skala' => $skala ?: 'Nasional',
                    'jenis_perusahaan' => $jenisPerusahaan,
                    'jenis_perusahaan_lainnya' => $jenisPerusahaanLainnya,
                    'jenis_lokasi' => $jenisLokasi,
                    'negara' => $negara,
                    'status_verifikasi' => 'Menunggu Verifikasi',
                    'created_by_user_id' => $pengguna?->id,
                    'created_by_prodi_id' => $prodiIdPengaju,
                ]
            );

            // Jika perusahaan belum terverifikasi atau alumni adalah founder/wiraswasta, perbarui data master
            $kategoriPekerjaan = $dataTervalidasi['kategori_pekerjaan'] ?? null;
            $posisiJabatan = $dataTervalidasi['posisi_jabatan'] ?? null;
            $isOwnerOrFounder = ($kategoriPekerjaan === 'Wiraswasta') || ($posisiJabatan && in_array(strtolower((string) $posisiJabatan), [
                'owner', 'founder', 'wiraswasta', 'wirausaha', 'wiraswasta / wirausaha', 'owner / founder',
            ]));

            if ($perusahaan->status_verifikasi !== 'Terverifikasi' || $isOwnerOrFounder) {
                $perusahaan->update([
                    'propinsi_id' => $propinsiId,
                    'kabupaten_id' => $kabupatenId,
                    'alamat' => $alamat ?: $perusahaan->alamat,
                    'kode_pos' => $kodePos ?: $perusahaan->kode_pos,
                    'skala' => $skala ?: $perusahaan->skala,
                    'jenis_perusahaan' => $jenisPerusahaan ?: $perusahaan->jenis_perusahaan,
                    'jenis_perusahaan_lainnya' => $jenisPerusahaanLainnya,
                    'jenis_lokasi' => $jenisLokasi,
                    'negara' => $negara,
                ]);
            }
            $idPerusahaan = $perusahaan->id;
        }

        // =========================================================================================
        // 3. TANGANI DATA ATASAN (SUPERVISOR / AUTO-FILL OWNER)
        // =========================================================================================
        // 3. TANGANI DATA ATASAN & KATEGORI PEKERJAAN
        // =========================================================================================
        // Jangan menghapus relasi atasan hanya karena form parsial tidak membawa field atasan.
        $idAtasan = $biodata->atasan_id;
        $kategoriPekerjaan = ! empty($dataTervalidasi['kategori_pekerjaan']) ? $dataTervalidasi['kategori_pekerjaan'] : null;
        $posisiJabatan = ! empty($dataTervalidasi['posisi_jabatan']) ? $dataTervalidasi['posisi_jabatan'] : null;
        $posisiWiraswasta = ! empty($dataTervalidasi['posisi_wiraswasta']) ? $dataTervalidasi['posisi_wiraswasta'] : null;
        if ($posisiWiraswasta === 'Lainnya' && ! empty($dataTervalidasi['posisi_wiraswasta_lainnya'])) {
            $posisiWiraswasta = $dataTervalidasi['posisi_wiraswasta_lainnya'];
        }
        $bidangPekerjaan = ! empty($dataTervalidasi['bidang_pekerjaan']) ? trim((string) $dataTervalidasi['bidang_pekerjaan']) : null;
        if ($bidangPekerjaan && strtolower($bidangPekerjaan) === 'lainnya') {
            $bidangPekerjaan = ! empty($dataTervalidasi['bidang_pekerjaan_lainnya'])
                ? trim((string) $dataTervalidasi['bidang_pekerjaan_lainnya'])
                : null;
        }

        // Pastikan posisi saling eksklusif sesuai pilihan peran
        if ($kategoriPekerjaan === 'Pekerja') {
            $posisiWiraswasta = null;
        } elseif ($kategoriPekerjaan === 'Wiraswasta') {
            $posisiJabatan = null;
        } elseif ($kategoriPekerjaan === 'Melanjutkan Pendidikan') {
            $posisiJabatan = null;
            $posisiWiraswasta = null;
        }

        $isOwnerOrFounder = ($kategoriPekerjaan === 'Wiraswasta') || ($posisiJabatan && in_array(strtolower((string) $posisiJabatan), [
            'owner',
            'founder',
            'wiraswasta',
            'wirausaha',
            'wiraswasta / wirausaha',
            'owner / founder',
        ]));

        $namaAtasan = ! empty($dataTervalidasi['nama_atasan']) ? $dataTervalidasi['nama_atasan'] : null;
        $emailAtasan = ! empty($dataTervalidasi['email_atasan']) ? $dataTervalidasi['email_atasan'] : null;
        $teleponAtasan = ! empty($dataTervalidasi['telepon_atasan']) ? $dataTervalidasi['telepon_atasan'] : null;

        // Jika alumni adalah Owner/Founder/Wiraswasta, otomatis isi data atasan dengan data diri alumni
        if ($isOwnerOrFounder) {
            $namaAtasan = $namaAtasan ?: ($dataTervalidasi['nama'] ?? $biodata->nama);
            $emailAtasan = $emailAtasan ?: ($dataTervalidasi['email_pribadi'] ?? ($biodata->email_pribadi ?? $biodata->email));
            $teleponAtasan = $teleponAtasan ?: ($dataTervalidasi['nomor_telepon'] ?? $biodata->nomor_telepon);
        }

        if (! empty($namaAtasan) || ! empty($emailAtasan) || ! empty($teleponAtasan)) {
            if ($biodata->atasan_id && $biodata->atasan) {
                $biodata->atasan->update([
                    'nama' => $namaAtasan ?: $biodata->atasan->nama,
                    'email' => $emailAtasan ?: $biodata->atasan->email,
                    'telepon' => $teleponAtasan ?: $biodata->atasan->telepon,
                ]);
                $idAtasan = $biodata->atasan->id;
            } else {
                $atasan = Atasan::create([
                    'nama' => $namaAtasan ?: ($dataTervalidasi['nama'] ?? 'Atasan'),
                    'email' => $emailAtasan,
                    'telepon' => $teleponAtasan,
                ]);
                $idAtasan = $atasan->id;
            }

        }

        // Gaji / Take Home Pay (bersihkan karakter non-digit dan simpan nominal murni)
        $gajiNominal = null;
        if (isset($dataTervalidasi['gaji']) && $dataTervalidasi['gaji'] !== '' && $dataTervalidasi['gaji'] !== null) {
            $rawGaji = (string) $dataTervalidasi['gaji'];
            if (preg_match('/^\d+(\.\d{1,2})?$/', $rawGaji)) {
                $gajiInt = (int) round((float) $rawGaji);
            } else {
                $rawGaji = preg_replace('/[.,]\d{2}$/', '', $rawGaji);
                $cleanGaji = preg_replace('/[^0-9]/', '', $rawGaji);
                $gajiInt = (int) $cleanGaji;
            }

            if ($gajiInt > 0 && $gajiInt < 1000) {
                return back()->withErrors([
                    'gaji' => 'Nominal rata-rata pendapatan minimal ribuan (minimal Rp 1.000) atau kosongkan kolom ini jika tidak berkenan membagikan nominal.',
                ]);
            }
            $gajiNominal = $gajiInt > 0 ? $gajiInt : null;
        }

        // Sinkronkan email pengguna pada tabel users jika email pribadi diisi
        if (! empty($dataTervalidasi['email_pribadi'])) {
            $pengguna->update([
                'email' => $dataTervalidasi['email_pribadi'],
            ]);
        }

        // =========================================================================================
        // 6. UPDATE PROFIL BIODATA ALUMNI UTAMA (BEBAS DUPLIKASI DATA AKADEMIK)
        // =========================================================================================
        $biodata->update([
            'nomor_telepon' => ! empty($dataTervalidasi['nomor_telepon']) ? $dataTervalidasi['nomor_telepon'] : null,
            'email_pribadi' => ! empty($dataTervalidasi['email_pribadi']) ? $dataTervalidasi['email_pribadi'] : null,
            'alamat_saat_ini' => ! empty($dataTervalidasi['alamat_saat_ini']) ? $dataTervalidasi['alamat_saat_ini'] : (! empty($dataTervalidasi['alamat']) ? $dataTervalidasi['alamat'] : null),
            'kabupaten_id' => ! empty($dataTervalidasi['kabupaten_id']) ? $dataTervalidasi['kabupaten_id'] : null,
            'propinsi_id' => ! empty($dataTervalidasi['propinsi_id']) ? $dataTervalidasi['propinsi_id'] : (! empty($dataTervalidasi['provinsi_id']) ? $dataTervalidasi['provinsi_id'] : null),
            'kode_pos' => ! empty($dataTervalidasi['kode_pos']) ? $dataTervalidasi['kode_pos'] : null,
            'instagram_url' => ! empty($dataTervalidasi['instagram_url']) ? $dataTervalidasi['instagram_url'] : null,
            'facebook_url' => ! empty($dataTervalidasi['facebook_url']) ? $dataTervalidasi['facebook_url'] : null,
            'linkedin_url' => ! empty($dataTervalidasi['linkedin_url']) ? $dataTervalidasi['linkedin_url'] : null,
            'linkedin_username' => ! empty($dataTervalidasi['linkedin_username']) ? $dataTervalidasi['linkedin_username'] : null,
            'skills' => ! empty($dataTervalidasi['skills']) ? $dataTervalidasi['skills'] : (! empty($dataTervalidasi['expert']) ? $dataTervalidasi['expert'] : null),
            'experience' => ! empty($dataTervalidasi['experience']) ? $dataTervalidasi['experience'] : (! empty($dataTervalidasi['minat']) ? $dataTervalidasi['minat'] : null),
            'kategori_pekerjaan' => $kategoriPekerjaan,
            'posisi_jabatan' => $posisiJabatan,
            'bidang_pekerjaan' => $bidangPekerjaan,
            'posisi_wiraswasta' => $posisiWiraswasta,
            'pendidikan_tingkat' => ! empty($dataTervalidasi['pendidikan_tingkat']) ? $dataTervalidasi['pendidikan_tingkat'] : null,
            'perguruan_tinggi' => ! empty($dataTervalidasi['perguruan_tinggi']) ? $dataTervalidasi['perguruan_tinggi'] : null,
            'pendidikan_prodi' => ! empty($dataTervalidasi['pendidikan_prodi']) ? $dataTervalidasi['pendidikan_prodi'] : null,
            'gaji' => $gajiNominal !== null ? $gajiNominal : null,
            'jenis_pekerjaan' => ! empty($dataTervalidasi['jenis_pekerjaan']) ? $dataTervalidasi['jenis_pekerjaan'] : (! empty($dataTervalidasi['company_jenis_perusahaan']) ? $dataTervalidasi['company_jenis_perusahaan'] : (! empty($dataTervalidasi['perusahaan_jenis_perusahaan']) ? $dataTervalidasi['perusahaan_jenis_perusahaan'] : null)),
            'perusahaan_id' => $idPerusahaan,
            'atasan_id' => $idAtasan,
        ]);

        // Tangani unggah foto profil mandiri (jika ada file diunggah)
        if ($request->hasFile('foto')) {
            $fotoFile = $request->file('foto');
            if ($fotoFile->isValid()) {
                $dir = public_path('uploads/profile');
                if (! file_exists($dir)) {
                    mkdir($dir, 0755, true);
                }
                $safeNim = preg_replace('/[^a-zA-Z0-9_-]/', '', $biodata->nim ?: 'alumni');
                $filename = "profile_{$safeNim}_".Str::uuid().'.'.$fotoFile->extension();
                $fotoFile->move($dir, $filename);
                $biodata->update(['foto' => "/uploads/profile/{$filename}"]);
            }
        }

        $biodata->refresh();

        // Rekam rekam jejak aktivitas (Audit Trail)
        LogActivity::record(
            'UPDATE_PROFIL_ALUMNI',
            "Alumni {$pengguna->name} ({$biodata->nim}) memperbarui data profil & kontak pribadi.",
            $biodata
        );

        // Sinkronisasi otomatis ke tabel tracer kuesioner
        KuesionerSyncService::syncProfileResponses($biodata);
        KuesionerSyncService::syncDynamicProfileResponses(
            $biodata,
            $dataTervalidasi['profile_answers'] ?? [],
            $dataTervalidasi['profile_answer_custom'] ?? []
        );

        // Jika data atasan dan email atasan terisi, buat / sinkronkan token evaluasi publik (email dikirim terpisah via tombol kirim)
        if ($idAtasan && $emailAtasan && filter_var($emailAtasan, FILTER_VALIDATE_EMAIL)) {
            try {
                $evaluasi = EvaluasiAtasan::firstOrCreate(
                    ['biodata_id' => $biodata->id],
                    [
                        'perusahaan_id' => $biodata->perusahaan_id,
                        'atasan_id' => $idAtasan,
                        'token' => Str::random(40),
                        'nama_perusahaan' => $biodata->perusahaan?->nama_perusahaan,
                        'alamat_lengkap' => $biodata->perusahaan?->alamat,
                    ]
                );

                $evaluasiUpdates = [];
                if ($evaluasi->atasan_id !== $idAtasan) {
                    $evaluasiUpdates['atasan_id'] = $idAtasan;
                }
                if ($biodata->perusahaan_id && $evaluasi->perusahaan_id !== $biodata->perusahaan_id) {
                    $evaluasiUpdates['perusahaan_id'] = $biodata->perusahaan_id;
                    $evaluasiUpdates['nama_perusahaan'] = $biodata->perusahaan?->nama_perusahaan;
                    $evaluasiUpdates['alamat_lengkap'] = $biodata->perusahaan?->alamat;
                }
                if (! empty($evaluasiUpdates)) {
                    $evaluasi->update($evaluasiUpdates);
                }
            } catch (\Throwable $e) {
                Log::error('Gagal menyinkronkan data evaluasi atasan: '.$e->getMessage());
            }
        }

        return redirect()->back()->with('success', 'Profil biodata berhasil diperbarui.');
    }

    /**
     * Mengirimkan email undangan kuesioner evaluasi atasan secara manual oleh alumni atau administrator.
     */
    public function kirimEmailAtasan(Request $request)
    {
        $user = $request->user();
        $biodata = null;

        if (in_array($user->role, ['superadmin', 'admin_fakultas', 'admin_prodi', 'admin_biro3'])) {
            $biodataId = $request->input('biodata_id') ?? $request->input('alumni_id');
            if ($biodataId) {
                $biodata = Biodata::with(['perusahaan', 'atasan', 'prodi.fakultas', 'dataAkademik'])->find($biodataId);
            }

            if (! $biodata) {
                return redirect()->back()->with('error', 'Data biodata alumni tidak ditemukan.');
            }

            // Validasi otorisasi lingkup peran admin
            if ($user->role === 'admin_prodi' && $user->prodi_id) {
                if ((int) $biodata->prodi_id !== (int) $user->prodi_id) {
                    abort(403, 'Anda tidak memiliki akses ke data alumni program studi ini.');
                }
            } elseif ($user->role === 'admin_fakultas' && $user->fakultas_id) {
                if ((int) $biodata->prodi?->fakultas_id !== (int) $user->fakultas_id) {
                    abort(403, 'Anda tidak memiliki akses ke data alumni fakultas ini.');
                }
            }
        } else {
            $biodata = $user->biodata;
        }

        if (! $biodata) {
            return redirect()->back()->with('error', 'Data biodata alumni tidak ditemukan.');
        }

        $emailAtasan = $request->input('email_atasan') ?: ($biodata->atasan?->email ?: null);
        if (! $emailAtasan || ! filter_var($emailAtasan, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->with('error', 'Alamat email atasan belum diisi atau format tidak valid. Mohon lengkapi pada form atasan terlebih dahulu.');
        }

        // Sinkronkan email ke tabel atasan jika belum ada atau berbeda
        if ($biodata->atasan) {
            if ($biodata->atasan->email !== $emailAtasan) {
                $biodata->atasan->update(['email' => $emailAtasan]);
            }
        } else {
            $atasan = Atasan::create([
                'nama' => 'Atasan',
                'email' => $emailAtasan,
            ]);
            $biodata->update(['atasan_id' => $atasan->id]);
            $biodata->setRelation('atasan', $atasan);
        }

        $idAtasan = $biodata->atasan_id;
        $evaluasi = EvaluasiAtasan::firstOrCreate(
            ['biodata_id' => $biodata->id],
            [
                'perusahaan_id' => $biodata->perusahaan_id,
                'atasan_id' => $idAtasan,
                'token' => Str::random(40),
                'nama_perusahaan' => $biodata->perusahaan?->nama_perusahaan,
                'alamat_lengkap' => $biodata->perusahaan?->alamat,
            ]
        );

        // Pastikan token selalu terisi jika record evaluasi sudah ada sebelumnya tanpa token
        if (empty($evaluasi->token)) {
            $evaluasi->token = Str::random(40);
        }
        if (! $evaluasi->atasan_id && $idAtasan) {
            $evaluasi->atasan_id = $idAtasan;
        }
        if (! $evaluasi->perusahaan_id && $biodata->perusahaan_id) {
            $evaluasi->perusahaan_id = $biodata->perusahaan_id;
            $evaluasi->nama_perusahaan = $biodata->perusahaan?->nama_perusahaan;
            $evaluasi->alamat_lengkap = $biodata->perusahaan?->alamat;
        }
        if ($evaluasi->isDirty()) {
            $evaluasi->save();
        }

        try {
            Mail::to($emailAtasan)->send(new UndanganEvaluasiAtasanMail($biodata, $evaluasi));
            Log::info("Email evaluasi atasan terkirim ke: {$emailAtasan} | Alumni: {$biodata->nama} (NIM: {$biodata->nim}) | Evaluasi ID: {$evaluasi->id}");

            return redirect()->back()->with('success', "Email undangan evaluasi berhasil dikirimkan ke atasan ({$emailAtasan}).");
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email evaluasi ke atasan: '.$e->getMessage());

            return redirect()->back()->with('error', 'Gagal mengirim email: '.$e->getMessage());
        }
    }

    /**
     * Menyimpan Perusahaan Baru Langsung dari Modal Pop-up Tambah Perusahaan
     */
    public function tambahPerusahaanBaru(Request $request)
    {
        $jenisLokasi = $request->input('jenis_lokasi', 'Dalam Negeri');

        $rules = [
            'nama_perusahaan' => 'required|string|max:255',
            'jenis_lokasi' => 'nullable|string|in:Dalam Negeri,Luar Negeri',
            'negara' => $jenisLokasi === 'Luar Negeri' ? 'required|string|max:100|not_in:Indonesia|exists:ref_negara,nama_negara' : 'nullable|string|max:100',
            'propinsi_id' => 'nullable|exists:propinsi,id',
            'province_id' => 'nullable|exists:propinsi,id',
            'kabupaten_id' => $jenisLokasi === 'Luar Negeri' ? 'nullable|exists:kabupaten,id' : 'required|exists:kabupaten,id',
            'kode_pos' => 'nullable|string|max:15',
            'alamat' => 'nullable|string',
            'skala' => 'nullable|string',
            'jenis_perusahaan' => 'nullable|string|max:50',
            'jenis_perusahaan_lainnya' => 'nullable|string|max:150',
        ];

        $messages = [
            'nama_perusahaan.required' => 'Nama perusahaan wajib diisi.',
            'kabupaten_id.required' => 'Kabupaten/Kota perusahaan wajib dipilih untuk lokasi Dalam Negeri.',
            'negara.required' => 'Nama negara tempat perusahaan berada wajib dipilih.',
            'negara.not_in' => 'Indonesia tidak dapat dipilih untuk lokasi Luar Negeri.',
            'negara.exists' => 'Negara yang dipilih tidak terdaftar dalam master data negara resmi dunia.',
        ];

        $validated = $request->validate($rules, $messages);

        $propinsiId = $jenisLokasi === 'Luar Negeri' ? null : ($validated['propinsi_id'] ?? ($validated['province_id'] ?? null));
        $kabupatenId = $jenisLokasi === 'Luar Negeri' ? null : ($validated['kabupaten_id'] ?? null);
        $negara = $jenisLokasi === 'Luar Negeri' ? $validated['negara'] : 'Indonesia';

        $user = Auth::user();
        $biodata = $user?->biodata;
        $prodiIdPengaju = $user?->prodi_id ?? $biodata?->prodi_id;

        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => $validated['nama_perusahaan'],
            'jenis_lokasi' => $jenisLokasi,
            'negara' => $negara,
            'propinsi_id' => $propinsiId,
            'kabupaten_id' => $kabupatenId,
            'alamat' => $validated['alamat'] ?? null,
            'kode_pos' => $validated['kode_pos'] ?? null,
            'skala' => $validated['skala'] ?? ($jenisLokasi === 'Luar Negeri' ? 'Internasional' : 'Lokal'),
            'jenis_perusahaan' => $validated['jenis_perusahaan'] ?? null,
            'jenis_perusahaan_lainnya' => $validated['jenis_perusahaan_lainnya'] ?? null,
            'status_verifikasi' => 'Menunggu Verifikasi',
            'created_by_user_id' => $user?->id,
            'created_by_prodi_id' => $prodiIdPengaju,
        ]);

        if ($user?->role === 'alumni' && $biodata) {
            $biodata->update(['perusahaan_id' => $perusahaan->id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Perusahaan berhasil ditambahkan ke database! Status: Menunggu Verifikasi.',
            'perusahaan' => $perusahaan,
            'company' => $perusahaan,
        ]);
    }
}
