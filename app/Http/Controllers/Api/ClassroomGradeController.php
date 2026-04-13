<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ClassroomGradeController extends Controller
{
    // View Classroom Grade
    public function index($classroomId)
    {
        try {
            // I-check kung nag-eexist ang classroom at kung siya ang teacher
            $classroom = DB::table('classrooms')
                ->where('id', $classroomId)
                ->where('creator_id', Auth::id())
                ->first();

            if (!$classroom) {
                return response()->json(['message' => 'Classroom not found or unauthorized'], 404);
            }

            // Kunin lahat ng Classworks na may grade. EXCLUDE "MATERIAL"
            $classworks = DB::table('classworks')
                ->where('classroom_id', $classroomId)
                ->where('type', '!=', 'material') 
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'asc') 
                ->select('id', 'title', 'type', 'points', 'created_at', 'deadline')
                ->get();

            // Kunin ang mga enrolled students
            $studentIds = DB::table('classroom_student')
                ->where('classroom_id', $classroomId)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->pluck('student_id')
                ->toArray();

            $students = DB::table('users')
                ->whereIn('id', $studentIds)
                ->select('id', 'first_name', 'last_name', 'lrn')
                ->orderBy('last_name', 'asc')
                ->get();

            // Kunin lahat ng submissions nila
            $classworkIds = $classworks->pluck('id')->toArray();
            $submissions = [];
            
            if (!empty($classworkIds) && !empty($studentIds)) {
                $submissionsData = DB::table('classwork_submissions')
                    ->whereIn('classwork_id', $classworkIds)
                    ->whereIn('student_id', $studentIds)
                    ->select('classwork_id', 'student_id', 'status', 'grade', 'teacher_feedback')
                    ->get();
                    
                foreach ($submissionsData as $sub) {
                    $submissions[$sub->student_id][] = (array) $sub;
                }
            }

            // Pagsamahin ang Students at Submissions (Lagyan ng logic for 'missing')
            $studentsData = [];
            $now = now();

            foreach ($students as $student) {
                $studentSubs = $submissions[$student->id] ?? [];
                $processedSubs = [];

                foreach ($classworks as $cw) {
                    $sub = collect($studentSubs)->firstWhere('classwork_id', $cw->id);
                    
                    if ($sub) {
                        $processedSubs[] = $sub;
                    } else {
                        // Kung walang pinasa at lagpas na sa deadline, markahan as 'missing'
                        if ($cw->deadline && $now > $cw->deadline) {
                            $processedSubs[] = [
                                'classwork_id' => $cw->id,
                                'student_id' => $student->id,
                                'status' => 'missing',
                                'grade' => null,
                                'teacher_feedback' => null
                            ];
                        }
                    }
                }

                $studentsData[] = [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'lrn' => $student->lrn,
                    'submissions' => $processedSubs
                ];
            }

            return response()->json([
                'classworks' => $classworks,
                'students' => $studentsData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Backend Error: ' . $e->getMessage() . ' on line ' . $e->getLine()
            ], 500);
        }
    }
}