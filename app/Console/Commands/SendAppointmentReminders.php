<?php

namespace App\Console\Commands;

use App\Mail\AppointmentReminder;
use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Send reminder emails for appointments whose reminder_at time has passed';

    public function handle(): void
    {
        $appointments = Appointment::with(['service', 'dentist'])
            ->whereNotNull('reminder_at')
            ->whereNull('reminder_sent_at')
            ->where('reminder_at', '<=', now())
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($appointments as $appointment) {
            Mail::to($appointment->email)->queue(new AppointmentReminder($appointment));
            $appointment->update(['reminder_sent_at' => now()]);

            $this->info("Reminder sent for appointment #{$appointment->id}");
        }

        $this->info("Processed {$appointments->count()} reminder(s).");
    }
}
