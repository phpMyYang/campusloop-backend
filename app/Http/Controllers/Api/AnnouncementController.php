<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Announcement;
use App\Models\File;
use Illuminate\Http\Request;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class AnnouncementController extends Controller
{
    // View announcement
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

    // Create Announcement
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'link' => 'nullable|url',
            'publish_from' => 'required|date',
            'valid_until' => 'required|date|after:publish_from',
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

    // Update Announcement
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

        // Ginamit ang $request directly at inayos ang SoftDeletes
        if ($request->has('deleted_file_ids') && is_array($request->deleted_file_ids)) {
            $filesToDelete = File::whereIn('id', $request->deleted_file_ids)->get();
            foreach ($filesToDelete as $file) {
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

    // Single Delete Announcement
    public function destroy(Request $request, $id)
    {
        $announcement = Announcement::where('creator_id', $request->user()->id)->findOrFail($id);
        $announcement->delete(); 
        return response()->json(['message' => 'Moved to recycle bin.'], 200);
    }

    // Bulk Delete Announcement
    public function bulkDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        Announcement::where('creator_id', $request->user()->id)
            ->whereIn('id', $request->ids)
            ->delete();
        return response()->json(['message' => 'Selected items moved to recycle bin.'], 200);
    }

    // Post Comment
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

        // NOTIFICATION LOGIC 
        $currentUser = $request->user();
        $adminName = $currentUser->first_name. ' ' . $currentUser->last_name;
        
        // Paiksiin ang text para maganda sa notification dropdown
        $snippet = Str::limit($request->content, 30);
        $announcementTitle = Str::limit($announcement->title, 25);

        if ($request->parent_id) {
            // KUNG NAG-REPLY SI ADMIN SA SPECIFIC NA COMMENT
            $parentComment = Comment::with('user')->find($request->parent_id);
            
            if ($parentComment && $parentComment->user_id !== $currentUser->id) {
                $targetUser = $parentComment->user;
                $role = $targetUser->role;
                // I-set ang tamang link base sa role ng nire-replyan
                $link = "/";
                if ($role === 'student') $link = "/student/home";
                elseif ($role === 'teacher') $link = "/teacher/home";
                elseif ($role === 'admin') $link = "/admin/announcements";
                // Notify Specific User
                DB::table('notifications')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $targetUser->id,
                    'description' => "Admin {$adminName} replied to your comment on '{$announcementTitle}': \"{$snippet}\"",
                    'link' => $link,
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } else {
            // KUNG DIRECT COMMENT SI ADMIN SA POST (Notify participants)
            // Kukunin natin lahat ng nag-interact sa announcement na ito para ma-notify sila
            $participantIds = $announcement->comments()
                ->where('user_id', '!=', $currentUser->id)
                ->pluck('user_id')
                ->unique();

            foreach ($participantIds as $pId) {
                $pUser = User::find($pId);
                if ($pUser) {
                    // I-set ang tamang link base sa role ng participant
                    $link = "/";
                    if ($pUser->role === 'student') $link = "/student/home";
                    elseif ($pUser->role === 'teacher') $link = "/teacher/home";
                    elseif ($pUser->role === 'admin') $link = "/admin/announcements";
                    // Notify Teacher and Student (Comment)
                    DB::table('notifications')->insert([
                        'id' => Str::uuid()->toString(),
                        'user_id' => $pUser->id,
                        'description' => "Admin {$adminName} added a comment on '{$announcementTitle}': \"{$snippet}\"",
                        'link' => $link,
                        'is_read' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
        return response()->json(['message' => 'Comment posted successfully', 'comment' => $comment], 201);
    }

    // Update Comment
    public function updateComment(Request $request, $id)
    {
        $request->validate(['content' => 'required|string']);
        $comment = Comment::where('user_id', $request->user()->id)->findOrFail($id);
        $comment->update(['content' => $request->content]);
        
        return response()->json(['message' => 'Comment updated successfully']);
    }

    // Delete Comment
    public function deleteComment(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        $comment->forceDelete(); 
        
        return response()->json(['message' => 'Comment deleted successfully']);
    }
}