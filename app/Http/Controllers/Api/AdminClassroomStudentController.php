<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminClassroomStudentController extends Controller
{
    // Kunin lahat ng students na nasa classroom (Pending at Approved)
    public function index($classroomId)
    {
        $classroom = Classroom::with(['students.strand'])->findOrFail($classroomId);
        return response()->json($classroom->students, 200);
    }

    // I-approve ang mga pending students
    public function approve(Request $request, $classroomId)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:users,id'
        ]);

        DB::table('classroom_student')
            ->where('classroom_id', $classroomId)
            ->whereIn('student_id', $request->student_ids)
            ->update(['status' => 'approved', 'updated_at' => now()]);

        return response()->json(['message' => 'Students approved successfully.'], 200);
    }

    // I-remove o i-decline ang mga students
    public function remove(Request $request, $classroomId)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:users,id'
        ]);

        DB::table('classroom_student')
            ->where('classroom_id', $classroomId)
            ->whereIn('student_id', $request->student_ids)
            ->delete();

        return response()->json(['message' => 'Students removed successfully.'], 200);
    }
}