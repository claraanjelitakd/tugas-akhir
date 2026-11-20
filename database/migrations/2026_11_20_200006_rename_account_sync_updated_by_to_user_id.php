<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mengganti nama kolom account_sync_settings.updated_by -> user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('account_sync_settings', 'updated_by')) {
            return;
        }

        Schema::table('account_sync_settings', function (Blueprint $table): void {
            $table->renameColumn('updated_by', 'user_id');
        });
    }

    public function down(): void {}
};
