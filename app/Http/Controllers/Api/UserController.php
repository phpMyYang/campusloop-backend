<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('strand'); // I-load ang strand data 

        // Sorting by Role and Gender 
        if ($request->has('role') && $request->role != 'all') {
            $query->where('role', $request->role);
        }
        if ($request->has('gender') && $request->gender != 'all') {
            $query->where('gender', $request->gender);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get(), 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string',
            'birthday' => 'required|date',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:admin,teacher,student',
            'status' => 'required|in:active,inactive',
            'lrn' => 'required_if:role,student|nullable|unique:users,lrn',
            'strand_id' => 'required_if:role,student|nullable|exists:strands,id',
            'password' => ['nullable', 'string', Password::min(8)->letters()->mixedCase()->numbers()->symbols()]
        ]);

        $rawPassword = $request->password ? $request->password : Str::random(10);
        $validated['password'] = Hash::make($rawPassword);

        // GOAL 1: Laging NULL ang email_verified_at sa pag-create para piliting dumaan sa Verification Link.
        $validated['email_verified_at'] = null; 

        $user = User::create($validated);

        if (in_array($user->role, ['teacher', 'student'])) {
            $folderName = str_replace(' ', '_', strtolower($user->first_name . '_' . $user->last_name . '_' . $user->id));
            Storage::disk('public')->makeDirectory("users_files/{$folderName}");
        }

        // SEND WELCOME & VERIFICATION EMAIL
        $verifyLink = env('FRONTEND_URL') . '/verify?id=' . $user->id . '&hash=' . sha1($user->email);
        $loginLink = env('FRONTEND_URL') . '/login';

        Mail::send('emails.welcome_user', [
            'user' => $user,
            'rawPassword' => $rawPassword,
            'verifyLink' => $verifyLink,
            'loginLink' => $loginLink
        ], function($message) use($user) {
            $message->to($user->email);
            $message->subject('Welcome to CampusLoop - Your Account Details');
        });

        return response()->json(['message' => 'User created successfully!'], 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        // I-save ang kopya ng lumang data bago natin i-update
        $originalUser = clone $user;

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string',
            'birthday' => 'required|date',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|in:admin,teacher,student',
            'status' => 'required|in:active,inactive',
            'lrn' => ['required_if:role,student', 'nullable', Rule::unique('users')->ignore($user->id)],
            'strand_id' => 'required_if:role,student|nullable|exists:strands,id',
            'password' => ['nullable', 'string', Password::min(8)->letters()->mixedCase()->numbers()->symbols()]
        ]);

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        } else {
            unset($validated['password']);
        }

        // Logic para sa status change
        if ($request->status === 'inactive') {
            $validated['email_verified_at'] = null;
        } elseif ($request->status === 'active' && is_null($user->email_verified_at)) {
            $validated['email_verified_at'] = now();
        }

        $user->fill($validated);
        $dirtyAttributes = $user->getDirty(); 
        $user->save();

        $labels = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'gender' => 'Gender',
            'birthday' => 'Birthday',
            'email' => 'Email Address',
            'role' => 'Role',
            'status' => 'Account Status',
            'lrn' => 'LRN',
            'strand_id' => 'Strand ID'
        ];

        $changedFields = [];
        foreach ($dirtyAttributes as $key => $newValue) {
            if (array_key_exists($key, $labels) && $key !== 'password' && $key !== 'email_verified_at') {
                $changedFields[$labels[$key]] = [
                    'old' => $originalUser->{$key},
                    'new' => $newValue
                ];
            }
        }

        if ($request->filled('password')) {
            $changedFields['Password'] = [
                'old' => '********', // Nakatago ang lumang password para sa security
                'new' => $request->password // Ipapakita ang bagong raw password
            ];
        }

        // Magse-send lang ng email kung mayroong aktwal na nagbago
        if (count($changedFields) > 0) {
            Mail::send('emails.user_updated', [
                'user' => $user,
                'changedFields' => $changedFields
            ], function($message) use($user) {
                $message->to($user->email);
                $message->subject('CampusLoop - Account Information Updated');
            });
        }

        return response()->json(['message' => 'User updated successfully!'], 200);
    }

    public function destroy(Request $request, $id)
    {
        // Bawal i-delete ang sarili
        if ($request->user()->id == $id) {
            return response()->json(['message' => 'Action denied. You cannot delete your own account.'], 403);
        }

        $user = User::findOrFail($id);
        $user->delete(); 
        return response()->json(['message' => 'User moved to recycle bin.'], 200);
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        
        // I-filter ang array at tanggalin ang ID ng current Admin kung sakaling nasama
        $idsToDelete = array_filter($request->ids, function($id) use ($request) {
            return $id != $request->user()->id;
        });

        if (empty($idsToDelete)) {
            return response()->json(['message' => 'No valid users selected for deletion.'], 400);
        }

        User::whereIn('id', $idsToDelete)->delete(); 
        return response()->json(['message' => 'Selected users moved to recycle bin.'], 200);
    }
}