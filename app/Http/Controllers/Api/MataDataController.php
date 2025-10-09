<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MataData;
use Illuminate\Http\Request;

class MataDataController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/mata-data",
     *     summary="Get all mata data with user information",
     *     tags={"MataData"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID (optional)",
     *         required=false,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of all mata data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="total", type="integer", example=10),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="mata_id", type="string", format="uuid"),
     *                     @OA\Property(property="device_name", type="string", example="iPhone 14 Pro"),
     *                     @OA\Property(property="device_type", type="string", example="mobile"),
     *                     @OA\Property(property="last_login_time", type="string", format="date-time"),
     *                     @OA\Property(property="user_id", type="string", format="uuid"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="string", format="uuid"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="email", type="string"),
     *                         @OA\Property(property="role", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $query = MataData::with('user:id,name,email,role');

        // Optional: Filter by user_id if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $mataData = $query->orderBy('last_login_time', 'desc')->get();

        return response()->json([
            'success' => true,
            'total' => $mataData->count(),
            'data' => $mataData
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/mata-data/{mata_id}",
     *     summary="Get specific mata data by ID",
     *     tags={"MataData"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="mata_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mata data details"
     *     ),
     *     @OA\Response(response=404, description="Mata data not found")
     * )
     */
    public function show($mata_id)
    {
        $mataData = MataData::with('user:id,name,email,role')->find($mata_id);

        if (!$mataData) {
            return response()->json([
                'success' => false,
                'message' => 'Mata data not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $mataData
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/mata-data/user/{user_id}",
     *     summary="Get all mata data for a specific user",
     *     tags={"MataData"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of mata data for the user"
     *     )
     * )
     */
    public function getByUser($user_id)
    {
        $mataData = MataData::where('user_id', $user_id)
            ->with('user:id,name,email,role')
            ->orderBy('last_login_time', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'total' => $mataData->count(),
            'data' => $mataData
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/mata-data/{mata_id}",
     *     summary="Delete mata data (Admin only)",
     *     tags={"MataData"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="mata_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mata data deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Mata data not found")
     * )
     */
    public function destroy($mata_id)
    {
        $mataData = MataData::find($mata_id);

        if (!$mataData) {
            return response()->json([
                'success' => false,
                'message' => 'Mata data not found'
            ], 404);
        }

        $mataData->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mata data deleted successfully'
        ]);
    }
}