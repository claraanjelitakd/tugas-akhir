<?php

namespace Database\Seeders;

use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Menyediakan akun awal aplikasi beserta kontrak relasi unit kerjanya.
 *
 * Admin Fakultas wajib memiliki fakultas_id dan email akun agar scope akses
 * serta pengiriman tautan reset kata sandi selalu dapat digunakan.
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminPassword = Hash::make('password123'); // Ganti dengan password yang lebih aman di production

        // 1. Seed Superadmin
        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@ukdw.ac.id',
                'password' => $adminPassword,
                'role' => 'superadmin',
                'must_change_password' => false,
            ]
        );

        // 1b. Seed Admin Biro 3
        User::updateOrCreate(
            ['username' => 'admin_biro3'],
            [
                'name' => 'Admin Biro 3 UKDW',
                'email' => 'biro3@ukdw.ac.id',
                'password' => $adminPassword,
                'role' => 'admin_biro3',
                'must_change_password' => false,
            ]
        );

        // 1c. Seed Admin Prodi untuk Setiap Program Studi
        $prodiUserMapping = [
            '71' => ['username' => 'admin_ti', 'name' => 'Admin Prodi Informatika'],
            '72' => ['username' => 'admin_si', 'name' => 'Admin Prodi Sistem Informasi'],
            '11' => ['username' => 'admin_manajemen', 'name' => 'Admin Prodi Manajemen'],
            '12' => ['username' => 'admin_akuntansi', 'name' => 'Admin Prodi Akuntansi'],
            '21' => ['username' => 'admin_arsitektur', 'name' => 'Admin Prodi Arsitektur'],
            '22' => ['username' => 'admin_desainproduk', 'name' => 'Admin Prodi Desain Produk'],
            '41' => ['username' => 'admin_biologi', 'name' => 'Admin Prodi Biologi'],
            '42' => ['username' => 'admin_tekpangan', 'name' => 'Admin Prodi Teknologi Pangan'],
            '61' => ['username' => 'admin_kedokteran', 'name' => 'Admin Prodi Kedokteran'],
            '31' => ['username' => 'admin_filsafat', 'name' => 'Admin Prodi Filsafat Keilahian'],
            '81' => ['username' => 'admin_pbi', 'name' => 'Admin Prodi Pendidikan Bahasa Inggris'],
            '82' => ['username' => 'admin_humanitas', 'name' => 'Admin Prodi Studi Humanitas'],
        ];

        foreach ($prodiUserMapping as $kode => $userData) {
            $prodi = Prodi::where('kode_prodi', $kode)->first();
            if ($prodi) {
                User::updateOrCreate(
                    ['username' => $userData['username']],
                    [
                        'name' => $userData['name'],
                        'email' => $userData['username'].'@ukdw.ac.id',
                        'password' => $adminPassword,
                        'role' => 'admin_prodi',
                        'prodi_id' => $prodi->id,
                        'must_change_password' => false,
                    ]
                );
            }
        }

        // 1d. Seed Admin Fakultas untuk Setiap Fakultas
        $fakultasUserMapping = [
            '7' => ['username' => 'admin_fti', 'name' => 'Admin Fakultas Teknologi Informasi'],
            '1' => ['username' => 'admin_fb', 'name' => 'Admin Fakultas Bisnis'],
            '2' => ['username' => 'admin_fad', 'name' => 'Admin Fakultas Arsitektur dan Desain'],
            '4' => ['username' => 'admin_biotek', 'name' => 'Admin Fakultas Bioteknologi'],
            '6' => ['username' => 'admin_fk', 'name' => 'Admin Fakultas Kedokteran'],
            '3' => ['username' => 'admin_theologi', 'name' => 'Admin Fakultas Theologi'],
            '8' => ['username' => 'admin_fkh', 'name' => 'Admin Fakultas Kependidikan dan Humaniora'],
        ];

        foreach ($fakultasUserMapping as $kode => $userData) {
            $fakultas = RefFakultas::where('kode_fakultas', $kode)->first();
            if ($fakultas) {
                User::updateOrCreate(
                    ['username' => $userData['username']],
                    [
                        'name' => $userData['name'],
                        'email' => $userData['username'].'@ukdw.ac.id',
                        'password' => $adminPassword,
                        'role' => 'admin_fakultas',
                        'fakultas_id' => $fakultas->id,
                        'must_change_password' => false,
                    ]
                );
            }
        }

        $invalidAdminFakultas = User::query()
            ->where('role', 'admin_fakultas')
            ->get()
            ->filter(function (User $user): bool {
                return ! $user->fakultas_id
                    || ! filter_var($user->getRawOriginal('email'), FILTER_VALIDATE_EMAIL);
            });

        if ($invalidAdminFakultas->isNotEmpty()) {
            $usernames = $invalidAdminFakultas->pluck('username')->implode(', ');

            throw new RuntimeException(
                "Setiap akun Admin Fakultas wajib memiliki fakultas_id dan email valid. Periksa: {$usernames}."
            );
        }

    }
}
