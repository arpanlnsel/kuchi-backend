<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TermsAndConditions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Terms & Conditions",
 *     description="API endpoints for Terms and Conditions management"
 * )
 */
class TermsAndConditionsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/terms-and-conditions",
     *     summary="Get all terms and conditions (Public)",
     *     tags={"Terms & Conditions"},
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of terms and conditions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="total", type="integer", example=5),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="title", type="string", example="General Terms"),
     *                 @OA\Property(property="content", type="string", example="These terms and conditions..."),
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
        $query = TermsAndConditions::with('user:id,name,email'); // Eager load user data
        
        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        $terms = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'total' => $terms->count(),
            'data' => $terms
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/terms-and-conditions/{id}",
     *     summary="Get single terms and conditions by ID (Public)",
     *     tags={"Terms & Conditions"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Terms and Conditions ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Terms and conditions details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Terms and conditions not found")
     * )
     */
    public function show($id)
    {
        $terms = TermsAndConditions::with('user:id,name,email')->find($id); // Eager load user data
        
        if (!$terms) {
            return response()->json([
                'success' => false,
                'message' => 'Terms and conditions not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $terms
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/terms-and-conditions",
     *     summary="Create new terms and conditions (Admin only)",
     *     tags={"Terms & Conditions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "content"},
     *             @OA\Property(property="title", type="string", example="General Terms"),
     *             @OA\Property(property="content", type="string", example="These terms and conditions govern your use of our website..."),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Terms and conditions created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Terms and conditions created successfully"),
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

        $terms = TermsAndConditions::create([
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => $request->is_active ?? true,
            'user_id' => Auth::id(), // Add the authenticated user's ID
        ]);

        // Load user relationship for response
        $terms->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'message' => 'Terms and conditions created successfully',
            'data' => $terms
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/terms-and-conditions/{id}",
     *     summary="Update terms and conditions (Admin only)",
     *     tags={"Terms & Conditions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Terms and Conditions ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Updated General Terms"),
     *             @OA\Property(property="content", type="string", example="Updated content..."),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Terms and conditions updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Terms and conditions updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Terms and conditions not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $terms = TermsAndConditions::find($id);
        
        if (!$terms) {
            return response()->json([
                'success' => false,
                'message' => 'Terms and conditions not found'
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

        $terms->update($request->only(['title', 'content', 'is_active']));
        
        // Load user relationship for response
        $terms->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'message' => 'Terms and conditions updated successfully',
            'data' => $terms
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/terms-and-conditions/{id}",
     *     summary="Delete terms and conditions (Admin only)",
     *     tags={"Terms & Conditions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Terms and Conditions ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Terms and conditions deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Terms and conditions deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Terms and conditions not found")
     * )
     */
    public function destroy($id)
    {
        $terms = TermsAndConditions::find($id);
        
        if (!$terms) {
            return response()->json([
                'success' => false,
                'message' => 'Terms and conditions not found'
            ], 404);
        }

        $terms->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Terms and conditions deleted successfully'
        ]);
    }
}