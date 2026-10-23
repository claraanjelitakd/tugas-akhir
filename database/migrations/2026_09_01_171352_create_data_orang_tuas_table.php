<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Historical placeholder for the removed parent-data table.
 *
 * The filename remains in the migration timeline so existing installations keep
 * their migration history. New installations must not create parent data.
 */
return new class extends Migration
{
    /**
     * Intentionally empty: parent data is not part of the application schema.
     */
    public function up(): void {}

    /**
     * Intentionally empty: there is no parent-data table to restore.
     */
    public function down(): void {}
};
