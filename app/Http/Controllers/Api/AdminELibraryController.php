<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use \App\Models\ELibrary;

class AdminELibraryController extends Controller
{
    // KUNIN LAHAT NG E-LIBRARIES (With Creator & Files)
    public function index(Request $request)
    {
        try {
            $eLibraries = ELibrary::with(['creator', 'files'])->orderBy('created_at', 'desc')->get();
            return response()->json($eLibraries, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // BULK APPROVE
    public function bulkApprove(Request $request)
    {
        try {
            $request->validate(['ids' => 'required|array']);
            
            ELibrary::whereIn('id', $request->ids)->update([
                'status' => 'approved',
                'admin_feedback' => null, // Linisin ang feedback kapag in-approve na
                'updated_at' => now()
            ]);

            // NOTIFY TEACHERS (Approve)
            $libraries = ELibrary::whereIn('id', $request->ids)->get();
            foreach($libraries as $lib) {
                DB::table('notifications')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $lib->creator_id,
                    'description' => "Your E-Library material '{$lib->title}' was approved by the Admin.",
                    'link' => "/teacher/e-library",
                    'is_read' => false,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return response()->json(['message' => 'Selected materials approved successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // BULK DECLINE WITH FEEDBACK
    public function bulkDecline(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'feedback' => 'required|string'
            ]);
            
            ELibrary::whereIn('id', $request->ids)->update([
                'status' => 'declined',
                'admin_feedback' => $request->feedback,
                'updated_at' => now()
            ]);

            // NOTIFY TEACHERS (Decline)
            $libraries = ELibrary::whereIn('id', $request->ids)->get();
            foreach($libraries as $lib) {
                DB::table('notifications')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $lib->creator_id,
                    'description' => "Your E-Library material '{$lib->title}' was declined. Feedback: " . Str::limit($request->feedback, 30),
                    'link' => "/teacher/e-library",
                    'is_read' => false,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return response()->json(['message' => 'Selected materials declined successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // BULK DELETE (SoftDeletes)
    public function bulkDelete(Request $request)
    {
        try {
            $request->validate(['ids' => 'required|array']);
            ELibrary::whereIn('id', $request->ids)->delete(); // Soft delete
            return response()->json(['message' => 'Selected materials moved to recycle bin.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}