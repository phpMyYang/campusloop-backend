<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    // Kukunin lahat ng Announcements at iko-convert sa Calendar Event format
    public function getAdminEvents(Request $request)
    {
        // ILI-LOAD NATIN ANG FILES RELATIONSHIP
        $announcements = Announcement::with('files')
            ->where('creator_id', $request->user()->id)
            ->get();
        
        $events = $announcements->map(function($a) {
            $status = $a->status;
            
            // DYNAMIC HIGHLIGHT COLORS BASE SA STATUS
            if ($status === 'Pending') {
                $color = '#fd7e14'; // Orange
            } elseif ($status === 'Published') {
                $color = '#198754'; // Green
            } else {
                $color = '#6c757d'; // Gray (Done)
            }

            return [
                'id' => $a->id,
                'title' => $a->title,
                'start' => $a->publish_from,
                'end' => $a->valid_until,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'type' => 'Announcement',
                    'content' => $a->content,
                    'status' => $status,
                    'link' => $a->link, 
                    'files' => $a->files 
                ]
            ];
        });

        return response()->json($events, 200);
    }

    public function checkActiveIndicator(Request $request)
    {
        $now = now();
        
        $hasActive = Announcement::where('creator_id', $request->user()->id)
            ->where('publish_from', '<=', $now)
            ->where('valid_until', '>=', $now)
            ->exists();

        return response()->json(['has_active_events' => $hasActive], 200);
    }
}