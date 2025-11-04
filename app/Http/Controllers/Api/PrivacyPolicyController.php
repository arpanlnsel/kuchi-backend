<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivacyPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Privacy Policy",
 *     description="API endpoints for Privacy Policy management"
 * )
 */
class PrivacyPolicyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/privacy-policy",
     *     summary="Get all Privacy Policy (Public)",
     *     tags={"Privacy Policy"},
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of privacy policies",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="total", type="integer", example=5),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="title", type="string", example="General Privacy Policy"),
     *                 @OA\Property(property="content", type="string", example="This privacy policy describes how we handle your data..."),
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
        $query = PrivacyPolicy::with('user:id,name,email');
        
        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        $privacyPolicies = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'total' => $privacyPolicies->count(),
            'data' => $privacyPolicies
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/privacy-policy/{id}",
     *     summary="Get single privacy policy by ID (Public)",
     *     tags={"Privacy Policy"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Privacy Policy ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Privacy policy details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Privacy policy not found")
     * )
     */
    public function show($id)
    {
        $privacyPolicy = PrivacyPolicy::with('user:id,name,email')->find($id);
        
        if (!$privacyPolicy) {
            return response()->json([
                'success' => false,
                'message' => 'Privacy policy not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $privacyPolicy
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/privacy-policy",
     *     summary="Create new privacy policy (Admin only)",
     *     tags={"Privacy Policy"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "content"},
     *             @OA\Property(property="title", type="string", example="General Privacy Policy"),
     *             @OA\Property(property="content", type="string", example="This privacy policy describes how we collect, use, and protect your personal data..."),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Privacy policy created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Privacy policy created successfully"),
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

        $privacyPolicy = PrivacyPolicy::create([
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => $request->is_active ?? true,
            'user_id' => Auth::id(),
        ]);

        $privacyPolicy->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'message' => 'Privacy policy created successfully',
            'data' => $privacyPolicy
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/privacy-policy/{id}",
     *     summary="Update privacy policy (Admin only)",
     *     tags={"Privacy Policy"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Privacy Policy ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Updated Privacy Policy"),
     *             @OA\Property(property="content", type="string", example="Updated privacy policy content..."),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Privacy policy updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Privacy policy updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Privacy policy not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $privacyPolicy = PrivacyPolicy::find($id);
        
        if (!$privacyPolicy) {
            return response()->json([
                'success' => false,
                'message' => 'Privacy policy not found'
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

        $privacyPolicy->update($request->only(['title', 'content', 'is_active']));
        
        $privacyPolicy->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'message' => 'Privacy policy updated successfully',
            'data' => $privacyPolicy
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/privacy-policy/{id}",
     *     summary="Delete privacy policy (Admin only)",
     *     tags={"Privacy Policy"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Privacy Policy ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Privacy policy deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Privacy policy deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Privacy policy not found")
     * )
     */
    public function destroy($id)
    {
        $privacyPolicy = PrivacyPolicy::find($id);
        
        if (!$privacyPolicy) {
            return response()->json([
                'success' => false,
                'message' => 'Privacy policy not found'
            ], 404);
        }

        $privacyPolicy->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Privacy policy deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/privacy-policy/latest/active",
     *     summary="Get latest active privacy policy (Public)",
     *     tags={"Privacy Policy"},
     *     @OA\Response(
     *         response=200,
     *         description="Latest active privacy policy",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No active privacy policy found")
     * )
     */
    public function getLatestActive()
    {
        $privacyPolicy = PrivacyPolicy::with('user:id,name,email')
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$privacyPolicy) {
            return response()->json([
                'success' => false,
                'message' => 'No active privacy policy found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $privacyPolicy
        ]);
    }
}