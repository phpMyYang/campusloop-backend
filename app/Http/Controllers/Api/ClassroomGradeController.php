<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Classwork;
use Illuminate\Http\Request;

class ClassroomGradeController extends Controller
{
    public function index($classroomId)
    {
        // 1. Kunin lahat ng Classworks na hindi 'material' (Chronological Order)
        $classworks = Classwork::where('classroom_id', $classroomId)
            ->where('type', '!=', 'material')
            ->orderBy('created_at', 'asc')
            ->get();

        // 2. Kunin ang Classroom at ang mga "Approved" Students lang
        $classroom = Classroom::findOrFail($classroomId);

        $students = $classroom->students()
            ->wherePivot('status', 'approved')
            ->with(['submissions' => function ($query) use ($classroomId) {
                // Kunin lang ang submissions para sa current classroom na ito
                $query->whereHas('classwork', function ($q) use ($classroomId) {
                    $q->where('classroom_id', $classroomId);
                });
            }])
            ->get();

        return response()->json([
            'classworks' => $classworks,
            'students' => $students
        ], 200);
    }
}