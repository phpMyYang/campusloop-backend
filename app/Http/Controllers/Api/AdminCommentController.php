<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Log; 

class AdminCommentController extends Controller
{
    // SECURITY FEATURE
    private function checkAdmin(Request $request)
    {
        return $request->user() && $request->user()->role === 'admin';
    }

    // Delete ng specific comment or reply
    public function destroy(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        try {
            DB::beginTransaction();

            $comment = Comment::findOrFail($id);
            Comment::where('parent_id', $comment->id)->delete();
            $comment->delete();

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Deleted Comment',
                'description' => "Deleted a comment/reply from a classwork stream."
            ]);
            
            DB::commit(); 
            return response()->json(['message' => 'Comment deleted successfully.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AdminCommentController destroy Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'An unexpected error occurred while deleting the comment.'], 500);
        }
    }
}