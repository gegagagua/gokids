<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonType;

/**
 * @OA\Tag(
 *     name="Person Types",
 *     description="API Endpoints for managing person types"
 * )
 */
class PersonTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/person-types",
     *     operationId="getPersonTypes",
     *     tags={"Person Types"},
     *     summary="Get all person types",
     *     description="Retrieve a list of all person types",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Parent"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json(PersonType::all());
    }
}