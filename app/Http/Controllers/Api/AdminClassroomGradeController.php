<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminClassroomGradeController extends Controller
{
    // SECURITY
    private function checkAdmin(Request $request)
    {
        return $request->user() && $request->user()->role === 'admin';
    }

    // View Classworks Grades 
    public function index(Request $request, $classroomId)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $classroom = DB::table('classrooms')->where('id', $classroomId)->first();
            if (!$classroom) {
                return response()->json(['message' => 'Classroom not found'], 404);
            }

            $classworks = DB::table('classworks')
                ->where('classroom_id', $classroomId)
                ->where('type', '!=', 'material')
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'asc') 
                ->select('id', 'title', 'type', 'points', 'created_at', 'deadline')
                ->get();

            $studentIds = DB::table('classroom_student')
                ->where('classroom_id', $classroomId)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->pluck('student_id')
                ->toArray();

            $studentsQuery = DB::table('users')
                ->whereIn('id', $studentIds)
                ->select('id', 'first_name', 'last_name', 'lrn');

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $studentsQuery->where(function($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('last_name', 'LIKE', "%{$search}%")
                      ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search}%")
                      ->orWhere('lrn', 'LIKE', "%{$search}%");
                });
            }

            $entries = $request->has('entries') ? (int) $request->entries : 10;
            $paginatedStudents = $studentsQuery->orderBy('last_name', 'asc')->paginate($entries);
            $classworkIds = $classworks->pluck('id')->toArray();
            $currentPageStudentIds = collect($paginatedStudents->items())->pluck('id')->toArray();
            $submissions = [];
            
            if (!empty($classworkIds) && !empty($currentPageStudentIds)) {
                $submissionsData = DB::table('classwork_submissions')
                    ->whereIn('classwork_id', $classworkIds)
                    ->whereIn('student_id', $currentPageStudentIds)
                    ->get();
                    
                foreach ($submissionsData as $sub) {
                    $submissions[$sub->student_id][] = (array) $sub;
                }
            }

            $studentsData = [];
            $now = now();

            foreach ($paginatedStudents->items() as $student) {
                $studentSubs = $submissions[$student->id] ?? [];
                $processedSubs = [];

                foreach ($classworks as $cw) {
                    $sub = collect($studentSubs)->firstWhere('classwork_id', $cw->id);
                    
                    if ($sub) {
                        $processedSubs[] = $sub;
                    } else {
                        if ($cw->deadline && $now > $cw->deadline) {
                            $processedSubs[] = [
                                'classwork_id' => $cw->id,
                                'student_id' => $student->id,
                                'status' => 'missing',
                                'grade' => null
                            ];
                        }
                    }
                }

                $studentsData[] = [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'lrn' => $student->lrn,
                    'submissions' => $processedSubs
                ];
            }

            return response()->json([
                'classworks' => $classworks,
                'students' => $studentsData,
                'total' => $paginatedStudents->total(),
                'last_page' => $paginatedStudents->lastPage()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Classroom Grade Fetch Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            return response()->json([
                'message' => 'Unable to load class records. Please try again later or contact support if the issue persists.'
            ], 500);
        }
    }
}