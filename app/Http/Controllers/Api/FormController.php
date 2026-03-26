<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormController extends Controller
{
    public function index(Request $request)
    {
        $forms = Form::with('creator')
            ->where('creator_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($forms, 200);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'instruction' => 'required|string',
                'timer' => 'nullable|numeric|min:0',
                'is_shuffle_questions' => 'boolean',
                'is_focus_mode' => 'boolean',
            ]);
            $validated['timer'] = $validated['timer'] ?? 0;
            $validated['is_shuffle_questions'] = filter_var($validated['is_shuffle_questions'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $validated['is_focus_mode'] = filter_var($validated['is_focus_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $form = Form::create(array_merge($validated, ['creator_id' => $request->user()->id]));

            return response()->json(['message' => 'Form setup saved! Redirecting to builder...', 'form' => $form], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $form = Form::where('creator_id', $request->user()->id)->findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'instruction' => 'required|string',
                'timer' => 'nullable|numeric|min:0',
                'is_shuffle_questions' => 'boolean',
                'is_focus_mode' => 'boolean',
            ]);
            $validated['timer'] = $validated['timer'] ?? 0;
            $validated['is_shuffle_questions'] = filter_var($validated['is_shuffle_questions'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $validated['is_focus_mode'] = filter_var($validated['is_focus_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $form->update($validated);
            
            return response()->json(['message' => 'Form details updated successfully!'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $form = Form::where('creator_id', $request->user()->id)->findOrFail($id);
        $form->delete();
        return response()->json(['message' => 'Form moved to recycle bin.'], 200);
    }

    public function duplicate(Request $request, $id)
    {
        // Kasama na ang questions na ife-fetch para maduplicate
        $original = Form::with('questions')->where('creator_id', $request->user()->id)->findOrFail($id);
        
        $baseName = trim(preg_replace('/\(\d+\)$/', '', $original->name));
        $count = Form::where('creator_id', $request->user()->id)
            ->where(function ($query) use ($baseName) {
                $query->where('name', $baseName)
                      ->orWhere('name', 'LIKE', $baseName . ' (%)');
            })->count();

        // I-duplicate ang Form details
        $newForm = $original->replicate();
        $newForm->name = $baseName . ' (' . ($count > 0 ? $count : 1) . ')';
        $newForm->duplicate_from_id = $original->id;
        $newForm->save();

        // I-duplicate lahat ng questions papunta sa bagong form (Walang respondents)
        foreach ($original->questions as $question) {
            $newQuestion = $question->replicate();
            $newQuestion->form_id = $newForm->id; // I-assign sa bagong Form ID
            $newQuestion->save();
        }

        return response()->json(['message' => 'Form duplicated successfully!', 'form' => $newForm], 201);
    }

    public function show(Request $request, $id)
    {
        $form = Form::with(['questions' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }])->where('creator_id', $request->user()->id)->findOrFail($id);

        return response()->json($form, 200);
    }

    public function respondents(Request $request, $id)
    {
        Form::where('creator_id', $request->user()->id)->findOrFail($id);

        $submissions = \App\Models\FormSubmission::with(['student.strand'])
            ->where('form_id', $id)
            ->orderBy('submitted_at', 'desc')
            ->get();

        // Kunin lahat ng isinagot ng student at i-attach sa submission data
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
}