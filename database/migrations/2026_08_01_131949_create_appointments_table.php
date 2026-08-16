<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dentist_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->text('reason')->nullable();
            $table->date('date');
            $table->string('time'); // e.g. "11:30 AM"
            $table->string('status')->default('pending'); // pending | confirmed | cancelled
            $table->timestamps();

            $table->unique(['dentist_id', 'date', 'time']); // prevents double-booking
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
