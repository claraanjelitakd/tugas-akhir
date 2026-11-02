<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Yudisium;
use Illuminate\Database\Seeder;

/**
 * Seeder Yudisium
 *
 * Mengisi data tugas akhir, publikasi, dan status yudisium.
 * Tanpa duplikasi tahun akademik / tahun lulus (terkelola di DataAkademik).
 */
class YudisiumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $alumniUsers = User::where('role', 'alumni')
            ->where('username', '!=', '72220001')
            ->get();

        $taIndonesia = [
            'Rancang Bangun Sistem Informasi Tracer Study Berbasis Arsitektur Microservices',
            'Analisis Sentimen Opini Publik Menggunakan Algoritma Bidirectional LSTM',
            'Implementasi Model Machine Learning untuk Prediksi Keterserapan Alumni di Dunia Kerja',
            'Perancangan Antarmuka Pengguna Adaptif untuk Aplikasi Manajemen Karier Lulusan',
            'Evaluasi Kinerja Jaringan Cloud Hybrid Menggunakan Metode Quality of Service',
            'Penerapan Natural Language Processing untuk Ekstraksi Data Dokumen Legal',
            'Desain Sistem Rekomendasi Lowongan Kerja Berdasarkan Profil dan Keahlian Lulusan',
            'Pengembangan Aplikasi Mobile Deteksi Dini Kualitas Air Menggunakan IoT',
            'Analisis Efektivitas Kurikulum Berbasis Outcome-Based Education terhadap Kesiapan Kerja',
            'Optimasi Rute Distribusi Logistik Menggunakan Algoritma Genetika',
        ];

        $taInggris = [
            'Design and Development of Tracer Study Information System Based on Microservices Architecture',
            'Public Sentiment Analysis Using Bidirectional LSTM Deep Learning Algorithm',
            'Machine Learning Model Implementation for Predicting Alumni Employability in Job Market',
            'Adaptive User Interface Design for Graduate Career Management Application',
            'Performance Evaluation of Hybrid Cloud Network Using Quality of Service Method',
            'Natural Language Processing Application for Legal Document Information Extraction',
            'Job Vacancy Recommendation System Design Based on Graduate Skill Profile',
            'Mobile Application Development for Early Water Quality Detection Utilizing IoT',
            'Effectiveness Analysis of Outcome-Based Education Curriculum on Job Readiness',
            'Logistics Distribution Route Optimization Using Genetic Algorithm',
        ];

        $predikatList = [
            'Lulus dengan Pujian',
            'Sangat Memuaskan',
            'Lulus dengan Pujian',
            'Sangat Memuaskan',
            'Memuaskan',
        ];

        foreach ($alumniUsers as $index => $user) {
            $nim = $user->username;

            Yudisium::updateOrCreate(
                ['nim' => $nim],
                [
                    'judul_ta' => $taIndonesia[$index % count($taIndonesia)],
                    'judul_ta_inggris' => $taInggris[$index % count($taInggris)],
                    'url_publikasi' => 'https://repository.ukdw.ac.id/handle/123456789/'.$nim,
                    'jenis_publikasi' => ($index % 2 == 0) ? 'Jurnal Nasional' : 'Prosiding Seminar',
                    'status_publikasi' => 'Terbit',
                    'keterangan_hasil_yudisium' => $predikatList[$index % count($predikatList)],
                    'status_lulus' => 'Lulus',
                ]
            );
        }
    }
}
