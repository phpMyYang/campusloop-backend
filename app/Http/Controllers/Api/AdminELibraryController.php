<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminELibraryController extends Controller
{
    // KUNIN LAHAT NG E-LIBRARIES (With Creator & Files)
    public function index(Request $request)
    {
        try {
            $eLibraries = \App\Models\ELibrary::with(['creator', 'files'])->orderBy('created_at', 'desc')->get();
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
            
            \App\Models\ELibrary::whereIn('id', $request->ids)->update([
                'status' => 'approved',
                'admin_feedback' => null, // Linisin ang feedback kapag in-approve na
                'updated_at' => now()
            ]);

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
            
            \App\Models\ELibrary::whereIn('id', $request->ids)->update([
                'status' => 'declined',
                'admin_feedback' => $request->feedback,
                'updated_at' => now()
            ]);

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
            
            \App\Models\ELibrary::whereIn('id', $request->ids)->delete(); // Soft delete

            return response()->json(['message' => 'Selected materials moved to recycle bin.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}