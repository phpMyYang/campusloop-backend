<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Classwork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(Request $request, $classworkId)
    {
        $request->validate([
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:comments,id'
        ]);

        $comment = Comment::create([
            'user_id' => Auth::id(),
            'commentable_type' => Classwork::class,
            'commentable_id' => $classworkId,
            'parent_id' => $request->parent_id,
            'content' => $request->content
        ]);

        return response()->json(['message' => 'Comment posted successfully!', 'comment' => $comment], 201);
    }
}