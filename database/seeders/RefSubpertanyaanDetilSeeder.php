<?php

namespace Database\Seeders;

use App\Models\RefSubpertanyaan2021;
use App\Models\RefSubpertanyaanDetil;
use Illuminate\Database\Seeder;

class RefSubpertanyaanDetilSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $options = [
            // BIO_JK
            ['kode_pertanyaan' => 'BIO_JK', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Pria', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_JK', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Wanita', 'jump_to' => null],

            // BIO_BIDANG_DIVISI (Bidang pekerjaan atau divisi)
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Akademik / Pendidikan', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Administrasi / Tata Usaha', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 3, 'kode_opsi' => '3', 'option_text' => 'Keuangan / Akuntansi', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 4, 'kode_opsi' => '4', 'option_text' => 'Sumber Daya Manusia', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 5, 'kode_opsi' => '5', 'option_text' => 'Teknologi Informasi', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 6, 'kode_opsi' => '6', 'option_text' => 'Operasional', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 7, 'kode_opsi' => '7', 'option_text' => 'Pemasaran / Komunikasi', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 8, 'kode_opsi' => '8', 'option_text' => 'Penjualan / Pengembangan Bisnis', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 9, 'kode_opsi' => '9', 'option_text' => 'Penelitian dan Pengembangan', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 10, 'kode_opsi' => '10', 'option_text' => 'Pelayanan / Customer Service', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 11, 'kode_opsi' => '11', 'option_text' => 'Produksi / Manufaktur', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 12, 'kode_opsi' => '12', 'option_text' => 'Kesehatan', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 13, 'kode_opsi' => '13', 'option_text' => 'Hukum', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 14, 'kode_opsi' => '14', 'option_text' => 'Teknik', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_BIDANG_DIVISI', 'order' => 15, 'kode_opsi' => '15', 'option_text' => 'Lainnya', 'jump_to' => null],

            // BIO_PEND_TINGKAT (Jenjang studi lanjut)
            ['kode_pertanyaan' => 'BIO_PEND_TINGKAT', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'D3', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_PEND_TINGKAT', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'D4', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_PEND_TINGKAT', 'order' => 3, 'kode_opsi' => '3', 'option_text' => 'S1', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_PEND_TINGKAT', 'order' => 4, 'kode_opsi' => '4', 'option_text' => 'S2', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_PEND_TINGKAT', 'order' => 5, 'kode_opsi' => '5', 'option_text' => 'S3', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_PEND_TINGKAT', 'order' => 6, 'kode_opsi' => '6', 'option_text' => 'Profesi', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_PEND_TINGKAT', 'order' => 7, 'kode_opsi' => '7', 'option_text' => 'Spesialis', 'jump_to' => null],
            ['kode_pertanyaan' => 'BIO_PEND_TINGKAT', 'order' => 8, 'kode_opsi' => '8', 'option_text' => 'Lainnya', 'jump_to' => null],

            // F2G (Posisi jabatan)
            ['kode_pertanyaan' => 'F2G', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Direksi', 'jump_to' => null],
            ['kode_pertanyaan' => 'F2G', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Top Manager', 'jump_to' => null],
            ['kode_pertanyaan' => 'F2G', 'order' => 3, 'kode_opsi' => '3', 'option_text' => 'Middle Manager', 'jump_to' => null],
            ['kode_pertanyaan' => 'F2G', 'order' => 4, 'kode_opsi' => '4', 'option_text' => 'Low Manager', 'jump_to' => null],
            ['kode_pertanyaan' => 'F2G', 'order' => 5, 'kode_opsi' => '5', 'option_text' => 'Supervisor', 'jump_to' => null],
            ['kode_pertanyaan' => 'F2G', 'order' => 6, 'kode_opsi' => '6', 'option_text' => 'Staff', 'jump_to' => null],

            // F2H (Skala perusahaan)
            ['kode_pertanyaan' => 'F2H', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Regional/Lokal', 'jump_to' => null],
            ['kode_pertanyaan' => 'F2H', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Nasional', 'jump_to' => null],
            ['kode_pertanyaan' => 'F2H', 'order' => 3, 'kode_opsi' => '3', 'option_text' => 'Internasional', 'jump_to' => null],

            // F5D (Skala / Tingkat Tempat Kerja)
            ['kode_pertanyaan' => 'F5D', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Regional/Lokal', 'jump_to' => null],
            ['kode_pertanyaan' => 'F5D', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Nasional', 'jump_to' => null],
            ['kode_pertanyaan' => 'F5D', 'order' => 3, 'kode_opsi' => '3', 'option_text' => 'Internasional', 'jump_to' => null],

            // F3 (Waktu mulai mencari kerja)
            ['kode_pertanyaan' => 'F3', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Sebelum lulus ... bulan', 'jump_to' => null],
            ['kode_pertanyaan' => 'F3', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Sesudah lulus ... bulan', 'jump_to' => null],
            ['kode_pertanyaan' => 'F3', 'order' => 3, 'kode_opsi' => '3', 'option_text' => 'Saya tidak mencari kerja', 'jump_to' => 'F504'],

            // F4 (Cara mencari pekerjaan)
            ['kode_pertanyaan' => 'F4', 'order' => 1, 'kode_opsi' => 'F401', 'option_text' => 'Melalui iklan di koran/majalah, brosur', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 2, 'kode_opsi' => 'F402', 'option_text' => 'Melamar ke perusahaan tanpa mengetahui lowongan yang ada', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 3, 'kode_opsi' => 'F403', 'option_text' => 'Pergi ke bursa/pameran kerja', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 4, 'kode_opsi' => 'F404', 'option_text' => 'Mencari lewat internet/iklan online/milis', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 5, 'kode_opsi' => 'F405', 'option_text' => 'Dihubungi oleh perusahaan', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 6, 'kode_opsi' => 'F406', 'option_text' => 'Menghubungi Kemenakertrans', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 7, 'kode_opsi' => 'F407', 'option_text' => 'Menghubungi agen tenaga kerja komersial/swasta', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 8, 'kode_opsi' => 'F408', 'option_text' => 'Memeroleh informasi dari pusat/kantor pengembangan karir fakultas/universitas', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 9, 'kode_opsi' => 'F409', 'option_text' => 'Menghubungi kantor kemahasiswaan/hubungan alumni', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 10, 'kode_opsi' => 'F410', 'option_text' => 'Membangun jejaring (network) sejak masih kuliah', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 11, 'kode_opsi' => 'F411', 'option_text' => 'Melalui relasi (misalnya dosen, orang tua, saudara, teman, dll)', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 12, 'kode_opsi' => 'F412', 'option_text' => 'Membangun bisnis sendiri', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 13, 'kode_opsi' => 'F413', 'option_text' => 'Melalui penempatan kerja atau magang', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 14, 'kode_opsi' => 'F414', 'option_text' => 'Bekerja di tempat yang sama dengan tempat kerja semasa kuliah', 'jump_to' => null],
            ['kode_pertanyaan' => 'F4', 'order' => 15, 'kode_opsi' => 'F415', 'option_text' => 'Lainnya', 'jump_to' => null],

            // F504 (Apakah mendapatkan pekerjaan <= 6 bulan)
            ['kode_pertanyaan' => 'F504', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Ya', 'jump_to' => 'F502'],
            ['kode_pertanyaan' => 'F504', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Tidak', 'jump_to' => 'F506'],

            // F505 (Berapa rata-rata pendapatan per bulan / Take home pay - hanya 1 opsi tunggal)
            ['kode_pertanyaan' => 'F505', 'order' => 1, 'kode_opsi' => 'F5051', 'option_text' => 'Rata-rata Pendapatan per Bulan (Take Home Pay)', 'jump_to' => null],

            // F8 (Status pekerjaan saat ini)
            ['kode_pertanyaan' => 'F8', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Bekerja (full time/part time)', 'jump_to' => null],
            ['kode_pertanyaan' => 'F8', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Belum memungkinkan bekerja', 'jump_to' => 'F18'],
            ['kode_pertanyaan' => 'F8', 'order' => 3, 'kode_opsi' => '3', 'option_text' => 'Wiraswasta', 'jump_to' => null],
            ['kode_pertanyaan' => 'F8', 'order' => 4, 'kode_opsi' => '4', 'option_text' => 'Melanjutkan Pendidikan', 'jump_to' => 'F18'],
            ['kode_pertanyaan' => 'F8', 'order' => 5, 'kode_opsi' => '5', 'option_text' => 'Tidak Kerja tetapi sedang mencari kerja', 'jump_to' => 'F3'],

            // F10 (Aktif mencari pekerjaan dalam 4 minggu terakhir)
            ['kode_pertanyaan' => 'F10', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Tidak', 'jump_to' => 'F3'],
            ['kode_pertanyaan' => 'F10', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Tidak, tetapi saya sedang menunggu hasil lamaran kerja', 'jump_to' => 'F3'],
            ['kode_pertanyaan' => 'F10', 'order' => 3, 'kode_opsi' => '3', 'option_text' => 'Ya, saya akan mulai bekerja dalam 2 minggu ke depan', 'jump_to' => 'F3'],
            ['kode_pertanyaan' => 'F10', 'order' => 4, 'kode_opsi' => '4', 'option_text' => 'Ya, tapi saya belum pasti akan bekerja dalam 2 minggu ke depan', 'jump_to' => 'F3'],
            ['kode_pertanyaan' => 'F10', 'order' => 5, 'kode_opsi' => '5', 'option_text' => 'Lainnya', 'jump_to' => 'F3'],

            // F12 (Sumber biaya studi lanjut)
            ['kode_pertanyaan' => 'F12', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Biaya Sendiri / Keluarga', 'jump_to' => null],
            ['kode_pertanyaan' => 'F12', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Beasiswa Pemerintah', 'jump_to' => null],
            ['kode_pertanyaan' => 'F12', 'order' => 3, 'kode_opsi' => '3', 'option_text' => 'Beasiswa Swasta / Perusahaan', 'jump_to' => null],
            ['kode_pertanyaan' => 'F12', 'order' => 4, 'kode_opsi' => '4', 'option_text' => 'Beasiswa Luar Negeri', 'jump_to' => null],
            ['kode_pertanyaan' => 'F12', 'order' => 5, 'kode_opsi' => '5', 'option_text' => 'Lainnya', 'jump_to' => null],

            // F11 (Jenis perusahaan/instansi)
            ['kode_pertanyaan' => 'F11', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Instansi pemerintah', 'jump_to' => null],
            ['kode_pertanyaan' => 'F11', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Organisasi non-profit/LSM', 'jump_to' => null],
            ['kode_pertanyaan' => 'F11', 'order' => 3, 'kode_opsi' => '3', 'option_text' => 'Perusahaan swasta', 'jump_to' => null],
            ['kode_pertanyaan' => 'F11', 'order' => 4, 'kode_opsi' => '4', 'option_text' => 'Wiraswasta/perusahaan sendiri', 'jump_to' => null],
            ['kode_pertanyaan' => 'F11', 'order' => 5, 'kode_opsi' => '5', 'option_text' => 'Lainnya', 'jump_to' => null],
            ['kode_pertanyaan' => 'F11', 'order' => 6, 'kode_opsi' => '6', 'option_text' => 'BUMN/BUMD', 'jump_to' => null],
            ['kode_pertanyaan' => 'F11', 'order' => 7, 'kode_opsi' => '7', 'option_text' => 'Institusi/Organisasi Multilateral', 'jump_to' => null],

            // F14 (Keeratan hubungan bidang studi dengan pekerjaan)
            ['kode_pertanyaan' => 'F14', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Sangat erat', 'jump_to' => null],
            ['kode_pertanyaan' => 'F14', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Erat', 'jump_to' => null],
            ['kode_pertanyaan' => 'F14', 'order' => 3, 'kode_opsi' => '3', 'option_text' => 'Cukup erat', 'jump_to' => null],
            ['kode_pertanyaan' => 'F14', 'order' => 4, 'kode_opsi' => '4', 'option_text' => 'Kurang erat', 'jump_to' => null],
            ['kode_pertanyaan' => 'F14', 'order' => 5, 'kode_opsi' => '5', 'option_text' => 'Tidak erat', 'jump_to' => null],

            // F15 (Tingkat pendidikan paling tepat)
            ['kode_pertanyaan' => 'F15', 'order' => 1, 'kode_opsi' => '1', 'option_text' => 'Setingkat lebih tinggi', 'jump_to' => null],
            ['kode_pertanyaan' => 'F15', 'order' => 2, 'kode_opsi' => '2', 'option_text' => 'Tingkat yang sama', 'jump_to' => null],
            ['kode_pertanyaan' => 'F15', 'order' => 3, 'kode_opsi' => '3', 'option_text' => 'Setingkat lebih rendah', 'jump_to' => null],
            ['kode_pertanyaan' => 'F15', 'order' => 4, 'kode_opsi' => '4', 'option_text' => 'Tidak perlu pendidikan tinggi', 'jump_to' => null],

            // F16 (Alasan mengambil pekerjaan tidak sesuai)
            ['kode_pertanyaan' => 'F16', 'order' => 1, 'kode_opsi' => 'F1601', 'option_text' => 'Pertanyaan tidak sesuai; pekerjaan saya sekarang sudah sesuai dengan pendidikan saya', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 2, 'kode_opsi' => 'F1602', 'option_text' => 'Saya belum mendapatkan pekerjaan yang lebih sesuai', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 3, 'kode_opsi' => 'F1603', 'option_text' => 'Di pekerjaan ini saya memeroleh prospek karir yang baik', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 4, 'kode_opsi' => 'F1604', 'option_text' => 'Saya lebih suka bekerja di area pekerjaan yang tidak ada hubungannya dengan pendidikan saya', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 5, 'kode_opsi' => 'F1605', 'option_text' => 'Saya dipromosikan ke posisi yang kurang berhubungan dengan pendidikan saya dibanding posisi sebelumnya', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 6, 'kode_opsi' => 'F1606', 'option_text' => 'Saya dapat memeroleh pendapatan yang lebih tinggi di pekerjaan ini', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 7, 'kode_opsi' => 'F1607', 'option_text' => 'Pekerjaan saya saat ini lebih aman/terjamin/secure', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 8, 'kode_opsi' => 'F1608', 'option_text' => 'Pekerjaan saya saat ini lebih menarik', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 9, 'kode_opsi' => 'F1609', 'option_text' => 'Pekerjaan saya saat ini lebih memungkinkan saya mengambil pekerjaan tambahan/jadwal yang fleksibel, dll.', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 10, 'kode_opsi' => 'F1610', 'option_text' => 'Pekerjaan saya saat ini lokasinya lebih dekat dari rumah saya', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 11, 'kode_opsi' => 'F1611', 'option_text' => 'Pekerjaan saya saat ini dapat lebih menjamin kebutuhan keluarga saya', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 12, 'kode_opsi' => 'F1612', 'option_text' => 'Pada awal meniti karir ini, saya harus menerima pekerjaan yang tidak berhubungan dengan pendidikan saya', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 13, 'kode_opsi' => 'F1613', 'option_text' => 'Lainnya', 'jump_to' => null],
            ['kode_pertanyaan' => 'F16', 'order' => 14, 'kode_opsi' => 'F1614', 'option_text' => 'Lainnya isian', 'jump_to' => null],
        ];

        // Opsi untuk F21 s/d F27 (Penekanan metode pembelajaran skala 1-5)
        $f2Codes = ['F21', 'F22', 'F23', 'F24', 'F25', 'F26', 'F27'];
        $f2Labels = [
            1 => 'Sangat Besar',
            2 => 'Besar',
            3 => 'Cukup Besar',
            4 => 'Kurang',
            5 => 'Tidak Sama Sekali',
        ];
        foreach ($f2Codes as $code) {
            foreach ($f2Labels as $val => $txt) {
                $options[] = [
                    'kode_pertanyaan' => $code,
                    'order' => $val,
                    'kode_opsi' => (string) $val,
                    'option_text' => $txt,
                    'jump_to' => null,
                ];
            }
        }

        // Opsi untuk F17a1..a7 dan F17b1..b7 (Skala kompetensi 1-5: Sangat Rendah s/d Sangat Tinggi)
        $f17Codes = [
            'F17a1', 'F17b1',
            'F17a2', 'F17b2',
            'F17a3', 'F17b3',
            'F17a4', 'F17b4',
            'F17a5', 'F17b5',
            'F17a6', 'F17b6',
            'F17a7', 'F17b7',
        ];
        $f17Labels = [
            1 => 'Sangat Rendah',
            2 => 'Rendah',
            3 => 'Cukup',
            4 => 'Tinggi',
            5 => 'Sangat Tinggi',
        ];
        foreach ($f17Codes as $code) {
            foreach ($f17Labels as $val => $txt) {
                $options[] = [
                    'kode_pertanyaan' => $code,
                    'order' => $val,
                    'kode_opsi' => (string) $val,
                    'option_text' => $txt,
                    'jump_to' => null,
                ];
            }
        }

        // Bersihkan opsi lama F505 yang berlebih jika sebelumnya ada 3 opsi (F5052, F5053)
        RefSubpertanyaanDetil::where('kode_pertanyaan', 'F505')
            ->whereNotIn('kode_opsi', ['F5051'])
            ->delete();

        // Bersihkan opsi F18a dan opsi lama F12 yang berlebih (> 5 opsi)
        RefSubpertanyaanDetil::where('kode_pertanyaan', 'F18a')->delete();
        RefSubpertanyaanDetil::where('kode_pertanyaan', 'F12')->where('order', '>', 5)->delete();

        $subpertanyaans = RefSubpertanyaan2021::all()->keyBy('kode_pertanyaan');

        foreach ($options as $item) {
            $q = $subpertanyaans[$item['kode_pertanyaan']] ?? null;
            if ($q) {
                RefSubpertanyaanDetil::updateOrCreate(
                    [
                        'pertanyaan_id' => $q->id,
                        'kode_opsi' => $item['kode_opsi'],
                    ],
                    [
                        'kode_pertanyaan' => $item['kode_pertanyaan'],
                        'option_text' => $item['option_text'],
                        'jump_to' => $item['jump_to'],
                        'order' => $item['order'],
                    ]
                );
            }
        }
    }
}
