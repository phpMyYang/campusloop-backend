<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classwork;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClassworkController extends Controller
{
    public function index($classroomId)
    {
        $classworks = Classwork::with([
                'files', 
                'form',
                'comments' => function ($query) {
                    $query->whereNull('parent_id')
                          ->with(['user', 'replies.user'])
                          ->orderBy('created_at', 'asc');
                }
            ])
            ->where('classroom_id', $classroomId)
            ->whereNull('deleted_at')
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
            'form_id' => 'nullable|uuid|exists:forms,id',
            'files.*' => 'file|max:51200'
        ]);

        $classwork = Classwork::create(array_merge($validated, ['classroom_id' => $classroomId]));

        if ($request->hasFile('files')) {
            $user = Auth::user();
            $folderName = str_replace(' ', '_', strtolower($user->first_name . '_' . $user->last_name . '_' . $user->id));
            $destinationPath = "users_files/{$folderName}/classworks";

            foreach ($request->file('files') as $uploadedFile) {
                $filename = $uploadedFile->getClientOriginalName();
                $path = $uploadedFile->storeAs($destinationPath, time() . '_' . $filename, 'public');

                File::create([
                    'id' => (string) Str::uuid(),
                    'owner_id' => $user->id,
                    'name' => $filename,
                    'path' => '/storage/' . $path,
                    'file_extension' => $uploadedFile->getClientOriginalExtension(),
                    'file_size' => $uploadedFile->getSize(),
                    'attachable_type' => Classwork::class,
                    'attachable_id' => $classwork->id
                ]);
            }
        }

        return response()->json(['message' => 'Classwork posted successfully!', 'classwork' => $classwork->load(['files', 'form'])], 201);
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
            'form_id' => 'nullable|uuid|exists:forms,id',
            'files.*' => 'file|max:51200'
        ]);

        $classwork->update($validated);

        if ($request->has('deleted_file_ids')) {
            $filesToDelete = File::whereIn('id', $request->deleted_file_ids)->get();
            foreach ($filesToDelete as $f) {
                $relativePath = str_replace('/storage/', '', $f->path);
                Storage::disk('public')->delete($relativePath);
                $f->delete();
            }
        }

        if ($request->hasFile('files')) {
            $user = Auth::user();
            $folderName = str_replace(' ', '_', strtolower($user->first_name . '_' . $user->last_name . '_' . $user->id));
            $destinationPath = "users_files/{$folderName}/classworks";

            foreach ($request->file('files') as $uploadedFile) {
                $filename = $uploadedFile->getClientOriginalName();
                $path = $uploadedFile->storeAs($destinationPath, time() . '_' . $filename, 'public');

                File::create([
                    'id' => (string) Str::uuid(),
                    'owner_id' => $user->id,
                    'name' => $filename,
                    'path' => '/storage/' . $path,
                    'file_extension' => $uploadedFile->getClientOriginalExtension(),
                    'file_size' => $uploadedFile->getSize(),
                    'attachable_type' => Classwork::class,
                    'attachable_id' => $classwork->id
                ]);
            }
        }

        return response()->json(['message' => 'Classwork updated successfully!'], 200);
    }

    public function destroy($id)
    {
        $classwork = Classwork::findOrFail($id);
        $classwork->delete(); 
        return response()->json(['message' => 'Classwork moved to recycle bin.'], 200);
    }

    public function getSubmissions($classworkId)
    {
        try {
            $classwork = Classwork::findOrFail($classworkId);
            $classroomId = $classwork->classroom_id;

            $students = DB::table('classroom_student')
                ->join('users', 'classroom_student.student_id', '=', 'users.id')
                ->where('classroom_student.classroom_id', $classroomId)
                ->where('classroom_student.status', 'approved')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.lrn', 'users.avatar')
                ->orderBy('users.last_name', 'asc')
                ->get();

            foreach ($students as $student) {
                $submission = DB::table('classwork_submissions')
                    ->where('classwork_id', $classworkId)
                    ->where('student_id', $student->id)
                    ->first();
                
                if ($submission) {
                    $submission->files = File::where('attachable_type', 'classwork_submission')
                        ->where('attachable_id', $submission->id)
                        ->get();
                }

                $student->submission = $submission;
            }

            return response()->json($students, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch submissions: ' . $e->getMessage()], 500);
        }
    }

    public function gradeSubmission(Request $request, $classworkId, $studentId)
    {
        try {
            $request->validate([
                'grade' => 'required|numeric|min:0'
            ]);

            $submission = DB::table('classwork_submissions')
                ->where('classwork_id', $classworkId)
                ->where('student_id', $studentId)
                ->first();

            if (!$submission) {
                return response()->json(['message' => 'No submission found to grade.'], 404);
            }

            DB::table('classwork_submissions')
                ->where('id', $submission->id)
                ->update([
                    'grade' => $request->grade,
                    'status' => 'graded', 
                    'updated_at' => now()
                ]);

            return response()->json(['message' => 'Grade saved successfully!'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to save grade: ' . $e->getMessage()], 500);
        }
    }

    public function returnSubmission(Request $request, $classworkId, $studentId)
    {
        try {
            $request->validate([
                'feedback' => 'required|string'
            ]);

            $submission = DB::table('classwork_submissions')
                ->where('classwork_id', $classworkId)
                ->where('student_id', $studentId)
                ->first();

            if (!$submission) {
                return response()->json(['message' => 'No submission found.'], 404);
            }

            // SET TO 'pending' (instead of returned) PARA HINDI MAGKA-ENUM ERROR SA DATABASE! 
            // PERO SINAVE ANG FEEDBACK PARA MALAMAN NG FRONTEND NA RETURNED ITO.
            DB::table('classwork_submissions')
                ->where('id', $submission->id)
                ->update([
                    'status' => 'pending', 
                    'teacher_feedback' => $request->feedback,
                    'grade' => null, 
                    'updated_at' => now()
                ]);

            return response()->json(['message' => 'Submission returned to student with feedback.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to return submission: ' . $e->getMessage()], 500);
        }
    }
}