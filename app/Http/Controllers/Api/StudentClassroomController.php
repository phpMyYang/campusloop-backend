<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Classroom;
use App\Models\Classwork;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentClassroomController extends Controller
{
    public function index()
    {
        try {
            $studentId = Auth::id();

            $classrooms = Classroom::whereHas('students', function ($query) use ($studentId) {
                $query->where('classroom_student.student_id', $studentId)
                      ->where('classroom_student.status', 'approved'); 
            })
            ->with(['creator', 'subject', 'strand'])
            ->withCount(['students as enrolled_count' => function ($query) {
                $query->where('classroom_student.status', 'approved');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

            return response()->json($classrooms, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Backend Error: ' . $e->getMessage()], 500);
        }
    }

    public function joinClassroom(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string'
            ]);

            $studentId = Auth::id();
            $classroom = Classroom::where('code', $request->code)->first();

            if (!$classroom) {
                return response()->json(['message' => 'Classroom not found. Please check the code.'], 404);
            }

            $existing = DB::table('classroom_student')
                ->where('classroom_id', $classroom->id)
                ->where('student_id', $studentId)
                ->first();

            if ($existing) {
                if ($existing->status === 'pending') {
                    return response()->json(['message' => 'You already have a pending request for this classroom. Please wait for the teacher to approve.'], 400);
                }
                return response()->json(['message' => 'You are already enrolled in this classroom.'], 400);
            }

            DB::table('classroom_student')->insert([
                'id' => Str::uuid(),
                'classroom_id' => $classroom->id,
                'student_id' => $studentId,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Join request sent! Please wait for your teacher to approve.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to join classroom: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $studentId = Auth::id();
            
            $classroom = Classroom::with(['creator', 'subject', 'strand'])
                ->withCount(['students as enrolled_count' => function ($query) {
                    $query->where('classroom_student.status', 'approved');
                }])
                ->whereHas('students', function ($query) use ($studentId) {
                    $query->where('classroom_student.student_id', $studentId)
                          ->where('classroom_student.status', 'approved');
                })
                ->findOrFail($id);

            return response()->json($classroom, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Classroom not found or unauthorized.'], 404);
        }
    }

    public function stream($id)
    {
        try {
            $studentId = Auth::id();
            $classworks = Classwork::with([
                'files', 
                'form',
                'comments' => function ($query) {
                    $query->whereNull('parent_id')
                          ->with(['user', 'replies.user'])
                          ->orderBy('created_at', 'asc');
                }
            ])
            ->where('classroom_id', $id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

            foreach ($classworks as $cw) {
                $submission = DB::table('classwork_submissions')
                    ->where('classwork_id', $cw->id)
                    ->where('student_id', $studentId)
                    ->first();
                
                $cw->student_submission = $submission;
                
                if ($submission) {
                    $cw->student_status = 'DONE';
                } else {
                    if ($cw->deadline) {
                        $deadline = \Carbon\Carbon::parse($cw->deadline);
                        $now = \Carbon\Carbon::now();

                        if ($now->greaterThan($deadline)) {
                            $cw->student_status = 'MISSING';
                        } elseif ($now->diffInDays($deadline, false) >= 0 && $now->diffInDays($deadline, false) <= 2) {
                            $cw->student_status = 'DUE SOON';
                        } else {
                            $cw->student_status = 'PENDING';
                        }
                    } else {
                        $cw->student_status = 'PENDING';
                    }
                }
            }

            return response()->json($classworks, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to load stream: ' . $e->getMessage()], 500);
        }
    }

    public function grades($id)
    {
        try {
            $studentId = Auth::id();
            $grades = DB::table('classwork_submissions')
                ->join('classworks', 'classwork_submissions.classwork_id', '=', 'classworks.id')
                ->where('classworks.classroom_id', $id)
                ->where('classwork_submissions.student_id', $studentId)
                ->where('classworks.type', '!=', 'material')
                ->whereNotNull('classwork_submissions.grade')
                ->select('classworks.title', 'classworks.type', 'classwork_submissions.grade', 'classworks.points')
                ->orderBy('classworks.created_at', 'desc')
                ->get();

            return response()->json($grades, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to load grades: ' . $e->getMessage()], 500);
        }
    }
}