<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormQuestion;
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

            return response()->json(['message' => 'Question updated successfully!'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    // Delete Form Question
    public function destroy(Request $request, $id)
    {
        $question = FormQuestion::whereHas('form', function ($query) use ($request) {
            $query->where('creator_id', $request->user()->id);
        })->findOrFail($id);
        
        $question->delete();
        
        return response()->json(['message' => 'Question deleted.'], 200);
    }
}