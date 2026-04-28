<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Classroom;
use App\Models\Classwork;
use App\Models\File;
use App\Models\Form;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    // SAFE ROLE-BASED AUTHORIZATION
    private function checkAdmin(Request $request)
    {
        return $request->user() && $request->user()->role === 'admin';
    }

    public function index(Request $request)
    {
        // IMPLICIT AUTHORIZATION CHECK
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        $validated = $request->validate([
            'strand_year' => 'nullable|integer|digits:4',
            'status_year' => 'nullable|integer|digits:4',
        ]);

        $strandYear = $validated['strand_year'] ?? null;
        $statusYear = $validated['status_year'] ?? null;

        try {
            // STATS CARDS (Active & Not Deleted Only)
            $stats = [
                'total_students' => User::where('role', 'student')->where('status', 'active')->count(),
                'total_teachers' => User::where('role', 'teacher')->where('status', 'active')->count(),
                'active_classrooms' => Classroom::count(),
                'files_uploaded' => File::count(),
            ];

            // BAR CHART: Students per Strand 
            $strandQuery = DB::table('users')
                ->where('users.role', 'student')
                ->whereNull('users.deleted_at')
                ->join('strands', 'users.strand_id', '=', 'strands.id');
            
            if ($strandYear) {
                $strandQuery->whereYear('users.created_at', $strandYear);
            }
            $studentsPerStrand = $strandQuery->selectRaw('strands.name, count(users.id) as value')->groupBy('strands.name')->get();

            // DOUGHNUT CHART: Active vs Inactive Users 
            $statusQuery = DB::table('users')->whereNull('deleted_at');
            if ($statusYear) {
                $statusQuery->whereYear('created_at', $statusYear);
            }
            $userStatus = $statusQuery->selectRaw('status, count(id) as value')->groupBy('status')->get();

            // APPLICATION-LAYER DoS (N+1 Queries Mitigated!)
            $teachers = User::where('role', 'teacher')
                ->where('status', 'active')
                ->addSelect([
                    'classrooms_count' => Classroom::selectRaw('count(*)')
                        ->whereColumn('creator_id', 'users.id')
                        ->whereNull('deleted_at'),
                    'forms_count' => Form::selectRaw('count(*)')
                        ->whereColumn('creator_id', 'users.id')
                        ->whereNull('deleted_at'),
                    'classworks_count' => Classwork::selectRaw('count(*)')
                        ->whereIn('classroom_id', Classroom::select('id')
                            ->whereColumn('creator_id', 'users.id')
                            ->whereNull('deleted_at')
                        )
                        ->whereNull('deleted_at')
                ])
                ->get()
                ->map(function ($teacher) {
                    $c = $teacher->classrooms_count ?? 0;
                    $w = $teacher->classworks_count ?? 0;
                    $f = $teacher->forms_count ?? 0;

                    return [
                        'id' => $teacher->id,
                        'name' => $teacher->first_name . ' ' . $teacher->last_name,
                        'classrooms' => $c,
                        'classworks' => $w,
                        'forms' => $f,
                        'total_activity' => $c + $w + $f
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
            Log::error('Admin Dashboard Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            
            return response()->json([
                'message' => 'An unexpected error occurred while fetching dashboard data.'
            ], 500);
        }
    }
}