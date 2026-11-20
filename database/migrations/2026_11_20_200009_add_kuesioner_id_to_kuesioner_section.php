<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan foreign key kuesioner_id pada tabel kuesioner_section
 * serta melakukan backfill data kuesioner_section dan kuesioner_jawaban
 * ke kuesioner induk aktif (id: 1, Tracer Study UKDW 2021).
 */
return new class extends Migration
{
    /**
     * Jalankan migrasi penambahan kolom kuesioner_id dan backfill data.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('kuesioner_section', 'kuesioner_id')) {
            Schema::table('kuesioner_section', function (Blueprint $table): void {
                $table->unsignedBigInteger('kuesioner_id')->nullable()->after('id')
                    ->comment('ID instrumen kuesioner induk periode tracer');
                $table->foreign('kuesioner_id', 'kuesioner_section_kuesioner_id_foreign')
                    ->references('id')
                    ->on('kuesioner')
                    ->nullOnDelete();
            });
        }

        // Backfill kuesioner_section yang belum terikat ke kuesioner induk default (id: 1)
        if (Schema::hasTable('kuesioner')) {
            $defaultKuesionerId = DB::table('kuesioner')->where('is_active', true)->value('id') ?? 1;

            if (Schema::hasColumn('kuesioner_section', 'kuesioner_id')) {
                DB::table('kuesioner_section')
                    ->whereNull('kuesioner_id')
                    ->update(['kuesioner_id' => $defaultKuesionerId]);
            }

            if (Schema::hasColumn('kuesioner_jawaban', 'kuesioner_id')) {
                DB::table('kuesioner_jawaban')
                    ->whereNull('kuesioner_id')
                    ->update(['kuesioner_id' => $defaultKuesionerId]);
            }
        }
    }

    /**
     * Batalkan migrasi penambahan kolom kuesioner_id pada kuesioner_section.
     */
    public function down(): void
    {
        if (Schema::hasColumn('kuesioner_section', 'kuesioner_id')) {
            Schema::table('kuesioner_section', function (Blueprint $table): void {
                $table->dropForeign('kuesioner_section_kuesioner_id_foreign');
                $table->dropColumn('kuesioner_id');
            });
        }
    }
};
