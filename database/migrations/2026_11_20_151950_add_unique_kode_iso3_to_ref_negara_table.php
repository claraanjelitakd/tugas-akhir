<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ref_negara', function (Blueprint $table) {
            $table->unique('kode_iso3');
        });
    }

    public function down(): void
    {
        Schema::table('ref_negara', function (Blueprint $table) {
            $table->dropUnique(['kode_iso3']);
        });
    }
};
