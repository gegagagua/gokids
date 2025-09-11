<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetOtpMail;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register new user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="phone", type="string", nullable=true),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password"),
     *             @OA\Property(property="type", type="string", enum={"user","admin","accountant","technical","garden","dister"}, nullable=true, description="User type (defaults to 'user' if not provided)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="User registered",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+995599123456", nullable=true),
     *                 @OA\Property(property="type", type="string", example="user"),
     *                 @OA\Property(property="type_display", type="string", example="მომხმარებელი")
     *             ),
     *             @OA\Property(property="token", type="string", example="1|abc123...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function register(Request $request)
    {
           $fields = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|confirmed',
            'type' => 'nullable|string|in:user,admin,accountant,technical,garden,dister',
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'phone' => $fields['phone'] ?? null,
            'password' => bcrypt($fields['password']),
            'type' => $fields['type'] ?? 'user', // Use provided type or default to 'user'
            'balance' => 0.00, // Default balance for new users
        ]);

        $token = $user->createToken('api_token')->plainTextToken;
  
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'type' => $user->type,
                'balance' => $user->balance,
            ],
            'token' => $token,
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Login user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login successful",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="type", type="string", example="garden"),
     *                 @OA\Property(property="type_display", type="string", example="ბაღი")
     *             ),
     *             @OA\Property(property="token", type="string", example="1|abc123..."),
     *             @OA\Property(property="garden", type="object", nullable=true, description="Garden data if user type is garden"),
     *             @OA\Property(property="dister", type="object", nullable=true, description="Dister data if user type is dister or if garden user has assigned dister (directly or via country ownership)")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request)
    {
        $fields = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $fields['email'])->first();

        if (!$user || !Hash::check($fields['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        $response = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type === 'user' ? 'admin' : $user->type,
                'type_display' => $user->type_display,
            ],
            'token' => $token,
        ];

        // Add balance for user type 'user'
        if ($user->type === 'user') {
            $response['user']['balance'] = $user->balance ?? 0.00;
        }

        if ($user->type === 'garden') {
            $garden = \App\Models\Garden::with(['city', 'country', 'images'])->where('email', $user->email)->first();
            if ($garden) {
                $response['garden'] = $garden;
                
                // First, try to find dister who has direct access to this garden
                $dister = \App\Models\Dister::whereJsonContains('gardens', $garden->id)->first();
                
                // If no direct dister found, try to find dister who owns the country where the garden is located
                if (!$dister && $garden->country) {
                    $dister = \App\Models\Dister::where('country', $garden->country->id)->first();
                }
                
                if ($dister) {
                    $dister->load(['country']);
                    $response['dister'] = $dister;
                }
            }
        } elseif ($user->type === 'dister') {
            $dister = \App\Models\Dister::with(['country'])->where('email', $user->email)->first();
            if ($dister) {
                $response['dister'] = $dister;
            }
        }

        return response()->json($response, 200);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Logout user",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Logged out")
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Change password for the authenticated user
     *
     * @OA\Post(
     *     path="/api/change-password",
     *     operationId="changePassword",
     *     tags={"Authentication"},
     *     summary="Change password for the authenticated user",
     *     description="Change password for the authenticated user (admin or garden). Requires current password, new password, and password confirmation.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "password", "password_confirmation"},
     *             @OA\Property(property="current_password", type="string", example="oldpassword"),
     *             @OA\Property(property="password", type="string", minLength=6, example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", minLength=6, example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password changed successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Current password is incorrect",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Current password is incorrect.")
     *         )
     *     )
     * )
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!\Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 400);
        }

        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    /**
     * @OA\Post(
     *     path="/api/request-password-reset",
     *     operationId="requestPasswordReset",
     *     tags={"Authentication"},
     *     summary="Request password reset (send OTP)",
     *     description="Send OTP code to user's email for password reset.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="OTP sent to email.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User not found.")
     *         )
     *     )
     * )
     */
    public function requestPasswordReset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $otp = rand(100000, 999999);
        $expiresAt = now()->addMinutes(10);

        \DB::table('password_resets')->updateOrInsert(
            ['email' => $request->email],
            [
                'otp' => $otp,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // აქ უნდა გაიგზავნოს მეილზე OTP
        Mail::to($user->email)->send(new PasswordResetOtpMail($otp));

        return response()->json(['message' => 'OTP sent to email.']);
    }

    /**
     * @OA\Post(
     *     path="/api/reset-password",
     *     operationId="resetPassword",
     *     tags={"Authentication"},
     *     summary="Reset password with OTP",
     *     description="Reset password using email, OTP code, new password and confirmation.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "otp", "password", "password_confirmation"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="otp", type="string", example="123456"),
     *             @OA\Property(property="password", type="string", minLength=6, example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", minLength=6, example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password reset successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid OTP or expired",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid or expired OTP.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User not found.")
     *         )
     *     )
     * )
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $reset = \DB::table('password_resets')
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$reset) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 400);
        }

        $user->password = bcrypt($request->password);
        $user->save();

        \DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successfully.']);
    }

    /**
     * Get authenticated user information
     *
     * @OA\Get(
     *     path="/api/me",
     *     operationId="getMe",
     *     tags={"Authentication"},
     *     summary="Get authenticated user information",
     *     description="Get current authenticated user information with the same format as login response",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User information retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="type", type="string", example="user"),
     *                 @OA\Property(property="type_display", type="string", example="მომხმარებელი"),
     *                 @OA\Property(property="balance", type="number", format="float", example=150.75, nullable=true)
     *             ),
     *             @OA\Property(property="garden", type="object", nullable=true, description="Garden data if user type is garden"),
     *             @OA\Property(property="dister", type="object", nullable=true, description="Dister data if user type is dister")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user();

        $response = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type === 'user' ? 'admin' : $user->type,
                'type_display' => $user->type_display,
            ],
        ];

        // Add balance for user type 'user'
        if ($user->type === 'user') {
            $response['user']['balance'] = $user->balance ?? 0.00;
        }

        if ($user->type === 'garden') {
            $garden = \App\Models\Garden::with(['city', 'country', 'images'])->where('email', $user->email)->first();
            if ($garden) {
                $response['garden'] = $garden;
                
                // First, try to find dister who has direct access to this garden
                $dister = \App\Models\Dister::whereJsonContains('gardens', $garden->id)->first();
                
                // If no direct dister found, try to find dister who owns the country where the garden is located
                if (!$dister && $garden->country) {
                    $dister = \App\Models\Dister::where('country', $garden->country->id)->first();
                }
                
                if ($dister) {
                    $dister->load(['country']);
                    $response['dister'] = $dister;
                }
            }
        } elseif ($user->type === 'dister') {
            $dister = \App\Models\Dister::with(['country'])->where('email', $user->email)->first();
            if ($dister) {
                $response['dister'] = $dister;
            }
        }

        return response()->json($response, 200);
    }

    /**
     * @OA\Put(
     *     path="/api/profile",
     *     operationId="updateProfile",
     *     tags={"Authentication"},
     *     summary="Update user profile",
     *     description="Update the authenticated user's profile information (name, email, phone)",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="Updated Name", description="User's full name"),
     *             @OA\Property(property="email", type="string", format="email", example="updated@example.com", description="User's email address"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+995599123456", nullable=true, description="User's phone number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Updated Name"),
     *                 @OA\Property(property="email", type="string", example="updated@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+995599123456", nullable=true),
     *                 @OA\Property(property="type", type="string", example="user"),
     *                 @OA\Property(property="type_display", type="string", example="მომხმარებელი"),
     *                 @OA\Property(property="balance", type="number", format="float", example=0.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'type' => $user->type,
                'type_display' => $user->type_display,
                'balance' => $user->balance,
            ]
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/staff-users",
     *     operationId="getStaffUsers",
     *     tags={"Authentication"},
     *     summary="Get staff users",
     *     description="Retrieve a list of all accountant and technical users",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Staff users retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="phone", type="string", example="+995599123456", nullable=true),
     *                     @OA\Property(property="type", type="string", example="accountant", enum={"accountant", "technical"}),
     *                     @OA\Property(property="type_display", type="string", example="ბუღალტერი"),
     *                     @OA\Property(property="balance", type="number", format="float", example=0.00),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=5),
     *             @OA\Property(property="accountants", type="integer", example=3),
     *             @OA\Property(property="technical", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function getStaffUsers(Request $request)
    {
        $staffUsers = User::whereIn('type', [User::TYPE_ACCOUNTANT, User::TYPE_TECHNICAL])
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $accountants = $staffUsers->where('type', User::TYPE_ACCOUNTANT)->count();
        $technical = $staffUsers->where('type', User::TYPE_TECHNICAL)->count();

        return response()->json([
            'data' => $staffUsers,
            'total' => $staffUsers->count(),
            'accountants' => $accountants,
            'technical' => $technical,
        ], 200);
    }

    /**
     * @OA\Patch(
     *     path="/api/users/{id}/change-type",
     *     operationId="changeUserType",
     *     tags={"Authentication"},
     *     summary="Change user type (Admin only)",
     *     description="Allow admin users to change the type of other users",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID to change type for",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type"},
     *             @OA\Property(property="type", type="string", enum={"user","admin","accountant","technical","garden","dister"}, example="admin", description="New user type")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User type changed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="User type updated successfully"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+995599123456", nullable=true),
     *                 @OA\Property(property="type", type="string", example="admin"),
     *                 @OA\Property(property="type_display", type="string", example="ადმინისტრატორი"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Only admins can change user types",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Only administrators can change user types")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="type", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function changeUserType(Request $request, $id)
    {
        // Check if the authenticated user is an admin
        $currentUser = $request->user();
        if ($currentUser->type !== 'admin') {
            return response()->json([
                'message' => 'Only administrators can change user types'
            ], 403);
        }

        // Validate the request
        $validated = $request->validate([
            'type' => 'required|string|in:user,admin,accountant,technical,garden,dister',
        ]);

        // Find the user to update
        $user = User::findOrFail($id);

        // Update the user type
        $user->update([
            'type' => $validated['type']
        ]);

        return response()->json([
            'message' => 'User type updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'type' => $user->type,
                'type_display' => $user->type_display,
                'updated_at' => $user->updated_at,
            ]
        ], 200);
    }
}
