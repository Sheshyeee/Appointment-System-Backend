<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the generated column first — NULL whenever the appointment
        // is cancelled, a fixed marker otherwise. MySQL/MariaDB unique
        // indexes never consider two NULLs a duplicate, so any number of
        // cancelled rows can share a slot; only one *active* row per
        // dentist/date/time is still enforced.
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('active_slot', 3)
                ->storedAs("IF(status = 'cancelled', NULL, 'yes')")
                ->nullable()
                ->after('status');
        });

        // 2. Add the new unique index. It also starts with dentist_id, so
        // MySQL can use it to satisfy the dentist_id foreign key once the
        // old index is dropped — this must exist BEFORE step 3, or MySQL
        // refuses to drop the old one (error 1553).
        Schema::table('appointments', function (Blueprint $table) {
            $table->unique(
                ['dentist_id', 'date', 'time', 'active_slot'],
                'appointments_active_slot_unique'
            );
        });

        // 3. Now safe to drop the old constraint — the new index above
        // covers the foreign key requirement.
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_dentist_id_date_time_unique');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unique(['dentist_id', 'date', 'time'], 'appointments_dentist_id_date_time_unique');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_active_slot_unique');
            $table->dropColumn('active_slot');
        });
    }
};
