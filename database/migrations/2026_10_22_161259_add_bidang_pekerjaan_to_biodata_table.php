<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('biodata', function (Blueprint $table): void {
            $table->string('bidang_pekerjaan', 150)
                ->nullable()
                ->after('posisi_jabatan')
                ->comment('Bidang pekerjaan atau divisi alumni');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biodata', function (Blueprint $table): void {
            $table->dropColumn('bidang_pekerjaan');
        });
    }
};
