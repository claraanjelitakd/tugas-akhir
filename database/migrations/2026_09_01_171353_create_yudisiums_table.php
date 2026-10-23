<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel Yudisium:
     * Menyimpan data penyelesaian tugas akhir, publikasi karya ilmiah,
     * dan status kelulusan yudisium.
     * Catatan arsitektur: Periode kelulusan (tahun_lulus, tahun_akademik_lulus, tanggal_lulus)
     * disimpan terpusat di tabel `data_akademik` agar tidak terjadi duplikasi data.
     */
    public function up(): void
    {
        if (! Schema::hasTable('yudisium')) {
            Schema::create('yudisium', function (Blueprint $table) {
                $table->id()->comment('ID unik rekam kelulusan yudisium');

                // Relasi ke tabel data_akademik (NIM CHAR 8)
                $table->char('nim', 8)->unique()->comment('NIM mahasiswa (8 karakter, FK ke data_akademik)');
                $table->foreign('nim')->references('nim')->on('data_akademik')->cascadeOnDelete();

                // Tugas Akhir / Skripsi / Tesis
                $table->text('judul_ta')->nullable()->comment('Judul Skripsi/Thesis/Disertasi/Perancangan');
                $table->text('judul_ta_inggris')->nullable()->comment('Judul Skripsi/Thesis dalam Bahasa Inggris');

                // Publikasi Ilmiah
                $table->string('url_publikasi', 200)->nullable()->comment('Tautan repositori / jurnal publikasi karya ilmiah');
                $table->string('jenis_publikasi', 100)->nullable()->comment('Kategori media publikasi ilmiah');
                $table->string('status_publikasi', 50)->nullable()->comment('Status publikasi (Published, Submitted, In Review)');

                // Status Hasil Yudisium (Tanpa duplikasi tahun lulus / tahun akademik)
                $table->string('keterangan_hasil_yudisium', 60)->nullable()->comment('Predikat yudisium: Lulus dengan Pujian, Sangat Memuaskan');
                $table->enum('status_lulus', ['Belum', 'Proses', 'Lulus', 'Tidak Lulus'])->default('Belum')->comment('Status kelulusan yudisium mahasiswa');

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yudisium');
    }
};
