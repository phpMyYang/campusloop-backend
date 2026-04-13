<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
            $role = ucfirst($currentUser->role); // "Student" o "Teacher"
            
            $snippet = Str::limit($request->content, 30);
            $classworkTitle = Str::limit($classwork->title, 25);

            // CHECK KUNG REPLY O DIRECT COMMENT
            $parentComment = null;
            if ($request->parent_id) {
                $parentComment = Comment::with('user')->find($request->parent_id);
                $parentName = ($parentComment && $parentComment->user) 
                    ? $parentComment->user->first_name . ' ' . $parentComment->user->last_name
                    : 'someone';

                $description = "{$role} {$fullName} replied to {$parentName} on '{$classworkTitle}' in {$subjectName} ({$sectionName}): \"{$snippet}\"";
            } else {
                $description = "{$role} {$fullName} commented on '{$classworkTitle}' in {$subjectName} ({$sectionName}): \"{$snippet}\"";
            }

            // NOTIFICATION LOGIC 
            $currentTime = now()->toDateTimeString();
            $notifications = [];

            // 1. NOTIFY TEACHER (Kung hindi si Teacher mismo ang nag-comment)
            if ($currentUser->id !== $classroom->creator_id) {
                $notifications[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $classroom->creator_id,
                    'description' => $description,
                    'link' => "/teacher/classrooms/{$classroom->id}/stream", // Direkta sa stream ni teacher
                    'is_read' => false,
                    'created_at' => $currentTime,
                    'updated_at' => $currentTime,
                ];
            }

            // NOTIFY STUDENTS
            if ($request->parent_id) {
                // KUNG REPLY: I-notify lang kung sino yung nireplyan (kung student siya)
                if ($parentComment && $parentComment->user_id !== $currentUser->id) {
                    $targetUser = $parentComment->user;
                    if ($targetUser && $targetUser->role === 'student') {
                        $notifications[] = [
                            'id' => Str::uuid()->toString(),
                            'user_id' => $targetUser->id,
                            'description' => $description,
                            'link' => "/student/classrooms/{$classroom->id}/stream",
                            'is_read' => false,
                            'created_at' => $currentTime,
                            'updated_at' => $currentTime,
                        ];
                    }
                }
            } else {
                // KUNG DIRECT COMMENT:
                if ($currentUser->id === $classroom->creator_id) {
                    // Pag si TEACHER ang nag-comment, i-notify LAHAT ng approved students sa class
                    $approvedStudents = $classroom->students()->wherePivot('status', 'approved')->get();
                    foreach ($approvedStudents as $student) {
                        $notifications[] = [
                            'id' => Str::uuid()->toString(),
                            'user_id' => $student->id,
                            'description' => $description,
                            'link' => "/student/classrooms/{$classroom->id}/stream",
                            'is_read' => false,
                            'created_at' => $currentTime,
                            'updated_at' => $currentTime,
                        ];
                    }
                } else {
                    // Pag si STUDENT ang nag-comment, i-notify lang 'yung ibang students na nag-interact sa comments (Participants)
                    $participantIds = Comment::where('commentable_id', $classworkId)
                        ->where('commentable_type', Classwork::class)
                        ->where('user_id', '!=', $currentUser->id)
                        ->where('user_id', '!=', $classroom->creator_id) // Exclude teacher
                        ->pluck('user_id')
                        ->unique();

                    if ($participantIds->isNotEmpty()) {
                        $participants = User::whereIn('id', $participantIds)->where('role', 'student')->get();
                        foreach ($participants as $pUser) {
                            $notifications[] = [
                                'id' => Str::uuid()->toString(),
                                'user_id' => $pUser->id,
                                'description' => $description,
                                'link' => "/student/classrooms/{$classroom->id}/stream",
                                'is_read' => false,
                                'created_at' => $currentTime,
                                'updated_at' => $currentTime,
                            ];
                        }
                    }
                }
            }

            // ISAHANG BULK INSERT PARA MABILIS
            if (!empty($notifications)) {
                foreach (array_chunk($notifications, 500) as $chunk) {
                    DB::table('notifications')->insert($chunk);
                }
            }

            return response()->json(['message' => 'Comment posted successfully!', 'comment' => $comment], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to post comment: ' . $e->getMessage()], 500);
        }
    }
}