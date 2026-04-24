<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\ActivityLog;
use App\Rules\Recaptcha;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;


class AuthController extends Controller
{
    // LOGIN LOGIC
    public function login(Request $request)
    {
        // Validation
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'g-recaptcha-response' => ['required', new Recaptcha()] 
        ]);

        $user = User::where('email', $request->email)->first();

        // Check Credentials
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // Check kung Active at Verified
        if ($user->status !== 'active' || is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'Account is inactive or not verified.',
                'require_verification' => true,
                'email' => $user->email // Ipapasa pabalik para magamit sa frontend resend
            ], 403);
        }

        // Single Session Policy para sa lahat ng users (o specifically for students) 
        // Automatic na buburahin ang lumang tokens para ma-logout ang lumang device
        $user->tokens()->delete();

        // Generate New Token at Update Last Login
        $token = $user->createToken('campusloop-session')->plainTextToken;
        
        $user->update([
            'last_login_at' => now(),
            'current_session_id' => hash('sha256', $token) // Tracking reference
        ]);

        // ACTIVITY LOG
        ActivityLog::create([
            'user_id' => $user->id, // Ginamit ang $user->id dahil wala pang Auth state si Request dito
            'action' => 'Logged In',
            'description' => 'Successfully logged into the system.'
        ]);

        // Success Response (Redirecting to Dashboard based on role)
        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
            'role' => $user->role
        ], 200);
    }

    // LOGOUT LOGIC
    public function logout(Request $request)
    {
        $userId = $request->user()->id;

        // ACTIVITY LOG
        ActivityLog::create([
            'user_id' => $userId,
            'action' => 'Logged Out',
            'description' => 'Securely logged out of the system.'
        ]);

        // Burahin ang current token ng user
        $request->user()->currentAccessToken()->delete();

        // Clear ang session tracking
        $request->user()->update(['current_session_id' => null]);

        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    // FORGOT PASSWORD LOGIC
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'g-recaptcha-response' => ['required', new Recaptcha()]
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'If your email is registered, you will receive a secure reset link shortly.'], 200);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $token, 'created_at' => Carbon::now()]
        );

        $resetLink = env('FRONTEND_URL') . '/reset-password?token=' . $token . '&email=' . urlencode($request->email);

        Mail::send('emails.reset_password', ['resetLink' => $resetLink, 'user' => $user], function($message) use($request){
            $message->to($request->email);
            $message->subject('Reset Your CampusLoop Password');
        });

        // ACTIVITY LOG 
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Requested Password Reset',
            'description' => 'Requested a secure link to reset account password.'
        ]);

        return response()->json(['message' => 'If your email is registered, you will receive a secure reset link shortly.'], 200);
    }

    // RESET PASSWORD LOGIC
    public function resetPassword(Request $request)
    {
        // Strict Validation
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => [
                'required',
                'confirmed', // Hahanapin ang password_confirmation field galing sa React
                Password::min(8) // 8 characters pataas
                    ->letters()  // May letters (uppercase & lowercase)
                    ->mixedCase()
                    ->numbers()  // May numbers
                    ->symbols()  // May special characters
            ]
        ]);

        // I-verify ang Token at Email sa database
        $resetRequest = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRequest || $resetRequest->token !== $request->token) {
            return response()->json(['message' => 'Invalid or expired reset token.'], 400);
        }

        if (Carbon::parse($resetRequest->created_at)->addHour()->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'Reset link has expired. Please request a new one.'], 400);
        }

        // I-update ang Password ng User
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Burahin ang ginamit na token para hindi na magamit ulit
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // ACTIVITY LOG 
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Reset Account Password',
            'description' => 'Successfully changed the account password.'
        ]);

        // I-return ang status at verification info para alam ng React kung saan ire-redirect
        return response()->json([
            'message' => 'Password has been successfully reset.',
            'status' => $user->status,
            'is_verified' => !is_null($user->email_verified_at),
            'role' => $user->role
        ], 200);
    }

    // EMAIL VERIFICATION LOGIC
    public function resendVerificationEmail(Request $request)
    {
        // Validation
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Account is already verified.'], 400);
        }

        $expires = now()->addHour()->timestamp;

        // Generate Secure Hash kasama ang expiration
        $hash = hash_hmac('sha256', $user->email . $expires, config('app.key'));
        
        // Buuin ang Verification Link pabalik sa React Frontend (Verify Page)
        $verifyLink = env('FRONTEND_URL') . '/verify?id=' . $user->id . '&hash=' . $hash . '&expires=' . $expires . '&email=' . urlencode($user->email);

        // I-send ang Email gamit ang Laravel Mail
        Mail::send('emails.verify_email', ['verifyLink' => $verifyLink, 'user' => $user], function($message) use($user){
            $message->to($user->email);
            $message->subject('Verify Your CampusLoop Account');
        });

        // ACTIVITY LOG 
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Requested Verification Email',
            'description' => 'Requested a new email verification link.'
        ]);

        return response()->json(['message' => 'Verification email sent successfully.'], 200);
    }

    // VERIFY EMAIL LOGIC
    public function verifyEmail(Request $request)
    {
        // REQUIRED ang expires para mamatay ang mga lumang links
        $request->validate([
            'id' => 'required',
            'hash' => 'required',
            'expires' => 'required' 
        ], [
            'expires.required' => 'This verification link is outdated and no longer valid. Please request a new one.'
        ]);

        $user = User::find($request->id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // ONE-TIME USE - Kung verified it means nagamit na ang link
        if ($user->email_verified_at) {
            return response()->json(['message' => 'This verification link has already been used. Your account is already active.'], 400);
        }

        // EXPIRATION CHECK (Strict 1 Hour)
        if (now()->timestamp > $request->expires) {
            return response()->json(['message' => 'Verification link has expired. Please request a new one.'], 400);
        }
        
        $expectedHash = hash_hmac('sha256', $user->email . $request->expires, config('app.key'));
        if (!hash_equals($expectedHash, $request->hash)) {
            return response()->json(['message' => 'Invalid verification link.'], 400);
        }

        $user->update([
            'email_verified_at' => now(),
            'status' => 'active' 
        ]);

        // ACTIVITY LOG
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Verified Account',
            'description' => 'Successfully verified email address and activated the account.'
        ]);

        return response()->json([
            'message' => 'Account successfully verified and activated.',
            'status' => $user->status,
            'role' => $user->role
        ], 200);
    }
}