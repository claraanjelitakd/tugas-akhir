<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom kuesioner_id sebagai FK ke tabel kuesioner di kuesioner_jawaban.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kuesioner_jawaban', 'kuesioner_id')) {
            return;
        }

        Schema::table('kuesioner_jawaban', function (Blueprint $table): void {
            $table->unsignedBigInteger('kuesioner_id')->nullable()->after('id')
                ->comment('ID kuesioner induk tempat jawaban ini dicatat');
            $table->foreign('kuesioner_id', 'kuesioner_jawaban_kuesioner_id_foreign')
                ->references('id')
                ->on('kuesioner')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kuesioner_jawaban', function (Blueprint $table): void {
            $table->dropForeign('kuesioner_jawaban_kuesioner_id_foreign');
            $table->dropColumn('kuesioner_id');
        });
    }
};
