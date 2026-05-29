<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Strand;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Log;

class StrandController extends Controller
{
    // SECURITY 
    private function checkAdmin(Request $request)
    {
        return $request->user() && $request->user()->role === 'admin';
    }

    // View Strand
    public function index()
    {
        try {
            // Kukunin lahat ng strands + bibilangin kung ilang students ang naka-enroll
            $strands = Strand::withCount(['users' => function ($query) {
                $query->where('role', 'student');
            }])->orderBy('name', 'asc')->get();

            return response()->json($strands, 200);

        } catch (\Exception $e) {
            Log::error('StrandController index Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while fetching strands.'], 500);
        }
    }

    // Create Strand
    public function store(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:strands,name',
            'description' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            $strand = Strand::create($validated);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Created Strand',
                'description' => "Created a new academic strand: {$strand->name}."
            ]);

            DB::commit();
            return response()->json(['message' => 'Strand created successfully!'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StrandController store Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An error occurred while creating the strand.'], 500);
        }
    }

    // Update Strand
    public function update(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:strands,name,' . $id,
            'description' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            $strand = Strand::findOrFail($id);
            $strand->update($validated);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Updated Strand',
                'description' => "Updated the details of academic strand: {$strand->name}."
            ]);

            DB::commit();
            return response()->json(['message' => 'Strand updated successfully!'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StrandController update Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An error occurred while updating the strand.'], 500);
        }
    }

    // Delete Strand
    public function destroy(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }
        
        try {
            DB::beginTransaction();

            $strand = Strand::findOrFail($id);
            
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Deleted Strand',
                'description' => "Moved academic strand '{$strand->name}' to the recycle bin."
            ]);

            $strand->delete(); 
            
            DB::commit();
            return response()->json(['message' => 'Strand moved to recycle bin.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StrandController destroy Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An error occurred while deleting the strand.'], 500);
        }
    }
}