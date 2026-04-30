<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Str;

class AdminClassroomStudentController extends Controller
{
    // SECURITY FEATURE
    private function checkAdmin(Request $request)
    {
        return $request->user() && $request->user()->role === 'admin';
    }

    // Kunin lahat ng students na nasa classroom (Pending at Approved)
    public function index(Request $request, $classroomId)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        // Kunin ang classroom at i-verify na nag-e-exist
        $classroom = Classroom::findOrFail($classroomId);

        // Mag-build ng query sa users table na naka-join sa classroom_student pivot
        $query = $classroom->students()->with('strand');

        // SERVER-SIDE SEARCH
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('users.first_name', 'LIKE', "%{$search}%")
                  ->orWhere('users.last_name', 'LIKE', "%{$search}%")
                  ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'LIKE', "%{$search}%")
                  ->orWhere('users.email', 'LIKE', "%{$search}%")
                  ->orWhere('users.lrn', 'LIKE', "%{$search}%");
            });
        }

        // SERVER-SIDE GENDER FILTER
        if ($request->has('gender') && $request->gender !== 'all') {
            $query->where('users.gender', $request->gender);
        }

        // SERVER-SIDE STATUS FILTER (Mula sa pivot table)
        if ($request->has('status') && $request->status !== 'all') {
            $query->wherePivot('status', $request->status);
        }

        // PAGINATION
        $entries = $request->has('entries') ? (int) $request->entries : 10;
        
        // Arrange alphabetically para malinis
        $paginatedStudents = $query->orderBy('users.last_name', 'asc')->paginate($entries);

        return response()->json([
            'data' => $paginatedStudents->items(),
            'total' => $paginatedStudents->total(),
            'last_page' => $paginatedStudents->lastPage()
        ], 200);
    }

    // I-approve ang mga pending students
    public function approve(Request $request, $classroomId)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        $request->validate([
            'student_ids' => 'required|array|max:100',
            'student_ids.*' => 'exists:users,id'
        ]);

        DB::table('classroom_student')
            ->where('classroom_id', $classroomId)
            ->whereIn('student_id', $request->student_ids)
            ->update(['status' => 'approved', 'updated_at' => now()]);

        // NOTIFICATION LOGIC (APPROVED)
        $admin = $request->user();
        $adminName = $admin->first_name . ' ' . $admin->last_name;

        // Kunin ang classroom kasama ang subject para sa description
        $classroom = Classroom::with('subject')->findOrFail($classroomId);
        $subjectName = $classroom->subject ? $classroom->subject->description : 'the class';
        $sectionName = $classroom->section;
        $teacherId = $classroom->creator_id; // Kukunin ang ID ni Teacher para ma-notify din siya

        // Kunin ang mga details ng students na in-approve
        $students = User::whereIn('id', $request->student_ids)->get();

        $notifications = [];
        $currentTime = now()->toDateTimeString();

        foreach ($students as $student) {
            $studentName = $student->first_name . ' ' . $student->last_name;

            // NOTIFICATION PARA KAY STUDENT
            $notifications[] = [
                'id' => Str::uuid()->toString(),
                'user_id' => $student->id,
                'description' => "Admin {$adminName} approved your request to join {$subjectName} ({$sectionName}).",
                'link' => "/student/classrooms/{$classroomId}/stream", 
                'is_read' => false,
                'created_at' => $currentTime,
                'updated_at' => $currentTime,
            ];

            // NOTIFICATION PARA KAY TEACHER
            $notifications[] = [
                'id' => Str::uuid()->toString(),
                'user_id' => $teacherId,
                'description' => "Admin {$adminName} approved {$studentName}'s request to join {$subjectName} ({$sectionName}).",
                'link' => "/teacher/classrooms/{$classroomId}/people", 
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

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Approved Classroom Students',
            'description' => "Approved {$count} student(s) to join the classroom {$subjectName} ({$sectionName})."
        ]);

        return response()->json(['message' => 'Students approved successfully.'], 200);
    }

    // I-remove o i-decline ang mga students
    public function remove(Request $request, $classroomId)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        $request->validate([
            'student_ids' => 'required|array|max:100',
            'student_ids.*' => 'exists:users,id'
        ]);

        $classroom = Classroom::with('subject')->findOrFail($classroomId);
        $studentsToNotify = $classroom->students()->whereIn('student_id', $request->student_ids)->get();

        DB::table('classroom_student')
            ->where('classroom_id', $classroomId)
            ->whereIn('student_id', $request->student_ids)
            ->delete();

        // NOTIFICATION LOGIC (REMOVED / DECLINED)
        $admin = $request->user();
        $adminName = $admin->first_name . ' ' . $admin->last_name;

        $subjectName = $classroom->subject ? $classroom->subject->description : 'the class';
        $sectionName = $classroom->section;
        $teacherId = $classroom->creator_id;

        $notifications = [];
        $currentTime = now()->toDateTimeString();

        foreach ($studentsToNotify as $student) {
            $studentName = $student->first_name . ' ' . $student->last_name;
            $previousStatus = $student->pivot->status; // I-check kung dati ba siyang enrolled o pending

            // SMART DESCRIPTION DEPENDE SA STATUS
            if ($previousStatus === 'approved') {
                $studentDesc = "Admin {$adminName} unenrolled you from {$subjectName} ({$sectionName}).";
                $teacherDesc = "Admin {$adminName} unenrolled {$studentName} from {$subjectName} ({$sectionName}).";
            } else {
                $studentDesc = "Admin {$adminName} declined your request to join {$subjectName} ({$sectionName}).";
                $teacherDesc = "Admin {$adminName} declined {$studentName}'s request to join {$subjectName} ({$sectionName}).";
            }

            // NOTIFICATION PARA KAY STUDENT
            $notifications[] = [
                'id' => Str::uuid()->toString(),
                'user_id' => $student->id,
                'description' => $studentDesc,
                'link' => "/student/classrooms", // Pabalik lang sa listahan dahil wala na siya sa class
                'is_read' => false,
                'created_at' => $currentTime,
                'updated_at' => $currentTime,
            ];

            // NOTIFICATION PARA KAY TEACHER
            $notifications[] = [
                'id' => Str::uuid()->toString(),
                'user_id' => $teacherId,
                'description' => $teacherDesc,
                'link' => "/teacher/classrooms/{$classroomId}/people", 
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

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Removed/Declined Classroom Students',
            'description' => "Removed or declined {$count} student(s) from the classroom {$subjectName} ({$sectionName})."
        ]);

        return response()->json(['message' => 'Students removed successfully.'], 200);
    }
}