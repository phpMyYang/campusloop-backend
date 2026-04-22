<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ClassroomStudentController extends Controller
{
    // View Student Classroom
    public function index($classroomId)
    {
        $classroom = Classroom::findOrFail($classroomId);
        // Kukunin ang mga estudyante pati ang pivot status at strand
        $students = $classroom->students()->with('strand')->get();

        return response()->json($students, 200);
    }

    // APPROVE STUDENT REQUEST
    public function approve(Request $request, $classroomId)
    {
        $request->validate(['student_ids' => 'required|array']);
        
        // Kukunin natin ang classroom at i-load ang subject para makuha ang pangalan nito
        $classroom = Classroom::with('subject')->findOrFail($classroomId);

        foreach ($request->student_ids as $studentId) {
            $classroom->students()->updateExistingPivot($studentId, ['status' => 'approved']);
        }

        // NOTIFICATION LOGIC (APPROVED)
        $teacherName = $request->user()->first_name . ' ' . $request->user()->last_name;
        $subjectName = $classroom->subject ? $classroom->subject->description : 'the class';
        $sectionName = $classroom->section;

        $notifications = [];
        $currentTime = now()->toDateTimeString();

        foreach ($request->student_ids as $studentId) {
            $notifications[] = [
                'id' => Str::uuid()->toString(),
                'user_id' => $studentId,
                'description' => "Teacher {$teacherName} approved your request to join {$subjectName} ({$sectionName}).",
                'link' => "/student/classrooms/{$classroomId}", // Diretso sa loob ng class
                'is_read' => false,
                'created_at' => $currentTime,
                'updated_at' => $currentTime,
            ];
        }

        if (!empty($notifications)) {
            foreach (array_chunk($notifications, 500) as $chunk) {
                DB::table('notifications')->insert($chunk);
            }
        }

        $count = count($request->student_ids);

        // ACTIVITY LOG
        if ($count > 0) {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Approved Classroom Students',
                'description' => "Approved {$count} student(s) to join the classroom {$subjectName} ({$sectionName})."
            ]);
        }

        return response()->json(['message' => 'Students successfully enrolled.'], 200);
    }

    // REMOVE OR DECLINE STUDENT
    public function remove(Request $request, $classroomId)
    {
        $request->validate(['student_ids' => 'required|array']);
        $classroom = Classroom::with('subject')->findOrFail($classroomId);

        // KUNIN MUNA ANG MGA ESTUDYANTE BAGO I-DETACH (Para ma-check kung ano ang current status)
        $studentsToNotify = $classroom->students()->whereIn('student_id', $request->student_ids)->get();

        $classroom->students()->detach($request->student_ids);

        // NOTIFICATION LOGIC (DECLINED OR UNENROLLED)
        $teacherName = $request->user()->first_name . ' ' . $request->user()->last_name;
        $subjectName = $classroom->subject ? $classroom->subject->description : 'the class';
        $sectionName = $classroom->section;

        $notifications = [];
        $currentTime = now()->toDateTimeString();

        foreach ($studentsToNotify as $student) {
            // I-check ang status nila bago natin sila burahin kanina
            $previousStatus = $student->pivot->status;

            // SMART DESCRIPTION:
            if ($previousStatus === 'approved') {
                $description = "Teacher {$teacherName} unenrolled you from {$subjectName} ({$sectionName}).";
            } else {
                $description = "Teacher {$teacherName} declined your request to join {$subjectName} ({$sectionName}).";
            }

            $notifications[] = [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'user_id' => $student->id,
                'description' => $description,
                'link' => "/student/classrooms", 
                'is_read' => false,
                'created_at' => $currentTime,
                'updated_at' => $currentTime,
            ];
        }

        if (!empty($notifications)) {
            foreach (array_chunk($notifications, 500) as $chunk) {
                DB::table('notifications')->insert($chunk);
            }
        }

        $count = count($request->student_ids);

        // ACTIVITY LOG
        if ($count > 0) {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Removed/Declined Classroom Students',
                'description' => "Removed or declined {$count} student(s) from the classroom {$subjectName} ({$sectionName})."
            ]);
        }

        return response()->json(['message' => 'Students successfully removed from class.'], 200);
    }
}