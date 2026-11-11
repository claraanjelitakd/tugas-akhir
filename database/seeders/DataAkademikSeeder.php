<?php

namespace Database\Seeders;

use App\Models\DataAkademik;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder DataAkademik
 *
 * Mengisi data master riwayat akademik mahasiswa/alumni UKDW.
 */
class DataAkademikSeeder extends Seeder
{
    /**
     * Menyelaraskan data akademik dengan akun alumni yang sudah ada.
     *
     * Seeder ini tidak lagi membuat nama dummy atau mengganti nama akun.
     * Jika data akademik belum tersedia, identitas diambil dari akun dan
     * kohort contoh ditetapkan ke lulusan 2026.
     */
    public function run(): void
    {
        $alumniUsers = User::query()
            ->where('role', 'alumni')
            ->where('username', '!=', '72220001')
            ->get();

        foreach ($alumniUsers as $user) {
            $nim = $user->username;
            $existing = DataAkademik::query()->where('nim', $nim)->first();
            $nama = filled($existing?->nama) && $existing->nama !== '-' ? $existing->nama : $user->name;
            $personalEmail = $existing?->email_pribadi ?: $user->email;
            $angkatanMasuk = $existing?->angkatan_masuk ?? 2022;
            $ipk = $existing?->ip_kumulatif ?? 3.75;
            $totalSks = $existing?->total_sks ?? 144;

            DataAkademik::updateOrCreate(
                ['nim' => $nim],
                [
                    'nama' => $nama,
                    'email_pribadi' => $personalEmail,
                    'prodi_id' => $user->prodi_id ?? $existing?->prodi_id,
                    'angkatan_masuk' => $angkatanMasuk,
                    'tahun_akademik_masuk' => DataAkademik::formatAcademicEntryPeriod($angkatanMasuk),
                    'status_mahasiswa' => $existing?->status_mahasiswa ?? 'Lulus',
                    'tahun_akademik_lulus' => $existing?->tahun_akademik_lulus ?? 'Genap 2025/2026',
                    'tahun_lulus' => $existing?->tahun_lulus ?? 2026,
                    'tanggal_lulus' => $existing?->tanggal_lulus ?? '2026-08-15',
                    'ip_kumulatif' => $ipk,
                    'total_sks' => $totalSks,
                    'total_angka_kualitas' => $existing?->total_angka_kualitas ?? round($ipk * $totalSks, 2),
                    'tanggal_lahir' => $existing?->tanggal_lahir,
                    'jenis_kelamin' => $existing?->jenis_kelamin,
                    'warga_negara' => $existing?->warga_negara ?? 'WNI',
                ]
            );
        }
    }
}
