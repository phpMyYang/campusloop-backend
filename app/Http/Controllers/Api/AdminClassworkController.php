<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classwork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminClassworkController extends Controller
{
    // Kukunin lahat ng Classworks sa loob ng isang Classroom
    public function index($classroomId)
    {
        $classworks = Classwork::with([
            'files',
            'form',
            'comments' => function ($query) {
                $query->whereNull('parent_id')
                      ->with(['user', 'replies.user'])
                      ->orderBy('created_at', 'asc');
            }
        ])
        ->where('classroom_id', $classroomId)
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json($classworks, 200);
    }

    // Bulk Delete para sa Classworks
    public function destroyBulk(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:classworks,id'
        ]);

        Classwork::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => 'Selected classworks moved to recycle bin.'], 200);
    }

    // FETCH RESPONDENTS (BULLETPROOF RAW DB QUERY)
    public function submissions($id)
    {
        try {
            // Hanapin ang classwork
            $classwork = DB::table('classworks')->where('id', $id)->first();
            if (!$classwork) {
                return response()->json(['message' => 'Classwork not found'], 404);
            }

            // Kunin ang mga approved student IDs sa pivot table
            $studentIds = DB::table('classroom_student')
                ->where('classroom_id', $classwork->classroom_id)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->pluck('student_id')
                ->toArray();

            if (empty($studentIds)) {
                return response()->json([], 200); // Kung walang students, ibalik as empty array
            }

            // Kunin ang impormasyon ng mga estudyante
            $students = DB::table('users')
                ->whereIn('id', $studentIds)
                ->select('id', 'first_name', 'last_name', 'lrn')
                ->orderBy('last_name', 'asc') // Arrange alphabetically by default
                ->get();

            // Kunin ang mga submissions nila
            $submissions = DB::table('classwork_submissions')
                ->where('classwork_id', $id)
                ->whereIn('student_id', $studentIds)
                ->get()
                ->keyBy('student_id');

            // Kunin ang mga files ng mga submissions (TINAMA YUNG 'attachable_type' DITO)
            $submissionIds = $submissions->pluck('id')->toArray();
            $files = [];
            if (!empty($submissionIds)) {
                $filesData = DB::table('files')
                    // SALUHIN ANG KAHIT ANONG FORMAT NG PAGKA-SAVE SA DATABASE
                    ->whereIn('attachable_type', ['classwork_submission', 'App\\Models\\ClassworkSubmission']) 
                    ->whereIn('attachable_id', $submissionIds)
                    ->whereNull('deleted_at')
                    ->get();
                    
                foreach ($filesData as $file) {
                    $files[$file->attachable_id][] = (array) $file;
                }
            }

            // Pagsamahin nang manual
            $respondents = [];
            foreach ($students as $student) {
                $sub = $submissions->has($student->id) ? (array) $submissions->get($student->id) : null;
                
                if ($sub) {
                    $sub['files'] = isset($files[$sub['id']]) ? $files[$sub['id']] : [];
                }

                $respondents[] = [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'lrn' => $student->lrn,
                    'submission' => $sub,
                ];
            }

            return response()->json($respondents, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Backend PHP Error: ' . $e->getMessage() . ' on line ' . $e->getLine()
            ], 500);
        }
    }
}