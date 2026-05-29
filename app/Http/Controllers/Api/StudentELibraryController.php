<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ELibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; 

class StudentELibraryController extends Controller
{
    // security 
    private function checkStudent(Request $request)
    {
        return $request->user() && $request->user()->role === 'student';
    }

    // View Elibrary
    public function index(Request $request)
    {
        if (!$this->checkStudent($request)) {
            return response()->json(['message' => 'Unauthorized Access. Students only.'], 403);
        }

        try {
            $search = $request->input('search', '');
            $entries = (int) $request->input('entries', 12);

            $query = ELibrary::with(['creator', 'files'])
                ->where('status', 'approved');

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $libraries = $query->orderBy('created_at', 'desc')->paginate($entries);
                
            return response()->json($libraries, 200);

        } catch (\Exception $e) {
            Log::error('Student Fetch E-Library Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while fetching the E-Library.'], 500);
        }
    }
}