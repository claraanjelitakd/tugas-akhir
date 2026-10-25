<?php

namespace Database\Seeders;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\Kabupaten;
use App\Models\Perusahaan;
use App\Models\Propinsi;
use App\Models\RefSubpertanyaan2021;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menata ulang data alumni demo agar halaman analitik memiliki distribusi yang dapat diuji.
 *
 * Seeder ini hanya menyentuh biodata dengan akun demo yang dibuat oleh
 * DashboardScenarioSeeder. Data alumni non-demo tidak pernah dipilih atau diubah.
 */
class AnalyticsDemoDistributionSeeder extends Seeder
{
    /**
     * Baris jawaban yang dikumpulkan sebelum bulk upsert untuk menghindari
     * query per pertanyaan pada ratusan alumni demo.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $pendingTracerRows = [];

    /**
     * Membuat master perusahaan demo lintas provinsi dan memperbarui karier demo.
     *
     * Distribusi sengaja memuat alumni bekerja, alumni dengan gaji di bawah 1,2
     * kali UMP, dan alumni belum bekerja supaya semua cabang grafik dapat diuji.
     */
    public function run(): void
    {
        $locations = $this->locations();
        $companies = collect([
            ['name' => 'PT Demo Analitik Yogyakarta', 'province' => '34', 'city' => '34.71'],
            ['name' => 'PT Demo Analitik Jakarta', 'province' => '31', 'city' => '31.74'],
            ['name' => 'PT Demo Analitik Jawa Barat', 'province' => '32', 'city' => '32.73'],
            ['name' => 'PT Demo Analitik Jawa Tengah', 'province' => '33', 'city' => '33.74'],
            ['name' => 'PT Demo Analitik Jawa Timur', 'province' => '35', 'city' => '35.78'],
        ])->map(function (array $definition) use ($locations): ?Perusahaan {
            $province = $locations['provinces'][$definition['province']] ?? null;
            $city = $locations['cities'][$definition['city']] ?? null;

            if (! $province || ! $city) {
                return null;
            }

            return Perusahaan::updateOrCreate(
                ['nama_perusahaan' => $definition['name']],
                [
                    'propinsi_id' => $province->id,
                    'kabupaten_id' => $city->id,
                    'alamat' => 'Data simulasi analitik',
                    'sektor' => 'Data simulasi',
                    'skala' => 'Nasional',
                    'jenis_perusahaan' => 'Perusahaan swasta',
                    'jenis_lokasi' => 'Dalam Negeri',
                    'negara' => 'Indonesia',
                    'status_verifikasi' => 'Terverifikasi',
                ],
            );
        })->filter()->values();

        if ($companies->isEmpty()) {
            $this->command?->warn('AnalyticsDemoDistributionSeeder dilewati karena master wilayah belum lengkap.');

            return;
        }

        $demoBiodata = Biodata::query()
            ->with(['dataAkademik', 'prodi'])
            ->whereHas('user', fn ($query) => $query->where('email', 'like', 'demo.%@alumni.ukdw.ac.id'))
            ->whereIn('id', DB::table('tracer')->select('biodata_id')->where('kode_pertanyaan', 'F8'))
            ->orderBy('id')
            ->get();

        if ($demoBiodata->isEmpty()) {
            $this->command?->warn('AnalyticsDemoDistributionSeeder dilewati karena alumni demo belum tersedia.');

            return;
        }

        $questions = RefSubpertanyaan2021::query()
            ->whereIn('kode_pertanyaan', [
                'F3', 'F4', 'F6', 'F7', 'F7A', 'F8', 'F10', 'F11', 'F12',
                'F14', 'F15', 'F16', 'F18b', 'F18c', 'F18d', 'F502', 'F504',
                'F505', 'F506', 'F21', 'F22', 'F23', 'F24', 'F25', 'F26', 'F27',
                'F17a1', 'F17b1', 'F17a2', 'F17b2', 'F17a3', 'F17b3', 'F17a4',
                'F17b4', 'F17a5', 'F17b5', 'F17a6', 'F17b6', 'F17a7', 'F17b7',
            ])
            ->get()
            ->keyBy(fn (RefSubpertanyaan2021 $question): string => strtoupper($question->kode_pertanyaan));

        DB::transaction(function () use ($demoBiodata, $companies, $questions): void {
            $this->pendingTracerRows = [];

            foreach ($demoBiodata->values() as $index => $biodata) {
                $status = $this->employmentStatus($index);
                $hasCareer = in_array($status, ['Bekerja (full time/part time)', 'Wiraswasta'], true);

                if (! $hasCareer) {
                    $biodata->update([
                        'perusahaan_id' => null,
                        'atasan_id' => null,
                        'kategori_pekerjaan' => $status,
                        'posisi_jabatan' => null,
                        'gaji' => null,
                    ]);
                } else {
                    $company = $companies[$index % $companies->count()];
                    $isBelowUmp = $index % 5 === 0;
                    $salary = $isBelowUmp ? 2000000 + (($index % 4) * 150000) : 7500000 + (($index % 7) * 750000);
                    $atasan = Atasan::firstOrCreate(
                        ['email' => 'analytics.demo.'.$biodata->nim.'@example.test'],
                        ['nama' => 'Atasan Demo Analitik '.$biodata->nim, 'telepon' => '0812'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT)],
                    );

                    $biodata->update([
                        'perusahaan_id' => $company->id,
                        'atasan_id' => $atasan->id,
                        'kategori_pekerjaan' => $status,
                        'posisi_jabatan' => $status === 'Wiraswasta' ? 'Pemilik Usaha' : 'Profesional '.$biodata->nim,
                        'gaji' => $salary,
                    ]);
                }

                $this->seedTracerAnalyticsAnswers($biodata, $index, $status, $questions);
            }

            collect($this->pendingTracerRows)
                ->chunk(500)
                ->each(function (Collection $rows): void {
                    DB::table('tracer')->upsert(
                        $rows->all(),
                        ['biodata_id', 'kode_pertanyaan'],
                        [
                            'question_id', 'nim', 'kelompok', 'subpertanyaan',
                            'answer', 'answer_json', 'tahun_lulus', 'updated_at',
                        ],
                    );
                });
        });

        $this->command?->info('Distribusi data demo analitik diperbarui tanpa mengubah alumni non-demo.');
    }

    /**
     * Mengambil master provinsi dan kabupaten yang diperlukan perusahaan demo.
     *
     * @return array{provinces: array<string, Propinsi>, cities: array<string, Kabupaten>}
     */
    private function locations(): array
    {
        return [
            'provinces' => Propinsi::query()->whereIn('kode_provinsi', ['31', '32', '33', '34', '35'])->get()->keyBy('kode_provinsi')->all(),
            'cities' => Kabupaten::query()->whereIn('kode_kabupaten', ['31.74', '32.73', '33.74', '34.71', '35.78'])->get()->keyBy('kode_kabupaten')->all(),
        ];
    }

    /**
     * Menentukan distribusi status F8 agar IKU 1 tidak berisi satu kategori saja.
     */
    private function employmentStatus(int $index): string
    {
        return match ($index % 12) {
            0 => 'Tidak Kerja tetapi sedang mencari kerja',
            1 => 'Melanjutkan Pendidikan',
            2 => 'Belum memungkinkan bekerja',
            3 => 'Wiraswasta',
            default => 'Bekerja (full time/part time)',
        };
    }

    /**
     * Mengisi jawaban analitik demo sesuai percabangan instrumen universitas.
     *
     * @param  Collection<string, RefSubpertanyaan2021>  $questions
     */
    private function seedTracerAnalyticsAnswers(Biodata $biodata, int $index, string $status, Collection $questions): void
    {
        $hasCareer = in_array($status, ['Bekerja (full time/part time)', 'Wiraswasta'], true);
        $isFurtherStudy = $status === 'Melanjutkan Pendidikan';
        $isSearching = $status === 'Tidak Kerja tetapi sedang mencari kerja';
        $workplaceTypes = ['Perusahaan swasta', 'Instansi pemerintah', 'BUMN/BUMD', 'Organisasi non-profit/LSM', 'Wiraswasta/perusahaan sendiri'];
        $alignments = ['Sangat erat', 'Erat', 'Cukup erat', 'Kurang erat', 'Tidak erat'];
        $educationLevels = ['Tingkat yang sama', 'Setingkat lebih tinggi', 'Setingkat lebih rendah', 'Tidak perlu pendidikan tinggi'];
        $learningScale = ['Tidak Sama Sekali', 'Kurang', 'Cukup Besar', 'Besar', 'Sangat Besar'];
        $competencyScale = ['Sangat Rendah', 'Rendah', 'Cukup', 'Tinggi', 'Sangat Tinggi'];

        $this->saveAnswer($biodata, $questions, 'F8', $status);
        $this->saveAnswer($biodata, $questions, 'F505', $hasCareer ? (string) $biodata->gaji : null);

        if ($hasCareer) {
            $startsBeforeGraduation = $index % 4 !== 0;
            $waitMonths = $startsBeforeGraduation ? $index % 7 : 7 + ($index % 12);
            $jobSearchStart = $index % 6 === 0
                ? 'Sesudah lulus '.(($index % 8) + 1).' bulan'
                : 'Sebelum lulus '.(($index % 6) + 1).' bulan';
            $methods = [
                'Mencari lewat internet/iklan online/milis',
                $index % 2 === 0 ? 'Melalui relasi (misalnya dosen, orang tua, saudara, teman, dll)' : 'Melalui penempatan kerja atau magang',
            ];
            $applications = 4 + ($index % 18);
            $responses = max(1, (int) floor($applications * (0.35 + (($index % 3) * 0.1))));
            $interviews = max(1, min($responses, (int) floor($responses * 0.6)));
            $alignment = $alignments[$index % count($alignments)];

            $this->saveAnswer($biodata, $questions, 'F3', $jobSearchStart);
            $this->saveAnswer($biodata, $questions, 'F4', $methods);
            $this->saveAnswer($biodata, $questions, 'F6', (string) $applications);
            $this->saveAnswer($biodata, $questions, 'F7', (string) $responses);
            $this->saveAnswer($biodata, $questions, 'F7A', (string) $interviews);
            $this->saveAnswer($biodata, $questions, 'F11', $status === 'Wiraswasta' ? 'Wiraswasta/perusahaan sendiri' : $workplaceTypes[$index % 4]);
            $this->saveAnswer($biodata, $questions, 'F14', $alignment);
            $this->saveAnswer($biodata, $questions, 'F15', $educationLevels[$index % count($educationLevels)]);
            $this->saveAnswer($biodata, $questions, 'F504', $startsBeforeGraduation ? 'Ya' : 'Tidak');
            $this->saveAnswer($biodata, $questions, 'F502', $startsBeforeGraduation ? (string) $waitMonths : null);
            $this->saveAnswer($biodata, $questions, 'F506', $startsBeforeGraduation ? null : (string) $waitMonths);
            $this->saveAnswer(
                $biodata,
                $questions,
                'F16',
                in_array($alignment, ['Kurang erat', 'Tidak erat'], true)
                    ? ['Saya belum mendapatkan pekerjaan yang lebih sesuai', 'Pekerjaan saya saat ini lebih menarik']
                    : ['Pertanyaan tidak sesuai; pekerjaan saya sekarang sudah sesuai dengan pendidikan saya'],
            );
        } else {
            foreach (['F3', 'F4', 'F6', 'F7', 'F7A', 'F11', 'F14', 'F15', 'F16', 'F502', 'F506'] as $code) {
                $this->saveAnswer($biodata, $questions, $code, null);
            }

            $this->saveAnswer($biodata, $questions, 'F504', 'Tidak');
            $this->saveAnswer(
                $biodata,
                $questions,
                'F10',
                $isSearching ? 'Tidak, tetapi saya sedang menunggu hasil lamaran kerja' : 'Tidak',
            );
        }

        $this->saveAnswer($biodata, $questions, 'F12', $isFurtherStudy ? ['Biaya Sendiri / Keluarga', 'Beasiswa Pemerintah'][$index % 2] : null);
        $this->saveAnswer($biodata, $questions, 'F18b', $isFurtherStudy ? 'Universitas Gadjah Mada' : null);
        $this->saveAnswer($biodata, $questions, 'F18c', $isFurtherStudy ? 'Magister '.$biodata->prodi?->nama_prodi : null);
        $this->saveAnswer($biodata, $questions, 'F18d', $isFurtherStudy ? '2026-08-01' : null);

        foreach (range(21, 27) as $offset => $number) {
            $this->saveAnswer($biodata, $questions, 'F'.$number, $learningScale[($index + $offset) % count($learningScale)]);
        }

        foreach (range(1, 7) as $competency) {
            $graduationScore = 2 + (($index + $competency) % 3);
            $needScore = min(5, $graduationScore + 1 + ($index % 2));
            $this->saveAnswer($biodata, $questions, 'F17a'.$competency, $competencyScale[$graduationScore - 1]);
            $this->saveAnswer($biodata, $questions, 'F17b'.$competency, $competencyScale[$needScore - 1]);
        }
    }

    /**
     * Menyimpan satu jawaban demo dengan kode dan question_id yang konsisten.
     *
     * Nilai array disimpan di answer_json agar pilihan jamak tetap dapat
     * diagregasi per opsi, sedangkan answer mempertahankan representasi teks.
     *
     * @param  Collection<string, RefSubpertanyaan2021>  $questions
     * @param  string|array<int, string>|null  $answer
     */
    private function saveAnswer(Biodata $biodata, Collection $questions, string $code, string|array|null $answer): void
    {
        $question = $questions->get(strtoupper($code));
        if (! $question) {
            return;
        }

        $answerText = is_array($answer) ? implode('; ', $answer) : $answer;
        $answerJson = is_array($answer) ? ['values' => array_values($answer)] : ($answer !== null ? ['value' => $answer] : null);

        $this->pendingTracerRows[] = [
            'biodata_id' => $biodata->id,
            'question_id' => $question->id,
            'nim' => $biodata->nim,
            'kelompok' => $question->kelompok,
            'kode_pertanyaan' => $question->kode_pertanyaan,
            'subpertanyaan' => $question->subpertanyaan,
            'answer' => $answerText,
            'answer_json' => $answerJson === null ? null : json_encode($answerJson, JSON_UNESCAPED_UNICODE),
            'tahun_lulus' => $biodata->dataAkademik?->tahun_lulus,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
