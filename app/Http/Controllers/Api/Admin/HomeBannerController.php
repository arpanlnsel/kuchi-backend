<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HomeBannerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/home-banner",
     *     summary="Get all home banners (Public - No authentication required)",
     *     tags={"Home Banner"},
     *     @OA\Parameter(
     *         name="device_type",
     *         in="query",
     *         description="Filter by device type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"mobile", "desktop", "tablet", "all"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of home banners",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="total", type="integer", example=5),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = HomeBanner::with('creator:id,name,email');

        // Filter by device type if provided
        if ($request->has('device_type')) {
            $query->where('device_type', $request->device_type);
        }

        $banners = $query->orderBy('priority', 'asc')->get();

        // Add full image URL to each banner
        $banners->each(function ($banner) {
            $banner->image_url = $banner->image_url;
        });

        return response()->json([
            'success' => true,
            'total' => $banners->count(),
            'data' => $banners
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/home-banner",
     *     summary="Create a new home banner (Admin only)",
     *     tags={"Home Banner (Admin)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"banner_title", "priority", "device_type", "image"},
     *                 @OA\Property(property="banner_title", type="string", example="Summer Sale Banner"),
     *                 @OA\Property(property="priority", type="integer", example=1),
     *                 @OA\Property(property="device_type", type="string", enum={"mobile", "desktop", "tablet", "all"}, example="all"),
     *                 @OA\Property(property="image", type="string", format="binary", description="Banner image file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Banner created successfully"
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin only")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'banner_title' => 'required|string|max:255',
            'priority' => 'required|integer|min:0',
            'device_type' => 'required|in:mobile,desktop,tablet,all',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle image upload
        $imageName = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            
            // Store image in public/storage/banners directory
            $image->storeAs('public/banners', $imageName);
        }

        // Create banner
        $banner = HomeBanner::create([
            'banner_title' => $request->banner_title,
            'priority' => $request->priority,
            'device_type' => $request->device_type,
            'image' => $imageName,
            'create_user_id' => auth()->id(),
        ]);

        $banner->load('creator:id,name,email');
        $banner->image_url = $banner->image_url;

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully',
            'data' => $banner
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/home-banner/{id}",
     *     summary="Get a specific home banner (Public - No authentication required)",
     *     tags={"Home Banner"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Banner details"
     *     ),
     *     @OA\Response(response=404, description="Banner not found")
     * )
     */
    public function show($id)
    {
        $banner = HomeBanner::with('creator:id,name,email')->find($id);

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found'
            ], 404);
        }

        $banner->image_url = $banner->image_url;

        return response()->json([
            'success' => true,
            'data' => $banner
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/home-banner/{id}",
     *     summary="Update a home banner (Admin only)",
     *     tags={"Home Banner (Admin)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="_method", type="string", example="PUT"),
     *                 @OA\Property(property="banner_title", type="string", example="Updated Banner Title"),
     *                 @OA\Property(property="priority", type="integer", example=2),
     *                 @OA\Property(property="device_type", type="string", enum={"mobile", "desktop", "tablet", "all"}),
     *                 @OA\Property(property="image", type="string", format="binary", description="New banner image (optional)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Banner updated successfully"
     *     ),
     *     @OA\Response(response=404, description="Banner not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin only")
     * )
     */
    public function update(Request $request, $id)
    {
        $banner = HomeBanner::find($id);

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'banner_title' => 'sometimes|string|max:255',
            'priority' => 'sometimes|integer|min:0',
            'device_type' => 'sometimes|in:mobile,desktop,tablet,all',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle image upload if new image provided
        if ($request->hasFile('image')) {
            // Delete old image
            if ($banner->image) {
                Storage::delete('public/banners/' . $banner->image);
            }

            $image = $request->file('image');
            $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            $image->storeAs('public/banners', $imageName);
            
            $banner->image = $imageName;
        }

        // Update other fields
        if ($request->has('banner_title')) {
            $banner->banner_title = $request->banner_title;
        }
        if ($request->has('priority')) {
            $banner->priority = $request->priority;
        }
        if ($request->has('device_type')) {
            $banner->device_type = $request->device_type;
        }

        $banner->save();
        $banner->load('creator:id,name,email');
        $banner->image_url = $banner->image_url;

        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully',
            'data' => $banner
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/home-banner/{id}",
     *     summary="Delete a home banner (Admin only)",
     *     tags={"Home Banner (Admin)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Banner deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Banner not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin only")
     * )
     */
    public function destroy($id)
    {
        $banner = HomeBanner::find($id);

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found'
            ], 404);
        }

        // Delete image file
        if ($banner->image) {
            Storage::delete('public/banners/' . $banner->image);
        }

        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully'
        ]);
    }
}