<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Request;

class ClassroomStudentController extends Controller
{
    public function index($classroomId)
    {
        $classroom = Classroom::findOrFail($classroomId);
        // Kukunin natin ang mga estudyante pati ang pivot status at strand nila
        $students = $classroom->students()->with('strand')->get();

        return response()->json($students, 200);
    }

    public function approve(Request $request, $classroomId)
    {
        $request->validate(['student_ids' => 'required|array']);
        $classroom = Classroom::findOrFail($classroomId);

        foreach ($request->student_ids as $studentId) {
            $classroom->students()->updateExistingPivot($studentId, ['status' => 'approved']);
        }

        return response()->json(['message' => 'Students successfully enrolled.'], 200);
    }

    public function remove(Request $request, $classroomId)
    {
        // Gagamitin natin ito para sa parehong DECLINE at REMOVE (dahil ide-detach lang natin sila sa klase)
        $request->validate(['student_ids' => 'required|array']);
        $classroom = Classroom::findOrFail($classroomId);

        $classroom->students()->detach($request->student_ids);

        return response()->json(['message' => 'Students successfully removed from class.'], 200);
    }
}