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
     *                 @OA\Property(property="image", type="string", format="binary", description="Image file (jpg, png, etc.)"),
     *                 @OA\Property(property="index", type="integer", example=1, description="Index/order of the image")
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
     *             @OA\Property(property="index", type="integer", example=1),
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
            'index' => 'nullable|integer|min:0',
        ]);

        $path = $request->file('image')->store('garden_images', 'public');

        $gardenImage = GardenImage::create([
            'title' => $validated['title'],
            'garden_id' => $validated['garden_id'],
            'image' => $path,
            'index' => $validated['index'] ?? 0,
        ]);

        $response = $gardenImage->toArray();
        $response['image_url'] = $gardenImage->image_url;

        return response()->json($response, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * @OA\Put(
     *     path="/api/garden-images/{id}",
     *     operationId="updateGardenImage",
     *     tags={"Garden Images"},
     *     summary="Update a garden image",
     *     description="Update garden image information. Requires authentication.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the garden image to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255, example="Updated Title"),
     *             @OA\Property(property="index", type="integer", example=2, description="Index/order of the image")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Garden image updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Updated Title"),
     *             @OA\Property(property="garden_id", type="integer", example=1),
     *             @OA\Property(property="image", type="string", example="garden_images/abc123.jpg"),
     *             @OA\Property(property="index", type="integer", example=2),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garden image not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Garden image not found.")
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
    public function update(Request $request, string $id)
    {
        $gardenImage = GardenImage::find($id);
        if (!$gardenImage) {
            return response()->json(['message' => 'Garden image not found.'], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'index' => 'sometimes|nullable|integer|min:0',
        ]);

        $gardenImage->update($validated);

        $response = $gardenImage->toArray();
        $response['image_url'] = $gardenImage->image_url;

        return response()->json($response);
    }

    /**
     * @OA\Delete(
     *     path="/api/garden-images/{id}",
     *     operationId="deleteGardenImage",
     *     tags={"Garden Images"},
     *     summary="Delete a garden image",
     *     description="Deletes a garden image by ID. Requires authentication.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the garden image to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Garden image deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garden image not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Garden image not found.")
     *         )
     *     )
     * )
     */
    public function destroy(string $id)
    {
        $gardenImage = GardenImage::find($id);
        if (!$gardenImage) {
            return response()->json(['message' => 'Garden image not found.'], 404);
        }
        // Remove image file from storage
        if ($gardenImage->image && \Storage::disk('public')->exists($gardenImage->image)) {
            \Storage::disk('public')->delete($gardenImage->image);
        }
        $gardenImage->delete();
        return response()->noContent();
    }
}
