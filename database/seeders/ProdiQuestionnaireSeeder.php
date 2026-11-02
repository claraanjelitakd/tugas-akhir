<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\Prodi;
use App\Models\ProdiQuestion;
use App\Models\ProdiQuestionOption;
use App\Models\ProdiQuestionSection;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Database\Seeder;

/**
 * Menyediakan instrumen pertanyaan dan opsi kuesioner khusus program studi.
 */
class ProdiQuestionnaireSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSistemInformasi();
        $this->seedFilsafatKeilahian();
    }

    /**
     * Seeder Instrumen Kuesioner Khusus Program Studi Sistem Informasi (Kode: 72)
     */
    protected function seedSistemInformasi(): void
    {
        $prodiSI = Prodi::where('kode_prodi', '72')->first();
        if (! $prodiSI) {
            return;
        }

        $ratingOptions = [
            1 => '1 = Sangat Tidak Setuju',
            2 => '2 = Tidak Setuju',
            3 => '3 = Netral',
            4 => '4 = Setuju',
            5 => '5 = Sangat Setuju',
        ];

        // =========================================================================
        // SECTION 1: Formulir Peminatan & Kontribusi Alumni
        // =========================================================================
        $sec1 = ProdiQuestionSection::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'order' => 1],
            [
                'title' => 'Formulir Peminatan & Kontribusi Alumni',
                'description' => 'Data konsentrasi peminatan dan kesediaan berkontribusi untuk prodi.',
            ]
        );

        // Hapus butir PSI-1-01 jika ada (tidak digunakan pada Prodi Sistem Informasi)
        ProdiQuestion::where('prodi_id', $prodiSI->id)
            ->where('code', 'PSI-1-01')
            ->delete();

        // 1.1 Konsentrasi yang diambil *
        $qKonsentrasi = ProdiQuestion::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'code' => 'PSI-1-02'],
            [
                'prodi_question_section_id' => $sec1->id,
                'question_text' => 'Konsentrasi yang diambil',
                'type' => 'single_choice',
                'is_required' => true,
                'order' => 1,
            ]
        );
        $konsentrasiOpts = [
            'Sistem Informasi Enterprise',
            'Sistem Informasi Layanan',
            'Sistem Informasi Kesehatan',
            'Data Analytics',
            'Digital Entrepreneurship',
        ];
        foreach ($konsentrasiOpts as $idx => $optText) {
            $num = $idx + 1;
            ProdiQuestionOption::updateOrCreate(
                ['prodi_question_id' => $qKonsentrasi->id, 'code' => "PSI-1-02-0{$num}"],
                ['option_text' => $optText, 'order' => $num]
            );
        }

        // 1.2 Apakah Anda mau berkontribusi kepada Program Studi Sistem Informasi UKDW? *
        $qKontribusi = ProdiQuestion::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'code' => 'PSI-1-03'],
            [
                'prodi_question_section_id' => $sec1->id,
                'question_text' => 'Apakah Anda mau berkontribusi kepada Program Studi Sistem Informasi UKDW?',
                'type' => 'single_choice',
                'is_required' => true,
                'order' => 2,
            ]
        );
        $kontribusiOpts = ['Ya', 'Tidak'];
        foreach ($kontribusiOpts as $idx => $optText) {
            $num = $idx + 1;
            ProdiQuestionOption::updateOrCreate(
                ['prodi_question_id' => $qKontribusi->id, 'code' => "PSI-1-03-0{$num}"],
                ['option_text' => $optText, 'order' => $num]
            );
        }

        // 1.3 Jika ya, kontribusi apa yang akan Anda berikan?
        $qBentukKontribusi = ProdiQuestion::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'code' => 'PSI-1-04'],
            [
                'prodi_question_section_id' => $sec1->id,
                'question_text' => 'Jika ya, kontribusi apa yang akan Anda berikan?',
                'type' => 'multiple_choice',
                'is_required' => false,
                'order' => 3,
            ]
        );
        $bentukOpts = [
            'Kuliah Umum / Dosen Praktisi',
            'Kerjasama Magang',
            'Sponsor',
            'Lainnya',
        ];
        foreach ($bentukOpts as $idx => $optText) {
            $num = $idx + 1;
            ProdiQuestionOption::updateOrCreate(
                ['prodi_question_id' => $qBentukKontribusi->id, 'code' => "PSI-1-04-0{$num}"],
                ['option_text' => $optText, 'order' => $num]
            );
        }

        // =========================================================================
        // SECTION 2: Pengalaman Belajar Selama Studi
        // =========================================================================
        $sec2 = ProdiQuestionSection::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'order' => 2],
            [
                'title' => 'Pengalaman Belajar Selama Studi',
                'description' => 'Skala Penilaian: 1 = Sangat Tidak Setuju, 2 = Tidak Setuju, 3 = Netral, 4 = Setuju, 5 = Sangat Setuju',
            ]
        );

        $sec2Questions = [
            'Saya memperoleh pemahaman yang baik mengenai konsep dan praktik di bidang Sistem Informasi.',
            'Proses pembelajaran selama studi mendorong saya untuk berpikir kritis dan analitis.',
            'Saya diberi kesempatan untuk terlibat dalam proyek atau tugas yang aplikatif dengan metode pembelajaran project based learning atau case study.',
            'Sistem Informasi UKDW memberikan pemahaman tentang implementasi Sistem Informasi di dunia kerja dengan mendatangkan praktisi yang kompeten di bidangnya.',
            'Saya mendapatkan manfaat dari perkuliahan yang dibawakan oleh praktisi industri.',
            'Belajar di Sistem Informasi UKDW mampu meningkatkan kemampuan saya di bidang Sistem Informasi.',
            'Belajar di Sistem Informasi UKDW juga meningkatkan kemampuan soft skill dan komunikasi saya.',
            'Program Studi Sistem Informasi UKDW mendorong mahasiswa untuk belajar di luar program studi dengan skema studi independen.',
            'Lingkungan belajar di Program Studi Sistem Informasi UKDW mendorong saya untuk berpikir kritis, kolaboratif, dan kreatif dalam memecahkan masalah.',
        ];

        foreach ($sec2Questions as $idx => $qText) {
            $qNum = $idx + 1;
            $q = ProdiQuestion::updateOrCreate(
                ['prodi_id' => $prodiSI->id, 'code' => "PSI-2-0{$qNum}"],
                [
                    'prodi_question_section_id' => $sec2->id,
                    'question_text' => $qText,
                    'type' => 'rating_5',
                    'is_required' => true,
                    'order' => $qNum,
                ]
            );
            foreach ($ratingOptions as $val => $optText) {
                ProdiQuestionOption::updateOrCreate(
                    ['prodi_question_id' => $q->id, 'code' => "PSI-2-0{$qNum}-0{$val}"],
                    ['option_text' => $optText, 'order' => $val]
                );
            }
        }

        // =========================================================================
        // SECTION 3: Nilai-Nilai Program Studi Sistem Informasi
        // =========================================================================
        $sec3 = ProdiQuestionSection::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'order' => 3],
            [
                'title' => 'Nilai-Nilai Program Studi Sistem Informasi',
                'description' => 'Skala Penilaian: 1 = Sangat Tidak Setuju, 2 = Tidak Setuju, 3 = Netral, 4 = Setuju, 5 = Sangat Setuju',
            ]
        );

        $sec3Questions = [
            'Setelah belajar dari Program Studi Sistem Informasi, saya berani untuk memulai langkah pertama dalam mengembangkan atau mengadopsi teknologi terkini untuk mendukung proses bisnis organisasi.',
            'Setelah belajar dari Program Studi Sistem Informasi, saya akan mengedepankan proses inovasi teknologi digital sehingga memiliki nilai tambah dan meningkatkan produktivitas organisasi.',
            'Setelah belajar dari Program Studi Sistem Informasi, saya mampu mengintegrasikan semua elemen-elemen Sistem Informasi, seperti data, perangkat lunak, perangkat keras, prosedur, dan orang.',
            'Setelah belajar dari Program Studi Sistem Informasi, saya mampu untuk menyajikan informasi yang akurat dan tepat waktu.',
            'Setelah belajar dari Program Studi Sistem Informasi, saya akan menginspirasi masyarakat dengan karya kreatif dan inovatif.',
        ];

        foreach ($sec3Questions as $idx => $qText) {
            $qNum = $idx + 1;
            $q = ProdiQuestion::updateOrCreate(
                ['prodi_id' => $prodiSI->id, 'code' => "PSI-3-0{$qNum}"],
                [
                    'prodi_question_section_id' => $sec3->id,
                    'question_text' => $qText,
                    'type' => 'rating_5',
                    'is_required' => true,
                    'order' => $qNum,
                ]
            );
            foreach ($ratingOptions as $val => $optText) {
                ProdiQuestionOption::updateOrCreate(
                    ['prodi_question_id' => $q->id, 'code' => "PSI-3-0{$qNum}-0{$val}"],
                    ['option_text' => $optText, 'order' => $val]
                );
            }
        }

        // Hapus butir pertanyaan lama yang sebenarnya adalah header teks
        ProdiQuestion::where('prodi_id', $prodiSI->id)
            ->whereIn('question_text', [
                'Bagaimana dengan fasilitas yang telah disediakan Fakultas Teknologi Informasi? Apakah fasilitas yang diberikan sudah membantu Anda dalam studi?',
                'Bagaimana dengan pelayanan yang diberikan oleh rekan-rekan dosen dan tenaga kependidikan dari FTI UKDW?',
                'Bagaimana dengan mata kuliah yang ditawarkan oleh Program Studi Sistem Informasi UKDW?',
                'Bagaimana dengan kepuasan secara keseluruhan dari pengalaman Anda belajar selama kuliah di Program Studi Sistem Informasi UKDW?',
            ])
            ->delete();

        // =========================================================================
        // SECTION 4: Fasilitas yang Tersedia
        // =========================================================================
        $sec4 = ProdiQuestionSection::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'order' => 4],
            [
                'title' => 'Fasilitas yang Tersedia',
                'description' => 'Bagaimana dengan fasilitas yang telah disediakan Fakultas Teknologi Informasi? Apakah fasilitas yang diberikan sudah membantu Anda dalam studi?',
            ]
        );

        $sec4RatingQuestions = [
            'Fasilitas laboratorium komputer mendukung kegiatan perkuliahan saya.',
            'Akses terhadap perangkat lunak dan tools pendukung pembelajaran memadai.',
            'Ruang kelas nyaman dan menunjang kegiatan pembelajaran.',
            'Akses internet mendukung kegiatan perkuliahan saya.',
            'Ruang diskusi terbuka membantu kegiatan perkuliahan saya.',
        ];

        foreach ($sec4RatingQuestions as $idx => $qText) {
            $qNum = $idx + 1;
            $q = ProdiQuestion::updateOrCreate(
                ['prodi_id' => $prodiSI->id, 'code' => "PSI-4-0{$qNum}"],
                [
                    'prodi_question_section_id' => $sec4->id,
                    'question_text' => $qText,
                    'type' => 'rating_5',
                    'is_required' => true,
                    'order' => $qNum,
                ]
            );
            foreach ($ratingOptions as $val => $optText) {
                ProdiQuestionOption::updateOrCreate(
                    ['prodi_question_id' => $q->id, 'code' => "PSI-4-0{$qNum}-0{$val}"],
                    ['option_text' => $optText, 'order' => $val]
                );
            }
        }

        // =========================================================================
        // SECTION 5: Dosen dan Tenaga Kependidikan
        // =========================================================================
        $sec5 = ProdiQuestionSection::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'order' => 5],
            [
                'title' => 'Dosen dan Tenaga Kependidikan',
                'description' => 'Bagaimana dengan pelayanan yang diberikan oleh rekan-rekan dosen dan tenaga kependidikan dari FTI UKDW?',
            ]
        );

        $sec5RatingQuestions = [
            'Dosen menguasai materi yang diajarkan dan relevan dengan bidang Sistem Informasi.',
            'Dosen memberikan penjelasan yang mudah dipahami dan membantu pemahaman saya.',
            'Dosen terbuka terhadap diskusi, masukan, dan pertanyaan dari mahasiswa.',
            'Dosen mendukung perkembangan akademik dan profesional mahasiswa.',
            'Dosen sering memberikan contoh nyata atau studi kasus dalam perkuliahan.',
            'Staf FTI membantu mahasiswa dalam menyelesaikan hal-hal administratif.',
            'Staf FTI memberikan pelayanan yang baik dan ramah kepada mahasiswa.',
        ];

        foreach ($sec5RatingQuestions as $idx => $qText) {
            $qNum = $idx + 1;
            $q = ProdiQuestion::updateOrCreate(
                ['prodi_id' => $prodiSI->id, 'code' => "PSI-5-0{$qNum}"],
                [
                    'prodi_question_section_id' => $sec5->id,
                    'question_text' => $qText,
                    'type' => 'rating_5',
                    'is_required' => true,
                    'order' => $qNum,
                ]
            );
            foreach ($ratingOptions as $val => $optText) {
                ProdiQuestionOption::updateOrCreate(
                    ['prodi_question_id' => $q->id, 'code' => "PSI-5-0{$qNum}-0{$val}"],
                    ['option_text' => $optText, 'order' => $val]
                );
            }
        }

        // =========================================================================
        // SECTION 6: Mata Kuliah yang Ditawarkan
        // =========================================================================
        $sec6 = ProdiQuestionSection::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'order' => 6],
            [
                'title' => 'Mata Kuliah yang Ditawarkan',
                'description' => 'Bagaimana dengan mata kuliah yang ditawarkan oleh Program Studi Sistem Informasi UKDW?',
            ]
        );

        $sec6RatingQuestions = [
            'Mata kuliah yang ditawarkan relevan dengan kebutuhan industri dan perkembangan teknologi.',
            'Materi perkuliahan sesuai dengan minat dan tujuan karier saya.',
            'Mata kuliah pilihan memberikan fleksibilitas dan ruang eksplorasi sesuai minat mahasiswa.',
            'Muatan dan struktur pengambilan mata kuliah cukup terarah dan mendukung proses belajar.',
            'Saya merasa materi yang dipelajari membekali saya untuk menghadapi dunia kerja.',
        ];

        foreach ($sec6RatingQuestions as $idx => $qText) {
            $qNum = $idx + 1;
            $q = ProdiQuestion::updateOrCreate(
                ['prodi_id' => $prodiSI->id, 'code' => "PSI-6-0{$qNum}"],
                [
                    'prodi_question_section_id' => $sec6->id,
                    'question_text' => $qText,
                    'type' => 'rating_5',
                    'is_required' => true,
                    'order' => $qNum,
                ]
            );
            foreach ($ratingOptions as $val => $optText) {
                ProdiQuestionOption::updateOrCreate(
                    ['prodi_question_id' => $q->id, 'code' => "PSI-6-0{$qNum}-0{$val}"],
                    ['option_text' => $optText, 'order' => $val]
                );
            }
        }

        // =========================================================================
        // SECTION 7: Kepuasan Total
        // =========================================================================
        $sec7 = ProdiQuestionSection::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'order' => 7],
            [
                'title' => 'Kepuasan Total',
                'description' => 'Bagaimana dengan kepuasan secara keseluruhan dari pengalaman Anda belajar selama kuliah di Program Studi Sistem Informasi UKDW?',
            ]
        );

        $sec7RatingQuestions = [
            'Secara keseluruhan, saya puas dengan pengalaman studi saya di Program Studi Sistem Informasi UKDW.',
            'Saya merasa telah berkembang secara akademik, profesional, dan pribadi selama studi.',
            'Saya tidak menyesal memilih Program Studi Sistem Informasi UKDW sebagai tempat kuliah.',
        ];

        foreach ($sec7RatingQuestions as $idx => $qText) {
            $qNum = $idx + 1;
            $q = ProdiQuestion::updateOrCreate(
                ['prodi_id' => $prodiSI->id, 'code' => "PSI-7-0{$qNum}"],
                [
                    'prodi_question_section_id' => $sec7->id,
                    'question_text' => $qText,
                    'type' => 'rating_5',
                    'is_required' => true,
                    'order' => $qNum,
                ]
            );
            foreach ($ratingOptions as $val => $optText) {
                ProdiQuestionOption::updateOrCreate(
                    ['prodi_question_id' => $q->id, 'code' => "PSI-7-0{$qNum}-0{$val}"],
                    ['option_text' => $optText, 'order' => $val]
                );
            }
        }

        // =========================================================================
        // SECTION 8: Rekomendasi Program Studi Sistem Informasi
        // =========================================================================
        $sec8 = ProdiQuestionSection::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'order' => 8],
            [
                'title' => 'Rekomendasi Program Studi Sistem Informasi',
                'description' => 'Kesediaan alumni dalam merekomendasikan program studi kepada kerabat dan calon mahasiswa.',
            ]
        );

        $sec8RatingQuestions = [
            'Saya merasa Program Studi Sistem Informasi UKDW layak untuk direkomendasikan karena memiliki ciri khas yang unik.',
            'Saya akan merekomendasikan Program Studi Sistem Informasi UKDW kepada saudara atau teman saya.',
        ];

        foreach ($sec8RatingQuestions as $idx => $qText) {
            $qNum = $idx + 1;
            $q = ProdiQuestion::updateOrCreate(
                ['prodi_id' => $prodiSI->id, 'code' => "PSI-8-0{$qNum}"],
                [
                    'prodi_question_section_id' => $sec8->id,
                    'question_text' => $qText,
                    'type' => 'rating_5',
                    'is_required' => true,
                    'order' => $qNum,
                ]
            );
            foreach ($ratingOptions as $val => $optText) {
                ProdiQuestionOption::updateOrCreate(
                    ['prodi_question_id' => $q->id, 'code' => "PSI-8-0{$qNum}-0{$val}"],
                    ['option_text' => $optText, 'order' => $val]
                );
            }
        }

        // =========================================================================
        // SECTION 9: Harapan dan Masukan untuk Program Studi Sistem Informasi
        // =========================================================================
        $sec9 = ProdiQuestionSection::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'order' => 9],
            [
                'title' => 'Harapan dan Masukan untuk Program Studi Sistem Informasi',
                'description' => 'Uraian harapan dan aspirasi konstruktif untuk peningkatan mutu prodi ke depan.',
            ]
        );

        ProdiQuestion::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'code' => 'PSI-9-01'],
            [
                'prodi_question_section_id' => $sec9->id,
                'question_text' => 'Apa harapan kamu sebagai alumni Sistem Informasi ke depannya?',
                'type' => 'text',
                'is_required' => true,
                'order' => 1,
            ]
        );

        ProdiQuestion::updateOrCreate(
            ['prodi_id' => $prodiSI->id, 'code' => 'PSI-9-02'],
            [
                'prodi_question_section_id' => $sec9->id,
                'question_text' => 'Apa saran atau masukan untuk peningkatan kualitas pendidikan di program studi ini?',
                'type' => 'text',
                'is_required' => true,
                'order' => 2,
            ]
        );
    }

    /**
     * Seeder Instrumen Kuesioner Khusus Program Studi Filsafat Keilahian (Kode: 31)
     */
    protected function seedFilsafatKeilahian(): void
    {
        $prodiFilsafat = Prodi::where('kode_prodi', '31')->first();
        if (! $prodiFilsafat) {
            return;
        }

        // Section 1: Karakteristik Pekerjaan Alumni
        $sec1 = ProdiQuestionSection::updateOrCreate(
            ['prodi_id' => $prodiFilsafat->id, 'order' => 1],
            [
                'title' => 'Karakteristik Pekerjaan Alumni',
                'description' => 'Pertanyaan khusus bagi alumni Program Studi Filsafat Keilahian mengenai bidang pelayanan dan profesi.',
            ]
        );

        // Pertanyaan PFK-01 (dahulu F2D1 di tabel pertanyaan universitas)
        $q = ProdiQuestion::updateOrCreate(
            ['prodi_id' => $prodiFilsafat->id, 'code' => 'PFK-01'],
            [
                'prodi_question_section_id' => $sec1->id,
                'question_text' => 'Jenis Pekerjaan Anda (Khusus Alumni Teologi / Filsafat Keilahian)',
                'type' => 'single_choice',
                'is_required' => true,
                'order' => 1,
            ]
        );

        $options = [
            'Gerejawi',
            'Non Gerejawi',
        ];

        foreach ($options as $idx => $optText) {
            $num = $idx + 1;
            ProdiQuestionOption::updateOrCreate(
                ['prodi_question_id' => $q->id, 'code' => "PFK-01-0{$num}"],
                ['option_text' => $optText, 'order' => $num]
            );
        }

        // Hapus butir dummy lama jika masih tersisa
        ProdiQuestion::whereIn('code', ['PSI-01', 'PSI-02', 'PSI-03'])->delete();

        // Sinkronkan data akademik (Nama, NIM, Tahun Kelulusan) untuk seluruh alumni ke prodi_responses
        $allAlumni = Biodata::all();
        foreach ($allAlumni as $alm) {
            KuesionerSyncService::syncProdiResponses($alm);
        }
    }
}
