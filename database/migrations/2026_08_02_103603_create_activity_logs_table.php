<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Nullable + no cascade: an appointment can be deleted later
            // (see AppointmentController::destroy) but the log entry should
            // still exist and read fine on its own.
            $table->unsignedBigInteger('appointment_id')->nullable();

            $table->string('dentist_name');
            $table->string('patient_name');
            $table->string('service_name');
            $table->enum('action', ['booked', 'confirmed', 'completed', 'cancelled', 'pending', 'rescheduled']);
            $table->string('status'); // resulting appointment status at the time of this event
            $table->text('message');  // pre-built display sentence, so the frontend never re-derives copy

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
