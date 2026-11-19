<?php

namespace Database\Seeders;

use App\Models\DataAkademik;
use App\Models\Prodi;
use App\Models\Yudisium;
use Illuminate\Database\Seeder;

/**
 * Seeder Calon Alumni Yudisium
 *
 * Menambahkan data calon lulusan mahasiswa pada tabel data_akademik dan yudisium
 * yang belum memiliki akun pengguna (User) maupun profil Biodata, khusus untuk
 * kebutuhan pengujian modul sinkronisasi akun alumni oleh Super Admin.
 */
class CalonAlumniYudisiumSeeder extends Seeder
{
    /**
     * Jalankan penambahan 3 data calon alumni yudisium siap sinkron.
     */
    public function run(): void
    {
        $prodiInformatika = Prodi::firstOrCreate(
            ['kode_prodi' => '71'],
            ['nama_prodi' => 'Informatika', 'jenjang' => 'S1']
        );
        $prodiSI = Prodi::firstOrCreate(
            ['kode_prodi' => '72'],
            ['nama_prodi' => 'Sistem Informasi', 'jenjang' => 'S1']
        );
        $prodiManajemen = Prodi::firstOrCreate(
            ['kode_prodi' => '11'],
            ['nama_prodi' => 'Manajemen', 'jenjang' => 'S1']
        );

        $candidates = [
            [
                'nim' => '71220101',
                'nama' => 'Bima Arya Pratama',
                'email_pribadi' => 'bima.arya@gmail.com',
                'prodi_id' => $prodiInformatika->id,
                'angkatan_masuk' => 2022,
                'tahun_akademik_masuk' => 'Gasal 2022/2023',
                'status_mahasiswa' => 'Lulus',
                'tahun_akademik_lulus' => 'Genap 2025/2026',
                'tahun_lulus' => 2026,
                'tanggal_lulus' => '2026-08-15',
                'tanggal_lahir' => '2004-05-12',
                'jenis_kelamin' => 'Laki-laki',
                'warga_negara' => 'WNI',
                'ip_kumulatif' => 3.82,
                'total_sks' => 144,
                'total_angka_kualitas' => 550.08,
                'yudisium' => [
                    'judul_ta' => 'Rancang Bangun Sistem Deteksi Intrusi Jaringan Berbasis Deep Learning',
                    'judul_ta_inggris' => 'Design and Development of Network Intrusion Detection System Based on Deep Learning',
                    'url_publikasi' => 'https://repository.ukdw.ac.id/handle/123456789/71220101',
                    'jenis_publikasi' => 'Jurnal Nasional',
                    'status_publikasi' => 'Terbit',
                    'keterangan_hasil_yudisium' => 'Lulus dengan Pujian',
                    'status_lulus' => 'Lulus',
                ],
            ],
            [
                'nim' => '72220601',
                'nama' => 'Clara Stephanie Putri',
                'email_pribadi' => 'clara.stephanie@gmail.com',
                'prodi_id' => $prodiSI->id,
                'angkatan_masuk' => 2022,
                'tahun_akademik_masuk' => 'Gasal 2022/2023',
                'status_mahasiswa' => 'Lulus',
                'tahun_akademik_lulus' => 'Genap 2025/2026',
                'tahun_lulus' => 2026,
                'tanggal_lulus' => '2026-08-15',
                'tanggal_lahir' => '2004-09-18',
                'jenis_kelamin' => 'Perempuan',
                'warga_negara' => 'WNI',
                'ip_kumulatif' => 3.79,
                'total_sks' => 144,
                'total_angka_kualitas' => 545.76,
                'yudisium' => [
                    'judul_ta' => 'Analisis dan Perancangan Enterprise Architecture Sistem Informasi Akademik dengan TOGAF ADM',
                    'judul_ta_inggris' => 'Analysis and Design of Academic Information System Enterprise Architecture Using TOGAF ADM',
                    'url_publikasi' => 'https://repository.ukdw.ac.id/handle/123456789/72220601',
                    'jenis_publikasi' => 'Prosiding Seminar',
                    'status_publikasi' => 'Terbit',
                    'keterangan_hasil_yudisium' => 'Lulus dengan Pujian',
                    'status_lulus' => 'Lulus',
                ],
            ],
            [
                'nim' => '11220101',
                'nama' => 'Daniel Kristianto Wibowo',
                'email_pribadi' => 'daniel.kristianto@gmail.com',
                'prodi_id' => $prodiManajemen->id,
                'angkatan_masuk' => 2022,
                'tahun_akademik_masuk' => 'Gasal 2022/2023',
                'status_mahasiswa' => 'Lulus',
                'tahun_akademik_lulus' => 'Genap 2025/2026',
                'tahun_lulus' => 2026,
                'tanggal_lulus' => '2026-08-15',
                'tanggal_lahir' => '2003-11-20',
                'jenis_kelamin' => 'Laki-laki',
                'warga_negara' => 'WNI',
                'ip_kumulatif' => 3.75,
                'total_sks' => 144,
                'total_angka_kualitas' => 540.00,
                'yudisium' => [
                    'judul_ta' => 'Strategi Pemasaran Berkelanjutan dan Kepuasan Pelanggan pada Industri Kreatif Digital',
                    'judul_ta_inggris' => 'Sustainable Marketing Strategy and Customer Satisfaction in the Digital Creative Industry',
                    'url_publikasi' => 'https://repository.ukdw.ac.id/handle/123456789/11220101',
                    'jenis_publikasi' => 'Jurnal Nasional',
                    'status_publikasi' => 'Terbit',
                    'keterangan_hasil_yudisium' => 'Sangat Memuaskan',
                    'status_lulus' => 'Lulus',
                ],
            ],
        ];

        Yudisium::withoutEvents(function () use ($candidates) {
            foreach ($candidates as $cand) {
                $yudisiumData = $cand['yudisium'];
                unset($cand['yudisium']);

                DataAkademik::updateOrCreate(
                    ['nim' => $cand['nim']],
                    $cand
                );

                Yudisium::updateOrCreate(
                    ['nim' => $cand['nim']],
                    $yudisiumData
                );
            }
        });
    }
}
