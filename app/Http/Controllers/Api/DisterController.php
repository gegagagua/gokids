<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Dister;
use App\Models\Country;
use App\Models\City;
use App\Models\Garden;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @OA\Tag(
 *     name="Disters",
 *     description="API Endpoints for managing distributors"
 * )
 */
class DisterController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/disters",
     *     operationId="getDisters",
     *     tags={"Disters"},
     *     summary="Get all disters",
     *     description="Retrieve a paginated list of all distributors with their associated country and city information.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, description="Search in first_name, last_name, email fields", @OA\Schema(type="string")),
     *     @OA\Parameter(name="country_id", in="query", required=false, description="Filter by country ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="city_id", in="query", required=false, description="Filter by city ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Items per page (pagination). Default: 15", @OA\Schema(type="integer", default=15, minimum=1, maximum=100)),
     *     @OA\Parameter(name="page", in="query", required=false, description="Page number for pagination. Default: 1", @OA\Schema(type="integer", default=1, minimum=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="first_name", type="string", example="John"),
     *                     @OA\Property(property="last_name", type="string", example="Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="phone", type="string", example="+995599123456"),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="city_id", type="integer", example=1),
      *                     @OA\Property(property="gardens", type="array", @OA\Items(type="integer"), example={1, 2, 3}),
      *                     @OA\Property(property="percent", type="number", format="float", example=12.5, nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
      *                     @OA\Property(property="country", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Georgia")
     *                     ),
      *                     @OA\Property(property="city", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Tbilisi")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="first_page_url", type="string", example="http://localhost/api/disters?page=1"),
     *             @OA\Property(property="from", type="integer", example=1, description="First item number on current page"),
     *             @OA\Property(property="last_page", type="integer", example=5, description="Last page number"),
     *             @OA\Property(property="last_page_url", type="string", example="http://localhost/api/disters?page=5"),
     *             @OA\Property(property="next_page_url", type="string", example="http://localhost/api/disters?page=2", nullable=true),
     *             @OA\Property(property="path", type="string", example="http://localhost/api/disters"),
     *             @OA\Property(property="per_page", type="integer", example=15, description="Items per page"),
     *             @OA\Property(property="prev_page_url", type="string", example=null, nullable=true),
     *             @OA\Property(property="to", type="integer", example=15, description="Last item number on current page"),
     *             @OA\Property(property="total", type="integer", example=50, description="Total number of items")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Dister::with(['country', 'city']);
        
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }
        
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->query('country_id'));
        }
        
        if ($request->filled('city_id')) {
            $query->where('city_id', $request->query('city_id'));
        }
        
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);
        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @OA\Get(
     *     path="/api/disters/{id}",
     *     operationId="getDister",
     *     tags={"Disters"},
     *     summary="Get a specific dister",
     *     description="Retrieve detailed information about a specific distributor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Dister ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="gardens", type="array", @OA\Items(type="integer"), example={1, 2, 3}),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(property="country", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Georgia")
     *             ),
     *             @OA\Property(property="city", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Tbilisi")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Dister not found"
     *     )
     * )
     */
    public function show($id)
    {
        $dister = Dister::with(['country', 'city'])->findOrFail($id);
        return response()->json($dister);
    }

    /**
     * @OA\Post(
     *     path="/api/disters",
     *     operationId="createDister",
     *     tags={"Disters"},
     *     summary="Create a new dister",
     *     description="Create a new distributor with the provided information",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name", "last_name", "email", "password", "country_id", "city_id"},
     *             @OA\Property(property="first_name", type="string", maxLength=255, example="John", description="First name"),
     *             @OA\Property(property="last_name", type="string", maxLength=255, example="Doe", description="Last name"),
     *             @OA\Property(property="email", type="string", maxLength=255, example="john@example.com", description="Email address"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+995599123456", description="Phone number"),
     *             @OA\Property(property="password", type="string", minLength=6, example="password123", description="Password"),
      *             @OA\Property(property="country_id", type="integer", example=1, description="Country ID"),
      *             @OA\Property(property="city_id", type="integer", example=1, description="City ID"),
      *             @OA\Property(property="gardens", type="array", @OA\Items(type="integer"), example={1, 2, 3}, description="Array of garden IDs (optional)"),
      *             @OA\Property(property="percent", type="number", format="float", example=12.5, nullable=true, description="Optional percent value (0-100)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Dister created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Dister created successfully"),
     *             @OA\Property(property="dister", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+995599123456"),
     *                 @OA\Property(property="country_id", type="integer", example=1),
     *                 @OA\Property(property="city_id", type="integer", example=1),
      *                 @OA\Property(property="gardens", type="array", @OA\Items(type="integer"), example={1, 2, 3}),
      *                 @OA\Property(property="percent", type="number", format="float", example=12.5, nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="type", type="string", example="dister")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:disters|unique:users',
                'phone' => 'nullable|string|max:20',
                'password' => 'required|string|min:6',
                'country_id' => 'required|exists:countries,id',
                'city_id' => 'required|exists:cities,id',
                'gardens' => 'nullable|array',
                'gardens.*' => 'integer|exists:gardens,id',
                'percent' => 'nullable|numeric|min:0|max:100',
            ]);

            // Create User account first
            $user = \App\Models\User::create([
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'type' => 'dister',
            ]);

            // Create Dister
            $disterData = $validated;
            $disterData['password'] = Hash::make($validated['password']);

            // If the creator is an authenticated dister, store creator info in main_dister
            if ($request->user() instanceof \App\Models\Dister) {
                $creator = $request->user();
                $disterData['main_dister'] = [
                    'id' => $creator->id,
                ];
            } else {
                // Fallback: extract bearer token manually (route might not be under auth middleware)
                $plainToken = $request->bearerToken();
                if ($plainToken) {
                    $accessToken = PersonalAccessToken::findToken($plainToken);
                    if ($accessToken) {
                        $tokenable = $accessToken->tokenable;
                        if ($tokenable instanceof \App\Models\Dister) {
                            /** @var \App\Models\Dister $creator */
                            $creator = $tokenable;
                            $disterData['main_dister'] = [ 'id' => $creator->id ];
                        } elseif ($tokenable instanceof \App\Models\User && $tokenable->type === 'dister') {
                            // Map user token to corresponding dister by email
                            $creatorDister = \App\Models\Dister::where('email', $tokenable->email)->first();
                            if ($creatorDister) {
                                $disterData['main_dister'] = [ 'id' => $creatorDister->id ];
                            }
                        }
                    }
                }
            }
            
            $dister = Dister::create($disterData);
            $dister->load(['country', 'city']);

            return response()->json([
                'message' => 'Dister created successfully',
                'dister' => $dister,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => $user->type,
                ]
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Dister creation error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create dister: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/disters/{id}",
     *     operationId="updateDister",
     *     tags={"Disters"},
     *     summary="Update a dister",
     *     description="Update an existing distributor with new information",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Dister ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", maxLength=255, example="John", description="First name"),
     *             @OA\Property(property="last_name", type="string", maxLength=255, example="Doe", description="Last name"),
     *             @OA\Property(property="email", type="string", maxLength=255, example="john@example.com", description="Email address"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+995599123456", description="Phone number"),
      *             @OA\Property(property="country_id", type="integer", example=1, description="Country ID"),
      *             @OA\Property(property="city_id", type="integer", example=1, description="City ID"),
      *             @OA\Property(property="gardens", type="array", @OA\Items(type="integer"), example={1, 2, 3}, description="Array of garden IDs"),
      *             @OA\Property(property="percent", type="number", format="float", example=12.5, nullable=true, description="Optional percent value (0-100)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dister updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
      *             @OA\Property(property="country_id", type="integer", example=1),
      *             @OA\Property(property="city_id", type="integer", example=1),
      *             @OA\Property(property="gardens", type="array", @OA\Items(type="integer"), example={1, 2, 3}),
      *             @OA\Property(property="percent", type="number", format="float", example=12.5, nullable=true),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Dister not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $dister = Dister::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:disters,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'country_id' => 'sometimes|required|exists:countries,id',
            'city_id' => 'sometimes|required|exists:cities,id',
            'gardens' => 'nullable|array',
            'gardens.*' => 'integer|exists:gardens,id',
            'percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $dister->update($validated);
        $dister->load(['country', 'city']);

        return response()->json($dister);
    }

    /**
     * @OA\Patch(
     *     path="/api/disters/{id}/change-password",
     *     operationId="changeDisterPassword",
     *     tags={"Disters"},
     *     summary="Change dister password",
     *     description="Change the password for a specific distributor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Dister ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="password", type="string", minLength=6, example="newpassword123", description="New password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Password changed successfully"),
     *             @OA\Property(property="dister_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Dister not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function changePassword(Request $request, $id)
    {
        $dister = Dister::findOrFail($id);

        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        // Update password in both dister and user tables
        $dister->update([
            'password' => Hash::make($validated['password'])
        ]);

        // Also update the corresponding user account
        $user = \App\Models\User::where('email', $dister->email)->first();
        if ($user) {
            $user->update([
                'password' => Hash::make($validated['password'])
            ]);
        }

        return response()->json([
            'message' => 'Password changed successfully',
            'dister_id' => $dister->id
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/disters/{id}",
     *     operationId="deleteDister",
     *     tags={"Disters"},
     *     summary="Delete a dister",
     *     description="Permanently delete a distributor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Dister ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dister deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Dister deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Dister not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $dister = Dister::findOrFail($id);
        $dister->delete();

        return response()->json(['message' => 'Dister deleted']);
    }

    /**
     * @OA\Post(
     *     path="/api/disters/login",
     *     operationId="disterLogin",
     *     tags={"Disters"},
     *     summary="Dister login",
     *     description="Authenticate a distributor using email and password",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", example="john@example.com", description="Email address"),
     *             @OA\Property(property="password", type="string", example="password123", description="Password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="token", type="string", example="1|abc123..."),
     *             @OA\Property(property="dister", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+995599123456"),
     *                 @OA\Property(property="country_id", type="integer", example=1),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="gardens", type="array", @OA\Items(type="integer"), example={1, 2, 3}),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="country", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Georgia")
     *                 ),
     *                 @OA\Property(property="city", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Tbilisi")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $dister = Dister::where('email', $request->email)->first();

        if (!$dister || !Hash::check($request->password, $dister->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $dister->createToken('dister-token')->plainTextToken;
        $dister->load(['country', 'city']);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'dister' => $dister
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/disters/logout",
     *     operationId="disterLogout",
     *     tags={"Disters"},
     *     summary="Dister logout",
     *     description="Logout the authenticated distributor",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/disters/profile",
     *     operationId="getDisterProfile",
     *     tags={"Disters"},
     *     summary="Get dister profile",
     *     description="Get the authenticated distributor's profile information",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="gardens", type="array", @OA\Items(type="integer"), example={1, 2, 3}),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(property="country", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Georgia")
     *             ),
     *             @OA\Property(property="city", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Tbilisi")
     *             )
     *         )
     *     )
     * )
     */
    public function profile(Request $request)
    {
        $dister = $request->user()->load(['country', 'city']);
        return response()->json($dister);
    }
}
