<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ELibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ELibraryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $libraries = ELibrary::with(['creator', 'files'])
                ->where('status', 'approved')
                ->orWhere('creator_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($libraries, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'files.*' => 'required|file|mimes:pdf|max:15360', 
        ]);

        $library = ELibrary::create([
            'creator_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'status' => 'pending', 
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('elibrary_files', 'public');
                
                $library->files()->create([
                    'owner_id' => $request->user()->id,
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'file_extension' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        return response()->json(['message' => 'Uploaded to Global Library. Pending Admin Approval.'], 201);
    }

    public function update(Request $request, $id)
    {
        $library = ELibrary::where('creator_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'files.*' => 'nullable|file|mimes:pdf|max:15360',
            'deleted_file_ids' => 'nullable|array',
        ]);

        $library->update([
            'title' => $request->title,
            'description' => $request->description,
            'status' => 'pending', 
            'admin_feedback' => null 
        ]);

        if ($request->has('deleted_file_ids')) {
            $filesToDelete = $library->files()->whereIn('id', $request->deleted_file_ids)->get();
            foreach ($filesToDelete as $f) {
                Storage::disk('public')->delete($f->path);
                $f->delete();
            }
        }

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('elibrary_files', 'public');
                $library->files()->create([
                    'owner_id' => $request->user()->id,
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'file_extension' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        return response()->json(['message' => 'Changes saved and re-submitted for approval.'], 200);
    }

    public function destroy(Request $request, $id)
    {
        $library = ELibrary::where('creator_id', $request->user()->id)->findOrFail($id);
        $library->delete();
        
        return response()->json(['message' => 'Moved to Recycle Bin.'], 200);
    }
}