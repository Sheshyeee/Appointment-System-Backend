<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // GET /notifications
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->limit(30)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => Notification::where('user_id', Auth::id())
                ->unread()
                ->count(),
        ]);
    }

    // PUT /notifications/{notification}/read
    public function markRead(Notification $notification)
    {
        // Ownership check — a notification only ever belongs to one user,
        // never trust the id alone.
        if ($notification->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['notification' => $notification]);
    }

    // PUT /notifications/read-all
    public function markAllRead()
    {
        Notification::where('user_id', Auth::id())
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
