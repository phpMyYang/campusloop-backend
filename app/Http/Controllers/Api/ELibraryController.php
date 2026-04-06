<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ELibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB; 

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

        // KUNIN ANG DETAILS NI TEACHER AT ANG TITLE
        $currentUser = $request->user();
        $teacherName = $currentUser->first_name . ' ' . $currentUser->last_name;
        
        // Paiksiin ang title kung sakaling masyadong mahaba para maganda sa UI
        $shortTitle = Str::limit($request->title, 30);

        // NOTIFY ADMINS WITH DYNAMIC DESCRIPTION
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(),
                'user_id' => $admin->id,
                'description' => "Teacher: {$teacherName} uploaded a new material for approval: '{$shortTitle}'",
                'link' => "/admin/e-libraries",
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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