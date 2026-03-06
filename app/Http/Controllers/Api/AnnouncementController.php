<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $announcements = Announcement::with('files')
            ->where('creator_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($announcements, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'link' => 'nullable|url',
            // File Validation: Specific types only, Max 20MB per file
            'files.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,mp4,mov,avi|max:20480' 
        ]);

        $announcement = Announcement::create([
            'creator_id' => $request->user()->id,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'link' => $validated['link'] ?? null,
            'status' => 'Published'
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('announcements', 'public');
                $announcement->files()->create([
                    'owner_id' => $request->user()->id,
                    'name' => $file->getClientOriginalName(),
                    'path' => '/storage/' . $path,
                    'file_extension' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize()
                ]);
            }
        }

        return response()->json(['message' => 'Announcement posted successfully!'], 201);
    }

    public function update(Request $request, $id)
    {
        $announcement = Announcement::where('creator_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'link' => 'nullable',
            'files.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,mp4,mov,avi|max:20480',
            'deleted_file_ids' => 'nullable|array' // Array ng mga ID ng files na tinanggal
        ]);

        $announcement->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'link' => $validated['link'] === 'null' || empty($validated['link']) ? null : $validated['link'],
        ]);

        // Tanggalin ang mga files na in-X sa frontend
        if (!empty($validated['deleted_file_ids'])) {
            $filesToDelete = File::whereIn('id', $validated['deleted_file_ids'])->get();
            foreach ($filesToDelete as $file) {
                // Tanggalin sa local storage
                $rawPath = str_replace('/storage/', '', $file->path);
                Storage::disk('public')->delete($rawPath);
                $file->delete(); // Alisin sa database
            }
        }

        // Mag-add ng mga bagong uploaded files
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('announcements', 'public');
                $announcement->files()->create([
                    'owner_id' => $request->user()->id,
                    'name' => $file->getClientOriginalName(),
                    'path' => '/storage/' . $path,
                    'file_extension' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize()
                ]);
            }
        }

        return response()->json(['message' => 'Announcement updated successfully!'], 200);
    }

    public function destroy(Request $request, $id)
    {
        $announcement = Announcement::where('creator_id', $request->user()->id)->findOrFail($id);
        $announcement->delete(); 
        return response()->json(['message' => 'Moved to recycle bin.'], 200);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        Announcement::where('creator_id', $request->user()->id)
            ->whereIn('id', $request->ids)
            ->delete();
        return response()->json(['message' => 'Selected items moved to recycle bin.'], 200);
    }
}