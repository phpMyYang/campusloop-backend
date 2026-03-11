<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classwork;
use Illuminate\Http\Request;

class ClassworkController extends Controller
{
    public function index($classroomId)
    {
        $classworks = Classwork::where('classroom_id', $classroomId)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($classworks, 200);
    }

    public function store(Request $request, $classroomId)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:assignment,activity,quiz,exam,material',
            'instruction' => 'required|string',
            'points' => 'nullable|integer',
            'deadline' => 'nullable|date',
            'link' => 'nullable|string',
        ]);

        $classwork = Classwork::create(array_merge($validated, ['classroom_id' => $classroomId]));

        // Note: Ang File Upload processing para sa 'files[]' ay idadagdag dito sa susunod na phase
        // kapag ginawa na natin ang polymorphic files table.

        return response()->json(['message' => 'Classwork posted successfully!', 'classwork' => $classwork], 201);
    }

    public function update(Request $request, $id)
    {
        $classwork = Classwork::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:assignment,activity,quiz,exam,material',
            'instruction' => 'required|string',
            'points' => 'nullable|integer',
            'deadline' => 'nullable|date',
            'link' => 'nullable|string',
        ]);

        $classwork->update($validated);

        return response()->json(['message' => 'Classwork updated successfully!'], 200);
    }

    public function destroy($id)
    {
        $classwork = Classwork::findOrFail($id);
        $classwork->delete(); 
        return response()->json(['message' => 'Classwork deleted successfully.'], 200);
    }
}