<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Classroom;
use App\Models\Classwork;
use App\Models\File;
use App\Models\Form;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Specific filters para sa mga charts na may Year dropdown
            $strandYear = $request->query('strand_year');
            $statusYear = $request->query('status_year');

            // STATS CARDS (Active & Not Deleted Only)
            $stats = [
                'total_students' => User::where('role', 'student')->where('status', 'active')->whereNull('deleted_at')->count(),
                'total_teachers' => User::where('role', 'teacher')->where('status', 'active')->whereNull('deleted_at')->count(),
                'active_classrooms' => Classroom::whereNull('deleted_at')->count(),
                'files_uploaded' => File::whereNull('deleted_at')->count(),
            ];

            // BAR CHART: Students per Strand (With Year Filter)
            $strandQuery = DB::table('users')
                ->where('users.role', 'student')
                ->whereNull('users.deleted_at')
                ->join('strands', 'users.strand_id', '=', 'strands.id');
            
            if ($strandYear) {
                $strandQuery->whereYear('users.created_at', $strandYear);
            }
            $studentsPerStrand = $strandQuery->selectRaw('strands.name, count(users.id) as value')->groupBy('strands.name')->get();

            // DOUGHNUT CHART: Active vs Inactive Users (With Year Filter)
            $statusQuery = DB::table('users')->whereNull('deleted_at');
            if ($statusYear) {
                $statusQuery->whereYear('created_at', $statusYear);
            }
            $userStatus = $statusQuery->selectRaw('status, count(id) as value')->groupBy('status')->get();

            // RANKING TABLE (All Teachers)
            $teachers = User::where('role', 'teacher')->where('status', 'active')->whereNull('deleted_at')->get()->map(function ($teacher) {
                $classroomsCount = Classroom::where('creator_id', $teacher->id)->whereNull('deleted_at')->count();
                $classroomIds = Classroom::where('creator_id', $teacher->id)->pluck('id');
                
                $classworksCount = Classwork::whereIn('classroom_id', $classroomIds)->whereNull('deleted_at')->count();
                $formsCount = Form::where('creator_id', $teacher->id)->whereNull('deleted_at')->count();

                return [
                    'id' => $teacher->id,
                    'name' => $teacher->first_name . ' ' . $teacher->last_name,
                    'classrooms' => $classroomsCount,
                    'classworks' => $classworksCount,
                    'forms' => $formsCount,
                    'total_activity' => $classroomsCount + $classworksCount + $formsCount
                ];
            })->sortByDesc('total_activity')->values(); 

            // LINE DIAGRAM (System Logins)
            $logins = DB::table('users')
                ->whereNotNull('last_login_at')
                ->whereNull('deleted_at')
                ->where('last_login_at', '>=', Carbon::now()->subDays(6)->startOfDay())
                ->selectRaw('DATE(last_login_at) as date, role, count(id) as total')
                ->groupByRaw('DATE(last_login_at), role')
                ->get();

            $lineChartData = collect();
            for ($i = 6; $i >= 0; $i--) {
                $dateStr = Carbon::now()->subDays($i)->format('Y-m-d');
                $dayLogs = $logins->where('date', $dateStr);
                
                $lineChartData->push([
                    'date' => Carbon::parse($dateStr)->format('M d'),
                    'admin' => $dayLogs->where('role', 'admin')->sum('total'),
                    'teacher' => $dayLogs->where('role', 'teacher')->sum('total'),
                    'student' => $dayLogs->where('role', 'student')->sum('total'),
                ]);
            }

            // RECENT PUBLISHED ANNOUNCEMENTS
            $now = Carbon::now();
            $recentAnnouncements = DB::table('announcements')
                ->whereNull('deleted_at')
                ->whereNotNull('publish_from')
                ->where('publish_from', '<=', $now)
                ->where(function($query) use ($now) {
                    $query->whereNull('valid_until')
                          ->orWhere('valid_until', '>=', $now);
                })
                ->orderBy('publish_from', 'desc')
                ->get()
                ->map(function($a) {
                    return [
                        'id' => $a->id,
                        'title' => $a->title,
                        'date' => Carbon::parse($a->publish_from)->format('M d, Y'),
                        'time' => Carbon::parse($a->publish_from)->format('h:i A')
                    ];
                });

            return response()->json([
                'stats' => $stats,
                'students_per_strand' => $studentsPerStrand,
                'user_status' => $userStatus,
                'teacher_rankings' => $teachers,
                'login_activity' => $lineChartData,
                'recent_announcements' => $recentAnnouncements
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Backend Error: ' . $e->getMessage() . ' on line ' . $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}