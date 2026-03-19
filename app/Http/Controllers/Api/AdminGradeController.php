<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\FinalGrade;

class AdminGradeController extends Controller
{
    // KUNIN LAHAT NG STUDENTS NA MAY GRADES RECORD
    public function index(Request $request)
    {
        try {
            // Kunin lang ang mga students (role='student')
            // Mas maganda kung may with('strand') para makuha ang strand name
            $students = User::where('role', 'student')
                ->where('status', 'active')
                ->with('strand')
                ->get();

            // Pwede nating lagyan ng extra indicator kung may "pending" grades sila
            foreach ($students as $student) {
                $student->has_pending_grades = FinalGrade::where('student_id', $student->id)
                                                ->where('status', 'pending')
                                                ->exists();
                // Bilangin din ilang grades meron sila overall
                $student->grades_count = FinalGrade::where('student_id', $student->id)->count();
            }

            // I-filter out yung mga walang grades para malinis ang listahan (Optional)
            $studentsWithGrades = $students->filter(function($student) {
                return $student->grades_count > 0;
            })->values();

            return response()->json($studentsWithGrades, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Backend Error: ' . $e->getMessage()], 500);
        }
    }

    // KUNIN ANG SPECIFIC GRADES NG ISANG STUDENT (For the Modal Table)
    public function showStudentGrades($studentId)
    {
        try {
            $grades = DB::table('final_grades')
                ->join('subjects', 'final_grades.subject_id', '=', 'subjects.id')
                ->join('users', 'final_grades.teacher_id', '=', 'users.id') // JOIN PARA SA TEACHER
                ->where('final_grades.student_id', $studentId)
                ->whereNull('final_grades.deleted_at')
                ->select(
                    'final_grades.*', 
                    'subjects.code as subject_code', 
                    'subjects.description as subject_description',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) as teacher_name") // KUNIN ANG PANGALAN
                )
                ->orderBy('final_grades.school_year', 'desc')
                ->orderBy('final_grades.semester', 'asc')
                ->get();

            return response()->json($grades, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Backend Error: ' . $e->getMessage()], 500);
        }
    }

    public function approveGrade(Request $request)
    {
        try {
            $request->validate(['grade_id' => 'required|uuid']);
            
            DB::table('final_grades')->where('id', $request->grade_id)->update([
                'status' => 'approved',
                'admin_feedback' => null, // Linisin ang feedback
                'updated_at' => now()
            ]);

            return response()->json(['message' => 'Grade approved and locked.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Backend Error: ' . $e->getMessage()], 500);
        }
    }

    // DECLINE GRADE WITH FEEDBACK
    public function declineGrade(Request $request)
    {
        try {
            $request->validate([
                'grade_id' => 'required|uuid',
                'feedback' => 'required|string'
            ]);
            
            DB::table('final_grades')->where('id', $request->grade_id)->update([
                'status' => 'declined',
                'admin_feedback' => $request->feedback,
                'updated_at' => now()
            ]);

            return response()->json(['message' => 'Grade declined and returned to teacher.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Backend Error: ' . $e->getMessage()], 500);
        }
    }
}