<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use App\Models\PertanyaanEvaluasiAtasan;
use App\Models\ResponEvaluasiAtasan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Seeder data respon kuesioner evaluasi atasan (pengguna lulusan).
 *
 * Menghasilkan 20 data evaluasi atasan yang telah berstatus submitted
 * dengan variasi jawaban skala Likert, kesiapan kerja, dan catatan kualitatif.
 */
class SampleEvaluasiAtasanSeeder extends Seeder
{
    /**
     * Jalankan proses seeding evaluasi atasan.
     */
    public function run(): void
    {
        $biodatas = Biodata::with(['prodi', 'dataAkademik', 'perusahaan', 'atasan', 'user'])
            ->whereNotNull('atasan_id')
            ->whereNotNull('perusahaan_id')
            ->orderBy('id', 'asc')
            ->get()
            ->unique('prodi_id')
            ->values();

        if ($biodatas->count() < 20) {
            $existingIds = $biodatas->pluck('id')->all();
            $additional = Biodata::with(['prodi', 'dataAkademik', 'perusahaan', 'atasan', 'user'])
                ->whereNotNull('atasan_id')
                ->whereNotNull('perusahaan_id')
                ->whereNotIn('id', $existingIds)
                ->orderBy('id', 'asc')
                ->limit(20 - $biodatas->count())
                ->get();

            $biodatas = $biodatas->concat($additional);
        }

        $selectedBiodatas = $biodatas->take(20);

        // Ambil seluruh pertanyaan evaluasi non-company field yang aktif
        $questions = PertanyaanEvaluasiAtasan::where('is_active', true)
            ->where('kode', '!=', '891') // hindari data draft uji coba
            ->orderBy('order', 'asc')
            ->get()
            ->reject->is_company_field;

        $gajiOptions = [
            '2.000.000 - 3.000.000',
            '3.000.000 - 4.000.000',
            '> 5.000.000',
            '> 5.000.000',
            '> 5.000.000',
        ];

        $alumniCountOptions = [
            '< 5 Orang',
            '6 - 10 Orang',
            '11 - 20 Orang',
            '> 21 Orang',
        ];

        $catatanVariasi = [
            'Alumni memiliki integritas yang sangat tinggi, cepat beradaptasi dengan ritme kerja dan budaya perusahaan.',
            'Keahlian teknis dan kemampuan analisis sangat memuaskan, sangat membantu efisiensi operasional tim kami.',
            'Sikap kerja positif, inisiatif tinggi, dan selalu menyelesaikan tugas sebelum tenggat waktu yang ditentukan.',
            'Kemampuan kolaborasi dalam tim sangat baik. Rekomendasi peningkatan pada penguasaan bahasa asing untuk ekspansi global.',
            'Memiliki etos kerja yang solid dan kemampuan problem-solving yang tajam di situasi mendesak.',
            'Sangat bertanggung jawab dan proaktif dalam memberikan solusi alternatif terhadap kendala di lapangan.',
            'Penguasaan teknologi modern sangat baik. Perlu ditingkatkan sedikit pada rasa percaya diri saat memimpin rapat.',
            'Kualitas hasil kerja sangat rapi dan detail. Menunjukkan dedikasi tinggi terhadap standar mutu perusahaan.',
            'Kinerja sangat memuaskan, cepat menyerap materi pelatihan internal dan mampu membimbing staf junior.',
            'Disiplin waktu dan kepatuhan terhadap SOP sangat konsisten. Kami sangat puas dengan kontribusi alumni UKDW.',
        ];

        $likertOptions = ['Sangat Tinggi', 'Tinggi', 'Cukup', 'Kurang', 'Sangat Kurang'];

        foreach ($selectedBiodatas as $index => $biodata) {
            $submittedDaysAgo = ($index * 2) + 1; // 1 s.d. 39 hari lalu
            $submittedAt = Carbon::now()->subDays($submittedDaysAgo)->subHours($index % 12);

            $evaluasi = EvaluasiAtasan::updateOrCreate(
                ['biodata_id' => $biodata->id],
                [
                    'perusahaan_id' => $biodata->perusahaan_id,
                    'atasan_id' => $biodata->atasan_id,
                    'token' => Str::random(40),
                    'is_submitted' => true,
                    'submitted_at' => $submittedAt,
                    'jumlah_alumni_ukdw' => $alumniCountOptions[$index % count($alumniCountOptions)],
                    'standar_gaji_pertama' => $gajiOptions[$index % count($gajiOptions)],
                ]
            );

            $catatanItem = $catatanVariasi[$index % count($catatanVariasi)];

            foreach ($questions as $qIndex => $question) {
                $nilai = null;

                if ($question->kode === 'tingkat_kesiapan_kerja') {
                    // Distribusi: mayoritas Sangat siap, sebagian Cukup Siap, sedikit Kurang siap
                    if ($index % 7 === 0) {
                        $nilai = 'Kurang siap';
                    } elseif ($index % 2 === 0) {
                        $nilai = 'Sangat siap';
                    } else {
                        $nilai = 'Cukup Siap';
                    }
                } elseif ($question->tipe === 'likert_5') {
                    // Variasi nilai Likert berbasis indeks dan karakteristik aspek
                    $pattern = ($index + $qIndex) % 10;
                    if ($pattern <= 4) {
                        $nilai = 'Sangat Tinggi';
                    } elseif ($pattern <= 8) {
                        $nilai = 'Tinggi';
                    } else {
                        $nilai = 'Cukup';
                    }

                    // Sedikit variasi nilai "Kurang" untuk pertanyaan bahasa asing atau kemampuan manajerial
                    if (in_array($question->kode, ['bahasa_asing', 'kemampuan_manajerial'], true) && $index % 5 === 0) {
                        $nilai = 'Kurang';
                    }
                } else {
                    $pilihan = $question->pilihan_jawaban;
                    if (is_array($pilihan) && count($pilihan) > 0) {
                        $nilai = $pilihan[$index % count($pilihan)];
                    } else {
                        $nilai = 'Tinggi';
                    }
                }

                ResponEvaluasiAtasan::updateOrCreate(
                    [
                        'evaluasi_atasan_id' => $evaluasi->id,
                        'pertanyaan_id' => $question->id,
                    ],
                    [
                        'nilai' => $nilai,
                        'catatan' => $qIndex === 0 ? $catatanItem : null,
                    ]
                );
            }
        }
    }
}
