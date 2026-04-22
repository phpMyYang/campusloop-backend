<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\SystemSetting;
use App\Models\Subject; 
use App\Models\ActivityLog; 
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClassroomController extends Controller
{
    // View Classrooms
    public function index(Request $request)
    {
        $classrooms = Classroom::with(['subject', 'strand', 'creator'])
            ->withCount(['students as enrolled_count' => function ($query) {
                $query->where('classroom_student.status', 'approved');
            }])
            ->where('creator_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($classrooms, 200);
    }

    // Create Classroom
    public function store(Request $request)
    {
        $validated = $request->validate([
            'section' => 'required|string|max:255',
            'strand_id' => 'required|uuid|exists:strands,id',
            'grade_level' => 'required|in:11,12',
            'subject_id' => 'required|uuid|exists:subjects,id',
            'capacity' => 'required|integer|min:1',
            'color_bg' => 'required|string|max:20',
            'schedule' => 'required|array',
            'schedule.days' => 'required|array|min:1',
            'schedule.start_time' => 'required|date_format:H:i',
            'schedule.end_time' => 'required|date_format:H:i|after:schedule.start_time',
        ]);

        $activeSetting = class_exists('\App\Models\SystemSetting') 
            ? SystemSetting::where('is_active', true)->first() 
            : null;

        do {
            $code = strtoupper(Str::random(3) . '-' . Str::random(3) . '-' . Str::random(3));
        } while (Classroom::where('code', $code)->exists());

        $classroom = Classroom::create([
            'creator_id' => $request->user()->id,
            'section' => $validated['section'],
            'strand_id' => $validated['strand_id'],
            'grade_level' => $validated['grade_level'],
            'subject_id' => $validated['subject_id'],
            'capacity' => $validated['capacity'],
            'schedule' => $validated['schedule'], 
            'color_bg' => $validated['color_bg'],
            'code' => $code,
            'school_year' => $activeSetting ? $activeSetting->school_year : 'Not Set',
            'semester' => $activeSetting ? $activeSetting->semester : '1st',
        ]);

        // SUBJECT NAME PARA SA LOG
        $subject = Subject::find($validated['subject_id']);
        $subjectName = $subject ? $subject->description : 'a subject';

        // ACTIVITY LOG 
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Created Classroom',
            'description' => "Created a new classroom for {$subjectName} ({$validated['section']})."
        ]);

        return response()->json(['message' => 'Classroom created successfully!', 'code' => $code], 201);
    }

    // Inside Classroom
    public function show(Request $request, $id)
    {
        $classroom = Classroom::with(['subject', 'strand', 'creator'])
            ->withCount(['students as enrolled_count' => function ($query) {
                $query->where('classroom_student.status', 'approved');
            }])
            ->where('creator_id', $request->user()->id)
            ->findOrFail($id); // Siguraduhing findOrFail ito at hindi get()

        return response()->json($classroom, 200);
    }

    // Update Classroom
    public function update(Request $request, $id)
    {
        $classroom = Classroom::where('creator_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'section' => 'required|string|max:255',
            'strand_id' => 'required|uuid|exists:strands,id',
            'grade_level' => 'required|in:11,12',
            'subject_id' => 'required|uuid|exists:subjects,id',
            'capacity' => 'required|integer|min:1',
            'color_bg' => 'required|string|max:20',
            'schedule' => 'required|array',
            'schedule.days' => 'required|array|min:1',
            'schedule.start_time' => 'required|date_format:H:i',
            'schedule.end_time' => 'required|date_format:H:i|after:schedule.start_time',
        ]);

        $classroom->update($validated);

        // SUBJECT NAME PARA SA LOG
        $subject = Subject::find($validated['subject_id']);
        $subjectName = $subject ? $subject->description : 'a subject';

        // ACTIVITY LOG 
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Updated Classroom',
            'description' => "Updated the details of classroom {$subjectName} ({$validated['section']})."
        ]);

        return response()->json(['message' => 'Classroom updated successfully!'], 200);
    }

    // Delete Classroom
    public function destroy(Request $request, $id)
    {
        $classroom = Classroom::where('creator_id', $request->user()->id)->findOrFail($id);

        // DETALYE PARA SA LOG BAGO BURAHIN
        $subjectName = $classroom->subject ? $classroom->subject->description : 'a subject';
        $sectionName = $classroom->section;

        $classroom->delete(); 

        // ACTIVITY LOG 
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Deleted Classroom',
            'description' => "Moved the classroom {$subjectName} ({$sectionName}) to the recycle bin."
        ]);

        return response()->json(['message' => 'Classroom moved to recycle bin.'], 200);
    }
}