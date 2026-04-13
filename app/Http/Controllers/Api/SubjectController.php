<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    // View Subject
    public function index()
    {
        // Isinama natin ang 'strand' para makuha ang strand name 
        $subjects = Subject::with('strand')->orderBy('created_at', 'desc')->get();
        return response()->json($subjects, 200);
    }

    // Create Subject
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:subjects,code',
            'description' => 'required|string',
            'strand_id' => 'required|exists:strands,id',
            'grade_level' => 'required|in:11,12', // Enum 11, 12 
            'semester' => 'required|in:1st,2nd'  // Enum 1st, 2nd 
        ]);

        Subject::create($validated);
        return response()->json(['message' => 'Subject created successfully!'], 201);
    }

    // Update Subject
    public function update(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:subjects,code,' . $id,
            'description' => 'required|string',
            'strand_id' => 'required|exists:strands,id',
            'grade_level' => 'required|in:11,12',
            'semester' => 'required|in:1st,2nd'
        ]);

        $subject->update($validated);
        return response()->json(['message' => 'Subject updated successfully!'], 200);
    }

    // Delete Subject
    public function destroy($id)
    {
        $subject = Subject::findOrFail($id);
        $subject->delete();
        return response()->json(['message' => 'Subject moved to recycle bin.'], 200);
    }

    // Para sa Multiple Deletion 
    public function bulkDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        Subject::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Selected subjects moved to recycle bin.'], 200);
    }
}