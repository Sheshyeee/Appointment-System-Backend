<?php

namespace App\Http\Controllers;

use App\Mail\AppointmentCreated;
use App\Mail\AppointmentStatusUpdated;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\ClinicSetting;

use App\Models\Notification;

use App\Models\User;
use App\Services\SmsService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Mail;

class AppointmentController extends Controller
{
    /**
     * How far apart generated slots are, in minutes.
     */
    private const SLOT_INTERVAL_MINUTES = 30;

    public function __construct(private SmsService $smsService) {}

    /**
     * Resolve the current user via the sanctum guard explicitly.
     *
     * Several routes on this controller (store, availableSlots,
     * bookedSummary) are intentionally public so guests can book without an
     * account, which means they sit outside any `auth:sanctum` middleware
     * group. That middleware is normally what tells Laravel to check the
     * sanctum guard for the request's Bearer token — without it, the
     * default guard (web) is used instead, so Auth::user()/Auth::id()
     * silently return null for a logged-in user too.
     *
     * Calling the sanctum guard directly sidesteps that: it still resolves
     * a valid token if one is sent, but never requires one, giving true
     * "optional auth" behavior on public routes.
     */
    private function currentUser(): ?User
    {
        return Auth::guard('sanctum')->user();
    }

    // GET /appointments
    public function index(Request $request)
    {
        $user = $this->currentUser();

        $query = Appointment::with(['service', 'dentist'])
            ->orderBy('date')
            ->orderBy('time');

        // Staff/admins can see everything (and optionally filter by dentist);
        // regular patients only ever see their own appointments.
        if (!$user || $user->role === 'patient') {
            $query->where('user_id', $user?->id);
        }

        if ($request->has('dentist_id')) {
            $query->where('dentist_id', $request->query('dentist_id'));
        }

        return response()->json([
            'appointments' => $query->get(),
        ]);
    }

    public function bookedSummary(Request $request)
    {
        $validated = $request->validate([
            'dentist_id' => 'required|exists:dentists,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $counts = Appointment::where('dentist_id', $validated['dentist_id'])
            ->whereBetween('date', [$validated['start_date'], $validated['end_date']])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('date, count(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $closed = [];
        $full = [];

        $period = CarbonPeriod::create($validated['start_date'], $validated['end_date']);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayKey = strtolower($date->format('l')); // e.g. "monday"
            $slots = $this->slotsForDay($dayKey);

            if (empty($slots)) {
                // Admin has this day disabled (or no open/close set) — closed, not "booked out".
                $closed[] = $dateStr;
                continue;
            }

            if (($counts[$dateStr] ?? 0) >= count($slots)) {
                $full[] = $dateStr;
            }
        }

        return response()->json([
            'booked' => $counts,
            'closed' => $closed,
            'full'   => $full,
        ]);
    }

    public function availableSlots(Request $request)
    {
        $validated = $request->validate([
            'dentist_id' => 'required|exists:dentists,id',
            'date'       => 'required|date',
        ]);

        $dayKey = strtolower(Carbon::parse($validated['date'])->format('l'));
        $slots = $this->slotsForDay($dayKey);

        $booked = Appointment::where('dentist_id', $validated['dentist_id'])
            ->where('date', $validated['date'])
            ->where('status', '!=', 'cancelled')
            ->pluck('time');

        return response()->json([
            'slots'  => $slots,
            'booked' => $booked,
            'closed' => empty($slots),
        ]);
    }

    // POST /appointments
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'dentist_id' => 'required|exists:dentists,id',
            'date'       => 'required|date|after_or_equal:today',
            'time'       => 'required|string',
            'full_name'  => 'required|string|max:255',
            'email'      => 'required|email',
            'phone'      => 'required|string|max:30',
            'reason'     => 'nullable|string',
        ]);

        // Make sure the requested slot actually falls within the admin's
        // configured working hours for that day of the week.
        $dayKey = strtolower(Carbon::parse($validated['date'])->format('l'));
        $validSlots = $this->slotsForDay($dayKey);

        if (empty($validSlots) || !in_array($validated['time'], $validSlots, true)) {
            throw ValidationException::withMessages([
                'time' => 'The clinic is not open at that time. Please choose a different slot.',
            ]);
        }

        $alreadyBooked = Appointment::where('dentist_id', $validated['dentist_id'])
            ->where('date', $validated['date'])
            ->where('time', $validated['time'])
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($alreadyBooked) {
            throw ValidationException::withMessages([
                'time' => 'This slot was just booked. Please choose another time.',
            ]);
        }

        $appointment = Appointment::create([
            ...$validated,
            'user_id' => $this->currentUser()?->id,
            'status'  => 'pending',
            'reminder_at' => $this->computeReminderAt($validated['date'], $validated['time']),
        ]);

        $appointment->load(['service', 'dentist']);

        Mail::to($appointment->email)->queue(new AppointmentCreated($appointment));

        return response()->json([
            'message' => 'Appointment booked successfully.',
            'appointment' => [
                'id'            => $appointment->id,
                'patient_name'  => $appointment->full_name,
                'service_name'  => $appointment->service->name,
                'dentist_name'  => $appointment->dentist->full_name,
                'date'          => $appointment->date->format('D, M j, Y'),
                'time'          => $appointment->time,
                'duration'      => $appointment->service->duration,
                'price'         => $appointment->service->price,
            ],
        ], 201);
    }

    // GET /appointments/{appointment}
    // Type-hinted so Laravel's implicit route-model binding resolves this
    // to a real Appointment instance BEFORE the `can:view,appointment`
    // middleware runs. Without this, the middleware receives the raw id
    // string and authorization always fails, regardless of role.
    public function show(Appointment $appointment)
    {
        $appointment->load(['service', 'dentist']);

        return response()->json(['appointment' => $appointment]);
    }

    // PUT /appointments/{appointment}/update
    public function update(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'status'     => 'sometimes|required|in:pending,confirmed,completed,cancelled',
            'date'       => 'sometimes|required|date',
            'time'       => 'sometimes|required|string',
            'dentist_id' => 'sometimes|required|exists:dentists,id',
            'reason'     => 'nullable|string',
        ]);

        $previousStatus = $appointment->status;
        $previousDate = $appointment->date->format('Y-m-d');
        $previousTime = $appointment->time;

        // If the date, time, or dentist is changing, make sure the new slot
        // isn't already booked by someone else before saving.
        $dentistId = $validated['dentist_id'] ?? $appointment->dentist_id;
        $date      = $validated['date'] ?? $appointment->date->format('Y-m-d');
        $time      = $validated['time'] ?? $appointment->time;

        $slotChanged = $dentistId != $appointment->dentist_id
            || $date !== $appointment->date->format('Y-m-d')
            || $time !== $appointment->time;

        if ($slotChanged) {
            // New slot must fall within the admin's working hours for that day.
            $dayKey = strtolower(Carbon::parse($date)->format('l'));
            $validSlots = $this->slotsForDay($dayKey);

            if (empty($validSlots) || !in_array($time, $validSlots, true)) {
                throw ValidationException::withMessages([
                    'time' => 'The clinic is not open at that time. Please choose a different slot.',
                ]);
            }

            $conflict = Appointment::where('dentist_id', $dentistId)
                ->where('date', $date)
                ->where('time', $time)
                ->where('status', '!=', 'cancelled')
                ->where('id', '!=', $appointment->id)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'time' => 'This slot is already booked. Please choose another time.',
                ]);
            }

            // Slot moved — recompute when the reminder should fire, and
            // un-mark it as sent so the new time gets its own reminder.
            $validated['reminder_at'] = $this->computeReminderAt($date, $time);
            $validated['reminder_sent_at'] = null;
        }

        // Cancelling clears any pending reminder so the cron never sends
        // one for an appointment that no longer exists.
        if (($validated['status'] ?? null) === 'cancelled') {
            $validated['reminder_at'] = null;
        }

        $appointment->update($validated);
        $appointment->load(['service', 'dentist']);

        $this->notifyAppointmentChange($appointment, $previousStatus, $previousDate, $previousTime);
        $this->notifyAdminsOfStaffAction($appointment, $previousStatus, $previousDate, $previousTime);

        if ($appointment->date->format('Y-m-d') !== $previousDate || $appointment->time !== $previousTime) {
            $this->logActivity($appointment, 'rescheduled');
        }

        if ($appointment->status !== $previousStatus) {
            $this->logActivity($appointment, $appointment->status);
        }

        return response()->json([
            'message' => 'Appointment updated successfully.',
            'appointment' => $appointment,
        ]);
    }

    // DELETE /appointments/{appointment}
    public function destroy(Appointment $appointment)
    {
        $appointment->load(['service', 'dentist']);

        $actor = $this->currentUser();
        $isSelf = $actor && $appointment->user_id && $actor->id === $appointment->user_id;
        $serviceName = $appointment->service->name ?? 'appointment';
        $formattedDate = $appointment->date->format('D, M j, Y');

        $message = $isSelf
            ? "You cancelled your {$serviceName} appointment on {$formattedDate} at {$appointment->time}."
            : "Your {$serviceName} appointment on {$formattedDate} at {$appointment->time} has been cancelled.";

        if ($appointment->user_id) {
            Notification::create([
                'user_id' => $appointment->user_id,
                'appointment_id' => null,
                'title' => 'Appointment cancelled',
                'message' => $message,
            ]);
        }

        Mail::to($appointment->email)->queue(
            new AppointmentStatusUpdated($appointment, 'Your Appointment Has Been Cancelled', $message)
        );

        $this->smsService->send($appointment->phone, $message);

        $this->logActivity($appointment, 'cancelled');

        $appointment->delete();

        return response()->json(['message' => 'Appointment cancelled successfully.']);
    }

    private function logActivity(Appointment $appointment, string $action): void
    {
        $dentistName = $appointment->dentist->full_name ?? 'Dentist';
        $patientName = $appointment->full_name;
        $serviceName = $appointment->service->name ?? 'appointment';

        $verbs = [
            'booked'      => 'booked',
            'confirmed'   => 'confirmed',
            'completed'   => 'marked completed',
            'cancelled'   => 'cancelled',
            'pending'     => 'moved back to pending',
            'rescheduled' => 'rescheduled',
        ];
        $verb = $verbs[$action] ?? $action;

        ActivityLog::create([
            'appointment_id' => $appointment->id,
            'dentist_name'   => $dentistName,
            'patient_name'   => $patientName,
            'service_name'   => $serviceName,
            'action'         => $action,
            'status'         => $appointment->status,
            'message'        => "Dr. {$dentistName} {$verb} an appointment with {$patientName} — {$serviceName}",
            'occurred_at'    => now(),
        ]);
    }

    /**
     * Notify the patient whenever their appointment's status changes or it
     * gets rescheduled — regardless of whether the patient made the change
     * themselves or staff/admin did. The wording adapts ("You cancelled..."
     * vs "...has been cancelled") depending on who acted, so a patient's own
     * actions still show up as a confirmation in their notification feed.
     */
    private function notifyAppointmentChange(
        Appointment $appointment,
        string $previousStatus,
        string $previousDate,
        string $previousTime,
    ): void {
        $actor = $this->currentUser();
        $isSelf = $actor && $appointment->user_id && $actor->id === $appointment->user_id;

        $statusChanged = $appointment->status !== $previousStatus;
        $slotChanged = $appointment->date->format('Y-m-d') !== $previousDate
            || $appointment->time !== $previousTime;

        if (!$statusChanged && !$slotChanged) {
            return;
        }

        $serviceName = $appointment->service->name ?? 'appointment';
        $formattedDate = $appointment->date->format('D, M j, Y');

        if ($slotChanged) {
            $message = $isSelf
                ? "You rescheduled your {$serviceName} appointment to {$formattedDate} at {$appointment->time}."
                : "Your {$serviceName} appointment has been rescheduled to {$formattedDate} at {$appointment->time}.";

            if ($appointment->user_id) {
                Notification::create([
                    'user_id' => $appointment->user_id,
                    'appointment_id' => $appointment->id,
                    'title' => 'Appointment rescheduled',
                    'message' => $message,
                ]);
            }

            Mail::to($appointment->email)->queue(
                new AppointmentStatusUpdated($appointment, 'Your Appointment Has Been Rescheduled', $message)
            );

            $this->smsService->send($appointment->phone, $message);
        }

        if ($statusChanged) {
            $messages = $isSelf
                ? [
                    'confirmed' => "You confirmed your {$serviceName} appointment on {$formattedDate} at {$appointment->time}.",
                    'cancelled' => "You cancelled your {$serviceName} appointment on {$formattedDate} at {$appointment->time}.",
                    'completed' => "Your {$serviceName} appointment on {$formattedDate} has been marked as completed.",
                    'pending'   => "You moved your {$serviceName} appointment on {$formattedDate} at {$appointment->time} back to pending.",
                ]
                : [
                    'confirmed' => "Your {$serviceName} appointment on {$formattedDate} at {$appointment->time} has been confirmed.",
                    'cancelled' => "Your {$serviceName} appointment on {$formattedDate} at {$appointment->time} has been cancelled.",
                    'completed' => "Your {$serviceName} appointment on {$formattedDate} has been marked as completed.",
                    'pending'   => "Your {$serviceName} appointment on {$formattedDate} at {$appointment->time} is now pending confirmation.",
                ];

            $statusMessage = $messages[$appointment->status] ?? "Your appointment status changed to {$appointment->status}.";

            $this->smsService->send($appointment->phone, $statusMessage);

            if ($appointment->user_id) {
                Notification::create([
                    'user_id' => $appointment->user_id,
                    'appointment_id' => $appointment->id,
                    'title' => 'Appointment ' . $appointment->status,
                    'message' => $statusMessage,
                ]);
            }

            $subjects = [
                'confirmed' => 'Your Appointment is Confirmed',
                'cancelled' => 'Your Appointment Has Been Cancelled',
                'completed' => 'Your Appointment is Complete',
                'pending'   => 'Your Appointment is Pending Confirmation',
            ];

            Mail::to($appointment->email)->queue(
                new AppointmentStatusUpdated(
                    $appointment,
                    $subjects[$appointment->status] ?? 'Your Appointment Status Has Changed',
                    $statusMessage,
                )
            );
        }
    }

    /**
     * Let admins know when an appointment is rescheduled or its status
     * changes by someone other than an admin — either staff acting on a
     * dentist's behalf (dentists have no login of their own), or a patient
     * rescheduling their own appointment. Wording makes clear who acted.
     */
    private function notifyAdminsOfStaffAction(
        Appointment $appointment,
        string $previousStatus,
        string $previousDate,
        string $previousTime,
    ): void {
        $actor = $this->currentUser();

        if (!$actor) {
            return;
        }

        $isStaff = $actor->role === 'staff';
        $isSelfPatient = $actor->id === $appointment->user_id;

        // Admin's own actions don't need to notify admins, and this method
        // has nothing to say about actions taken by anyone else (e.g. a
        // different patient, which shouldn't be possible, but guard anyway).
        if (!$isStaff && !$isSelfPatient) {
            return;
        }

        $statusChanged = $appointment->status !== $previousStatus;
        $slotChanged = $appointment->date->format('Y-m-d') !== $previousDate
            || $appointment->time !== $previousTime;

        if (!$statusChanged && !$slotChanged) {
            return;
        }

        // A patient rescheduling their own appointment only triggers a
        // reschedule notice — status changes for patients (if you ever
        // allow that) aren't covered here, since staff/admin are the ones
        // expected to confirm/cancel/complete on the clinic side.
        if ($isSelfPatient && !$slotChanged) {
            return;
        }

        $dentistName = $appointment->dentist->full_name ?? 'a dentist';
        $patientName = $appointment->full_name;
        $serviceName = $appointment->service->name ?? 'appointment';
        $formattedDate = $appointment->date->format('D, M j, Y');

        $verbs = [
            'confirmed' => 'confirmed',
            'cancelled' => 'cancelled',
            'completed' => 'marked completed',
            'pending'   => 'moved back to pending',
        ];

        if ($isSelfPatient) {
            $title = 'Appointment rescheduled by patient';
            $message = "{$patientName} rescheduled their appointment with Dr. {$dentistName} — {$serviceName} to {$formattedDate} at {$appointment->time}.";
        } elseif ($statusChanged) {
            $verb = $verbs[$appointment->status] ?? $appointment->status;
            $title = 'Appointment ' . $appointment->status . ' by staff';
            $message = "Staff {$actor->name} {$verb} an appointment for Dr. {$dentistName} — {$patientName} ({$serviceName}) on {$formattedDate} at {$appointment->time}.";
        } else {
            $title = 'Appointment rescheduled by staff';
            $message = "Staff {$actor->name} rescheduled an appointment for Dr. {$dentistName} — {$patientName} ({$serviceName}) to {$formattedDate} at {$appointment->time}.";
        }

        $adminIds = User::where('role', 'admin')->pluck('id');

        foreach ($adminIds as $adminId) {
            Notification::create([
                'user_id' => $adminId,
                'appointment_id' => $appointment->id,
                'title' => $title,
                'message' => $message,
            ]);
        }
    }

    /**
     * Generate the list of bookable time slots (e.g. "9:00 AM") for a given
     * day of the week (e.g. "monday"), based on the clinic's configured
     * working_hours. Returns an empty array if the clinic is closed that day
     * or hours haven't been set.
     */
    private function slotsForDay(string $dayKey): array
    {
        $settings = ClinicSetting::first();
        $hours = $settings?->working_hours[$dayKey] ?? null;

        if (
            !$hours
            || !($hours['enabled'] ?? false)
            || empty($hours['open'])
            || empty($hours['close'])
        ) {
            return [];
        }

        $start = $this->timeToMinutes($hours['open']);
        $end = $this->timeToMinutes($hours['close']);

        if ($start === null || $end === null || $start >= $end) {
            return [];
        }

        $slots = [];
        for ($t = $start; $t <= $end; $t += self::SLOT_INTERVAL_MINUTES) {
            $slots[] = $this->minutesToTime($t);
        }

        return $slots;
    }

    /**
     * Parse a "8:00 AM" / "6:00 PM" style string into minutes since midnight.
     */
    private function timeToMinutes(string $time): ?int
    {
        $dt = \DateTime::createFromFormat('g:i A', trim($time));

        if (!$dt) {
            return null;
        }

        return ((int) $dt->format('H')) * 60 + (int) $dt->format('i');
    }

    /**
     * Format minutes since midnight back into "8:00 AM" style.
     */
    private function minutesToTime(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return Carbon::createFromTime($h, $m)->format('g:i A');
    }

    /**
     * Compute when the reminder email should go out for a given appointment
     * date/time. Reminders fire 24h before the slot; if that point has
     * already passed by the time of booking (or a reschedule), it falls
     * back to "send shortly after booking" instead of skipping entirely —
     * unless the slot itself is under ~2h away, in which case there's no
     * meaningful reminder to send.
     */
    private function computeReminderAt(string $date, string $time): ?Carbon
    {
        $slotDateTime = Carbon::parse("{$date} {$time}");
        $twentyFourHoursBefore = $slotDateTime->copy()->subHours(24);
        $now = now();

        if ($twentyFourHoursBefore->isFuture()) {
            return $twentyFourHoursBefore;
        }

        if ($now->diffInMinutes($slotDateTime, false) > 120) {
            // Less than 24h out already, but still more than 2h until the
            // slot — send the reminder right away instead of skipping it.
            return $now;
        }

        // Under ~2h until the appointment — too close for a reminder to help.
        return null;
    }
}
