<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'link' => $n->link,
                'is_read' => $n->is_read,
                'created_at' => $n->created_at->toIso8601String(),
            ]);

        return response()->json(['notifications' => $notifications]);
    }

    public function unreadCount(Request $request)
    {
        $count = $request->user()->unreadNotifications()->count();
        return response()->json(['count' => $count]);
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }
        $notification->update(['is_read' => true, 'read_at' => now()]);
        return response()->json(['message' => 'OK']);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['is_read' => true, 'read_at' => now()]);
        return response()->json(['message' => 'OK']);
    }
}