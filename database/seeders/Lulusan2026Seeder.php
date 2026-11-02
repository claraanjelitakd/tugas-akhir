<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use App\Models\Yudisium;
use Carbon\Carbon;
/**
 * Lulusan2026Seeder
 *
 * Menambahkan data 10 alumni lulusan tahun 2026 dengan profil karier kosong (skeleton),
 * membuktikan prinsip Zero Duplication & Single Source of Truth:
 * - Seluruh identitas pribadi, tanggal lahir, dan riwayat akademik otomatis ditarik dari `data_akademik`.
 * - Data akademik tetap menjadi sumber data identitas dan kelulusan.
 * - Data skripsi otomatis ditarik dari `yudisium`.
 * - Kolom karier & perusahaan di `biodata` sengaja kosong (NULL).
 */
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class Lulusan2026Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $propinsi = Propinsi::firstOrCreate(
            ['kode_provinsi' => '34'],
            ['nama_provinsi' => 'DI Yogyakarta']
        );
        $kabupaten = Kabupaten::firstOrCreate(
            ['kode_kabupaten' => '34.04'],
            ['propinsi_id' => $propinsi->id, 'nama_kabupaten' => 'Kabupaten Sleman']
        );

        $prodiInformatika = Prodi::firstOrCreate(['kode_prodi' => '71'], ['nama_prodi' => 'Informatika']);
        $prodiSI = Prodi::firstOrCreate(['kode_prodi' => '72'], ['nama_prodi' => 'Sistem Informasi']);

        $alumniData = [
            // 5 Alumni Informatika (71)
            [
                'nim' => '71220011',
                'nama' => 'Marcello Dave Christian',
                'prodi' => $prodiInformatika,
                'jk' => 'Laki-laki',
                'tgl_lahir' => '2002-03-12',
                'tempat_lahir' => 'Yogyakarta',
                'ipk' => 3.82,
                'judul_ta' => 'Implementasi Machine Learning untuk Deteksi Pola Fraud Transaksi Digital',
                'linkedin_username' => 'marcello.christian',
            ],
            [
                'nim' => '71220012',
                'nama' => 'Natasha Aurelia Budiman',
                'prodi' => $prodiInformatika,
                'jk' => 'Perempuan',
                'tgl_lahir' => '2002-05-18',
                'tempat_lahir' => 'Semarang',
                'ipk' => 3.75,
                'judul_ta' => 'Pengembangan Sistem Pengenalan Suara Berbasis Transformer untuk Aksesibilitas',
                'linkedin_username' => 'natasha.budiman',
            ],
            [
                'nim' => '71220013',
                'nama' => 'Rendy Saputra Pratama',
                'prodi' => $prodiInformatika,
                'jk' => 'Laki-laki',
                'tgl_lahir' => '2002-07-22',
                'tempat_lahir' => 'Solo',
                'ipk' => 3.68,
                'judul_ta' => 'Optimasi Routing Pengiriman Barang Berbasis Algoritma Genetika Terdistribusi',
                'linkedin_username' => 'rendy.pratama',
            ],
            [
                'nim' => '71220014',
                'nama' => 'Sarah Vania Maharani',
                'prodi' => $prodiInformatika,
                'jk' => 'Perempuan',
                'tgl_lahir' => '2002-09-05',
                'tempat_lahir' => 'Magelang',
                'ipk' => 3.90,
                'judul_ta' => 'Analisis Sentimen Multi-Bahasa pada Media Sosial Menggunakan Model LLM Terkuantisasi',
                'linkedin_username' => 'sarah.maharani',
            ],
            [
                'nim' => '71220015',
                'nama' => 'Thomas Aldo Wirawan',
                'prodi' => $prodiInformatika,
                'jk' => 'Laki-laki',
                'tgl_lahir' => '2002-11-14',
                'tempat_lahir' => 'Surabaya',
                'ipk' => 3.71,
                'judul_ta' => 'Rancang Bangun Arsitektur Microservices Terdesentralisasi dengan gRPC',
                'linkedin_username' => 'thomas.wirawan',
            ],

            // 5 Alumni Sistem Informasi (72)
            [
                'nim' => '72220011',
                'nama' => 'Albertus Denny Prasetyo',
                'prodi' => $prodiSI,
                'jk' => 'Laki-laki',
                'tgl_lahir' => '2002-01-20',
                'tempat_lahir' => 'Yogyakarta',
                'ipk' => 3.85,
                'judul_ta' => 'Evaluasi Kematangan Tata Kelola TI Berbasis COBIT 2019 pada Sektor Perbankan',
                'linkedin_username' => null,
            ],
            [
                'nim' => '72220012',
                'nama' => 'Chyntia Dewi Anggraini',
                'prodi' => $prodiSI,
                'jk' => 'Perempuan',
                'tgl_lahir' => '2002-04-16',
                'tempat_lahir' => 'Jakarta',
                'ipk' => 3.65,
                'judul_ta' => 'Perancangan Enterprise Architecture Sistem Logistik Menggunakan TOGAF ADM',
                'linkedin_username' => null,
            ],
            [
                'nim' => '72220013',
                'nama' => 'Felix Daniel Kurniawan',
                'prodi' => $prodiSI,
                'jk' => 'Laki-laki',
                'tgl_lahir' => '2002-06-30',
                'tempat_lahir' => 'Medan',
                'ipk' => 3.78,
                'judul_ta' => 'Analisis Adopsi Pembayaran Digital QRIS pada Usaha Mikro Menggunakan Model UTAUT2',
                'linkedin_username' => null,
            ],
            [
                'nim' => '72220014',
                'nama' => 'Gisella Nadia Christie',
                'prodi' => $prodiSI,
                'jk' => 'Perempuan',
                'tgl_lahir' => '2002-08-25',
                'tempat_lahir' => 'Klaten',
                'ipk' => 3.60,
                'judul_ta' => 'Implementasi Customer Relationship Management Berbasis Omnichannel pada E-Commerce',
                'linkedin_username' => null,
            ],
            [
                'nim' => '72220015',
                'nama' => 'Hendra Surya Wijaya',
                'prodi' => $prodiSI,
                'jk' => 'Laki-laki',
                'tgl_lahir' => '2002-10-10',
                'tempat_lahir' => 'Bandung',
                'ipk' => 3.92,
                'judul_ta' => 'Pemodelan Proses Bisnis dan Otomasi Layanan Akademik Kampus Berbasis BPMN 2.0',
                'linkedin_username' => null,
            ],
        ];

        foreach ($alumniData as $index => $item) {
            $nim = $item['nim'];
            $cleanName = strtolower(str_replace(' ', '', explode(' ', $item['nama'])[0]));
            $personalEmail = $cleanName.($index + 11).'@gmail.com';
            $defaultDobPassword = Carbon::parse($item['tgl_lahir'])->format('dmY');

            // 1. Data Akademik Master Mahasiswa (Single Source of Truth)
            $dataAkademik = DataAkademik::updateOrCreate(
                ['nim' => $nim],
                [
                    'nama' => $item['nama'],
                    'angkatan_masuk' => 2022,
                    'status_mahasiswa' => 'Lulus',
                    'tahun_akademik_lulus' => 'Genap 2025/2026',
                    'tahun_lulus' => 2026,
                    'tanggal_lulus' => '2026-08-15',
                    'ip_kumulatif' => $item['ipk'],
                    'total_sks' => 144,
                    'total_angka_kualitas' => round($item['ipk'] * 144, 2),
                    'tempat_lahir' => $item['tempat_lahir'],
                    'tanggal_lahir' => $item['tgl_lahir'],
                    'jenis_kelamin' => $item['jk'],
                    'warga_negara' => 'WNI',
                    'email_students' => $nim.'@students.ukdw.ac.id',
                    'asal_sekolah' => 'SMA Kolese De Britto',
                    'alamat_asal_sekolah' => 'Jl. Laksda Adisucipto No. 161',
                    'kota_kabupaten_asal_sekolah' => 'Kabupaten Sleman',
                    'provinsi_asal_sekolah' => 'DI Yogyakarta',
                    'jurusan_asal_sekolah' => 'IPA',
                ]
            );

            // 2. Catatan Yudisium Kelulusan
            $yudisium = Yudisium::updateOrCreate(
                ['nim' => $nim],
                [
                    'judul_ta' => $item['judul_ta'],
                    'judul_ta_inggris' => 'Final Project Title in English for '.$item['nama'],
                    'url_publikasi' => 'https://repository.ukdw.ac.id/handle/123456789/'.$nim,
                    'jenis_publikasi' => 'Jurnal Nasional Terakreditasi',
                    'status_publikasi' => 'Terbit',
                    'keterangan_hasil_yudisium' => ($item['ipk'] >= 3.75 ? 'Lulus dengan Pujian (Cumlaude)' : 'Sangat Memuaskan'),
                    'status_lulus' => 'Lulus',
                ]
            );

            // 4. Akun Pengguna User Alumni
            $user = User::updateOrCreate(
                ['username' => $nim],
                [
                    'name' => $item['nama'],
                    'email' => $personalEmail,
                    'password' => Hash::make($defaultDobPassword),
                    'role' => 'alumni',
                    'prodi_id' => $item['prodi']?->id,
                    'must_change_password' => true,
                ]
            );

            // 5. Skeleton Record Biodata (Profil Karier Dikosongkan Murni)
            Biodata::updateOrCreate(
                ['nim' => $nim],
                [
                    'user_id' => $user->id,
                    'yudisium_id' => $yudisium->id,
                    'prodi_id' => $item['prodi']?->id,
                    // Seluruh data karier & perusahaan dikosongkan (NULL)
                    'perusahaan_id' => null,
                    'atasan_id' => null,
                    'kategori_pekerjaan' => null,
                    'posisi_jabatan' => null,
                    'posisi_wiraswasta' => null,
                    'pendidikan_tingkat' => null,
                    'perguruan_tinggi' => null,
                    'pendidikan_prodi' => null,
                    'jenis_pekerjaan' => null,
                    'gaji' => null,
                    'alamat_saat_ini' => null,
                    'kabupaten_id' => null,
                    'propinsi_id' => null,
                    'kode_pos' => null,
                    'nomor_telepon' => null,
                    'email_pribadi' => null,
                    // LinkedIn username (opsional terisi untuk keperluan pengujian sinkronisasi LinkedIn)
                    'linkedin_username' => $item['linkedin_username'],
                    'linkedin_url' => $item['linkedin_username'] ? 'https://linkedin.com/in/'.$item['linkedin_username'] : null,
                    'instagram_url' => null,
                    'facebook_url' => null,
                    'skills' => null,
                    'experience' => null,
                ]
            );
        }

        $this->command?->info('Berhasil menambahkan 10 alumni lulusan 2026 dengan profil karier kosong!');
    }
}
