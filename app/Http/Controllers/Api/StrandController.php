<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Strand;
use Illuminate\Http\Request;

class StrandController extends Controller
{
    // View Strand
    public function index()
    {
        // Kukunin lahat ng strands + bibilangin kung ilang students ang naka-enroll dito
        $strands = Strand::withCount(['users' => function ($query) {
            $query->where('role', 'student');
        }])->orderBy('name', 'asc')->get();

        return response()->json($strands, 200);
    }

    // Create Strand
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:strands,name',
            'description' => 'required|string'
        ]);

        Strand::create($validated);
        return response()->json(['message' => 'Strand created successfully!'], 201);
    }

    // Update Strand
    public function update(Request $request, $id)
    {
        $strand = Strand::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:strands,name,' . $id,
            'description' => 'required|string'
        ]);

        $strand->update($validated);
        return response()->json(['message' => 'Strand updated successfully!'], 200);
    }

    // Delete Strand
    public function destroy($id)
    {
        $strand = Strand::findOrFail($id);
        $strand->delete(); // Naka SoftDeletes ito
        return response()->json(['message' => 'Strand moved to recycle bin.'], 200);
    }
}