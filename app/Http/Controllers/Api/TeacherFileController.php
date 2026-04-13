<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class TeacherFileController extends Controller
{
    // View Files
    public function index(Request $request)
    {
        try {
            // Kukunin lahat ng sariling files ni Teacher
            $files = File::where('owner_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($file) {
                    // I-check kung saan naka-attach ang file para sa Badge Label
                    $source = 'Other';
                    if (str_contains($file->attachable_type, 'ELibrary')) {
                        $source = 'E-Library';
                    } elseif (str_contains($file->attachable_type, 'Classwork')) {
                        $source = 'Classwork';
                    } elseif (str_contains($file->attachable_type, 'Announcement')) {
                        $source = 'Announcement';
                    }
                    
                    // Idagdag ang source label sa response
                    $file->source_label = $source;
                    return $file;
                });

            return response()->json($files, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch files.'], 500);
        }
    }

    // Gagawa ng ZIP file para sa pag-download
    public function downloadZip(Request $request)
    {
        $request->validate([
            'file_ids' => 'required|array'
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

        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            foreach ($files as $file) {
                // Kunin ang exact physical path
                $rawPath = str_replace('storage/', '', $file->path);
                $fullPath = storage_path('app/public/' . $rawPath);
                
                if (file_exists($fullPath)) {
                    $zip->addFile($fullPath, $file->name);
                }
            }
            $zip->close();
        }

        // I-return ang zip file at burahin sa server pagkatapos ma-download
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}