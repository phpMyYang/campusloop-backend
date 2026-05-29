<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\File;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TeacherFileController extends Controller
{
    // security
    private function checkTeacher(Request $request)
    {
        return $request->user() && $request->user()->role === 'teacher';
    }

    // view files
    public function index(Request $request)
    {
        if (!$this->checkTeacher($request)) {
            return response()->json(['message' => 'Unauthorized Access. Teachers only.'], 403);
        }

        try {
            $query = File::where('owner_id', $request->user()->id);

            if ($request->has('search') && $request->search !== '') {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $entries = $request->input('entries', 12);
            $files = $query->orderBy('created_at', 'desc')->paginate($entries);

            $files->getCollection()->transform(function ($file) {
                $source = 'Other';
                if (str_contains($file->attachable_type, 'ELibrary')) {
                    $source = 'E-Library';
                } elseif (str_contains($file->attachable_type, 'Classwork')) {
                    $source = 'Classwork';
                } elseif (str_contains($file->attachable_type, 'Announcement')) {
                    $source = 'Announcement';
                }
                
                $file->source_label = $source;
                return $file;
            });

            return response()->json($files, 200);

        } catch (\Exception $e) {
            Log::error('Fetch Teacher Files Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'Failed to fetch files.'], 500);
        }
    }

    // download zip
    public function downloadZip(Request $request)
    {
        if (!$this->checkTeacher($request)) {
            return response()->json(['message' => 'Unauthorized Access. Teachers only.'], 403);
        }

        try {
            $request->validate([
                'file_ids' => 'required|array|max:20'
            ]);

            $files = File::whereIn('id', $request->file_ids)
                ->where('owner_id', $request->user()->id)
                ->get();

            if ($files->isEmpty()) {
                return response()->json(['message' => 'No files found.'], 404);
            }

            $zip = new ZipArchive;
            $zipFileName = 'Teacher_Files_' . time() . '.zip';
            $zipPath = storage_path('app/public/' . $zipFileName);

            // storage path
            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                foreach ($files as $file) {
                    $rawPath = str_replace('storage/', '', $file->path);
                    $fullPath = storage_path('app/public/' . $rawPath);
                    
                    if (file_exists($fullPath)) {
                        $zip->addFile($fullPath, $file->name);
                    }
                }
                $zip->close();
            }

            $count = $files->count();

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Downloaded Files',
                'description' => "Downloaded a ZIP archive containing {$count} of your file(s)."
            ]);

            return response()->download($zipPath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Download ZIP Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => 'Failed to download files. An unexpected error occurred on the server.'], 500);
        }
    }
}