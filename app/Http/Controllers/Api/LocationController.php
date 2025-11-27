<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/location",
     *     summary="Save or update user location",
     *     tags={"User Location"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"lat", "long"},
     *             @OA\Property(property="lat", type="number", format="float", example=22.5726, description="Latitude"),
     *             @OA\Property(property="long", type="number", format="float", example=88.3639, description="Longitude"),
     *             @OA\Property(property="city", type="string", example="Kolkata", description="City name"),
     *             @OA\Property(property="country_code", type="string", example="IN", description="Country code (ISO 3166-1 alpha-2)"),
     *             @OA\Property(property="currency_code", type="string", example="INR", description="Currency code (ISO 4217)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Location saved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Location saved successfully"),
     *             @OA\Property(property="location", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function saveLocation(Request $request)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'long' => 'required|numeric|between:-180,180',
            'city' => 'nullable|string|max:255',
            'country_code' => 'nullable|string|max:3',
            'currency_code' => 'nullable|string|max:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $location = UserLocation::create([
            'user_id' => $user->id,
            'latitude' => $request->lat,
            'longitude' => $request->long,
            'city' => $request->city,
            'country_code' => $request->country_code,
            'currency_code' => $request->currency_code,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Location saved successfully',
            'location' => $location
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/location",
     *     summary="Get user's latest location",
     *     tags={"User Location"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Latest location retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="location", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="No location found")
     * )
     */
    public function getLatestLocation()
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $location = UserLocation::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'No location found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'location' => $location
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/location/{id}",
     *     summary="Get location by ID",
     *     tags={"User Location"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Location ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="location", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Location not found")
     * )
     */
    public function getLocationById($id)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $location = UserLocation::find($id);

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        if ($location->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this location'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'location' => $location
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/location/history",
     *     summary="Get user's location history with pagination",
     *     tags={"User Location"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Location history retrieved successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getLocationHistory(Request $request)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $perPage = $request->get('per_page', 15);
        $locations = UserLocation::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $locations->items(),
            'pagination' => [
                'current_page' => $locations->currentPage(),
                'per_page' => $locations->perPage(),
                'total' => $locations->total(),
                'last_page' => $locations->lastPage(),
                'from' => $locations->firstItem(),
                'to' => $locations->lastItem()
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/location/{id}",
     *     summary="Delete a specific location",
     *     tags={"User Location"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Location ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location deleted successfully"
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Location not found")
     * )
     */
    public function deleteLocation($id)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $location = UserLocation::find($id);

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        if ($location->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this location'
            ], 403);
        }

        $location->delete();

        return response()->json([
            'success' => true,
            'message' => 'Location deleted successfully'
        ]);
    }
}