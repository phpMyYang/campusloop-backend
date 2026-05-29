<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AdminFormController extends Controller
{
    // SECURITY FEATURE
    private function checkAdmin(Request $request)
    {
        return $request->user() && $request->user()->role === 'admin';
    }

    // View All Forms
    public function index(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $query = Form::with('creator');

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhereHas('creator', function($q2) use ($search) {
                        $q2->where('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%")
                            ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search}%");
                    });
                });
            }

            if ($request->has('teacher') && $request->teacher !== 'all') {
                $query->where('creator_id', $request->teacher);
            }

            $sortOrder = $request->has('sort') && $request->sort === 'oldest' ? 'asc' : 'desc';
            $query->orderBy('created_at', $sortOrder);
            $entries = $request->has('entries') ? (int) $request->entries : 12;
            $paginatedForms = $query->paginate($entries);
            $teacherIds = Form::select('creator_id')->distinct()->pluck('creator_id')->filter();

            $teachers = User::whereIn('id', $teacherIds)
                ->select('id', 'first_name', 'last_name')
                ->orderBy('first_name', 'asc')
                ->get();

            return response()->json([
                'data' => $paginatedForms->items(),
                'total' => $paginatedForms->total(),
                'last_page' => $paginatedForms->lastPage(),
                'teachers' => $teachers
            ], 200);

        } catch (\Exception $e) {
            Log::error('AdminFormController index Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while fetching forms.'], 500);
        }
    }

    // Bulk Delete (Soft Delete) para sa Forms
    public function destroyBulk(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $request->validate([
                'ids' => 'required|array|max:100',
                'ids.*' => 'exists:forms,id'
            ]);

            DB::beginTransaction();

            $forms = Form::whereIn('id', $request->ids)->get();
            Form::whereIn('id', $request->ids)->delete();
            $admin = $request->user();
            $adminName = $admin ? $admin->first_name . ' ' . $admin->last_name : 'Admin';
            $notifications = [];
            $currentTime = now()->toDateTimeString();

            foreach ($forms as $form) {
                if ($form->creator_id) {
                    $formName = $form->name ?? 'Quiz/Exam';
                    
                    $notifications[] = [
                        'id' => Str::uuid()->toString(),
                        'user_id' => $form->creator_id, 
                        'description' => "Admin {$adminName} deleted your form '{$formName}'. It was moved to the Recycle Bin.",
                        'link' => "/teacher/recycle-bin", 
                        'is_read' => false,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime,
                    ];
                }
            }

            if (!empty($notifications)) {
                foreach (array_chunk($notifications, 500) as $chunk) {
                    DB::table('notifications')->insert($chunk);
                }
            }

            $count = count($request->ids);

            if ($count > 0) {
                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'Deleted Forms',
                    'description' => "Moved {$count} selected form(s) to the recycle bin."
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Forms moved to recycle bin.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AdminFormController destroyBulk Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An error occurred while deleting forms.'], 500);
        }
    }

    // Inside of Form
    public function show(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $form = Form::with(['questions' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])->findOrFail($id);

            return response()->json($form, 200);

        } catch (\Exception $e) {
            Log::error('AdminFormController show Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while loading the form details.'], 500);
        }
    }

    // Student Answers 
    public function respondents(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }
        
        try {
            $query = FormSubmission::with(['student.strand'])
                ->where('form_id', $id);

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->whereHas('student', function($q) use ($search) {
                    $q->where('users.first_name', 'LIKE', "%{$search}%")
                    ->orWhere('users.last_name', 'LIKE', "%{$search}%")
                    ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'LIKE', "%{$search}%")
                    ->orWhere('users.email', 'LIKE', "%{$search}%")
                    ->orWhere('users.lrn', 'LIKE', "%{$search}%")
                    ->orWhereHas('strand', function($qStrand) use ($search) {
                        $qStrand->where('strands.name', 'LIKE', "%{$search}%");
                    });
                });
            }

            $query->orderBy('submitted_at', 'desc');
            $entries = $request->has('entries') ? (int) $request->entries : 10;
            $paginatedSubmissions = $query->paginate($entries);
            $submissionIds = collect($paginatedSubmissions->items())->pluck('id');

            $answers = DB::table('form_submission_answers')
                ->whereIn('submission_id', $submissionIds)
                ->get()
                ->groupBy('submission_id');

            foreach ($paginatedSubmissions->items() as $submission) {
                $submission->answers = isset($answers[$submission->id]) ? $answers[$submission->id] : [];
            }

            return response()->json([
                'data' => $paginatedSubmissions->items(),
                'total' => $paginatedSubmissions->total(),
                'last_page' => $paginatedSubmissions->lastPage()
            ], 200);

        } catch (\Exception $e) {
            Log::error('AdminFormController respondents Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while loading respondents.'], 500);
        }
    }

    // UNSUBMIT (ADMIN CONTROL)
    public function unsubmit(Request $request, $formId, $submissionId) 
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $submission = FormSubmission::where('id', $submissionId)
                                        ->where('form_id', $formId)
                                        ->firstOrFail();

            $studentId = $submission->student_id;
            $student = User::find($studentId);
            $form = DB::table('forms')->where('id', $formId)->first();
            $classwork = DB::table('classworks')->where('form_id', $formId)->first();

            DB::beginTransaction();

            DB::table('form_submission_answers')->where('submission_id', $submissionId)->delete();
            $submission->forceDelete();

            if ($classwork) {
                DB::table('classwork_submissions')
                    ->where('classwork_id', $classwork->id)
                    ->where('student_id', $studentId)
                    ->delete(); 
            }
            if ($form && $classwork) {
                $admin = $request->user();
                $adminName = $admin->first_name . ' ' . $admin->last_name;
                $studentName = $student ? $student->first_name . ' ' . $student->last_name : 'a student';
                $classroom = DB::table('classrooms')->where('id', $classwork->classroom_id)->first();
                $teacherId = $classroom ? $classroom->creator_id : null;
                $sectionName = $classroom ? $classroom->section : 'Unknown Section';
                $subject = $classroom ? DB::table('subjects')->where('id', $classroom->subject_id)->first() : null;
                $subjectName = $subject ? $subject->description : 'the class';
                $formName = $form->name ?? 'Quiz/Exam';
                $studentLink = $classroom ? "/student/classrooms/{$classroom->id}/stream" : "/student/classrooms";
                $teacherLink = $classroom ? "/teacher/classrooms/{$classroom->id}/stream" : "/teacher/classrooms";
                $currentTime = now()->toDateTimeString();
                $notifications = [];

                $notifications[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $studentId,
                    'description' => "Admin {$adminName} has reset your submission for '{$formName}' in {$subjectName} ({$sectionName}). You can now retake it.",
                    'link' => $studentLink,
                    'is_read' => false,
                    'created_at' => $currentTime,
                    'updated_at' => $currentTime,
                ];

                if ($teacherId) {
                    $notifications[] = [
                        'id' => Str::uuid()->toString(),
                        'user_id' => $teacherId,
                        'description' => "Admin {$adminName} has reset the submission of {$studentName} for '{$formName}' in {$subjectName} ({$sectionName}).",
                        'link' => $teacherLink,
                        'is_read' => false,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime,
                    ];
                }

                if (!empty($notifications)) {
                    DB::table('notifications')->insert($notifications);
                }
            }

            ActivityLog::create([
                'user_id' => $admin->id,
                'action' => 'Reset Student Submission',
                'description' => "Reset the submission of {$studentName} for the form '{$formName}'."
            ]);

            DB::commit();
            return response()->json(['message' => 'Student submission removed permanently.'], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Submission not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AdminFormController unsubmit Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An error occurred while resetting the submission.'], 500);
        }
    }

    // Print Teacher Form
    public function printTeacherForm(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        try {
            $form = Form::with(['creator', 'questions'])->findOrFail($id);
            $admin = $request->user(); 

            ActivityLog::create([
                'user_id' => $admin->id,
                'action' => 'Printed Blank Form',
                'description' => "Generated a print view for the form '{$form->name}'."
            ]);
            
            $html = view('print.teacherform', compact('form', 'admin'))->render();
            return response($html)->header('Content-Type', 'text/html');

        } catch (\Exception $e) {
            Log::error('AdminFormController printTeacherForm Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while generating the print view.'], 500);
        }
    }

    // Print Student Answer
    public function printStudentForm(Request $request, $formId, $submissionId)
    {
        if (!$this->checkAdmin($request)) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        try {
            $form = Form::with(['questions'])->findOrFail($formId);

            $submission = FormSubmission::with(['student', 'answers'])
                                        ->where('id', $submissionId)
                                        ->where('form_id', $formId)
                                        ->firstOrFail();

            $admin = $request->user(); 
            $studentName = $submission->student ? $submission->student->first_name . ' ' . $submission->student->last_name : 'a student';

            ActivityLog::create([
                'user_id' => $admin->id,
                'action' => 'Printed Student Submission',
                'description' => "Generated a print view for {$studentName}'s submission in '{$form->name}'."
            ]);

            $html = view('print.studentform', compact('form', 'submission', 'admin'))->render();
            return response($html)->header('Content-Type', 'text/html');

        } catch (\Exception $e) {
            Log::error('AdminFormController printStudentForm Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while generating the student print view.'], 500);
        }
    }
}