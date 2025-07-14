<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GardenImage;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Garden Images",
 *     description="API Endpoints for managing garden images"
 * )
 */
class GardenImageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * @OA\Post(
     *     path="/api/garden-images",
     *     operationId="createGardenImage",
     *     tags={"Garden Images"},
     *     summary="Upload a new garden image",
     *     description="Upload a new image for a garden. Requires authentication.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title", "garden_id", "image"},
     *                 @OA\Property(property="title", type="string", maxLength=255, example="Main Entrance"),
     *                 @OA\Property(property="garden_id", type="integer", example=1),
     *                 @OA\Property(property="image", type="string", format="binary", description="Image file (jpg, png, etc.)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Garden image uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Main Entrance"),
     *             @OA\Property(property="garden_id", type="integer", example=1),
     *             @OA\Property(property="image", type="string", example="garden_images/abc123.jpg"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'garden_id' => 'required|exists:gardens,id',
            'image' => 'required|image|max:2048',
        ]);

        $path = $request->file('image')->store('garden_images', 'public');

        $gardenImage = GardenImage::create([
            'title' => $validated['title'],
            'garden_id' => $validated['garden_id'],
            'image' => $path,
        ]);

        return response()->json($gardenImage, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
