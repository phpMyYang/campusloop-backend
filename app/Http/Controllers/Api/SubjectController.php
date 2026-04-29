<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    // SECURITY FEATURE
    private function checkAdmin(Request $request)
    {
        return $request->user() && $request->user()->role === 'admin';
    }

    // View Subject
    public function index(Request $request)
    {
        $query = Subject::with('strand');

        // Server-side Searching
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Server-side Filtering
        if ($request->has('filterStrand') && $request->filterStrand !== 'all') {
            $query->where('strand_id', $request->filterStrand);
        }
        if ($request->has('filterGrade') && $request->filterGrade !== 'all') {
            $query->where('grade_level', $request->filterGrade);
        }
        if ($request->has('filterSemester') && $request->filterSemester !== 'all') {
            $query->where('semester', $request->filterSemester);
        }

        $query->orderBy('created_at', 'desc');

        $entries = $request->has('entries') ? (int) $request->entries : 10;
        
        // Gagamit ng paginate() para iwas RAM overload (DoS mitigation)
        return response()->json($query->paginate($entries), 200);
    }

    // Create Subject
    public function store(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:subjects,code',
            'description' => 'required|string',
            'strand_id' => 'required|exists:strands,id',
            'grade_level' => 'required|in:11,12', // Enum 11, 12 
            'semester' => 'required|in:1st,2nd'  // Enum 1st, 2nd 
        ]);

        // Subject::create($validated);
        $subject = Subject::create($validated);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Created Subject',
            'description' => "Created a new subject: {$subject->code} - {$subject->description}."
        ]);

        return response()->json(['message' => 'Subject created successfully!'], 201);
    }

    // Update Subject
    public function update(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:subjects,code,' . $id,
            'description' => 'required|string',
            'strand_id' => 'required|exists:strands,id',
            'grade_level' => 'required|in:11,12',
            'semester' => 'required|in:1st,2nd'
        ]);

        $subject->update($validated);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Updated Subject',
            'description' => "Updated the details of subject: {$subject->code}."
        ]);

        return response()->json(['message' => 'Subject updated successfully!'], 200);
    }

    // Delete Subject
    public function destroy(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        $subject = Subject::findOrFail($id);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Deleted Subject',
            'description' => "Moved subject '{$subject->code}' to the recycle bin."
        ]);

        $subject->delete();
        return response()->json(['message' => 'Subject moved to recycle bin.'], 200);
    }

    // Para sa Multiple Deletion 
    public function bulkDelete(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        $request->validate(['ids' => 'required|array|max:100']);
        $count = count($request->ids);

        if ($count > 0) {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Bulk Deleted Subjects',
                'description' => "Moved {$count} selected subjects to the recycle bin."
            ]);
        }

        Subject::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Selected subjects moved to recycle bin.'], 200);
    }
}