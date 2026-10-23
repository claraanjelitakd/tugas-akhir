<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model DataAkademik (tabel: data_akademik)
 *
 * Mengelola data master riwayat akademik mahasiswa/alumni UKDW.
 * Merupakan Single Source of Truth untuk:
 * - Identitas master (nama, tempat/tanggal lahir, jenis kelamin, agama, golongan darah, warga negara)
 * - Identitas pendukung akademik (nisn, no_bpjs)
 * - Riwayat akademik (angkatan, IPK, total SKS, status kelulusan, tahun & periode kelulusan)
 * - Email resmi mahasiswa (email_students)
 *
 * Kontak dan domisili aktif alumni berada di Biodata agar tidak ada dua sumber
 * perubahan untuk alamat, telepon, email pribadi, dan wilayah tempat tinggal.
 */
class DataAkademik extends Model
{
    use HasFactory;

    protected $table = 'data_akademik';

    protected $fillable = [
        'nim',
        'nama',
        'angkatan_masuk',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'jenis_kelamin',
        'golongan_darah',
        'warga_negara',
        'email_students',
        'nisn',
        'no_bpjs',
        'asal_sekolah',
        'alamat_asal_sekolah',
        'kota_kabupaten_asal_sekolah',
        'provinsi_asal_sekolah',
        'jurusan_asal_sekolah',
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
