<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentStatusUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public string $emailSubject,
        public string $statusMessage,
    ) {}

    public function build()
    {
        return $this->subject($this->emailSubject)
            ->view('emails.appointment-status-updated');
    }
}
