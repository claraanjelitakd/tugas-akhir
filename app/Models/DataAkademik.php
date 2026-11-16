<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model DataAkademik (tabel: data_akademik)
 *
 * Mengelola data master riwayat akademik mahasiswa/alumni UKDW.
 * Merupakan Single Source of Truth untuk:
 * - Identitas master (nama, tanggal lahir, jenis kelamin, warga negara)
 * - Riwayat akademik (angkatan, IPK, total SKS, status kelulusan, tahun & periode kelulusan)
 * - Snapshot email pribadi dari sumber akademik saat impor data.
 *
 * Email pada model ini hanya menjadi nilai awal saat Biodata dibuat. Kontak aktif alumni
 * berada di Biodata dan perubahan pada Biodata tidak pernah ditulis balik ke model ini.
 * Kolom no_bpjs, golongan_darah, agama, dan nisn dihapus sesuai kepatuhan privasi UU PDP No. 27/2022.
 */
class DataAkademik extends Model
{
    use HasFactory;

    protected $table = 'data_akademik';

    protected $fillable = [
        'nim',
        'nama',
        'email_pribadi',
        'prodi_id',
        'angkatan_masuk',
        'tahun_akademik_masuk',
        'tanggal_lahir',
        'jenis_kelamin',
        'warga_negara',
        'status_mahasiswa',
        'tahun_akademik_lulus',
        'tahun_lulus',
        'tanggal_lulus',
        'ip_kumulatif',
        'total_sks',
        'total_angka_kualitas',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_lulus' => 'date',
        'angkatan_masuk' => 'integer',
        'tahun_lulus' => 'integer',
        'ip_kumulatif' => 'float',
    ];

    /**
     * Membentuk label periode masuk kanonik untuk mahasiswa reguler.
     *
     * @param  int|null  $angkatan  Tahun angkatan masuk.
     * @return string|null Label tahun akademik semester ganjil atau null jika tahun tidak valid.
     */
    public static function formatAcademicEntryPeriod(?int $angkatan): ?string
    {
        if ($angkatan === null || $angkatan < 1900) {
            return null;
        }

        return $angkatan.'/'.($angkatan + 1).' (Semester Ganjil)';
    }

    /**
     * Relasi ke model Biodata (profil aktif alumni & karier).
     */
    public function biodata()
    {
        return $this->hasOne(Biodata::class, 'nim', 'nim');
    }

    /**
     * Relasi ke model Yudisium (status lulus, tugas akhir, dan publikasi).
     */
    public function yudisium()
    {
        return $this->hasOne(Yudisium::class, 'nim', 'nim');
    }
}
