<?php

namespace Database\Seeders;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\ProdiQuestion;
use App\Models\ProdiResponse;
use App\Models\Propinsi;
use App\Models\RefSubpertanyaan2021;
use App\Models\Tracer;
use App\Models\User;
use App\Models\Yudisium;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DashboardScenarioSeeder extends Seeder
{
    /**
     * Menyiapkan data demo lintas tahun untuk dashboard tanpa menimpa data lama.
     *
     * Jumlah alumni per prodi merepresentasikan populasi lulusan, sedangkan jumlah
     * responden sengaja dibuat berbeda agar grafik response rate memiliki variasi.
     */
    public function run(): void
    {
        $prodis = Prodi::query()->get()->keyBy('kode_prodi');
        $propinsi = Propinsi::query()->where('kode_provinsi', '34')->first();
        $kabupaten = Kabupaten::query()->where('kode_kabupaten', '34.04')->first();
        $universityQuestions = RefSubpertanyaan2021::query()
            ->where('wajib', true)
            ->where('type', '!=', 'header')
            ->orderBy('order')
            ->get();

        if ($prodis->isEmpty() || ! $propinsi || ! $kabupaten) {
            $this->command?->warn('DashboardScenarioSeeder dilewati karena master prodi atau wilayah belum tersedia.');

            return;
        }

        $targetsByYear = [
            2024 => [
                'target' => ['71' => 60, '72' => 42, '11' => 64, '12' => 48, '21' => 30, '22' => 24, '31' => 18, '41' => 22, '42' => 26, '61' => 36, '81' => 45, '82' => 20],
                'respondents' => ['71' => 38, '72' => 29, '11' => 43, '12' => 28, '21' => 21, '22' => 14, '31' => 12, '41' => 16, '42' => 18, '61' => 25, '81' => 30, '82' => 11],
            ],
            2025 => [
                'target' => ['71' => 70, '72' => 48, '11' => 72, '12' => 55, '21' => 34, '22' => 27, '31' => 20, '41' => 25, '42' => 30, '61' => 38, '81' => 50, '82' => 22],
                'respondents' => ['71' => 46, '72' => 32, '11' => 50, '12' => 35, '21' => 22, '22' => 18, '31' => 15, '41' => 17, '42' => 23, '61' => 27, '81' => 37, '82' => 13],
            ],
            2026 => [
                'target' => ['71' => 80, '72' => 50, '11' => 81, '12' => 60, '21' => 36, '22' => 28, '31' => 20, '41' => 26, '42' => 32, '61' => 40, '81' => 55, '82' => 24],
                'respondents' => ['71' => 50, '72' => 35, '11' => 60, '12' => 39, '21' => 24, '22' => 17, '31' => 13, '41' => 19, '42' => 22, '61' => 30, '81' => 40, '82' => 14],
            ],
        ];

        $createdCount = 0;

        foreach ($targetsByYear as $year => $configuration) {
            foreach ($configuration['target'] as $prodiCode => $targetCount) {
                $prodi = $prodis->get($prodiCode);
                if (! $prodi) {
                    continue;
                }

                $respondentCount = $configuration['respondents'][$prodiCode] ?? 0;
                $prodiQuestions = ProdiQuestion::query()
                    ->where('prodi_id', $prodi->id)
                    ->where('type', '!=', 'header')
                    ->with('options')
                    ->orderBy('order')
                    ->get();

                for ($sequence = 1; $sequence <= $targetCount; $sequence++) {
                    $nim = $prodiCode.substr((string) ($year - 4), -2).str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                    if ($this->seedAlumni($nim, $sequence, $year, $respondentCount, $prodi, $propinsi, $kabupaten, $universityQuestions, $prodiQuestions)) {
                        $createdCount++;
                    }
                }
            }
        }

        $this->command?->info("Dashboard scenario selesai. {$createdCount} alumni baru dibuat tanpa mengubah data lama.");
    }

    /**
     * @param  Collection<int, RefSubpertanyaan2021>  $universityQuestions
     * @param  Collection<int, ProdiQuestion>  $prodiQuestions
     */
    private function seedAlumni(
        string $nim,
        int $sequence,
        int $graduationYear,
        int $respondentCount,
        Prodi $prodi,
        Propinsi $propinsi,
        Kabupaten $kabupaten,
        $universityQuestions,
        $prodiQuestions,
    ): bool {
        if (DataAkademik::query()->where('nim', $nim)->exists()) {
            return false;
        }

        return DB::transaction(function () use ($nim, $sequence, $graduationYear, $respondentCount, $prodi, $propinsi, $kabupaten, $universityQuestions, $prodiQuestions): bool {
            $isRespondent = $sequence <= $respondentCount;
            $isProfileComplete = $isRespondent || $sequence % 11 !== 0;
            $firstNames = ['Aurelia', 'Bagas', 'Catherine', 'Dimas', 'Elisa', 'Felix', 'Gisella', 'Hendra', 'Intan', 'Jovan', 'Kezia', 'Lukas'];
            $lastNames = ['Pratama', 'Wijaya', 'Kusuma', 'Santoso', 'Nugraha', 'Lestari', 'Haryanto', 'Saputra', 'Wibowo', 'Handayani', 'Setiawan', 'Permata'];
            $name = $firstNames[($sequence - 1) % count($firstNames)].' '.$lastNames[($sequence + (int) $prodi->kode_prodi) % count($lastNames)].' '.$prodi->kode_prodi.'-'.$sequence;
            $email = 'demo.'.strtolower($nim).'@alumni.ukdw.ac.id';
            $entryYear = $graduationYear - 4;
            $birthDate = ($entryYear - 18).'-'.str_pad((string) (($sequence % 9) + 1), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) (($sequence % 26) + 1), 2, '0', STR_PAD_LEFT);
            $salary = 5000000 + (($sequence * 375000) % 8500000);
            $hasCareer = $isRespondent && $sequence % 5 !== 0;
            $company = $hasCareer ? Perusahaan::query()->first() : null;
            $atasan = $hasCareer ? Atasan::firstOrCreate(
                ['email' => 'atasan.demo.'.strtolower($nim).'@example.test'],
                ['nama' => 'Atasan '.$prodi->nama_prodi.' '.$sequence, 'telepon' => '0812'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT)],
            ) : null;

            $user = User::create([
                'username' => $nim,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('1508'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT)),
                'role' => 'alumni',
                'prodi_id' => $prodi->id,
                'must_change_password' => true,
            ]);

            $ipk = round(3.20 + (($sequence * 7) % 75) / 100, 2);
            $totalSks = $prodi->kode_prodi === '61' ? 160 : 144;
            $dataAkademik = DataAkademik::create([
                'nim' => $nim,
                'nama' => $name,
                'angkatan_masuk' => $entryYear,
                'status_mahasiswa' => 'Lulus',
                'tahun_akademik_lulus' => 'Genap '.($graduationYear - 1).'/'.$graduationYear,
                'tahun_lulus' => $graduationYear,
                'tanggal_lulus' => $graduationYear.'-08-15',
                'ip_kumulatif' => $ipk,
                'total_sks' => $totalSks,
                'total_angka_kualitas' => round($ipk * $totalSks, 2),
                'tempat_lahir' => ['Yogyakarta', 'Semarang', 'Jakarta', 'Surabaya'][$sequence % 4],
                'tanggal_lahir' => $birthDate,
                'agama' => ['Kristen Protestan', 'Katolik', 'Islam', 'Buddha'][$sequence % 4],
                'jenis_kelamin' => $sequence % 2 === 0 ? 'Perempuan' : 'Laki-laki',
                'golongan_darah' => ['O', 'A', 'B', 'AB'][$sequence % 4],
                'warga_negara' => 'WNI',
                'nomor_telepon' => '0812'.str_pad((string) (10000000 + $sequence), 8, '0', STR_PAD_LEFT),
                'email_pribadi' => $email,
                'email_students' => $nim.'@students.ukdw.ac.id',
                'alamat_saat_ini' => 'Jl. Kaliurang Km. '.(($sequence % 9) + 1).' No. '.($sequence + 10).', Sleman',
                'kelurahan' => 'Condongcatur',
                'kecamatan' => 'Depok',
                'kabupaten_id' => $kabupaten->id,
                'propinsi_id' => $propinsi->id,
                'kode_pos' => '55281',
                'nisn' => $isProfileComplete ? '005'.str_pad((string) (1000000 + $sequence), 7, '0', STR_PAD_LEFT) : null,
                'no_bpjs' => $isProfileComplete ? '0002'.str_pad((string) (7000000 + $sequence), 10, '0', STR_PAD_LEFT) : null,
                'asal_sekolah' => 'SMA Negeri '.(($sequence % 6) + 1),
                'alamat_asal_sekolah' => 'Jl. Pendidikan No. '.($sequence + 1),
                'kota_kabupaten_asal_sekolah' => 'Kabupaten Sleman',
                'provinsi_asal_sekolah' => 'DI Yogyakarta',
                'jurusan_asal_sekolah' => in_array($prodi->kode_prodi, ['11', '12'], true) ? 'IPS' : 'IPA',
            ]);

            $biodata = Biodata::create([
                'user_id' => $user->id,
                'nim' => $nim,
                'prodi_id' => $prodi->id,
                'email_pribadi' => $email,
                'nomor_telepon' => $dataAkademik->nomor_telepon,
                'alamat_saat_ini' => $dataAkademik->alamat_saat_ini,
                'kabupaten_id' => $kabupaten->id,
                'propinsi_id' => $propinsi->id,
                'kode_pos' => '55281',
                'linkedin_url' => $sequence % 4 === 0 ? null : 'https://www.linkedin.com/in/demo-'.$nim,
                'linkedin_username' => $sequence % 4 === 0 ? null : 'demo-'.$nim,
                'skills' => $isRespondent ? 'Komunikasi, Analisis Data, '.$prodi->nama_prodi : null,
                'experience' => $isRespondent ? 'Pengalaman kerja pada bidang '.$prodi->nama_prodi : null,
                'perusahaan_id' => $company?->id,
                'atasan_id' => $atasan?->id,
                'kategori_pekerjaan' => $isRespondent ? ($hasCareer ? 'Bekerja (Full Time)' : 'Melanjutkan Pendidikan') : null,
                'posisi_jabatan' => $hasCareer ? ['Staff', 'Analyst', 'Developer', 'Coordinator'][$sequence % 4] : null,
                'bidang_pekerjaan' => $hasCareer ? ['Teknologi Informasi', 'Administrasi / Tata Usaha', 'Keuangan / Akuntansi', 'Lainnya'][$sequence % 4] : null,
                'pendidikan_tingkat' => $isRespondent && ! $hasCareer ? 'S2' : null,
                'perguruan_tinggi' => $isRespondent && ! $hasCareer ? 'Universitas Gadjah Mada' : null,
                'pendidikan_prodi' => $isRespondent && ! $hasCareer ? 'Magister '.$prodi->nama_prodi : null,
                'gaji' => $hasCareer ? $salary : null,
                'jenis_pekerjaan' => $hasCareer ? 'Profesional' : null,
            ]);

            $yudisium = Yudisium::create([
                'nim' => $nim,
                'judul_ta' => 'Analisis Data Alumni '.$prodi->nama_prodi.' Tahun '.$graduationYear,
                'judul_ta_inggris' => 'Alumni Data Analysis for '.$prodi->nama_prodi,
                'status_lulus' => 'Lulus',
                'keterangan_hasil_yudisium' => $dataAkademik->ip_kumulatif >= 3.75 ? 'Lulus dengan Pujian (Cumlaude)' : 'Sangat Memuaskan',
            ]);
            $biodata->update(['yudisium_id' => $yudisium->id]);

            if ($isRespondent) {
                $this->seedUniversityResponses($biodata, $graduationYear, $salary, $universityQuestions);
                $this->seedProdiResponses($biodata, $prodiQuestions);
            }

            return true;
        });
    }

    /**
     * @param  Collection<int, RefSubpertanyaan2021>  $questions
     */
    private function seedUniversityResponses(Biodata $biodata, int $graduationYear, int $salary, $questions): void
    {
        foreach ($questions as $question) {
            $code = strtoupper((string) $question->kode_pertanyaan);
            if ($code === 'F506') {
                continue;
            }

            $answer = match ($code) {
                'F8' => 'Bekerja (full time/part time)',
                'F504' => 'Ya',
                'F502' => '3',
                'F505' => (string) $salary,
                default => $this->defaultQuestionAnswer($question),
            };

            Tracer::firstOrCreate(
                ['biodata_id' => $biodata->id, 'question_id' => $question->id],
                [
                    'nim' => $biodata->nim,
                    'kelompok' => $question->kelompok,
                    'kode_pertanyaan' => $question->kode_pertanyaan,
                    'subpertanyaan' => $question->subpertanyaan,
                    'answer' => $answer,
                    'tahun_lulus' => (string) $graduationYear,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, ProdiQuestion>  $questions
     */
    private function seedProdiResponses(Biodata $biodata, $questions): void
    {
        foreach ($questions as $question) {
            $answer = $question->options->first()?->option_text ?? 'Data simulasi '.$question->code;

            if (in_array($question->type, ['text', 'textarea'], true)) {
                $answer = 'Respon simulasi alumni untuk '.$question->question_text;
            }

            ProdiResponse::firstOrCreate(
                ['biodata_id' => $biodata->id, 'prodi_question_id' => $question->id],
                ['answer_text' => $answer],
            );
        }
    }

    private function defaultQuestionAnswer(RefSubpertanyaan2021 $question): string
    {
        $option = $question->detils()->first();

        if ($option) {
            return (string) $option->option_text;
        }

        if (in_array($question->type, ['number', 'rating', 'scale'], true)) {
            return '4';
        }

        return 'Data simulasi alumni';
    }
}
