<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classwork;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClassworkController extends Controller
{
    public function index($classroomId)
    {
        $classworks = Classwork::with([
                'files', 
                'form',
                'comments' => function ($query) {
                    // Kunin ang main comments at i-include ang User at ang kanilang Replies
                    $query->whereNull('parent_id')
                          ->with(['user', 'replies.user'])
                          ->orderBy('created_at', 'asc');
                }
            ])
            ->where('classroom_id', $classroomId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($classworks, 200);
    }

    public function store(Request $request, $classroomId)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:assignment,activity,quiz,exam,material',
            'instruction' => 'required|string',
            'points' => 'nullable|integer',
            'deadline' => 'nullable|date',
            'link' => 'nullable|string',
            'form_id' => 'nullable|uuid|exists:forms,id',
            'files.*' => 'file|max:51200'
        ]);

        $classwork = Classwork::create(array_merge($validated, ['classroom_id' => $classroomId]));

        // EXACT FOLDER STRUCTURE FROM USER CONTROLLER
        if ($request->hasFile('files')) {
            $user = Auth::user();
            $folderName = str_replace(' ', '_', strtolower($user->first_name . '_' . $user->last_name . '_' . $user->id));
            $destinationPath = "users_files/{$folderName}/classworks";

            foreach ($request->file('files') as $uploadedFile) {
                $filename = $uploadedFile->getClientOriginalName();
                $path = $uploadedFile->storeAs($destinationPath, time() . '_' . $filename, 'public');

                File::create([
                    'id' => (string) Str::uuid(),
                    'owner_id' => $user->id,
                    'name' => $filename,
                    'path' => '/storage/' . $path,
                    'file_extension' => $uploadedFile->getClientOriginalExtension(),
                    'file_size' => $uploadedFile->getSize(),
                    'attachable_type' => Classwork::class,
                    'attachable_id' => $classwork->id
                ]);
            }
        }

        return response()->json(['message' => 'Classwork posted successfully!', 'classwork' => $classwork->load(['files', 'form'])], 201);
    }

    public function update(Request $request, $id)
    {
        $classwork = Classwork::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:assignment,activity,quiz,exam,material',
            'instruction' => 'required|string',
            'points' => 'nullable|integer',
            'deadline' => 'nullable|date',
            'link' => 'nullable|string',
            'form_id' => 'nullable|uuid|exists:forms,id',
            'files.*' => 'file|max:51200'
        ]);

        $classwork->update($validated);

        // TANGGALIN ANG MGA DELETED FILES SA STORAGE AT DB
        if ($request->has('deleted_file_ids')) {
            $filesToDelete = File::whereIn('id', $request->deleted_file_ids)->get();
            foreach ($filesToDelete as $f) {
                $relativePath = str_replace('/storage/', '', $f->path);
                Storage::disk('public')->delete($relativePath);
                $f->delete();
            }
        }

        // MAGDAGDAG NG MGA BAGONG UPLOADED FILES
        if ($request->hasFile('files')) {
            $user = Auth::user();
            $folderName = str_replace(' ', '_', strtolower($user->first_name . '_' . $user->last_name . '_' . $user->id));
            $destinationPath = "users_files/{$folderName}/classworks";

            foreach ($request->file('files') as $uploadedFile) {
                $filename = $uploadedFile->getClientOriginalName();
                $path = $uploadedFile->storeAs($destinationPath, time() . '_' . $filename, 'public');

                File::create([
                    'id' => (string) Str::uuid(),
                    'owner_id' => $user->id,
                    'name' => $filename,
                    'path' => '/storage/' . $path,
                    'file_extension' => $uploadedFile->getClientOriginalExtension(),
                    'file_size' => $uploadedFile->getSize(),
                    'attachable_type' => Classwork::class,
                    'attachable_id' => $classwork->id
                ]);
            }
        }

        return response()->json(['message' => 'Classwork updated successfully!'], 200);
    }

    public function destroy($id)
    {
        $classwork = Classwork::findOrFail($id);
        $classwork->delete(); 
        return response()->json(['message' => 'Classwork moved to recycle bin.'], 200);
    }
}