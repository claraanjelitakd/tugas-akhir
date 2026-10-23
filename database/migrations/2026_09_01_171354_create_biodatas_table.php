<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel Biodata:
     * Menyimpan profil aktif, kontak pribadi, jejak karier, dan data tracer alumni.
     *
     * Arsitektur Data Bebas Duplikasi:
     * Seluruh data identitas akademik master (nama, tempat/tanggal lahir, jenis kelamin,
     * agama, golongan darah, kewarganegaraan, dokumen sekolah/kependudukan lama, tahun kelulusan,
     * dan email_students) disimpan secara tunggal di tabel `data_akademik` melalui referensi kunci `nim`.
     * Tabel `biodata` hanya menggunakan `email_pribadi` untuk korespondensi aktif alumni.
     */
    public function up(): void
    {
        if (! Schema::hasTable('biodata')) {
            Schema::create('biodata', function (Blueprint $table) {
                $table->id()->comment('ID unik profil biodata alumni');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('Relasi ke akun login pengguna');

                // Relasi tunggal ke data_akademik melalui NIM (CHAR 8)
                $table->char('nim', 8)->unique()->comment('NIM mahasiswa/alumni (8 karakter, FK ke data_akademik)');
                $table->foreign('nim')->references('nim')->on('data_akademik')->cascadeOnDelete();

                // Relasi ke entitas pendukung akademik dan tracer.
                $table->foreignId('yudisium_id')->nullable()->constrained('yudisium')->nullOnDelete()->comment('Relasi ke rekam kelulusan yudisium');
                $table->foreignId('prodi_id')->nullable()->constrained('prodi')->nullOnDelete()->comment('Relasi ke program studi alumni');

                // Foto Profil Alumni
                $table->string('foto', 200)->nullable()->comment('Path berkas foto profil alumni');

                // Kontak Aktif Alumni
                $table->string('nomor_telepon', 20)->nullable()->comment('Nomor telepon/WhatsApp aktif alumni saat ini');
                $table->string('email_pribadi', 100)->nullable()->comment('Alamat email aktif korespondensi alumni');

                // Domisili Tempat Tinggal Saat Ini
                $table->text('alamat_saat_ini')->nullable()->comment('Alamat domisili tempat tinggal alumni saat ini');
                $table->string('kelurahan', 50)->nullable()->comment('Kelurahan/desa domisili alumni saat ini');
                $table->string('kecamatan', 50)->nullable()->comment('Kecamatan domisili alumni saat ini');
                $table->foreignId('kabupaten_id')->nullable()->constrained('kabupaten')->nullOnDelete()->comment('Kabupaten/kota domisili tempat tinggal saat ini');
                $table->foreignId('propinsi_id')->nullable()->constrained('propinsi')->nullOnDelete()->comment('Provinsi domisili tempat tinggal saat ini');
                $table->char('kode_pos', 5)->nullable()->comment('Kode pos tempat tinggal alumni saat ini (5 digit)');

                // Media Sosial & Portofolio Profesional
                $table->string('instagram_url', 150)->nullable()->comment('Tautan profil Instagram');
                $table->string('facebook_url', 150)->nullable()->comment('Tautan profil Facebook');
                $table->string('linkedin_url', 150)->nullable()->comment('Tautan profil LinkedIn');
                $table->string('linkedin_username', 100)->nullable()->comment('Username akun LinkedIn alumni');

                // Keahlian & Pengalaman Kerja (LinkedIn Compatible)
                $table->text('skills')->nullable()->comment('Daftar keahlian / skills spesifik alumni');
                $table->text('experience')->nullable()->comment('Pengalaman kerja / riwayat karier alumni');

                // Data Karier & Tracer Pekerjaan
                $table->foreignId('perusahaan_id')->nullable()->constrained('perusahaan')->nullOnDelete()->comment('Relasi ke master perusahaan tempat alumni bekerja');
                $table->foreignId('atasan_id')->nullable()->constrained('atasan')->nullOnDelete()->comment('Relasi ke data atasan langsung penilai');
                $table->string('kategori_pekerjaan', 50)->nullable()->comment('Kategori aktivitas karier: Bekerja, Wiraswasta, Melanjutkan Pendidikan, dll');
                $table->string('posisi_jabatan', 100)->nullable()->comment('Jabatan atau posisi pekerjaan alumni');
                $table->string('posisi_wiraswasta', 100)->nullable()->comment('Jenis usaha atau posisi wirausaha alumni');
                $table->string('pendidikan_tingkat', 30)->nullable()->comment('Jenjang studi lanjut: S2, S3, Profesi, Spesialis');
                $table->string('perguruan_tinggi', 100)->nullable()->comment('Nama perguruan tinggi studi lanjut');
                $table->string('pendidikan_prodi', 100)->nullable()->comment('Program studi pada jenjang studi lanjut');
                $table->decimal('gaji', 15, 2)->nullable()->comment('Nominal gaji / penghasilan pertama per bulan');
                $table->string('jenis_pekerjaan', 100)->nullable()->comment('Jenis atau klasifikasi bidang pekerjaan');

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biodata');
    }
};
