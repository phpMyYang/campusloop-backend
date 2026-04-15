<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Classroom;
use App\Models\Classwork;
use App\Models\Form;
use App\Models\File;
use App\Models\ELibrary;
use App\Models\AdvisoryClass;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class TeacherRecycleBinController extends Controller
{
    public function index()
    {
        try {
            $trashedItems = collect();
            $userId = Auth::id();
            $user = Auth::user();
            $userName = $user->first_name . ' ' . $user->last_name;

            // Classrooms
            Classroom::onlyTrashed()->where('creator_id', $userId)->get()->each(function ($item) use ($trashedItems, $userName) {
                $trashedItems->push($this->formatItem($item->id, $item->section, 'Classrooms', $userName, $item->deleted_at));
            });

            // Classworks
            Classwork::onlyTrashed()->whereHas('classroom', function($query) use ($userId) {
                $query->withTrashed()->where('creator_id', $userId);
            })->get()->each(function ($item) use ($trashedItems, $userName) {
                $title = ucfirst($item->type) . ' - ' . Str::limit($item->instruction, 30);
                $trashedItems->push($this->formatItem($item->id, $title, 'Classworks', $userName, $item->deleted_at));
            });

            // Forms
            Form::onlyTrashed()->where('creator_id', $userId)->get()->each(function ($item) use ($trashedItems, $userName) {
                $trashedItems->push($this->formatItem($item->id, $item->name, 'Forms', $userName, $item->deleted_at));
            });

            // Files
            File::onlyTrashed()->where('owner_id', $userId)->get()->each(function ($item) use ($trashedItems, $userName) {
                $trashedItems->push($this->formatItem($item->id, $item->name, 'Files', $userName, $item->deleted_at));
            });

            // E-Libraries
            ELibrary::onlyTrashed()->where('creator_id', $userId)->get()->each(function ($item) use ($trashedItems, $userName) {
                $trashedItems->push($this->formatItem($item->id, $item->title, 'E-Libraries', $userName, $item->deleted_at));
            });

            // Advisory Classes
            AdvisoryClass::onlyTrashed()->where('teacher_id', $userId)->get()->each(function ($item) use ($trashedItems, $userName) {
                $trashedItems->push($this->formatItem($item->id, $item->section . ' (' . $item->school_year . ')', 'Advisory Classes', $userName, $item->deleted_at));
            });

            // Sort: Pinakabagong nabura ang nasa itaas
            $sortedItems = $trashedItems->sortByDesc('deleted_at')->values()->all();

            return response()->json($sortedItems, 200);

        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to load recycle bin data.', 'error' => $e->getMessage()], 500);
        }
    }

    // Display
    private function formatItem($id, $title, $type, $owner, $deletedAt)
    {
        return [
            'id' => $id,
            'title' => $title ?? 'Untitled Item',
            'type' => $type,
            'owner' => 'Me (' . $owner . ')',
            'deleted_at' => $deletedAt
        ];
    }

    // Categories
    private function getModelInstance($type)
    {
        return match ($type) {
            'Classrooms' => new Classroom(),
            'Classworks' => new Classwork(),
            'Forms' => new Form(),
            'Files' => new File(),
            'E-Libraries' => new ELibrary(),
            'Advisory Classes' => new AdvisoryClass(),
            default => null,
        };
    }

    // Double check kung si Teacher talaga ang may ari ng file bago i-restore
    private function verifyOwnership($record, $type, $userId)
    {
        if (!$record) return false;
        return match ($type) {
            'Classrooms', 'Forms', 'E-Libraries' => $record->creator_id === $userId,
            'Files' => $record->owner_id === $userId,
            'Advisory Classes' => $record->teacher_id === $userId,
            'Classworks' => $record->classroom()->withTrashed()->first()?->creator_id === $userId,
            default => false,
        };
    }

    // RESTORE 
    public function restore(Request $request)
    {
        $userId = Auth::id();
        $items = $request->input('items', []); 
        
        foreach ($items as $item) {
            $model = $this->getModelInstance($item['type']);
            if ($model) {
                $record = $model->withTrashed()->find($item['id']);
                // Titingnan kung sa kanya bago i-restore
                if ($this->verifyOwnership($record, $item['type'], $userId)) {
                    $record->restore();
                }
            }
        }
        
        return response()->json(['message' => 'Items successfully restored.']);
    }
}