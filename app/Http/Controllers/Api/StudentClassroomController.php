<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Classroom;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentClassroomController extends Controller
{
    public function index()
    {
        try {
            $studentId = Auth::id();

            // Kukunin ang mga classrooms na ang student_id mo ay may 'approved' status sa pivot table
            $classrooms = Classroom::whereHas('students', function ($query) use ($studentId) {
                $query->where('classroom_student.student_id', $studentId)
                      ->where('classroom_student.status', 'approved'); // Explicit pivot table name
            })
            ->with(['creator', 'subject', 'strand'])
            ->withCount(['students as enrolled_count' => function ($query) {
                $query->where('classroom_student.status', 'approved');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

            return response()->json($classrooms, 200);
        } catch (\Exception $e) {
            // Ipapasa natin ang exact error para madaling makita sa Network tab kung sakali
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
}