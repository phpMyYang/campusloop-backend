<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Classwork;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    // Post Comment and Reply
    public function store(Request $request, $classworkId)
    {
        try {
            $request->validate([
                'content' => 'required|string',
                'parent_id' => 'nullable|exists:comments,id'
            ]);

            // I-SAVE ANG COMMENT SA DATABASE
            $comment = Comment::create([
                'user_id' => Auth::id(),
                'commentable_type' => Classwork::class,
                'commentable_id' => $classworkId,
                'parent_id' => $request->parent_id,
                'content' => $request->content
            ]);
            
            $classwork = Classwork::findOrFail($classworkId);
            $classroom = Classroom::findOrFail($classwork->classroom_id);
            $currentUser = Auth::user();

            // KUNIN ANG SUBJECT AT SECTION
            $subject = DB::table('subjects')->where('id', $classroom->subject_id)->first();
            $subjectName = $subject ? $subject->description : 'Class';
            $sectionName = $classroom->section;

            // FORMAT DETAILS PARA SA DROPDOWN
            $fullName = $currentUser->first_name . ' ' . $currentUser->last_name;
            $role = ucfirst($currentUser->role); // Para maging "Student" o "Teacher"
            
            $snippet = Str::limit($request->content, 30);
            $classworkTitle = Str::limit($classwork->title, 25);

            // CHECK KUNG REPLY O DIRECT COMMENT
            if ($request->parent_id) {
                $parentComment = Comment::with('user')->find($request->parent_id);
                $parentName = ($parentComment && $parentComment->user) 
                    ? $parentComment->user->first_name . ' ' . $parentComment->user->last_name
                    : 'someone';

                $description = "{$role} {$fullName} replied to {$parentName} on '{$classworkTitle}' in {$subjectName} ({$sectionName}): \"{$snippet}\"";
            } else {
                $description = "{$role} {$fullName} commented on '{$classworkTitle}' in {$subjectName} ({$sectionName}): \"{$snippet}\"";
            }

            // NOTIFY TEACHER (Kung hindi si Teacher mismo ang nag-comment)
            if ($currentUser->id !== $classroom->creator_id) {
                DB::table('notifications')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $classroom->creator_id,
                    'description' => $description,
                    'link' => "/teacher/classrooms/{$classroom->id}/stream", // Direkta sa stream ng klase
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            return response()->json(['message' => 'Comment posted successfully!', 'comment' => $comment], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to post comment: ' . $e->getMessage()], 500);
        }
    }
}