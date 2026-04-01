<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\File;
use Illuminate\Http\Request;
use App\Models\Comment;
use Illuminate\Support\Facades\Storage;


class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $announcements = Announcement::with([
            'files',
            'creator',
            'comments' => function($q) {
                // Kukunin ang comments na walang parent (main comments)
                $q->whereNull('parent_id')
                  ->with(['user', 'replies.user']) // Isasama ang user at mga replies
                  ->orderBy('created_at', 'asc');
            }
        ])
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
            'publish_from' => 'required|date',
            'valid_until' => 'required|date|after:publish_from', // Dapat mas huli ang valid_until kaysa publish_from
            // File Validation: Specific types only, Max 20MB per file
            'files.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,mp4,mov,avi|max:20480' 
        ]);

        $announcement = Announcement::create([
            'creator_id' => $request->user()->id,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'link' => $validated['link'] ?? null,
            'publish_from' => date('Y-m-d H:i:s', strtotime($validated['publish_from'])),
            'valid_until' => date('Y-m-d H:i:s', strtotime($validated['valid_until'])),
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
            'publish_from' => 'required|date',
            'valid_until' => 'required|date|after:publish_from',
            'files.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,mp4,mov,avi|max:20480',
        ]);

        $announcement->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'link' => empty($validated['link']) || $validated['link'] === 'null' ? null : $validated['link'],
            'publish_from' => date('Y-m-d H:i:s', strtotime($validated['publish_from'])),
            'valid_until' => date('Y-m-d H:i:s', strtotime($validated['valid_until'])),
        ]);

        // Ginamit natin ang $request directly at inayos ang SoftDeletes
        if ($request->has('deleted_file_ids') && is_array($request->deleted_file_ids)) {
            $filesToDelete = File::whereIn('id', $request->deleted_file_ids)->get();
            foreach ($filesToDelete as $file) {
                // Inalis natin ang Storage::delete() para hindi tuluyang mawala sa server
                // Para pwede pa siyang i-restore sa Recycle Bin feature mo mamaya!
                $file->delete(); 
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

    public function postComment(Request $request, $announcementId)
    {
        $request->validate([
            'content' => 'required|string',
            'parent_id' => 'nullable'
        ]);

        $announcement = Announcement::findOrFail($announcementId);
        $comment = $announcement->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $request->content,
            'parent_id' => $request->parent_id
        ]);

        return response()->json(['message' => 'Comment posted successfully', 'comment' => $comment], 201);
    }

    public function updateComment(Request $request, $id)
    {
        $request->validate(['content' => 'required|string']);
        $comment = Comment::where('user_id', $request->user()->id)->findOrFail($id);
        $comment->update(['content' => $request->content]);
        
        return response()->json(['message' => 'Comment updated successfully']);
    }

    public function deleteComment(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        $comment->forceDelete(); 
        
        return response()->json(['message' => 'Comment deleted successfully']);
    }
}