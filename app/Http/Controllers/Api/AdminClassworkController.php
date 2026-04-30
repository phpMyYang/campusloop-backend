<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classwork;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminClassworkController extends Controller
{
    // SECURITY FEATURE
    private function checkAdmin(Request $request)
    {
        return $request->user() && $request->user()->role === 'admin';
    }

    // Kukunin lahat ng Classworks sa loob ng isang Classroom
    public function index(Request $request, $classroomId)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        $query = Classwork::with([
            'files',
            'form',
            'comments' => function ($query) {
                $query->whereNull('parent_id')
                      ->with(['user', 'replies.user'])
                      ->orderBy('created_at', 'asc');
            }
        ])
        ->where('classroom_id', $classroomId);

        // Server-Side Search implementation
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('instruction', 'LIKE', "%{$search}%"); 
            });
        }

        // Kunin lahat ng filtered results
        $classworks = $query->orderBy('created_at', 'desc')->get();

        return response()->json($classworks, 200);
    }

    // Bulk Delete para sa Classworks
    public function destroyBulk(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        $request->validate([
            'ids' => 'required|array|max:100',
            'ids.*' => 'exists:classworks,id'
        ]);

        // KUNIN ANG CLASSWORKS BAGO BURAHIN PARA SA NOTIF
        // (Isasama ang classroom, subject, at creator para makuha ang names)
        $classworks = Classwork::with(['classroom.subject', 'classroom.creator'])
            ->whereIn('id', $request->ids)
            ->get();

        // Soft Delete
        Classwork::whereIn('id', $request->ids)->delete();

        // NOTIFICATION LOGIC 
        $admin = $request->user();
        $adminName = $admin ? $admin->first_name . ' ' . $admin->last_name : 'Admin';

        $notifications = [];
        $currentTime = now()->toDateTimeString();

        foreach ($classworks as $classwork) {
            $classroom = $classwork->classroom;
            
            if ($classroom && $classroom->creator_id) {
                $teacherId = $classroom->creator_id;
                
                // MGA DETALYE NA ILALAGAY SA DESCRIPTION
                $teacherName = $classroom->creator ? $classroom->creator->first_name . ' ' . $classroom->creator->last_name : 'Teacher';
                $subjectName = $classroom->subject ? $classroom->subject->description : 'the class';
                $sectionName = $classroom->section;
                
                // Kunin ang Title ng Classwork
                $classworkTitle = $classwork->title ?? $classwork->name ?? 'Activity';

                // DIRECT ADMIN DESCRIPTION
                $description = "Admin {$adminName} deleted the classwork '{$classworkTitle}' created by Teacher {$teacherName} in {$subjectName} ({$sectionName}).";

                $notifications[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $teacherId, // Ise-send natin diretso sa Teacher na may-ari ng class
                    'description' => $description,
                    'link' => "/teacher/recycle-bin", // Pabalik sa recycle bin para makita
                    'is_read' => false,
                    'created_at' => $currentTime,
                    'updated_at' => $currentTime,
                ];
            }
        }

        // ISAHANG BULK INSERT
        if (!empty($notifications)) {
            foreach (array_chunk($notifications, 500) as $chunk) {
                DB::table('notifications')->insert($chunk);
            }
        }

        $count = count($request->ids);

        if ($count > 0) {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Deleted Classworks',
                'description' => "Moved {$count} selected classwork(s) to the recycle bin."
            ]);
        }

        return response()->json(['message' => 'Selected classworks moved to recycle bin.'], 200);
    }

    // FETCH RESPONDENTS (BULLETPROOF RAW DB QUERY)
    public function submissions(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $classwork = DB::table('classworks')->where('id', $id)->first();
            if (!$classwork) {
                return response()->json(['message' => 'Classwork not found'], 404);
            }

            $studentIds = DB::table('classroom_student')
                ->where('classroom_id', $classwork->classroom_id)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->pluck('student_id')
                ->toArray();

            if (empty($studentIds)) {
                return response()->json([
                    'data' => [],
                    'total' => 0,
                    'last_page' => 1
                ], 200);
            }

            // SERVER-SIDE SEARCH QUERY
            $studentQuery = DB::table('users')
                ->whereIn('id', $studentIds)
                ->select('id', 'first_name', 'last_name', 'lrn');

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $studentQuery->where(function($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('last_name', 'LIKE', "%{$search}%")
                      ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search}%")
                      ->orWhere('lrn', 'LIKE', "%{$search}%");
                });
            }

            // PAGINATION (Kukunin lang ang naka-limit per page)
            $entries = $request->has('entries') ? (int) $request->entries : 10;
            $paginatedStudents = $studentQuery->orderBy('last_name', 'asc')->paginate($entries);

            // Kunin ang mga submissions ng NAKA-PAGINATE na students lang (Para mabilis)
            $currentPageStudentIds = collect($paginatedStudents->items())->pluck('id')->toArray();

            $submissions = DB::table('classwork_submissions')
                ->where('classwork_id', $id)
                ->whereIn('student_id', $currentPageStudentIds)
                ->get()
                ->keyBy('student_id');

            $submissionIds = $submissions->pluck('id')->toArray();
            $files = [];
            if (!empty($submissionIds)) {
                $filesData = DB::table('files')
                    ->whereIn('attachable_type', ['classwork_submission', 'App\\Models\\ClassworkSubmission']) 
                    ->whereIn('attachable_id', $submissionIds)
                    ->whereNull('deleted_at')
                    ->get();
                    
                foreach ($filesData as $file) {
                    $files[$file->attachable_id][] = (array) $file;
                }
            }

            $respondents = [];
            foreach ($paginatedStudents->items() as $student) {
                $sub = $submissions->has($student->id) ? (array) $submissions->get($student->id) : null;
                
                if ($sub) {
                    $sub['files'] = isset($files[$sub['id']]) ? $files[$sub['id']] : [];
                }

                $respondents[] = [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'lrn' => $student->lrn,
                    'submission' => $sub,
                ];
            }

            // Ibabalik natin kasama ang pagination metadata
            return response()->json([
                'data' => $respondents,
                'total' => $paginatedStudents->total(),
                'last_page' => $paginatedStudents->lastPage(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Backend PHP Error: ' . $e->getMessage() . ' on line ' . $e->getLine()
            ], 500);
        }
    }
}