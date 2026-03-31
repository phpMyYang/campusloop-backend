<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class AdminFileController extends Controller
{
    // Kukunin ang lahat ng Users (Teacher & Student) at ang Virtual System Folders
    public function folders()
    {
        try {
            $users = User::whereIn('role', ['teacher', 'student'])
                ->orderBy('role')
                ->orderBy('first_name')
                ->get();
            
            // Bilangin ang files per owner
            $fileCounts = File::selectRaw('owner_id, count(*) as total')
                ->groupBy('owner_id')
                ->pluck('total', 'owner_id');

            $folders = $users->map(function($user) use ($fileCounts) {
                return [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'role' => $user->role,
                    'file_count' => $fileCounts[$user->id] ?? 0
                ];
            });

            // GUMAWA NG VIRTUAL FOLDER PARA SA ANNOUNCEMENTS
            $announcementCount = File::where('attachable_type', 'like', '%Announcement%')->count();
            
            // Ilagay sa pinaka-unahan ang Announcement folder gamit ang prepend
            $folders->prepend([
                'id' => 'system_announcements', // Special ID para ma-detect sa userFiles
                'name' => 'System Announcements',
                'role' => 'system',
                'file_count' => $announcementCount
            ]);

            return response()->json($folders, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch folders.'], 500);
        }
    }

    // Kukunin ang laman ng folder ng specific user o ng system folder
    public function userFiles($userId)
    {
        try {
            // CHECK KUNG "SYSTEM ANNOUNCEMENTS" YUNG FOLDER NA BINUBUKSAN
            if ($userId === 'system_announcements') {
                $filesQuery = File::where('attachable_type', 'like', '%Announcement%');
            } else {
                $filesQuery = File::where('owner_id', $userId);
            }

            $files = $filesQuery->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($file) {
                    $source = 'Other';
                    $type = $file->attachable_type ?? '';
                    
                    if (str_contains($type, 'ELibrary')) {
                        $source = 'E-Library';
                    } elseif (str_contains($type, 'ClassworkSubmission')) {
                        $source = 'Submission';
                    } elseif (str_contains($type, 'Classwork')) {
                        $source = 'Classwork';
                    } elseif (str_contains($type, 'Announcement')) {
                        $source = 'Announcement';
                    }
                    
                    $file->source_label = $source;
                    return $file;
                });

            return response()->json($files, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch files.'], 500);
        }
    }

    // Gagawa ng ZIP file para sa mga na-select na files at id-download
    public function downloadZip(Request $request)
    {
        $request->validate(['file_ids' => 'required|array']);

        $files = File::whereIn('id', $request->file_ids)->get();

        if ($files->isEmpty()) {
            return response()->json(['message' => 'No files found.'], 404);
        }

        $zip = new ZipArchive;
        $zipFileName = 'Admin_Export_' . time() . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);

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

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    // Soft delete para sa mga selected files
    public function bulkDelete(Request $request)
    {
        $request->validate(['file_ids' => 'required|array']);
        File::whereIn('id', $request->file_ids)->delete();
        return response()->json(['message' => 'Files moved to recycle bin.'], 200);
    }
}