<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    // View School Setting
    public function index()
    {
        $activeSetting = SystemSetting::where('is_active', true)->first();
        return response()->json($activeSetting, 200);
    }

    // Set School Setting
    public function store(Request $request)
    {
        $validated = $request->validate([
            'school_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['required', 'in:1st,2nd'] 
        ], [
            'semester.in' => 'Invalid semester selected. It must be either 1st or 2nd.'
        ]);

        SystemSetting::where('is_active', true)->update(['is_active' => false]);

        $newSetting = SystemSetting::create([
            'school_year' => $validated['school_year'],
            'semester' => $validated['semester'],
            'maintenance_mode' => false,
            'is_active' => true
        ]);

        return response()->json([
            'message' => 'School Settings successfully updated!',
            'setting' => $newSetting
        ], 200);
    }

    // Reset School Setting
    public function reset()
    {
        $activeSetting = SystemSetting::where('is_active', true)->first();
        
        if ($activeSetting) {
            $activeSetting->delete();
        }

        return response()->json(['message' => 'School settings have been completely reset.'], 200);
    }
}