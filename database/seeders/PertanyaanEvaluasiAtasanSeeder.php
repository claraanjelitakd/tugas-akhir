<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder Master Butir Instrumen Evaluasi Kepuasan Pengguna Lulusan (Atasan) UKDW
 *
 * Mengisi master butir pertanyaan evaluasi atasan yang mencakup:
 * 1. Butir Evaluasi Kesiapan Kerja Alumni (Pilihan Ganda: Sangat siap, Cukup Siap, Kurang siap, Tidak siap)
 * 2. 5 Butir Karakteristik Perusahaan & Sebaran Alumni (pilihan ganda)
 * 3. 12 Butir Penilaian Aspek Kinerja Lulusan (Skala Likert 5: Sangat Tinggi, Tinggi, Cukup, Kurang, Sangat Kurang)
 */
class PertanyaanEvaluasiAtasanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $likertOptions = ['Sangat Tinggi', 'Tinggi', 'Cukup', 'Kurang', 'Sangat Kurang'];

        $pertanyaans = [
            // =========================================================================
            // 1. TINGKAT KESIAPAN KERJA ALUMNI (EVALUASI KESIAPAN UMUM)
            // =========================================================================
            [
                'kode' => 'tingkat_kesiapan_kerja',
                'aspek' => 'Tingkat Kesiapan Alumni dalam Bekerja',
                'deskripsi' => 'Tingkat kesiapan alumni UKDW dalam bekerja dan beradaptasi di Perusahaan / Institusi ini.',
                'kategori' => 'Kesiapan Kerja',
                'tipe' => 'pilihan_ganda',
                'pilihan_jawaban' => json_encode(['Sangat siap', 'Cukup Siap', 'Kurang siap', 'Tidak siap']),
                'order' => 1,
            ],

            // =========================================================================
            // 2. KARAKTERISTIK PERUSAHAAN & SEBARAN ALUMNI (LANGKAH 2)
            // =========================================================================
            [
                'kode' => 'bentuk_perusahaan',
                'aspek' => 'Bentuk Perusahaan / Institusi',
                'deskripsi' => 'Bentuk badan usaha atau institusi tempat alumni bekerja.',
                'kategori' => 'Karakteristik Perusahaan',
                'tipe' => 'pilihan_ganda',
                'pilihan_jawaban' => json_encode(['BUMN', 'Perusahaan Terbatas', 'Koperasi', 'CV', 'Firma']),
                'order' => 2,
            ],
            [
                'kode' => 'skala_perusahaan',
                'aspek' => 'Skala Perusahaan / Organisasi',
                'deskripsi' => 'Jangkauan operasional utama perusahaan atau organisasi.',
                'kategori' => 'Karakteristik Perusahaan',
                'tipe' => 'pilihan_ganda',
                'pilihan_jawaban' => json_encode(['Lokal', 'Nasional', 'Internasional']),
                'order' => 3,
            ],
            [
                'kode' => 'jumlah_pegawai',
                'aspek' => 'Jumlah Pegawai Keseluruhan di Perusahaan ini',
                'deskripsi' => 'Perkiraan jumlah seluruh pegawai pada perusahaan atau organisasi.',
                'kategori' => 'Karakteristik Perusahaan',
                'tipe' => 'pilihan_ganda',
                'pilihan_jawaban' => json_encode(['< 50 Orang', '51 - 100 Orang', '101 - 150 Orang', '151 - 300 Orang', '301 - 500 Orang', '> 500 Orang']),
                'order' => 4,
            ],
            [
                'kode' => 'jumlah_alumni_ukdw',
                'aspek' => 'Jumlah Alumni UKDW yang Bekerja di Perusahaan ini',
                'deskripsi' => 'Perkiraan jumlah alumni UKDW yang bekerja pada perusahaan atau organisasi ini.',
                'kategori' => 'Karakteristik Perusahaan',
                'tipe' => 'pilihan_ganda',
                'pilihan_jawaban' => json_encode(['< 5 Orang', '6 - 10 Orang', '11 - 20 Orang', '> 21 Orang']),
                'order' => 5,
            ],
            [
                'kode' => 'standar_gaji_pertama',
                'aspek' => 'Standar Gaji Pertama (per bulan) yang Diberikan ke Alumni UKDW',
                'deskripsi' => 'Rentang gaji pertama per bulan yang diberikan kepada alumni UKDW.',
                'kategori' => 'Karakteristik Perusahaan',
                'tipe' => 'pilihan_ganda',
                'pilihan_jawaban' => json_encode(['< 1.000.000', '1.000.000 - 1.500.000', '1.500.000 - 2.000.000', '2.000.000 - 3.000.000', '3.000.000 - 4.000.000', '> 5.000.000']),
                'order' => 6,
            ],

            // =========================================================================
            // 3. 12 BUTIR ASPEK PENILAIAN KINERJA LULUSAN (STANDAR KEMENDIKBUD / DIKTI)
            // =========================================================================
            [
                'kode' => 'integritas',
                'aspek' => 'Integritas (Etika dan Moral)',
                'deskripsi' => 'Kejujuran, etika profesi, kedisiplinan, dan tanggung jawab kerja.',
                'kategori' => 'Kinerja',
                'tipe' => 'likert_5',
                'pilihan_jawaban' => json_encode($likertOptions),
                'order' => 7,
            ],
            [
                'kode' => 'keahlian_ilmu',
                'aspek' => 'Keahlian Berdasarkan Bidang Ilmu (Profesionalisme)',
                'deskripsi' => 'Kemampuan penguasaan teori dan keilmuan yang dipelajari selama kuliah.',
                'kategori' => 'Kinerja',
                'tipe' => 'likert_5',
                'pilihan_jawaban' => json_encode($likertOptions),
                'order' => 8,
            ],
            [
                'kode' => 'komunikasi',
                'aspek' => 'Komunikasi',
                'deskripsi' => 'Kemampuan menyampaikan gagasan, koordinasi lisan maupun tulisan.',
                'kategori' => 'Kinerja',
                'tipe' => 'likert_5',
                'pilihan_jawaban' => json_encode($likertOptions),
                'order' => 9,
            ],
            [
                'kode' => 'kerjasama_tim',
                'aspek' => 'Kerjasama Tim',
                'deskripsi' => 'Kemampuan berkolaborasi dan bekerja sama efektif dalam kelompok kerja.',
                'kategori' => 'Kinerja',
                'tipe' => 'likert_5',
                'pilihan_jawaban' => json_encode($likertOptions),
                'order' => 10,
            ],
            [
                'kode' => 'pengembangan_diri',
                'aspek' => 'Pengembangan Diri',
                'deskripsi' => 'Kemauan belajar hal baru, adaptif, dan terus meningkatkan keterampilan.',
                'kategori' => 'Kinerja',
                'tipe' => 'likert_5',
                'pilihan_jawaban' => json_encode($likertOptions),
                'order' => 11,
            ],
            [
                'kode' => 'kreativitas',
                'aspek' => 'Kreativitas',
                'deskripsi' => 'Kemampuan memberikan gagasan segar dan ide solutif.',
                'kategori' => 'Kinerja',
                'tipe' => 'likert_5',
                'pilihan_jawaban' => json_encode($likertOptions),
                'order' => 12,
            ],
            [
                'kode' => 'bahasa_asing',
                'aspek' => 'Kemampuan Bahasa Asing (Misal: Bahasa Inggris)',
                'deskripsi' => 'Kemampuan berbahasa internasional dalam konteks pekerjaan.',
                'kategori' => 'Kinerja',
                'tipe' => 'likert_5',
                'pilihan_jawaban' => json_encode($likertOptions),
                'order' => 13,
            ],
            [
                'kode' => 'penggunaan_teknologi',
                'aspek' => 'Penggunaan Alat / Teknologi Modern (Teknologi IT)',
                'deskripsi' => 'Kecakapan memanfaatkan perangkat lunak dan teknologi digital penunjang.',
                'kategori' => 'Kinerja',
                'tipe' => 'likert_5',
                'pilihan_jawaban' => json_encode($likertOptions),
                'order' => 14,
            ],
            [
                'kode' => 'kemampuan_manajerial',
                'aspek' => 'Kemampuan Manajerial',
                'deskripsi' => 'Kemampuan merencanakan, mengorganisasi, dan memimpin tugas.',
                'kategori' => 'Kinerja',
                'tipe' => 'likert_5',
                'pilihan_jawaban' => json_encode($likertOptions),
                'order' => 15,
            ],
            [
                'kode' => 'kemampuan_analisis',
                'aspek' => 'Kemampuan Melakukan Analisis',
                'deskripsi' => 'Kemampuan menganalisis masalah secara kritis dan berbasis data.',
                'kategori' => 'Kinerja',
                'tipe' => 'likert_5',
                'pilihan_jawaban' => json_encode($likertOptions),
                'order' => 16,
            ],
            [
                'kode' => 'menulis_laporan',
                'aspek' => 'Menulis Laporan',
                'deskripsi' => 'Kemampuan menyusun dokumentasi kerja dan laporan resmi yang rapi.',
                'kategori' => 'Kinerja',
                'tipe' => 'likert_5',
                'pilihan_jawaban' => json_encode($likertOptions),
                'order' => 17,
            ],
            [
                'kode' => 'inovasi_kreativitas',
                'aspek' => 'Inovasi / Kreativitas',
                'deskripsi' => 'Inisiatif menciptakan terobosan atau perbaikan proses kerja baru.',
                'kategori' => 'Kinerja',
                'tipe' => 'likert_5',
                'pilihan_jawaban' => json_encode($likertOptions),
                'order' => 18,
            ],
        ];

        foreach ($pertanyaans as $p) {
            DB::table('pertanyaan_evaluasi_atasan')->updateOrInsert(
                ['kode' => $p['kode']],
                [
                    'aspek' => $p['aspek'],
                    'deskripsi' => $p['deskripsi'],
                    'kategori' => $p['kategori'],
                    'tipe' => $p['tipe'],
                    'pilihan_jawaban' => $p['pilihan_jawaban'],
                    'is_active' => true,
                    'order' => $p['order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
