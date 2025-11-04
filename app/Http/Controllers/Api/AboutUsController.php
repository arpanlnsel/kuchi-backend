<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AboutUs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="About Us",
 *     description="API endpoints for About Us management"
 * )
 */
class AboutUsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/about-us",
     *     summary="Get all About Us (Public)",
     *     tags={"About Us"},
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of about us entries",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="total", type="integer", example=5),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="title", type="string", example="About Our Company"),
     *                 @OA\Property(property="content", type="string", example="This is about our company history and mission..."),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="user_id", type="string", format="uuid"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="user", type="object", 
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="email", type="string")
     *                 )
     *             ))
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = AboutUs::with('user:id,name,email');
        
        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        $aboutUsEntries = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'total' => $aboutUsEntries->count(),
            'data' => $aboutUsEntries
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/about-us/{id}",
     *     summary="Get single about us entry by ID (Public)",
     *     tags={"About Us"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="About Us ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="About us entry details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="About us entry not found")
     * )
     */
    public function show($id)
    {
        $aboutUs = AboutUs::with('user:id,name,email')->find($id);
        
        if (!$aboutUs) {
            return response()->json([
                'success' => false,
                'message' => 'About us entry not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $aboutUs
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/about-us",
     *     summary="Create new about us entry (Admin only)",
     *     tags={"About Us"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "content"},
     *             @OA\Property(property="title", type="string", example="About Our Company"),
     *             @OA\Property(property="content", type="string", example="This is about our company history and mission..."),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="About us entry created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="About us entry created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $aboutUs = AboutUs::create([
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => $request->is_active ?? true,
            'user_id' => Auth::id(),
        ]);

        $aboutUs->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'message' => 'About us entry created successfully',
            'data' => $aboutUs
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/about-us/{id}",
     *     summary="Update about us entry (Admin only)",
     *     tags={"About Us"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="About Us ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Updated About Us"),
     *             @OA\Property(property="content", type="string", example="Updated about us content..."),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="About us entry updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="About us entry updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="About us entry not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $aboutUs = AboutUs::find($id);
        
        if (!$aboutUs) {
            return response()->json([
                'success' => false,
                'message' => 'About us entry not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $aboutUs->update($request->only(['title', 'content', 'is_active']));
        
        $aboutUs->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'message' => 'About us entry updated successfully',
            'data' => $aboutUs
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/about-us/{id}",
     *     summary="Delete about us entry (Admin only)",
     *     tags={"About Us"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="About Us ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="About us entry deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="About us entry deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="About us entry not found")
     * )
     */
    public function destroy($id)
    {
        $aboutUs = AboutUs::find($id);
        
        if (!$aboutUs) {
            return response()->json([
                'success' => false,
                'message' => 'About us entry not found'
            ], 404);
        }

        $aboutUs->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'About us entry deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/about-us/latest/active",
     *     summary="Get latest active about us entry (Public)",
     *     tags={"About Us"},
     *     @OA\Response(
     *         response=200,
     *         description="Latest active about us entry",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No active about us entry found")
     * )
     */
    public function getLatestActive()
    {
        $aboutUs = AboutUs::with('user:id,name,email')
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$aboutUs) {
            return response()->json([
                'success' => false,
                'message' => 'No active about us entry found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $aboutUs
        ]);
    }
}