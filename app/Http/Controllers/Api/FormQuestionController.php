<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class FormQuestionController extends Controller
{
    // Create Form Questions
    public function store(Request $request, $formId)
    {
        try {
            // Verify kung sa teacher ang form
            $form = Form::where('creator_id', $request->user()->id)->findOrFail($formId);

            $validated = $request->validate([
                'section' => 'nullable|string|max:255',
                'instruction' => 'nullable|string',
                'text' => 'required|string',
                'type' => 'required|in:multiple_choice,short_answer',
                'choices' => 'nullable|array',
                'correct_answer' => 'required|string',
                'points' => 'required|integer|min:1',
            ]);

            $question = $form->questions()->create($validated);

            // ACTIVITY LOG 
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Added Form Question',
                'description' => "Added a new question to the form '{$form->name}'."
            ]);

            return response()->json(['message' => 'Question added successfully!', 'question' => $question], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    // Update Form Questions
    public function update(Request $request, $id)
    {
        try {
            // Verify kung sa teacher ang question via form relationship
            $question = FormQuestion::whereHas('form', function ($query) use ($request) {
                $query->where('creator_id', $request->user()->id);
            })->findOrFail($id);

            $validated = $request->validate([
                'section' => 'nullable|string|max:255',
                'instruction' => 'nullable|string',
                'text' => 'required|string',
                'type' => 'required|in:multiple_choice,short_answer',
                'choices' => 'nullable|array',
                'correct_answer' => 'required|string',
                'points' => 'required|integer|min:1',
            ]);

            $question->update($validated);
            $formName = $question->form ? $question->form->name : 'Quiz/Exam';

            // ACTIVITY LOG
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Updated Form Question',
                'description' => "Updated a question inside the form '{$formName}'."
            ]);

            return response()->json(['message' => 'Question updated successfully!'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    // Delete Form Question
    public function destroy(Request $request, $id)
    {
        // Isinama ang 'form' para makuha ang pangalan bago burahin
        $question = FormQuestion::with('form')->whereHas('form', function ($query) use ($request) {
            $query->where('creator_id', $request->user()->id);
        })->findOrFail($id);

        $formName = $question->form ? $question->form->name : 'Quiz/Exam';
        
        $question->delete();

        // ACTIVITY LOG
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Deleted Form Question',
            'description' => "Deleted a question from the form '{$formName}'."
        ]);
        
        return response()->json(['message' => 'Question deleted.'], 200);
    }
}