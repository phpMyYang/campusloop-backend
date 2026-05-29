<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminClassroomController extends Controller
{
    // security
    private function checkAdmin(Request $request)
    {
        return $request->user() && $request->user()->role === 'admin';
    }

    // Fetch all classrooms with relations and enrolled count
    public function index(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $query = Classroom::with(['creator', 'subject', 'strand'])
                ->withCount(['students as enrolled_count' => function ($query) {
                    $query->where('classroom_student.status', 'approved')
                        ->whereNull('users.deleted_at');
                }]);

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('section', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhereHas('subject', function($sq) use ($search) {
                        $sq->where('description', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('creator', function($cq) use ($search) {
                        $cq->where('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%");
                    });
                });
            }

            if ($request->has('filterGradeLevel') && $request->filterGradeLevel !== 'all') {
                $query->where('grade_level', $request->filterGradeLevel);
            }

            $sortOrder = $request->input('sortOrder', 'newest') === 'oldest' ? 'asc' : 'desc';
            $query->orderBy('created_at', $sortOrder);
            $entries = $request->has('entries') ? (int) $request->entries : 12;

            return response()->json($query->paginate($entries), 200);

        } catch (\Exception $e) {
            Log::error('AdminClassroomController index Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while fetching classrooms.'], 500);
        }
    }

    // Bulk Delete (Soft Delete)
    public function destroyBulk(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $request->validate([
                'ids' => 'required|array|max:100',
                'ids.*' => 'exists:classrooms,id'
            ]);

            DB::beginTransaction();
            $classrooms = Classroom::with('subject')->whereIn('id', $request->ids)->get();
            Classroom::whereIn('id', $request->ids)->delete();
            $admin = $request->user();
            $adminName = $admin ? $admin->first_name . ' ' . $admin->last_name : 'Admin';
            $notifications = [];
            $currentTime = now()->toDateTimeString();

            foreach ($classrooms as $classroom) {
                $teacherId = $classroom->creator_id;
                $subjectName = $classroom->subject ? $classroom->subject->description : 'the class';
                $sectionName = $classroom->section;

                if ($teacherId) {
                    $notifications[] = [
                        'id' => Str::uuid()->toString(),
                        'user_id' => $teacherId,
                        'description' => "Admin {$adminName} deleted your classroom {$subjectName} ({$sectionName}). It was moved to the Recycle Bin.",
                        'link' => "/teacher/recycle-bin", 
                        'is_read' => false,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime,
                    ];
                }
            }

            if (!empty($notifications)) {
                foreach (array_chunk($notifications, 500) as $chunk) {
                    DB::table('notifications')->insert($chunk);
                }
            }

            $count = count($request->ids);

            if ($count > 0) {
                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'Deleted Classrooms',
                    'description' => "Moved {$count} selected classroom(s) to the recycle bin."
                ]);
            }

            DB::commit(); 
            return response()->json(['message' => 'Selected classrooms moved to recycle bin.'], 200);

        } catch (\Exception $e) {
            DB::rollBack(); 
            Log::error('AdminClassroomController destroyBulk Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while deleting classrooms.'], 500);
        }
    }

    // Fetch Specific Classroom for Inside View
    public function show(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $classroom = Classroom::with(['creator', 'subject', 'strand'])
                ->withCount(['students as enrolled_count' => function ($query) {
                    $query->where('classroom_student.status', 'approved')
                        ->whereNull('users.deleted_at');
                }])
                ->findOrFail($id);

            return response()->json($classroom, 200);

        } catch (\Exception $e) {
            Log::error('AdminClassroomController show Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while loading the classroom details.'], 500);
        }
    }
}