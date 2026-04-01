<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Announcement;
use App\Models\ClassworkSubmission;
use App\Models\Comment;
use App\Models\Classroom;
use App\Models\Classwork;

class TeacherHomeController extends Controller
{
    public function dashboard(Request $request)
    {
        $teacher = $request->user();
        $now = now();

        // GET PUBLISHED ANNOUNCEMENTS
        $announcements = Announcement::with([
            'creator', 
            'files', 
            'comments' => function($q) {
                $q->whereNull('parent_id')
                  ->with(['user', 'replies.user'])
                  ->orderBy('created_at', 'asc');
            }
        ])
        ->where('publish_from', '<=', $now)
        ->where(function($q) use ($now) {
            $q->where('valid_until', '>=', $now)->orWhereNull('valid_until');
        })
        ->orderBy('publish_from', 'desc')
        ->get();

        // GET TO-DO LIST (Needs Grading)
        $todos = ClassworkSubmission::with(['classwork.classroom.subject', 'student'])
            ->whereHas('classwork.classroom', function($q) use ($teacher) {
                $q->where('creator_id', $teacher->id);
            })
            ->whereIn('status', ['pending', 'late_submission'])
            ->whereNull('grade')
            ->whereNull('teacher_feedback') 
            ->orderBy('submitted_at', 'asc')
            ->get();

        // GET TOTAL CLASSROOMS
        $classroomsCount = Classroom::where('creator_id', $teacher->id)->count();

        // GET TODAY'S SCHEDULES
        $todayName = strtolower($now->format('l')); 
        $shortToday = strtolower($now->format('D')); 
        $todayDate = $now->format('Y-m-d');

        $todaySchedules = [];

        $deadlinesToday = Classwork::with('classroom.subject')
            ->whereHas('classroom', function($q) use ($teacher) {
                $q->where('creator_id', $teacher->id);
            })->whereDate('deadline', $todayDate)->get();

        foreach ($deadlinesToday as $cw) {
            $todaySchedules[] = [
                'id' => 'cw_' . $cw->id,
                'classroom_id' => $cw->classroom_id, 
                'title' => 'Deadline: ' . $cw->title,
                'type' => 'deadline',
                'time' => $cw->deadline->format('h:i A')
            ];
        }

        $classrooms = Classroom::with('subject')->where('creator_id', $teacher->id)->get();
        foreach ($classrooms as $room) {
            $scheds = $room->schedule;
            if (is_string($scheds)) {
                $decoded = json_decode($scheds, true);
                $scheds = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
            }
            if (is_array($scheds) && isset($scheds['days'])) {
                $days = array_map('strtolower', $scheds['days']);
                if (in_array($todayName, $days) || in_array($shortToday, $days)) {
                    $startTime = $scheds['start_time'] ?? null;
                    $endTime = $scheds['end_time'] ?? null;
                    $timeString = ($startTime ? date('h:i A', strtotime($startTime)) : 'TBA') . ' - ' . ($endTime ? date('h:i A', strtotime($endTime)) : 'TBA');
                    
                    $todaySchedules[] = [
                        'id' => 'class_' . $room->id,
                        'classroom_id' => $room->id, 
                        'title' => ($room->subject->code ?? 'Class') . ' - ' . $room->section,
                        'type' => 'class',
                        'time' => $timeString
                    ];
                }
            }
        }

        return response()->json([
            'user' => $teacher,
            'announcements' => $announcements,
            'todos' => $todos,
            'classrooms_count' => $classroomsCount,
            'today_schedules' => $todaySchedules
        ], 200);
    }

    public function postComment(Request $request, $announcementId)
    {
        $request->validate(['content' => 'required|string']);
        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'commentable_type' => 'App\Models\Announcement',
            'commentable_id' => $announcementId,
            'content' => $request->content,
            'parent_id' => $request->parent_id
        ]);
        return response()->json(['message' => 'Comment posted successfully', 'comment' => $comment], 201);
    }

    public function updateComment(Request $request, $id)
    {
        $request->validate(['content' => 'required|string']);
        $comment = Comment::where('user_id', $request->user()->id)->findOrFail($id);
        $comment->update(['content' => $request->content]);
        return response()->json(['message' => 'Comment updated successfully']);
    }

    public function deleteComment(Request $request, $id)
    {
        $comment = Comment::where('user_id', $request->user()->id)->findOrFail($id);
        $comment->forceDelete();
        return response()->json(['message' => 'Comment deleted successfully']);
    }
}