<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FinalGrade;
use App\Models\SystemSetting;

class StudentGradeController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Kunin ang active system setting para malaman ang current SY at Semester
            $activeSetting = SystemSetting::where('is_active', true)->first();

            // Kukunin natin ang records sa FinalGrade table na APPROVED/LOCKED na ni Admin
            $grades = FinalGrade::with(['subject'])
                ->where('student_id', $request->user()->id)
                ->whereIn('status', ['locked', 'approved']) 
                ->get()
                ->map(function ($finalGrade) {
                    return [
                        'id' => $finalGrade->id,
                        'grade' => $finalGrade->grade,
                        'school_year' => $finalGrade->school_year ?? 'N/A',
                        'semester' => $finalGrade->semester ?? 'N/A',
                        'subject_code' => $finalGrade->subject->code ?? 'N/A',
                        'subject_description' => $finalGrade->subject->description ?? 'N/A',
                        'date_locked' => $finalGrade->updated_at,
                    ];
                });

            // I-sort by School Year (descending) at Semester
            $sortedGrades = collect($grades)->sortByDesc('school_year')->values()->all();

            // Ibalik ang settings kasama ang grades
            return response()->json([
                'active_setting' => $activeSetting,
                'grades' => $sortedGrades
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch grades: ' . $e->getMessage()], 500);
        }
    }
}