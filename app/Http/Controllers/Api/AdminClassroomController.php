<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Request;

class AdminClassroomController extends Controller
{
    // Fetch all classrooms with relations and enrolled count
    public function index()
    {
        $classrooms = Classroom::with(['creator', 'subject', 'strand'])
            ->withCount(['students as enrolled_count' => function ($query) {
                $query->where('classroom_student.status', 'approved');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($classrooms, 200);
    }

    // Bulk Delete (Soft Delete)
    public function destroyBulk(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:classrooms,id'
        ]);

        Classroom::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => 'Selected classrooms moved to recycle bin.'], 200);
    }

    // Fetch Specific Classroom for Inside View
    public function show($id)
    {
        $classroom = Classroom::with(['creator', 'subject', 'strand'])
            ->withCount(['students as enrolled_count' => function ($query) {
                $query->where('classroom_student.status', 'approved');
            }])
            ->findOrFail($id);

        return response()->json($classroom, 200);
    }
}