<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminFormController extends Controller
{
    public function index()
    {
        $forms = Form::with('creator')->orderBy('created_at', 'desc')->get();
        return response()->json($forms, 200);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        Form::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Forms moved to recycle bin.'], 200);
    }

    public function show($id)
    {
        $form = Form::with(['questions' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }])->findOrFail($id);

        return response()->json($form, 200);
    }

    public function respondents($id)
    {
        $submissions = \App\Models\FormSubmission::with(['student.strand'])
            ->where('form_id', $id)
            ->orderBy('submitted_at', 'desc')
            ->get();

        $submissionIds = $submissions->pluck('id');
        $answers = DB::table('form_submission_answers')
            ->whereIn('submission_id', $submissionIds)
            ->get()
            ->groupBy('submission_id');

        foreach ($submissions as $submission) {
            $submission->answers = isset($answers[$submission->id]) ? $answers[$submission->id] : [];
        }

        return response()->json($submissions, 200);
    }

    // UNSUBMIT (ADMIN CONTROL)
    public function unsubmit(Request $request, $formId, $submissionId) 
    {
        try {
            $submission = \App\Models\FormSubmission::findOrFail($submissionId);
            $studentId = $submission->student_id;
            
            // Kunin ang student user para makuha natin ang pangalan niya para sa notification ni teacher
            $student = \App\Models\User::find($studentId);

            // KUNIN ANG DETAILS BAGO BURAHIN PARA SA NOTIF
            $form = DB::table('forms')->where('id', $formId)->first();
            $classwork = DB::table('classworks')->where('form_id', $formId)->first();

            // Alisin ang sagot sa form_submission_answers (Hard delete via DB query)
            DB::table('form_submission_answers')->where('submission_id', $submissionId)->delete();

            // Alisin ang mismong submission record NANG PERMANENTE (Hard Delete)
            $submission->forceDelete();

            // Hanapin ang classwork na nakakabit sa form na ito at alisin din ang classwork_submission record niya
            if ($classwork) {
                DB::table('classwork_submissions')
                    ->where('classwork_id', $classwork->id)
                    ->where('student_id', $studentId)
                    ->delete(); // Hard delete via DB query
            }

            // NOTIFICATION LOGIC FOR TEACHER & STUDENT
            if ($form && $classwork) {
                $admin = $request->user();
                $adminName = $admin->first_name . ' ' . $admin->last_name;
                $studentName = $student ? $student->first_name . ' ' . $student->last_name : 'a student';

                // Kunin ang details ng classroom para makuha ang Teacher ID, Subject, at Section
                $classroom = DB::table('classrooms')->where('id', $classwork->classroom_id)->first();
                
                $teacherId = $classroom ? $classroom->creator_id : null;
                $sectionName = $classroom ? $classroom->section : 'Unknown Section';
                
                $subject = $classroom ? DB::table('subjects')->where('id', $classroom->subject_id)->first() : null;
                $subjectName = $subject ? $subject->description : 'the class';
                
                $formName = $form->name ?? 'Quiz/Exam';

                // I-setup ang magkaibang link para sa magkaibang dashboard
                $studentLink = $classroom ? "/student/classrooms/{$classroom->id}/stream" : "/student/classrooms";
                $teacherLink = $classroom ? "/teacher/classrooms/{$classroom->id}/stream" : "/teacher/classrooms";

                $currentTime = now()->toDateTimeString();
                $notifications = [];

                // NOTIFICATION PARA KAY STUDENT
                $notifications[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $studentId,
                    'description' => "Admin {$adminName} has reset your submission for '{$formName}' in {$subjectName} ({$sectionName}). You can now retake it.",
                    'link' => $studentLink,
                    'is_read' => false,
                    'created_at' => $currentTime,
                    'updated_at' => $currentTime,
                ];

                // NOTIFICATION PARA KAY TEACHER (Creator ng class)
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

                // Isahang Insert para mabilis!
                if (!empty($notifications)) {
                    DB::table('notifications')->insert($notifications);
                }
            }

            return response()->json(['message' => 'Student submission removed permanently and notified both teacher and student.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to unsubmit: ' . $e->getMessage()], 500);
        }
    }

    // PRINT RENDERERS 
    public function printTeacherForm(Request $request, $id)
    {
        $form = Form::with(['creator', 'questions'])->findOrFail($id);
        $admin = $request->user(); 
        
        $html = view('print.teacherform', compact('form', 'admin'))->render();
        return response($html)->header('Content-Type', 'text/html');
    }

    public function printStudentForm(Request $request, $formId, $submissionId)
    {
        $form = Form::with(['questions'])->findOrFail($formId);
        $submission = \App\Models\FormSubmission::with(['student', 'answers'])->findOrFail($submissionId);
        $admin = $request->user(); 

        $html = view('print.studentform', compact('form', 'submission', 'admin'))->render();
        return response($html)->header('Content-Type', 'text/html');
    }
}