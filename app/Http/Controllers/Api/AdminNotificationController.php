<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminNotificationController extends Controller
{
    // View All Notifications
    public function index(Request $request)
    {
        $notifications = DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications, 200);
    }

    // Selected Notification Read
    public function markAsRead(Request $request, $id)
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->update(['is_read' => true, 'updated_at' => now()]);

        return response()->json(['message' => 'Marked as read'], 200);
    }

    // All Notification Read
    public function markAllAsRead(Request $request)
    {
        DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'updated_at' => now()]);

        return response()->json(['message' => 'All marked as read'], 200);
    }
}