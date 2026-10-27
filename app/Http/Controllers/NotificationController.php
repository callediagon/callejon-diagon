<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        // Si es una petición AJAX, devolver JSON
        if ($request->wantsJson() || $request->ajax()) {
            $limit = $request->input('limit', 20);
            
            $notifications = auth()->user()->notifications()
                ->paginate($limit);

            $unreadCount = auth()->user()->unreadNotifications()->count();

            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        }

        // Si no, devolver la página Inertia
        $notifications = auth()->user()->notifications()->paginate(20);
        
        return inertia('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', auth()->id())->findOrFail($id);
        $notification->markAsRead();
        
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['success' => true]);
        }
        
        return back();
    }

    public function markAllAsRead()
    {
        auth()->user()->notifications()->where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
        
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['success' => true]);
        }
        
        return back();
    }

    public function unreadCount()
    {
        $count = auth()->user()->unreadNotifications()->count();
        
        return response()->json($count);
    }

    public function getNotificationsData(Request $request)
    {
        $limit = $request->input('limit', 10);
        
        $notifications = auth()->user()->notifications()
            ->limit($limit)
            ->get();

        $unreadCount = auth()->user()->unreadNotifications()->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }
}