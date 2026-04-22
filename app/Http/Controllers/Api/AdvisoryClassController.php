<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdvisoryClass;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AdvisoryClassController extends Controller
{
    public function index(Request $request)
    {
        try {
            $classes = AdvisoryClass::where('teacher_id', $request->user()->id)->orderBy('created_at', 'desc')->get();
            foreach($classes as $class) {
                $class->students_count = DB::table('advisory_student')->where('advisory_class_id', $class->id)->count();
            }
            return response()->json($classes, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // Create Advisory Class
    public function store(Request $request)
    {
        $request->validate([
            'section' => 'required|string|max:255',
            'school_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'capacity' => 'required|integer|min:1',
        ]);

        $advisory = AdvisoryClass::create([
            'teacher_id' => $request->user()->id,
            'section' => $request->section,
            'school_year' => $request->school_year,
            'capacity' => $request->capacity,
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Created Advisory Class',
            'description' => "Created a new advisory class: {$request->section} for SY {$request->school_year}."
        ]);

        return response()->json(['message' => 'Created successfully.', 'advisory' => $advisory], 201);
    }

    // Update Advisory Class
    public function update(Request $request, $id)
    {
        $request->validate([
            'section' => 'required|string|max:255',
            'school_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'capacity' => 'required|integer|min:1',
        ]);

        $advisory = AdvisoryClass::where('teacher_id', $request->user()->id)->findOrFail($id);
        $advisory->update($request->only('section', 'school_year', 'capacity'));

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Updated Advisory Class',
            'description' => "Updated the details of the advisory class: {$request->section}."
        ]);

        return response()->json(['message' => 'Updated successfully.', 'advisory' => $advisory], 200);
    }

    public function destroy(Request $request, $id)
    {
        $advisory = AdvisoryClass::where('teacher_id', $request->user()->id)->findOrFail($id);
        $section = $advisory->section;

        $advisory->delete();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Deleted Advisory Class',
            'description' => "Moved the advisory class {$section} to the recycle bin."
        ]);
        return response()->json(['message' => 'Moved to recycle bin.'], 200);
    }

    // View Advisory Class
    public function show(Request $request, $id)
    {
        try {
            $advisory = AdvisoryClass::where('teacher_id', $request->user()->id)->findOrFail($id);
            $enrolledIds = DB::table('advisory_student')->where('advisory_class_id', $id)->pluck('student_id');
            $students = User::whereIn('id', $enrolledIds)->with('strand')->get();

            return response()->json(['advisory' => $advisory, 'students' => $students], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // Available Student
    public function getAvailableStudents($id)
    {
        try {
            $enrolledIds = DB::table('advisory_student')->where('advisory_class_id', $id)->pluck('student_id');
            $available = User::where('role', 'student')->where('status', 'active')
                            ->whereNotIn('id', $enrolledIds)->with('strand')->get();
            return response()->json($available, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // Enroll Student in Advisory Class
    public function addStudents(Request $request, $id)
    {
        try {
            $request->validate(['student_ids' => 'required|array', 'student_ids.*' => 'exists:users,id']);
            
            $advisory = AdvisoryClass::where('teacher_id', $request->user()->id)->findOrFail($id);
            
            $currentCount = DB::table('advisory_student')->where('advisory_class_id', $id)->count();
            $incomingCount = count($request->student_ids);
            
            if (($currentCount + $incomingCount) > $advisory->capacity) {
                $available = $advisory->capacity - $currentCount;
                return response()->json([
                    'message' => "Cannot add students. Only {$available} slot(s) remaining."
                ], 422); 
            }

            $inserts = [];
            foreach ($request->student_ids as $studentId) {
                $exists = DB::table('advisory_student')->where('advisory_class_id', $id)->where('student_id', $studentId)->exists();
                if (!$exists) {
                    $inserts[] = [
                        'id' => Str::uuid()->toString(),
                        'advisory_class_id' => $id,
                        'student_id' => $studentId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
            if(count($inserts) > 0) {
                DB::table('advisory_student')->insert($inserts);

                $enrolledCount = count($inserts);

                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'Enrolled Advisory Students',
                    'description' => "Enrolled {$enrolledCount} student(s) to the advisory class {$advisory->section}."
                ]);
            }
            return response()->json(['message' => 'Students added.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Backend Error: ' . $e->getMessage()], 500);
        }
    }

    public function removeStudent(Request $request, $classId, $studentId)
    {
        $advisory = AdvisoryClass::findOrFail($classId);
        $student = User::find($studentId);
        $studentName = $student ? ($student->first_name . ' ' . $student->last_name) : 'a student';

        DB::table('advisory_student')->where('advisory_class_id', $classId)->where('student_id', $studentId)->delete();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Removed Advisory Student',
            'description' => "Removed {$studentName} from the advisory class {$advisory->section}."
        ]);

        return response()->json(['message' => 'Student removed.'], 200);
    }

    // View Student Grade
    public function getStudentGrades($classId, $studentId)
    {
        try {
            $advisory = DB::table('advisory_classes')->where('id', $classId)->first();

            $grades = DB::table('final_grades')
                ->join('subjects', 'final_grades.subject_id', '=', 'subjects.id')
                ->where('final_grades.student_id', $studentId)
                ->where('final_grades.school_year', $advisory->school_year)
                ->whereNull('final_grades.deleted_at')
                ->select(
                    'final_grades.*', 
                    'subjects.code as subject_code', 
                    'subjects.description as subject_description' // Dito natin kinuha ang description imbes na name
                )
                ->orderBy('final_grades.created_at', 'desc')
                ->get();
                
            return response()->json($grades, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Backend Error: ' . $e->getMessage()], 500);
        }
    }

    // Input Student Grade
    public function storeStudentGrade(Request $request, $classId, $studentId)
    {
        try {
            $request->validate([
                'subject_id' => 'required',
                'semester' => 'required',
                'grade' => ['required', 'numeric', 'between:1,100', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            ]);

            $advisory = DB::table('advisory_classes')->where('id', $classId)->first();
            $gradeId = Str::uuid()->toString();

            DB::table('final_grades')->insert([
                'id' => $gradeId,
                'student_id' => $studentId,
                'teacher_id' => $request->user()->id,
                'subject_id' => $request->subject_id,
                'school_year' => $advisory->school_year,
                'semester' => $request->semester,
                'grade' => $request->grade,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // KUNIN ANG DETAILS NG TEACHER, STUDENT AT SECTION
            $currentUser = $request->user();
            $teacherName = $currentUser->first_name . ' ' . $currentUser->last_name;
            $student = DB::table('users')->where('id', $studentId)->first();
            $studentName = $student ? ($student->first_name . ' ' . $student->last_name) : 'a student';
            $section = $advisory->section;

            // KUNIN ANG SUBJECT PARA SA LOG
            $subject = DB::table('subjects')->where('id', $request->subject_id)->first();
            $subjectName = $subject ? $subject->description : 'a subject';

            // NOTIFY ADMINS WITH DYNAMIC DESCRIPTION
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                DB::table('notifications')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $admin->id,
                    'description' => "Teacher: {$teacherName} submitted a grade for {$studentName} ({$section}).",
                    'link' => "/admin/student-grades",
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Encoded Final Grade',
                'description' => "Submitted a final grade for {$studentName} in {$subjectName} ({$section}) for admin approval."
            ]);

            $newGrade = DB::table('final_grades')->where('id', $gradeId)->first();
            return response()->json(['message' => 'Grade encoded.', 'grade' => $newGrade], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Backend Error: ' . $e->getMessage()], 500);
        }
    }

    // Update Student Grade
    public function updateStudentGrade(Request $request, $classId, $studentId, $gradeId)
    {
        try {
            $request->validate([
                'subject_id' => 'required',
                'semester' => 'required',
                'grade' => ['required', 'numeric', 'between:1,100', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            ]);

            DB::table('final_grades')
                ->where('id', $gradeId)
                ->where('student_id', $studentId)
                ->whereNull('deleted_at')
                ->update([
                'subject_id' => $request->subject_id,
                'semester' => $request->semester,
                'grade' => $request->grade,
                'status' => 'pending',
                'updated_at' => now()
            ]);

            // KUNIN ANG DETAILS PARA SA LOG
            $advisory = DB::table('advisory_classes')->where('id', $classId)->first();
            $student = DB::table('users')->where('id', $studentId)->first();
            $studentName = $student ? ($student->first_name . ' ' . $student->last_name) : 'a student';
            $subject = DB::table('subjects')->where('id', $request->subject_id)->first();
            $subjectName = $subject ? $subject->description : 'a subject';
            $section = $advisory ? $advisory->section : 'the class';
            
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Updated Final Grade',
                'description' => "Updated the pending final grade of {$studentName} in {$subjectName} ({$section})."
            ]);

            return response()->json(['message' => 'Grade updated.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Backend Error: ' . $e->getMessage()], 500);
        }
    }
}