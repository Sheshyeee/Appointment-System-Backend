<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\User;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    // GET /dashboard/admin
    public function overview()
    {
        $weekStart = Carbon::now()->startOfWeek(Carbon::SUNDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $apptsThisWeek = Appointment::whereBetween('date', [
            $weekStart->format('Y-m-d'),
            $weekEnd->format('Y-m-d'),
        ])->count();

        $totalPatients = User::where('role', 'patient')->count();
        $totalDentists = Dentist::count();

        $revenueCompleted = Appointment::where('status', 'completed')
            ->join('services', 'appointments.service_id', '=', 'services.id')
            ->sum('services.price');

        $totalAppointments = Appointment::count();
        $cancelledCount = Appointment::where('status', 'cancelled')->count();
        $cancellationRate = $totalAppointments > 0
            ? round(($cancelledCount / $totalAppointments) * 100, 1)
            : 0.0;

        return response()->json([
            'stats' => [
                'appts_this_week'   => $apptsThisWeek,
                'total_patients'    => $totalPatients,
                'total_dentists'    => $totalDentists,
                'revenue_completed' => (float) $revenueCompleted,
                'cancellation_rate' => $cancellationRate,
            ],
            'weekly_chart'     => $this->weeklyChart($weekStart),
            'status_breakdown' => $this->statusBreakdown(),
            'recent_activity'  => $this->recentActivity(),
        ]);
    }

    /**
     * Appointment counts per day (Sun–Sat) for the current week, in a
     * fixed day order so the frontend never has to sort/pad the array.
     */
    private function weeklyChart(Carbon $weekStart): array
    {
        $counts = Appointment::whereBetween('date', [
            $weekStart->format('Y-m-d'),
            $weekStart->copy()->addDays(6)->format('Y-m-d'),
        ])
            ->selectRaw('date, count(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $days[] = [
                'day'   => $date->format('D'), // "Sun", "Mon", ...
                'date'  => $date->format('Y-m-d'),
                'count' => (int) ($counts[$date->format('Y-m-d')] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * All-time counts by status, used for the donut chart.
     */
    private function statusBreakdown(): array
    {
        $counts = Appointment::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending'   => (int) ($counts['pending'] ?? 0),
            'confirmed' => (int) ($counts['confirmed'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
        ];
    }

    /**
     * Latest activity log entries, newest first. Pulled from ActivityLog
     * (see AppointmentController::logActivity) rather than re-derived from
     * current appointment state, so history stays accurate even after an
     * appointment is later rescheduled, completed, or deleted.
     */
    private function recentActivity(int $limit = 15): array
    {
        return ActivityLog::orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn($log) => [
                'id'         => $log->id,
                'message'    => $log->message,
                'status'     => $log->status,
                'action'     => $log->action,
                'occurred_at' => $log->occurred_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
