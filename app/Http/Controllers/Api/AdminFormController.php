<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    // UNSUBMIT
    public function unsubmit($formId, $submissionId)
    {
        try {
            $submission = \App\Models\FormSubmission::findOrFail($submissionId);
            $studentId = $submission->student_id;

            // Alisin ang sagot sa form_submission_answers (Hard delete via DB query)
            DB::table('form_submission_answers')->where('submission_id', $submissionId)->delete();

            // Alisin ang mismong submission record NANG PERMANENTE (Hard Delete)
            $submission->forceDelete();

            // Hanapin ang classwork na nakakabit sa form na ito at alisin din ang classwork_submission record niya
            $classwork = DB::table('classworks')->where('form_id', $formId)->first();
            if ($classwork) {
                DB::table('classwork_submissions')
                    ->where('classwork_id', $classwork->id)
                    ->where('student_id', $studentId)
                    ->delete(); // Hard delete via DB query
            }

            return response()->json(['message' => 'Student submission removed permanently.'], 200);
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