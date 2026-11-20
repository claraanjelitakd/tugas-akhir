<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kuesioner_section_pertanyaan', function (Blueprint $table) {
            $table->tinyInteger('profil_step')->nullable()->default(null)->after('tampil_di')
                ->comment('Step stepper profil alumni: 1=Identitas, 2=Karier, 3=Akademik. Hanya berlaku jika tampil_di=profil/both.');
        });
    }

    public function down(): void
    {
        Schema::table('kuesioner_section_pertanyaan', function (Blueprint $table) {
            $table->dropColumn('profil_step');
        });
    }
};
