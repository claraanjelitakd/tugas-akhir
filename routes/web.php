<?php

use App\Http\Controllers\AdminBiroTiga\KelolaAlumni\DaftarAlumniController;
use App\Http\Controllers\AdminBiroTiga\KelolaAlumni\DetailAlumniController;
use App\Http\Controllers\AdminFakultas\Dashboard\DashboardController as DashboardFakultasController;
use App\Http\Controllers\AdminFakultas\KelolaAlumni\DaftarAlumniFakultasController;
use App\Http\Controllers\AdminFakultas\KelolaAlumni\DetailAlumniFakultasController;
use App\Http\Controllers\AdminFakultas\ManajemenAkunFakultasController;
use App\Http\Controllers\AdminFakultas\VerifikasiPerusahaan\VerifikasiPerusahaanFakultasController;
use App\Http\Controllers\AdminProdi\KelolaAlumni\DaftarAlumniProdiController;
use App\Http\Controllers\AdminProdi\KelolaPertanyaan\DaftarPertanyaanProdiController;
use App\Http\Controllers\AdminProdi\KelolaPertanyaan\KelolaOpsiProdiController;
use App\Http\Controllers\AdminProdi\KelolaPertanyaan\KelolaSectionProdiController;
use App\Http\Controllers\AdminProdi\KelolaPertanyaan\SimpanPertanyaanProdiController;
use App\Http\Controllers\AdminProdi\ManajemenAkunProdiController;
use App\Http\Controllers\AdminProdi\VerifikasiPerusahaan\VerifikasiPerusahaanProdiController;
use App\Http\Controllers\Alumni\Dashboard\DashboardController;
use App\Http\Controllers\Alumni\Kuesioner\KuesionerController;
use App\Http\Controllers\Alumni\Kuesioner\KuesionerProdiController;
use App\Http\Controllers\Alumni\Kuesioner\SimpanJawabanController;
use App\Http\Controllers\Alumni\Profil\ProfilController;
use App\Http\Controllers\Alumni\Profil\SimpanProfilController;
use App\Http\Controllers\EvaluasiAtasanController;
use App\Http\Controllers\Otentikasi\LoginController;
use App\Http\Controllers\Otentikasi\LupaKataSandiController;
use App\Http\Controllers\Otentikasi\UbahKataSandiController;
use App\Http\Controllers\Shared\LinkedInSyncController;
use App\Http\Controllers\SuperAdmin\Analytics\TracerAnalyticsController;
use App\Http\Controllers\SuperAdmin\KelolaAlumni\DaftarAlumniSuperAdminController;
use App\Http\Controllers\SuperAdmin\KelolaAlumni\DetailAlumniSuperAdminController;
use App\Http\Controllers\SuperAdmin\KelolaEvaluasiAtasanController;
use App\Http\Controllers\SuperAdmin\KelolaPertanyaan\DaftarPertanyaanController;
use App\Http\Controllers\SuperAdmin\KelolaPertanyaan\KelolaKuesionerProdiSuperAdminController;
use App\Http\Controllers\SuperAdmin\KelolaPertanyaan\KelolaOpsiController;
use App\Http\Controllers\SuperAdmin\KelolaPertanyaan\SimpanPertanyaanController;
use App\Http\Controllers\SuperAdmin\KelolaSection\KelolaKuesionerController;
use App\Http\Controllers\SuperAdmin\KelolaSection\KelolaSectionController;
use App\Http\Controllers\SuperAdmin\LogAktivitasController;
use App\Http\Controllers\SuperAdmin\ManajemenAkunController;
use App\Http\Controllers\SuperAdmin\MasterWilayah\MasterWilayahController;
use App\Http\Controllers\SuperAdmin\Perusahaan\VerifikasiPerusahaanSuperAdminController;
use App\Http\Controllers\Tamu\BerandaController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// Rute Tamu & Akses Publik (Guest / Public Token)
// =========================================================================
Route::get('/', [BerandaController::class, 'tampilkanBeranda']);

// Public Route: Kuesioner Evaluasi Pengguna Lulusan / Atasan UKDW (Tanpa Login)
Route::get('/evaluasi-atasan', [EvaluasiAtasanController::class, 'index'])->name('evaluasi-atasan.index');
Route::get('/evaluasi-atasan/{token}', [EvaluasiAtasanController::class, 'show'])->middleware('throttle:30,1')->name('evaluasi-atasan.show');
Route::post('/evaluasi-atasan/{token}', [EvaluasiAtasanController::class, 'store'])->middleware('throttle:10,1')->name('evaluasi-atasan.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'tampilkanHalamanLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'prosesLogin'])->middleware('throttle:5,1');

    // Lupa & Reset Kata Sandi via Email
    Route::get('/forgot-password', [LupaKataSandiController::class, 'tampilkanHalamanLupaKataSandi'])->name('password.request');
    Route::post('/forgot-password', [LupaKataSandiController::class, 'prosesKirimLinkReset'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [LupaKataSandiController::class, 'tampilkanHalamanResetKataSandi'])->name('password.reset');
    Route::post('/reset-password', [LupaKataSandiController::class, 'prosesResetKataSandi'])->middleware('throttle:5,1')->name('password.update');
});

// =========================================================================
// Rute Otentikasi Sesi (Logout)
// =========================================================================
// Rute logout mendukung method POST & GET agar selalu berhasil mengarahkan pengguna kembali ke Beranda (Home)
Route::post('/logout', [LoginController::class, 'prosesLogout'])->middleware('auth')->name('logout');

// =========================================================================
// Rute Terotentikasi (Authenticated)
// =========================================================================
Route::middleware('auth')->group(function () {

    // Ganti kata sandi wajib
    Route::get('/change-password', [UbahKataSandiController::class, 'tampilkanUbahKataSandi'])->name('change-password');
    Route::post('/change-password', [UbahKataSandiController::class, 'prosesUbahKataSandi']);

    // API endpoint helper wilayah untuk cascading dropdown asinkron (Kabupaten berdasarkan Provinsi)
    Route::get('/api/wilayah/kabupaten/{propinsiId}', [MasterWilayahController::class, 'getKabupatenByProvinsi'])->name('api.wilayah.kabupaten');

    // Rute yang mengharuskan password bawaan sudah diganti
    Route::middleware('must_change_password')->group(function () {

        // Rute Tambah Perusahaan Baru via Modal (Dapat diakses oleh Alumni & seluruh Admin pengelola)
        Route::middleware('role:alumni,admin_prodi,admin_fakultas,admin_biro3,superadmin')->group(function () {
            Route::post('/alumni/company', [SimpanProfilController::class, 'tambahPerusahaanBaru'])->name('alumni.company.store');
        });

        // -----------------------------------------------------------------
        // Rute Alumni
        // -----------------------------------------------------------------
        Route::middleware('role:alumni')->group(function () {
            // Redirect /alumni ke Dashboard
            Route::redirect('/alumni', '/alumni/dashboard');

            // Dashboard
            Route::get('/alumni/dashboard', [DashboardController::class, 'tampilkanDashboard'])->name('alumni.dashboard');

            // Profil
            Route::get('/alumni/profile', [ProfilController::class, 'tampilkanHalamanProfil'])->name('alumni.profile');
            Route::post('/alumni/profile', [SimpanProfilController::class, 'simpanPerubahanProfil'])->name('alumni.profile.simpan');

            // Kuesioner Umum Tracer Study
            Route::get('/alumni/kuesioner', [KuesionerController::class, 'tampilkanKuesioner'])->name('alumni.kuesioner');
            Route::post('/alumni/kuesioner', [SimpanJawabanController::class, 'simpanJawabanKuesioner'])->name('alumni.kuesioner.simpan');

            // Kuesioner Khusus Program Studi
            Route::get('/alumni/kuesioner-prodi', [KuesionerProdiController::class, 'tampilkanKuesionerProdi'])->name('alumni.kuesioner-prodi');
            Route::post('/alumni/kuesioner-prodi', [KuesionerProdiController::class, 'simpanJawaban'])->name('alumni.kuesioner-prodi.simpan');
        });

        // -----------------------------------------------------------------
        // Rute Undangan Evaluasi Atasan (Alumni & Seluruh Administrator)
        // -----------------------------------------------------------------
        Route::middleware('role:alumni,superadmin,admin_fakultas,admin_prodi,admin_biro3')->group(function () {
            Route::post('/alumni/kirim-email-atasan', [SimpanProfilController::class, 'kirimEmailAtasan'])->name('alumni.kirim-email-atasan');
        });

        // -----------------------------------------------------------------
        // Rute Admin Biro 3 (Biro Kemahasiswaan, Alumni & Pengembangan Karir)
        // -----------------------------------------------------------------
        Route::middleware('role:admin_biro3')->group(function () {
            // Dashboard Utama Biro 3
            Route::get('/biro3/dashboard', [App\Http\Controllers\AdminBiroTiga\Dashboard\DashboardController::class, 'tampilkanDashboard'])->name('biro3.dashboard');

            // Kelola Data Alumni, Verifikasi, & Sinkronisasi Profil LinkedIn
            Route::get('/biro3/alumni', [DaftarAlumniController::class, 'tampilkanDaftarAlumni'])->name('biro3.alumni.index');
            Route::get('/biro3/alumni/export-zip', [DaftarAlumniController::class, 'exportZip'])->name('biro3.alumni.export-zip');
            Route::post('/biro3/alumni/blast-email', [DaftarAlumniController::class, 'blastEmail'])->name('biro3.alumni.blast-email');
            Route::post('/biro3/alumni/{id}/send-email', [DaftarAlumniController::class, 'sendReminderEmail'])->name('biro3.alumni.send-email');
            Route::get('/biro3/alumni/{id}', [DetailAlumniController::class, 'tampilkanDetailAlumni'])->name('biro3.alumni.show');
            Route::get('/biro3/alumni/{id}/export-excel', [DetailAlumniController::class, 'exportExcel'])->name('biro3.alumni.export-excel');
            Route::post('/biro3/alumni/{id}/profile', [DetailAlumniController::class, 'updateProfile'])->name('biro3.alumni.profile.update');
        });

        // -----------------------------------------------------------------
        // Rute Admin Prodi (Program Studi)
        // -----------------------------------------------------------------
        Route::middleware('role:admin_prodi')->group(function () {
            // Pengaturan Kuesioner Prodi dalam lingkup Prodi yang sedang login
            Route::get('/prodi/kuesioner', [KelolaKuesionerProdiSuperAdminController::class, 'index'])->name('prodi.kuesioner.index');
            Route::post('/prodi/kuesioner/sections', [KelolaKuesionerProdiSuperAdminController::class, 'storeSection'])->name('prodi.kuesioner.sections.store');
            Route::put('/prodi/kuesioner/sections/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'updateSection'])->name('prodi.kuesioner.sections.update');
            Route::delete('/prodi/kuesioner/sections/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'destroySection'])->name('prodi.kuesioner.sections.destroy');
            Route::post('/prodi/kuesioner/sections/reorder', [KelolaKuesionerProdiSuperAdminController::class, 'reorderSection'])->name('prodi.kuesioner.sections.reorder');
            Route::post('/prodi/kuesioner/pertanyaan', [KelolaKuesionerProdiSuperAdminController::class, 'storeQuestion'])->name('prodi.kuesioner.pertanyaan.store');
            Route::put('/prodi/kuesioner/pertanyaan/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'updateQuestion'])->name('prodi.kuesioner.pertanyaan.update');
            Route::delete('/prodi/kuesioner/pertanyaan/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'destroyQuestion'])->name('prodi.kuesioner.pertanyaan.destroy');
            Route::post('/prodi/kuesioner/pertanyaan/reorder', [KelolaKuesionerProdiSuperAdminController::class, 'reorderQuestion'])->name('prodi.kuesioner.pertanyaan.reorder');
            Route::post('/prodi/kuesioner/opsi', [KelolaKuesionerProdiSuperAdminController::class, 'storeOption'])->name('prodi.kuesioner.opsi.store');
            Route::put('/prodi/kuesioner/opsi/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'updateOption'])->name('prodi.kuesioner.opsi.update');
            Route::delete('/prodi/kuesioner/opsi/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'destroyOption'])->name('prodi.kuesioner.opsi.destroy');

            // Dashboard Utama Program Studi
            Route::get('/prodi/dashboard', [App\Http\Controllers\AdminProdi\Dashboard\DashboardController::class, 'tampilkanDashboard'])->name('prodi.dashboard');

            // Direktori & Detail Alumni Khusus Program Studi
            Route::get('/prodi/alumni', [DaftarAlumniProdiController::class, 'index'])->name('prodi.alumni.index');
            Route::post('/prodi/alumni/{id}/send-email', [DaftarAlumniProdiController::class, 'sendReminderEmail'])->name('prodi.alumni.send-email');
            Route::get('/prodi/alumni/{id}', [DaftarAlumniProdiController::class, 'show'])->name('prodi.alumni.show');
            Route::get('/prodi/alumni/{id}/export-excel', [DaftarAlumniProdiController::class, 'exportExcel'])->name('prodi.alumni.export-excel');
            Route::post('/prodi/alumni/{id}/profile', [DaftarAlumniProdiController::class, 'updateProfile'])->name('prodi.alumni.profile.update');

            // Kelola Section Kuesioner Prodi
            Route::get('/prodi/sections', [KelolaSectionProdiController::class, 'index'])->name('prodi.sections.index');
            Route::post('/prodi/sections/reorder', [KelolaSectionProdiController::class, 'reorder'])->name('prodi.sections.reorder');
            Route::post('/prodi/sections', [KelolaSectionProdiController::class, 'store'])->name('prodi.sections.store');
            Route::put('/prodi/sections/{id}', [KelolaSectionProdiController::class, 'update'])->name('prodi.sections.update');
            Route::delete('/prodi/sections/{id}', [KelolaSectionProdiController::class, 'destroy'])->name('prodi.sections.destroy');

            // Kelola Pertanyaan & Opsi Kuesioner Khusus Prodi
            Route::get('/prodi/pertanyaan', [DaftarPertanyaanProdiController::class, 'index'])->name('prodi.pertanyaan.index');
            Route::post('/prodi/pertanyaan', [SimpanPertanyaanProdiController::class, 'store'])->name('prodi.pertanyaan.store');
            Route::post('/prodi/pertanyaan/reorder', [SimpanPertanyaanProdiController::class, 'reorder'])->name('prodi.pertanyaan.reorder');
            Route::put('/prodi/pertanyaan/{id}', [SimpanPertanyaanProdiController::class, 'update'])->name('prodi.pertanyaan.update');
            Route::delete('/prodi/pertanyaan/{id}', [SimpanPertanyaanProdiController::class, 'destroy'])->name('prodi.pertanyaan.destroy');

            // Kelola Pilihan Opsi Pertanyaan Prodi
            Route::post('/prodi/opsi', [KelolaOpsiProdiController::class, 'store'])->name('prodi.opsi.store');
            Route::put('/prodi/opsi/{id}', [KelolaOpsiProdiController::class, 'update'])->name('prodi.opsi.update');
            Route::delete('/prodi/opsi/{id}', [KelolaOpsiProdiController::class, 'destroy'])->name('prodi.opsi.destroy');
            // Verifikasi & Approval Perusahaan Pengajuan Alumni (Khusus Prodi)
            Route::get('/prodi/perusahaan', [VerifikasiPerusahaanProdiController::class, 'index'])->name('prodi.perusahaan.index');
            Route::post('/prodi/perusahaan/{id}/verify', [VerifikasiPerusahaanProdiController::class, 'verify'])->name('prodi.perusahaan.verify');
            Route::post('/prodi/perusahaan/{id}/replace', [VerifikasiPerusahaanProdiController::class, 'replace'])->name('prodi.perusahaan.replace');
            Route::put('/prodi/perusahaan/{id}', [VerifikasiPerusahaanProdiController::class, 'updateAndVerify'])->name('prodi.perusahaan.update');
            Route::post('/prodi/perusahaan/{id}/reject', [VerifikasiPerusahaanProdiController::class, 'reject'])->name('prodi.perusahaan.reject');
            Route::delete('/prodi/perusahaan/{id}', [VerifikasiPerusahaanProdiController::class, 'destroy'])->name('prodi.perusahaan.destroy');
            Route::get('/prodi/perusahaan/search-verified', [VerifikasiPerusahaanProdiController::class, 'searchVerified'])->name('prodi.perusahaan.search-verified');
            Route::get('/prodi/perusahaan/{id}/recommendations', [VerifikasiPerusahaanProdiController::class, 'recommendations'])->name('prodi.perusahaan.recommendations');

            // Manajemen Akun Mahasiswa / Alumni Prodi Sendiri
            Route::get('/prodi/manajemen-akun', [ManajemenAkunProdiController::class, 'index'])->name('prodi.manajemen-akun.index');
            Route::put('/prodi/manajemen-akun/{id}', [ManajemenAkunProdiController::class, 'update'])->name('prodi.manajemen-akun.update');
            Route::post('/prodi/manajemen-akun/{id}/reset-default-notify', [ManajemenAkunProdiController::class, 'resetToDefaultAndNotify'])->name('prodi.manajemen-akun.reset-default-notify');

            // Sinkronisasi Profil LinkedIn Khusus Program Studi
            Route::get('/prodi/linkedin-sync', [LinkedInSyncController::class, 'index'])->name('prodi.linkedin-sync.index');
            Route::post('/prodi/linkedin-sync/batch', [LinkedInSyncController::class, 'syncBatch'])->name('prodi.linkedin-sync.batch');
            Route::post('/prodi/linkedin-sync/{id}', [LinkedInSyncController::class, 'syncSingle'])->name('prodi.linkedin-sync.single');
            Route::get('/prodi/linkedin-sync/results/{id}', [LinkedInSyncController::class, 'showResult'])->name('prodi.linkedin-sync.result.show');
            Route::post('/prodi/linkedin-sync/results/{id}/approve', [LinkedInSyncController::class, 'approveResult'])->name('prodi.linkedin-sync.result.approve');
            Route::post('/prodi/linkedin-sync/results/{id}/plot-again', [LinkedInSyncController::class, 'plotAgain'])->name('prodi.linkedin-sync.result.plot-again');
            Route::post('/prodi/linkedin-sync/results/{id}/reject', [LinkedInSyncController::class, 'rejectResult'])->name('prodi.linkedin-sync.result.reject');
            Route::get('/prodi/linkedin-sync/alumni/{id}/history', [LinkedInSyncController::class, 'history'])->name('prodi.linkedin-sync.alumni.history');
        });

        // -----------------------------------------------------------------
        // Rute Admin Fakultas (Dekanat & Gugus Kendali Mutu Fakultas)
        // -----------------------------------------------------------------
        Route::middleware('role:admin_fakultas')->group(function () {
            // Pengaturan Kuesioner Prodi untuk seluruh Prodi dalam Fakultas
            Route::get('/fakultas/prodi-kuesioner', [KelolaKuesionerProdiSuperAdminController::class, 'index'])->name('fakultas.prodi-kuesioner.index');
            Route::post('/fakultas/prodi-kuesioner/sections', [KelolaKuesionerProdiSuperAdminController::class, 'storeSection'])->name('fakultas.prodi-kuesioner.sections.store');
            Route::put('/fakultas/prodi-kuesioner/sections/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'updateSection'])->name('fakultas.prodi-kuesioner.sections.update');
            Route::delete('/fakultas/prodi-kuesioner/sections/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'destroySection'])->name('fakultas.prodi-kuesioner.sections.destroy');
            Route::post('/fakultas/prodi-kuesioner/sections/reorder', [KelolaKuesionerProdiSuperAdminController::class, 'reorderSection'])->name('fakultas.prodi-kuesioner.sections.reorder');
            Route::post('/fakultas/prodi-kuesioner/pertanyaan', [KelolaKuesionerProdiSuperAdminController::class, 'storeQuestion'])->name('fakultas.prodi-kuesioner.pertanyaan.store');
            Route::put('/fakultas/prodi-kuesioner/pertanyaan/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'updateQuestion'])->name('fakultas.prodi-kuesioner.pertanyaan.update');
            Route::delete('/fakultas/prodi-kuesioner/pertanyaan/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'destroyQuestion'])->name('fakultas.prodi-kuesioner.pertanyaan.destroy');
            Route::post('/fakultas/prodi-kuesioner/pertanyaan/reorder', [KelolaKuesionerProdiSuperAdminController::class, 'reorderQuestion'])->name('fakultas.prodi-kuesioner.pertanyaan.reorder');
            Route::post('/fakultas/prodi-kuesioner/opsi', [KelolaKuesionerProdiSuperAdminController::class, 'storeOption'])->name('fakultas.prodi-kuesioner.opsi.store');
            Route::put('/fakultas/prodi-kuesioner/opsi/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'updateOption'])->name('fakultas.prodi-kuesioner.opsi.update');
            Route::delete('/fakultas/prodi-kuesioner/opsi/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'destroyOption'])->name('fakultas.prodi-kuesioner.opsi.destroy');

            // Dashboard Utama Fakultas
            Route::get('/fakultas/dashboard', [DashboardFakultasController::class, 'tampilkanDashboard'])->name('fakultas.dashboard');

            // Direktori & Detail Alumni dalam Lingkup Fakultas (Filter Prodi dalam Fakultas)
            Route::get('/fakultas/alumni', [DaftarAlumniFakultasController::class, 'index'])->name('fakultas.alumni.index');
            Route::post('/fakultas/alumni/{id}/send-email', [DaftarAlumniFakultasController::class, 'sendReminderEmail'])->name('fakultas.alumni.send-email');
            Route::get('/fakultas/alumni/{id}', [DetailAlumniFakultasController::class, 'show'])->name('fakultas.alumni.show');
            Route::get('/fakultas/alumni/{id}/export-excel', [DetailAlumniFakultasController::class, 'exportExcel'])->name('fakultas.alumni.export-excel');
            Route::post('/fakultas/alumni/{id}/profile', [DetailAlumniFakultasController::class, 'updateProfile'])->name('fakultas.alumni.profile.update');

            // Verifikasi & Approval Perusahaan Pengajuan Alumni (Lingkup Fakultas)
            Route::get('/fakultas/perusahaan', [VerifikasiPerusahaanFakultasController::class, 'index'])->name('fakultas.perusahaan.index');
            Route::post('/fakultas/perusahaan/{id}/verify', [VerifikasiPerusahaanFakultasController::class, 'verify'])->name('fakultas.perusahaan.verify');
            Route::post('/fakultas/perusahaan/{id}/replace', [VerifikasiPerusahaanFakultasController::class, 'replace'])->name('fakultas.perusahaan.replace');
            Route::put('/fakultas/perusahaan/{id}', [VerifikasiPerusahaanFakultasController::class, 'updateAndVerify'])->name('fakultas.perusahaan.update');
            Route::post('/fakultas/perusahaan/{id}/reject', [VerifikasiPerusahaanFakultasController::class, 'reject'])->name('fakultas.perusahaan.reject');
            Route::delete('/fakultas/perusahaan/{id}', [VerifikasiPerusahaanFakultasController::class, 'destroy'])->name('fakultas.perusahaan.destroy');
            Route::get('/fakultas/perusahaan/search-verified', [VerifikasiPerusahaanFakultasController::class, 'searchVerified'])->name('fakultas.perusahaan.search-verified');
            Route::get('/fakultas/perusahaan/{id}/recommendations', [VerifikasiPerusahaanFakultasController::class, 'recommendations'])->name('fakultas.perusahaan.recommendations');

            // Manajemen Akun Mahasiswa / Alumni Se-Fakultas
            Route::get('/fakultas/manajemen-akun', [ManajemenAkunFakultasController::class, 'index'])->name('fakultas.manajemen-akun.index');
            Route::put('/fakultas/manajemen-akun/{id}', [ManajemenAkunFakultasController::class, 'update'])->name('fakultas.manajemen-akun.update');
            Route::post('/fakultas/manajemen-akun/{id}/reset-default-notify', [ManajemenAkunFakultasController::class, 'resetToDefaultAndNotify'])->name('fakultas.manajemen-akun.reset-default-notify');

            // Sinkronisasi Profil LinkedIn Khusus Lingkup Fakultas
            Route::get('/fakultas/linkedin-sync', [LinkedInSyncController::class, 'index'])->name('fakultas.linkedin-sync.index');
            Route::post('/fakultas/linkedin-sync/batch', [LinkedInSyncController::class, 'syncBatch'])->name('fakultas.linkedin-sync.batch');
            Route::post('/fakultas/linkedin-sync/{id}', [LinkedInSyncController::class, 'syncSingle'])->name('fakultas.linkedin-sync.single');
            Route::get('/fakultas/linkedin-sync/results/{id}', [LinkedInSyncController::class, 'showResult'])->name('fakultas.linkedin-sync.result.show');
            Route::post('/fakultas/linkedin-sync/results/{id}/approve', [LinkedInSyncController::class, 'approveResult'])->name('fakultas.linkedin-sync.result.approve');
            Route::post('/fakultas/linkedin-sync/results/{id}/plot-again', [LinkedInSyncController::class, 'plotAgain'])->name('fakultas.linkedin-sync.result.plot-again');
            Route::post('/fakultas/linkedin-sync/results/{id}/reject', [LinkedInSyncController::class, 'rejectResult'])->name('fakultas.linkedin-sync.result.reject');
            Route::get('/fakultas/linkedin-sync/alumni/{id}/history', [LinkedInSyncController::class, 'history'])->name('fakultas.linkedin-sync.alumni.history');
        });

        // -----------------------------------------------------------------
        // Rute Superadmin (Otoritas Tertinggi & Pengaturan Instrumen Kuesioner)
        // -----------------------------------------------------------------
        Route::middleware('role:superadmin')->group(function () {
            // Redirect /superadmin langsung ke dashboard
            Route::redirect('/superadmin', '/superadmin/dashboard');

            // Direktori Mahasiswa/Alumni & Audit Kuesioner Tracer
            Route::get('/superadmin/alumni', [DaftarAlumniSuperAdminController::class, 'index'])->name('superadmin.alumni.index');
            Route::get('/superadmin/alumni/export-zip', [DaftarAlumniSuperAdminController::class, 'exportZip'])->name('superadmin.alumni.export-zip');
            Route::post('/superadmin/alumni/{id}/send-email', [DaftarAlumniSuperAdminController::class, 'sendReminderEmail'])->name('superadmin.alumni.send-email');
            Route::get('/superadmin/alumni/{id}', [DetailAlumniSuperAdminController::class, 'show'])->name('superadmin.alumni.show');
            Route::get('/superadmin/alumni/{id}/export-excel', [DetailAlumniSuperAdminController::class, 'exportExcel'])->name('superadmin.alumni.export-excel');
            Route::post('/superadmin/alumni/{id}/profile', [DetailAlumniSuperAdminController::class, 'updateProfile'])->name('superadmin.alumni.profile.update');

            // Dashboard Utama Superadmin
            Route::get('/superadmin/dashboard', [App\Http\Controllers\SuperAdmin\Dashboard\DashboardController::class, 'tampilkanDashboard'])->name('superadmin.dashboard');
            Route::get('/superadmin/analitik', [TracerAnalyticsController::class, 'index'])->name('superadmin.analytics.index');
            Route::get('/superadmin/analitik/data', [TracerAnalyticsController::class, 'data'])->name('superadmin.analytics.data');
            Route::get('/superadmin/analitik/export-response-rate', [TracerAnalyticsController::class, 'exportResponseRate'])->name('superadmin.analytics.export_response_rate');

            // Kelola Butir Pertanyaan, Opsi Jawaban, & Alur Branching Kuesioner (Modular Controllers)
            Route::get('/superadmin/pertanyaan', [DaftarPertanyaanController::class, 'index'])->name('superadmin.pertanyaan.index');
            Route::post('/superadmin/pertanyaan', [SimpanPertanyaanController::class, 'store'])->name('superadmin.pertanyaan.store');
            Route::post('/superadmin/pertanyaan/reorder', [SimpanPertanyaanController::class, 'reorder'])->name('superadmin.pertanyaan.reorder');
            Route::put('/superadmin/pertanyaan/{id}', [SimpanPertanyaanController::class, 'update'])->name('superadmin.pertanyaan.update');
            Route::delete('/superadmin/pertanyaan/{id}', [SimpanPertanyaanController::class, 'destroy'])->name('superadmin.pertanyaan.destroy');
            Route::post('/superadmin/pertanyaan/{questionId}/options', [KelolaOpsiController::class, 'storeOption'])->name('superadmin.pertanyaan.options.store');
            Route::put('/superadmin/pertanyaan/options/{optionId}', [KelolaOpsiController::class, 'updateOption'])->name('superadmin.pertanyaan.options.update');
            Route::delete('/superadmin/pertanyaan/options/{optionId}', [KelolaOpsiController::class, 'destroyOption'])->name('superadmin.pertanyaan.options.destroy');

            // Kelola Bagian Kuesioner / Section (CRUD & Reorder)
            Route::get('/superadmin/sections', [KelolaSectionController::class, 'index'])->name('superadmin.sections.index');
            Route::post('/superadmin/sections', [KelolaSectionController::class, 'store'])->name('superadmin.sections.store');
            Route::post('/superadmin/sections/reorder', [KelolaSectionController::class, 'reorder'])->name('superadmin.sections.reorder');
            Route::put('/superadmin/sections/{id}', [KelolaSectionController::class, 'update'])->name('superadmin.sections.update');
            Route::delete('/superadmin/sections/{id}', [KelolaSectionController::class, 'destroy'])->name('superadmin.sections.destroy');

            // Kelola Kuesioner Induk (CRUD & Toggle Active)
            Route::post('/superadmin/kuesioner', [KelolaKuesionerController::class, 'store'])->name('superadmin.kuesioner.store');
            Route::put('/superadmin/kuesioner/{id}', [KelolaKuesionerController::class, 'update'])->name('superadmin.kuesioner.update');
            Route::patch('/superadmin/kuesioner/{id}/toggle-active', [KelolaKuesionerController::class, 'toggleActive'])->name('superadmin.kuesioner.toggle-active');
            Route::delete('/superadmin/kuesioner/{id}', [KelolaKuesionerController::class, 'destroy'])->name('superadmin.kuesioner.destroy');

            // Verifikasi & Approval Perusahaan Pengajuan Alumni (Superadmin Scope: All / Per Prodi)
            Route::get('/superadmin/perusahaan', [VerifikasiPerusahaanSuperAdminController::class, 'index'])->name('superadmin.perusahaan.index');
            Route::post('/superadmin/perusahaan', [VerifikasiPerusahaanSuperAdminController::class, 'store'])->name('superadmin.perusahaan.store');
            Route::post('/superadmin/perusahaan/{id}/verify', [VerifikasiPerusahaanSuperAdminController::class, 'verify'])->name('superadmin.perusahaan.verify');
            Route::post('/superadmin/perusahaan/{id}/replace', [VerifikasiPerusahaanSuperAdminController::class, 'replace'])->name('superadmin.perusahaan.replace');
            Route::put('/superadmin/perusahaan/{id}', [VerifikasiPerusahaanSuperAdminController::class, 'updateAndVerify'])->name('superadmin.perusahaan.update');
            Route::post('/superadmin/perusahaan/{id}/reject', [VerifikasiPerusahaanSuperAdminController::class, 'reject'])->name('superadmin.perusahaan.reject');
            Route::delete('/superadmin/perusahaan/{id}', [VerifikasiPerusahaanSuperAdminController::class, 'destroy'])->name('superadmin.perusahaan.destroy');
            Route::get('/superadmin/perusahaan/search-verified', [VerifikasiPerusahaanSuperAdminController::class, 'searchVerified'])->name('superadmin.perusahaan.search-verified');
            Route::get('/superadmin/perusahaan/{id}/recommendations', [VerifikasiPerusahaanSuperAdminController::class, 'recommendations'])->name('superadmin.perusahaan.recommendations');

            // Master Data Wilayah Administrasi & Regulasi Standar UMP (Provinsi, Kabupaten/Kota, UMP Daerah)
            Route::get('/superadmin/master-wilayah', [MasterWilayahController::class, 'index'])->name('superadmin.master-wilayah.index');
            Route::post('/superadmin/master-wilayah/provinsi', [MasterWilayahController::class, 'storeProvinsi'])->name('superadmin.master-wilayah.provinsi.store');
            Route::put('/superadmin/master-wilayah/provinsi/{id}', [MasterWilayahController::class, 'updateProvinsi'])->name('superadmin.master-wilayah.provinsi.update');
            Route::delete('/superadmin/master-wilayah/provinsi/{id}', [MasterWilayahController::class, 'destroyProvinsi'])->name('superadmin.master-wilayah.provinsi.destroy');

            Route::post('/superadmin/master-wilayah/kabupaten', [MasterWilayahController::class, 'storeKabupaten'])->name('superadmin.master-wilayah.kabupaten.store');
            Route::put('/superadmin/master-wilayah/kabupaten/{id}', [MasterWilayahController::class, 'updateKabupaten'])->name('superadmin.master-wilayah.kabupaten.update');
            Route::delete('/superadmin/master-wilayah/kabupaten/{id}', [MasterWilayahController::class, 'destroyKabupaten'])->name('superadmin.master-wilayah.kabupaten.destroy');

            Route::post('/superadmin/master-wilayah/ump', [MasterWilayahController::class, 'storeOrUpdateUmp'])->name('superadmin.master-wilayah.ump.store');
            Route::delete('/superadmin/master-wilayah/ump/{id}', [MasterWilayahController::class, 'destroyUmp'])->name('superadmin.master-wilayah.ump.destroy');
            Route::post('/superadmin/master-wilayah/ump/copy-year', [MasterWilayahController::class, 'copyYearUmp'])->name('superadmin.master-wilayah.ump.copy-year');

            Route::post('/superadmin/master-wilayah/negara', [MasterWilayahController::class, 'storeNegara'])->name('superadmin.master-wilayah.negara.store');
            Route::put('/superadmin/master-wilayah/negara/{id}', [MasterWilayahController::class, 'updateNegara'])->name('superadmin.master-wilayah.negara.update');
            Route::delete('/superadmin/master-wilayah/negara/{id}', [MasterWilayahController::class, 'destroyNegara'])->name('superadmin.master-wilayah.negara.destroy');

            // Manajemen Akun Terpadu & Pemulihan Kata Sandi (Admin Fakultas, Admin Prodi, Mahasiswa per Prodi)
            Route::get('/superadmin/manajemen-akun', [ManajemenAkunController::class, 'index'])->name('superadmin.manajemen-akun.index');
            Route::put('/superadmin/manajemen-akun/{id}', [ManajemenAkunController::class, 'update'])->name('superadmin.manajemen-akun.update');
            Route::post('/superadmin/manajemen-akun/{id}/reset-default-notify', [ManajemenAkunController::class, 'resetToDefaultAndNotify'])->name('superadmin.manajemen-akun.reset-default-notify');

            // Pengaturan Kuesioner Prodi (Superadmin Scope: Pilih Prodi lalu Kelola Section & Pertanyaan)
            Route::get('/superadmin/prodi-kuesioner', [KelolaKuesionerProdiSuperAdminController::class, 'index'])->name('superadmin.prodi-kuesioner.index');
            Route::post('/superadmin/prodi-kuesioner/sections', [KelolaKuesionerProdiSuperAdminController::class, 'storeSection'])->name('superadmin.prodi-kuesioner.sections.store');
            Route::put('/superadmin/prodi-kuesioner/sections/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'updateSection'])->name('superadmin.prodi-kuesioner.sections.update');
            Route::delete('/superadmin/prodi-kuesioner/sections/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'destroySection'])->name('superadmin.prodi-kuesioner.sections.destroy');
            Route::post('/superadmin/prodi-kuesioner/sections/reorder', [KelolaKuesionerProdiSuperAdminController::class, 'reorderSection'])->name('superadmin.prodi-kuesioner.sections.reorder');

            Route::post('/superadmin/prodi-kuesioner/pertanyaan', [KelolaKuesionerProdiSuperAdminController::class, 'storeQuestion'])->name('superadmin.prodi-kuesioner.pertanyaan.store');
            Route::put('/superadmin/prodi-kuesioner/pertanyaan/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'updateQuestion'])->name('superadmin.prodi-kuesioner.pertanyaan.update');
            Route::delete('/superadmin/prodi-kuesioner/pertanyaan/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'destroyQuestion'])->name('superadmin.prodi-kuesioner.pertanyaan.destroy');
            Route::post('/superadmin/prodi-kuesioner/pertanyaan/reorder', [KelolaKuesionerProdiSuperAdminController::class, 'reorderQuestion'])->name('superadmin.prodi-kuesioner.pertanyaan.reorder');

            Route::post('/superadmin/prodi-kuesioner/opsi', [KelolaKuesionerProdiSuperAdminController::class, 'storeOption'])->name('superadmin.prodi-kuesioner.opsi.store');
            Route::put('/superadmin/prodi-kuesioner/opsi/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'updateOption'])->name('superadmin.prodi-kuesioner.opsi.update');
            Route::delete('/superadmin/prodi-kuesioner/opsi/{id}', [KelolaKuesionerProdiSuperAdminController::class, 'destroyOption'])->name('superadmin.prodi-kuesioner.opsi.destroy');

            // Pengaturan Kuesioner Evaluasi Atasan (SuperAdmin)
            Route::get('/superadmin/evaluasi-atasan', [KelolaEvaluasiAtasanController::class, 'index'])->name('superadmin.evaluasi-atasan.index');
            Route::post('/superadmin/evaluasi-atasan/pertanyaan', [KelolaEvaluasiAtasanController::class, 'storeQuestion'])->name('superadmin.evaluasi-atasan.pertanyaan.store');
            Route::put('/superadmin/evaluasi-atasan/pertanyaan/{id}', [KelolaEvaluasiAtasanController::class, 'updateQuestion'])->name('superadmin.evaluasi-atasan.pertanyaan.update');
            Route::patch('/superadmin/evaluasi-atasan/pertanyaan/{id}/toggle', [KelolaEvaluasiAtasanController::class, 'toggleQuestion'])->name('superadmin.evaluasi-atasan.pertanyaan.toggle');
            Route::delete('/superadmin/evaluasi-atasan/pertanyaan/{id}', [KelolaEvaluasiAtasanController::class, 'destroyQuestion'])->name('superadmin.evaluasi-atasan.pertanyaan.destroy');
            Route::post('/superadmin/evaluasi-atasan/pertanyaan/reorder', [KelolaEvaluasiAtasanController::class, 'reorderQuestions'])->name('superadmin.evaluasi-atasan.pertanyaan.reorder');

            // Log Aktivitas / Audit Trail Sistem
            Route::get('/superadmin/logs', [LogAktivitasController::class, 'index'])->name('superadmin.logs.index');

            // Sinkronisasi LinkedIn Super Admin
            Route::get('/superadmin/linkedin-sync', [LinkedInSyncController::class, 'index'])->name('superadmin.linkedin-sync.index');
            Route::post('/superadmin/linkedin-sync/batch', [LinkedInSyncController::class, 'syncBatch'])->name('superadmin.linkedin-sync.batch');
            Route::post('/superadmin/linkedin-sync/{id}', [LinkedInSyncController::class, 'syncSingle'])->name('superadmin.linkedin-sync.single');
            Route::get('/superadmin/linkedin-sync/results/{id}', [LinkedInSyncController::class, 'showResult'])->name('superadmin.linkedin-sync.result.show');
            Route::post('/superadmin/linkedin-sync/results/{id}/approve', [LinkedInSyncController::class, 'approveResult'])->name('superadmin.linkedin-sync.result.approve');
            Route::post('/superadmin/linkedin-sync/results/{id}/plot-again', [LinkedInSyncController::class, 'plotAgain'])->name('superadmin.linkedin-sync.result.plot-again');
            Route::post('/superadmin/linkedin-sync/results/{id}/reject', [LinkedInSyncController::class, 'rejectResult'])->name('superadmin.linkedin-sync.result.reject');
            Route::get('/superadmin/linkedin-sync/alumni/{id}/history', [LinkedInSyncController::class, 'history'])->name('superadmin.linkedin-sync.alumni.history');
        });

    });
});
