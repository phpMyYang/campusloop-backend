<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Classwork;
use App\Models\File;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewClassworkPosted;

class ClassworkController extends Controller
{
    // View Classwork
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

    // Create Classwork
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

        // NOTIFICATION & EMAIL LOGIC
        $teacher = $request->user();
        $teacherName = $teacher->first_name . ' ' . $teacher->last_name;
        $classworkType = ucfirst($classwork->type);
        
        // Kunin ang classroom at subject
        $classroom = Classroom::with('subject')->findOrFail($classroomId);
        $subjectName = $classroom->subject ? $classroom->subject->description : 'the class';
        
        // Kunin ang lahat ng APPROVED students sa classroom
        $approvedStudents = $classroom->students()->wherePivot('status', 'approved')->get();

        $notifications = [];
        $currentTime = now()->toDateTimeString();
        
        // Setup ng link na gagamitin sa notification at sa email
        $frontendBaseUrl = env('FRONTEND_URL', 'http://localhost:5173'); // Siguraduhin nasa .env mo ito!
        $linkPath = "/student/classrooms/{$classroomId}/stream";
        $fullLink = $frontendBaseUrl . $linkPath;

        foreach ($approvedStudents as $student) {
            // Ihanda ang IN-APP Bell Notification
            $notifications[] = [
                'id' => Str::uuid()->toString(),
                'user_id' => $student->id,
                'description' => "Teacher {$teacherName} posted a new {$classworkType}: '{$classwork->title}' in {$subjectName}.",
                'link' => $linkPath,
                'is_read' => false,
                'created_at' => $currentTime,
                'updated_at' => $currentTime,
            ];

            // I-queue ang pag-send ng EMAIL sa background
            if (!empty($student->email)) {
                Mail::to($student->email)->send(new NewClassworkPosted(
                    $student->first_name,
                    $teacherName,
                    $subjectName,
                    $classwork->type,
                    $classwork->title,
                    $classwork->deadline,
                    $fullLink
                ));
            }
        }

        // Bulk Insert sa Database Notifications
        if (!empty($notifications)) {
            foreach (array_chunk($notifications, 500) as $chunk) {
                DB::table('notifications')->insert($chunk);
            }
        }

        // ACTIVITY LOG
        ActivityLog::create([
            'user_id' => $teacher->id,
            'action' => 'Created Classwork',
            'description' => "Posted a new {$classworkType}: '{$classwork->title}' in {$subjectName}."
        ]);

        return response()->json(['message' => 'Classwork posted successfully!', 'classwork' => $classwork->load(['files', 'form'])], 201);
    }

    // Update Classwork
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

        $classworkType = ucfirst($classwork->type);

        // ACTIVITY LOG
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Updated Classwork',
            'description' => "Updated the details of {$classworkType}: '{$classwork->title}'."
        ]);

        return response()->json(['message' => 'Classwork updated successfully!'], 200);
    }

    // Delete Classwork
    public function destroy(Request $request, $id)
    {
        $classwork = Classwork::with('classroom.subject')->findOrFail($id);

        $title = $classwork->title ?? 'Activity';
        $type = ucfirst($classwork->type);
        $subjectName = $classwork->classroom && $classwork->classroom->subject ? $classwork->classroom->subject->description : 'the class';

        $classwork->delete(); 

        // ACTIVITY LOG TRIGGER: Delete Classwork
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Deleted Classwork',
            'description' => "Moved the {$type} '{$title}' from {$subjectName} to the recycle bin."
        ]);

        return response()->json(['message' => 'Classwork moved to recycle bin.'], 200);
    }

    // View Student Submission
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

    // Put Grade to Student Submission
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

            // KUNIN ANG DETAILS NG STUDENT PARA SA LOG
            $student = DB::table('users')->where('id', $studentId)->first();
            $studentName = $student ? $student->first_name . ' ' . $student->last_name : 'a student';

            // NOTIFICATION LOGIC FOR STUDENT
            // Kunin ang details ng classwork para malaman kung anong type (e.g., assignment, quiz) at perfect score
            $classwork = DB::table('classworks')->where('id', $classworkId)->first();

            if ($classwork) {
                // Kunin ang classroom at subject details para sa smart description
                $classroom = DB::table('classrooms')->where('id', $classwork->classroom_id)->first();
                $subject = $classroom ? DB::table('subjects')->where('id', $classroom->subject_id)->first() : null;

                $teacherName = $request->user()->first_name . ' ' . $request->user()->last_name;
                $subjectName = $subject ? $subject->description : 'the class';
                $classworkType = ucfirst($classwork->type); // Gagawing Capital ang unang letter (e.g., "Assignment")
                $classworkTitle = $classwork->title ?? $classwork->name ?? 'Activity';
                $sectionName = $classroom ? "({$classroom->section})" : "";
                
                // Format score (Example: "85/100" kung may perfect points, or "85" lang kung wala)
                $scoreString = $classwork->points ? "{$request->grade}/{$classwork->points}" : "{$request->grade}";

                // I-insert ang notification
                DB::table('notifications')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $studentId,
                    'description' => "Teacher {$teacherName} graded your {$classworkType}: '{$classworkTitle}' in {$subjectName} {$sectionName}. Score: {$scoreString}",
                    'link' => "/student/classrooms/" . ($classroom ? $classroom->id : ''), // Ididirekta sa loob ng classroom nila
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // ACTIVITY LOG
                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'Graded Submission',
                    'description' => "Graded {$studentName}'s submission for the {$classworkType}: '{$classworkTitle}'."
                ]);
            }
            return response()->json(['message' => 'Grade saved successfully!'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to save grade: ' . $e->getMessage()], 500);
        }
    }

    // Unsubmit Student Submission
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

            // SET TO 'pending' (instead of returned) 
            // PERO SINAVE ANG FEEDBACK PARA MALAMAN NG FRONTEND NA RETURNED ITO.
            DB::table('classwork_submissions')
                ->where('id', $submission->id)
                ->update([
                    'status' => 'pending', 
                    'teacher_feedback' => $request->feedback,
                    'grade' => null, 
                    'updated_at' => now()
                ]);

            // KUNIN ANG DETAILS NG STUDENT PARA SA LOG
            $student = DB::table('users')->where('id', $studentId)->first();
            $studentName = $student ? $student->first_name . ' ' . $student->last_name : 'a student';

            // NOTIFICATION LOGIC FOR STUDENT (RETURNED)
            $classwork = DB::table('classworks')->where('id', $classworkId)->first();

            if ($classwork) {
                $classroom = DB::table('classrooms')->where('id', $classwork->classroom_id)->first();
                $subject = $classroom ? DB::table('subjects')->where('id', $classroom->subject_id)->first() : null;

                $teacherName = $request->user()->first_name . ' ' . $request->user()->last_name;
                $subjectName = $subject ? $subject->description : 'the class';
                $classworkType = ucfirst($classwork->type); 
                $classworkTitle = $classwork->title ?? $classwork->name ?? 'Activity';
                $sectionName = $classroom ? "({$classroom->section})" : "";
                
                // Limitahan natin ang feedback sa 30 characters para maganda sa bell notification
                $feedbackSnippet = Str::limit($request->feedback, 30);

                // I-insert ang notification
                DB::table('notifications')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $studentId,
                    'description' => "Teacher {$teacherName} returned your {$classworkType}: '{$classworkTitle}' in {$subjectName} {$sectionName}. Feedback: \"{$feedbackSnippet}\"",
                    'link' => "/student/classrooms/" . ($classroom ? $classroom->id : ''), // Direkta sa classroom
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // ACTIVITY LOG TRIGGER: Return Submission
                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'Returned Submission',
                    'description' => "Returned {$studentName}'s submission for the {$classworkType}: '{$classworkTitle}' with feedback."
                ]);
            }

            return response()->json(['message' => 'Submission returned to student with feedback.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to return submission: ' . $e->getMessage()], 500);
        }
    }
}