<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Announcement;
use App\Models\File;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    // SECURITY FEATURE: Admin Checker
    private function checkAdmin(Request $request)
    {
        return $request->user() && $request->user()->role === 'admin';
    }

    // View announcement
    public function index(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        $query = Announcement::with([
            'files',
            'creator',
            'comments' => function($q) {
                $q->whereNull('parent_id')
                  ->with(['user', 'replies.user'])
                  ->orderBy('created_at', 'asc');
            }
        ])->where('creator_id', $request->user()->id);

        // Server-side Searching
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('content', 'LIKE', "%{$search}%");
            });
        }

        // Server-side Filtering by Attachments
        if ($request->has('filterAttachment') && $request->filterAttachment != 'all') {
            $attachment = $request->filterAttachment;
            if ($attachment === 'files') {
                $query->has('files');
            } elseif ($attachment === 'links') {
                $query->whereNotNull('link');
            } elseif ($attachment === 'both') {
                $query->has('files')->whereNotNull('link');
            } elseif ($attachment === 'none') {
                $query->doesntHave('files')->whereNull('link');
            }
        }

        // Server-side Filtering by Status (Based on dates)
        if ($request->has('filterStatus') && $request->filterStatus != 'all') {
            $now = now();
            $status = $request->filterStatus;
            if ($status === 'Pending') {
                $query->where('publish_from', '>', $now);
            } elseif ($status === 'Published') {
                $query->where('publish_from', '<=', $now)
                      ->where('valid_until', '>=', $now);
            } elseif ($status === 'Done') {
                $query->where('valid_until', '<', $now);
            }
        }

        // Server-side Sorting
        $sortOrder = $request->input('sortDate', 'newest') === 'oldest' ? 'asc' : 'desc';
        $query->orderBy('created_at', $sortOrder);

        $entries = $request->has('entries') ? (int) $request->entries : 10;
        
        return response()->json($query->paginate($entries), 200);
    }

    // Create Announcement
    public function store(Request $request)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'link' => 'nullable|url|starts_with:http://,https://',
            'publish_from' => 'required|date',
            'valid_until' => 'required|date|after:publish_from',
            'files' => 'nullable|array|max:5',
            'files.*' => 'nullable|file|mimes:pdf,jpg,jpeg,gif,mp4,mov,avi|max:20480'
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

        $publishFromDate = Carbon::parse($validated['publish_from']);
        $now = now();

        $adminName = $request->user()->first_name . ' ' . $request->user()->last_name;
        $announcementTitle = Str::limit($announcement->title, 25);

        // CONDITIONAL DESCRIPTION
        if ($publishFromDate->greaterThan($now)) {
            // KUNG FUTURE DATE (Scheduled)
            $formattedDate = $publishFromDate->format('M d, Y h:i A'); // Apr 08, 2026 08:00 AM
            $descriptionText = "Admin {$adminName} scheduled an announcement: '{$announcementTitle}'. Wait for it on {$formattedDate}.";
        } else {
            // KUNG PUBLISHED NA NGAYON
            $descriptionText = "Admin {$adminName} posted a new global announcement: '{$announcementTitle}'.";
        }

        // Kunin lahat ng ACTIVE students at teachers
        $targetUsers = User::whereIn('role', ['student', 'teacher'])
                            ->where('status', 'active')
                            ->get();

        $notifications = [];
        $currentTime = $now->toDateTimeString();

        foreach ($targetUsers as $user) {
            $link = $user->role === 'student' ? "/student/home" : "/teacher/home";
            
            $notifications[] = [
                'id' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'description' => $descriptionText, // Gagamitin yung variable text mula sa itaas
                'link' => $link,
                'is_read' => false,
                'created_at' => $currentTime,
                'updated_at' => $currentTime,
            ];
        }

        // I-insert nang isahan para mabilis
        if (!empty($notifications)) {
            foreach (array_chunk($notifications, 500) as $chunk) {
                DB::table('notifications')->insert($chunk);
            }
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Created Announcement',
            'description' => "Posted a new global announcement: '{$announcementTitle}'."
        ]);
        
        return response()->json(['message' => 'Announcement posted successfully!'], 201);
    }

    // Update Announcement
    public function update(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);

        $announcement = Announcement::where('creator_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'link' => 'nullable|url|starts_with:http://,https://',
            'publish_from' => 'required|date',
            'valid_until' => 'required|date|after:publish_from',
            'files' => 'nullable|array|max:5',
            'files.*' => 'nullable|file|mimes:pdf,jpg,jpeg,gif,mp4,mov,avi|max:20480'
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
                // Remove file from server storage
                Storage::disk('public')->delete(str_replace('/storage/', '', $file->path));
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

        $announcementTitle = Str::limit($announcement->title, 25);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Updated Announcement',
            'description' => "Updated the global announcement: '{$announcementTitle}'."
        ]);

        return response()->json(['message' => 'Announcement updated successfully!'], 200);
    }

    // Single Delete Announcement
    public function destroy(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);

        $announcement = Announcement::where('creator_id', $request->user()->id)->findOrFail($id);
        $announcementTitle = Str::limit($announcement->title, 25);

        // Delete all associated physical files before deleting the announcement
        foreach ($announcement->files as $file) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $file->path));
        }

        $announcement->delete(); 

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Deleted Announcement',
            'description' => "Moved the global announcement '{$announcementTitle}' to the recycle bin."
        ]);
        return response()->json(['message' => 'Moved to recycle bin.'], 200);
    }

    // Bulk Delete Announcement
    public function bulkDelete(Request $request)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);

        $request->validate(['ids' => 'required|array']);
        $count = count($request->ids);
        Announcement::where('creator_id', $request->user()->id)
            ->whereIn('id', $request->ids)
            ->delete();

        if ($count > 0) {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Bulk Deleted Announcements',
                'description' => "Moved {$count} selected announcements to the recycle bin."
            ]);
        }
        return response()->json(['message' => 'Selected items moved to recycle bin.'], 200);
    }

    // Post Comment
    public function postComment(Request $request, $announcementId)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);

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
            // Kukunin lahat ng nag-interact sa announcement na ito para ma-notify
            $participantIds = $announcement->comments()
                ->where('user_id', '!=', $currentUser->id)
                ->pluck('user_id')
                ->unique();

            // Fetch all users at once (Single Query)
            $pUsers = User::whereIn('id', $participantIds)->get();
            $notifications = [];

            foreach ($pUsers as $pUser) {
                $link = "/";
                if ($pUser->role === 'student') $link = "/student/home";
                elseif ($pUser->role === 'teacher') $link = "/teacher/home";
                elseif ($pUser->role === 'admin') $link = "/admin/announcements";
                
                // Prepare array instead of inserting immediately
                $notifications[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $pUser->id,
                    'description' => "Admin {$adminName} added a comment on '{$announcementTitle}': \"{$snippet}\"",
                    'link' => $link,
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk insert outside the loop
            if (!empty($notifications)) {
                foreach (array_chunk($notifications, 500) as $chunk) {
                    DB::table('notifications')->insert($chunk);
                }
            }
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Commented on Announcement',
            'description' => "Added a comment/reply on the announcement '{$announcementTitle}'."
        ]);

        return response()->json(['message' => 'Comment posted successfully', 'comment' => $comment], 201);
    }

    // Update Comment
    public function updateComment(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);

        $request->validate(['content' => 'required|string']);
        $comment = Comment::where('user_id', $request->user()->id)->findOrFail($id);
        $comment->update(['content' => $request->content]);
        
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Updated Comment',
            'description' => "Updated a comment on an announcement thread."
        ]);

        return response()->json(['message' => 'Comment updated successfully']);
    }

    // Delete Comment
    public function deleteComment(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);

        // Ensure only the owner or an admin can delete the comment
        $comment = Comment::where('id', $id)
            ->where(function($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                      ->orWhere(DB::raw("'".$request->user()->role."'"), 'admin'); 
            })->firstOrFail();

        $comment->forceDelete(); 
        
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Deleted Comment',
            'description' => "Deleted a comment from an announcement thread."
        ]);
        
        return response()->json(['message' => 'Comment deleted successfully']);
    }
}