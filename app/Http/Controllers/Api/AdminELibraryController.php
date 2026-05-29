<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use \App\Models\ELibrary;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class AdminELibraryController extends Controller
{
    // security
    private function checkAdmin(Request $request)
    {
        return $request->user() && $request->user()->role === 'admin';
    }

    // KUNIN LAHAT NG E-LIBRARIES
    public function index(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $query = ELibrary::with(['creator', 'files']);

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                      ->orWhereHas('creator', function($creatorQuery) use ($search) {
                          $creatorQuery->where('first_name', 'LIKE', "%{$search}%")
                                       ->orWhere('last_name', 'LIKE', "%{$search}%")
                                       ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search}%");
                      });
                });
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            $sortOrder = $request->has('sort') && $request->sort === 'oldest' ? 'asc' : 'desc';
            $query->orderBy('created_at', $sortOrder);
            $entries = $request->has('entries') ? (int) $request->entries : 12;
            $paginatedLibs = $query->paginate($entries);

            return response()->json([
                'data' => $paginatedLibs->items(),
                'total' => $paginatedLibs->total(),
                'last_page' => $paginatedLibs->lastPage()
            ], 200);

        } catch (\Exception $e) {
            Log::error('AdminELibraryController index Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while fetching materials.'], 500);
        }
    }

    // BULK APPROVE
    public function bulkApprove(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $request->validate(['ids' => 'required|array|max:100']);
            
            DB::beginTransaction();

            ELibrary::whereIn('id', $request->ids)->update([
                'status' => 'approved',
                'admin_feedback' => null,
                'updated_at' => now()
            ]);

            $adminUser = $request->user();
            $adminName = $adminUser->first_name . ' ' . $adminUser->last_name;
            $libraries = ELibrary::with('creator')->whereIn('id', $request->ids)->get();

            $students = User::where('role', 'student')
                                        ->where('status', 'active')
                                        ->get();

            $notifications = [];
            $currentTime = now()->toDateTimeString();

            foreach($libraries as $lib) {
                $creatorName = $lib->creator ? $lib->creator->first_name . ' ' . $lib->creator->last_name : 'a Teacher';
                
                $notifications[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $lib->creator_id,
                    'description' => "Your E-Library material '{$lib->title}' was approved by Admin {$adminName}.",
                    'link' => "/teacher/e-library",
                    'is_read' => false,
                    'created_at' => $currentTime,
                    'updated_at' => $currentTime,
                ];

                foreach($students as $student) {
                    $notifications[] = [
                        'id' => Str::uuid()->toString(),
                        'user_id' => $student->id,
                        'description' => "Admin {$adminName} added a new E-Library material: '{$lib->title}' by {$creatorName}.",
                        'link' => "/student/e-library", 
                        'is_read' => false,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime,
                    ];
                }
            }

            if (!empty($notifications)) {
                foreach (array_chunk($notifications, 500) as $chunk) {
                    DB::table('notifications')->insert($chunk);
                }
            }

            $count = count($request->ids);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Approved E-Library Materials',
                'description' => "Approved {$count} pending E-Library material(s)."
            ]);

            DB::commit(); 
            return response()->json(['message' => 'Selected materials approved successfully.'], 200);

        } catch (\Exception $e) {
            DB::rollBack(); 
            Log::error('AdminELibraryController bulkApprove Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while approving materials.'], 500);
        }
    }

    // BULK DECLINE WITH FEEDBACK
    public function bulkDecline(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $request->validate([
                'ids' => 'required|array|max:100', 
                'feedback' => 'required|string'
            ]);
            
            DB::beginTransaction();

            ELibrary::whereIn('id', $request->ids)->update([
                'status' => 'declined',
                'admin_feedback' => $request->feedback,
                'updated_at' => now()
            ]);

            $libraries = ELibrary::whereIn('id', $request->ids)->get();
            $notifications = [];
            $currentTime = now()->toDateTimeString();

            foreach($libraries as $lib) {
                $notifications[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $lib->creator_id,
                    'description' => "Your E-Library material '{$lib->title}' was declined. Feedback: " . Str::limit($request->feedback, 30),
                    'link' => "/teacher/e-library",
                    'is_read' => false,
                    'created_at' => $currentTime, 
                    'updated_at' => $currentTime,
                ];
            }

            if (!empty($notifications)) {
                DB::table('notifications')->insert($notifications);
            }

            $count = count($request->ids);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Declined E-Library Materials',
                'description' => "Declined {$count} pending E-Library material(s) and provided feedback."
            ]);

            DB::commit();
            return response()->json(['message' => 'Selected materials declined successfully.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AdminELibraryController bulkDecline Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while declining materials.'], 500);
        }
    }

    // BULK DELETE 
    public function bulkDelete(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            $request->validate(['ids' => 'required|array|max:100']);

            DB::beginTransaction();

            $libraries = ELibrary::whereIn('id', $request->ids)->get();
            ELibrary::whereIn('id', $request->ids)->delete(); 
            $admin = $request->user();
            $adminName = $admin ? $admin->first_name . ' ' . $admin->last_name : 'Admin';
            $notifications = [];
            $currentTime = now()->toDateTimeString();

            foreach ($libraries as $lib) {
                $notifications[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $lib->creator_id,
                    'description' => "Admin {$adminName} deleted your E-Library material '{$lib->title}'. It was moved to the Recycle Bin.",
                    'link' => "/teacher/recycle-bin", 
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

            $count = count($request->ids);
            
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Deleted E-Library Materials',
                'description' => "Moved {$count} E-Library material(s) to the recycle bin."
            ]);

            DB::commit();
            return response()->json(['message' => 'Selected materials moved to recycle bin.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AdminELibraryController bulkDelete Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while deleting materials.'], 500);
        }
    }
}