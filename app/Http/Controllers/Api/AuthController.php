<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MataData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Info(
 *     title="Kuchi Backend API",
 *     version="1.0.0",
 *     description="API documentation for Kuchi Backend with JWT Authentication",
 *     @OA\Contact(
 *         email="admin@kuchi.com"
 *     )
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     summary="Register a new user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","role"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="role", type="string", enum={"admin", "sales"}, example="sales")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User registered successfully"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,sales',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Login user and auto-save device metadata",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="device_name", type="string", example="iPhone 14 Pro"),
     *             @OA\Property(property="device_type", type="string", example="mobile")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer"),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="mata_data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        
        // Check if user exists
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Check if user is active
        if (!$user->isActive) {
            return response()->json(['error' => 'User account is inactive'], 403);
        }
        
        // Check password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Password does not match'], 401);
        }
        
        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = auth()->user();
        $mataData = $this->saveMataData($request, $user->id);
        
        return $this->respondWithToken($token, $mataData);
    }

    /**
     * Save mata data on login
     */
    private function saveMataData(Request $request, $userId)
    {
        // Get device information from request or user agent
        $deviceName = $request->input('device_name', $this->getDeviceNameFromUserAgent($request));
        $deviceType = $request->input('device_type', $this->getDeviceTypeFromUserAgent($request));

        // Create mata_data record
        $mataData = MataData::create([
            'device_name' => $deviceName,
            'device_type' => $deviceType,
            'last_login_time' => now(),
            'user_id' => $userId,
        ]);

        return $mataData;
    }

    /**
     * Extract device name from user agent
     */
    private function getDeviceNameFromUserAgent(Request $request)
    {
        $userAgent = $request->header('User-Agent');
        
        // Simple device detection
        if (stripos($userAgent, 'iPhone') !== false) {
            return 'iPhone';
        } elseif (stripos($userAgent, 'iPad') !== false) {
            return 'iPad';
        } elseif (stripos($userAgent, 'Android') !== false) {
            return 'Android Device';
        } elseif (stripos($userAgent, 'Windows') !== false) {
            return 'Windows PC';
        } elseif (stripos($userAgent, 'Mac') !== false) {
            return 'Mac';
        } elseif (stripos($userAgent, 'Linux') !== false) {
            return 'Linux PC';
        }
        
        return 'Unknown Device';
    }

    /**
     * Extract device type from user agent
     */
    private function getDeviceTypeFromUserAgent(Request $request)
    {
        $userAgent = $request->header('User-Agent');
        
        // Simple device type detection
        if (stripos($userAgent, 'Mobile') !== false || 
            stripos($userAgent, 'iPhone') !== false || 
            stripos($userAgent, 'Android') !== false) {
            return 'mobile';
        } elseif (stripos($userAgent, 'Tablet') !== false || 
                  stripos($userAgent, 'iPad') !== false) {
            return 'tablet';
        }
        
        return 'desktop';
    }

    /**
 * @OA\Post(
 *     path="/api/auth/logout",
 *     summary="Logout user and update mata data",
 *     tags={"Authentication"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Successfully logged out",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Successfully logged out")
 *         )
 *     )
 * )
 */
public function logout()
{
    $user = auth()->user();
    
    // Update the most recent mata_data entry for this user
    $latestMataData = MataData::where('user_id', $user->id)
        ->where('is_logout', false)
        ->orderBy('last_login_time', 'desc')
        ->first();
    
    if ($latestMataData) {
        $latestMataData->logout_time = now();
        $latestMataData->is_logout = true;
        $latestMataData->save();
    }
    
    auth()->logout();
    
    return response()->json(['message' => 'Successfully logged out']);
}

    /**
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     summary="Refresh JWT token",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer")
     *         )
     *     )
     * )
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * @OA\Get(
     *     path="/api/auth/me",
     *     summary="Get authenticated user",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string", format="uuid"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="role", type="string"),
     *             @OA\Property(property="isActive", type="boolean")
     *         )
     *     )
     * )
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * @OA\Put(
     *     path="/api/admin/sales/{id}/status",
     *     summary="Update user status (activate/deactivate)",
     *     tags={"User Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password", "isActive"},
     *             @OA\Property(property="password", type="string", format="password", example="your-current-password", description="Current user password for verification"),
     *             @OA\Property(property="isActive", type="boolean", example=true, description="Set user active status")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User status updated successfully"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid password"),
     *     @OA\Response(response=403, description="Forbidden - Cannot modify yourself"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function updateUserStatus(Request $request, $id)
    {
        // Find the user to update
        $userToUpdate = User::find($id);

        if (!$userToUpdate) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent user from modifying themselves
        if (auth()->id() === $userToUpdate->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot modify your own status'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'isActive' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify the current user's password
        if (!Hash::check($request->password, auth()->user()->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password. Password verification failed.'
            ], 401);
        }

        // Update the user status
        $userToUpdate->isActive = $request->isActive;
        $userToUpdate->save();

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'user' => $userToUpdate
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/sales/{id}/password",
     *     summary="Update user password",
     *     tags={"User Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID (UUID)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"old_password", "new_password", "reenter_new_password"},
     *             @OA\Property(property="old_password", type="string", format="password", example="oldpassword123", description="Current password"),
     *             @OA\Property(property="new_password", type="string", format="password", example="newpassword123", description="New password (min 6 characters)"),
     *             @OA\Property(property="reenter_new_password", type="string", format="password", example="newpassword123", description="Confirm new password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid old password"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updatePassword(Request $request, $id)
    {
        // Find the user to update
        $userToUpdate = User::find($id);

        if (!$userToUpdate) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6',
            'reenter_new_password' => 'required|string|same:new_password',
        ], [
            'reenter_new_password.same' => 'New password and re-entered password must match'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify the old password
        if (!Hash::check($request->old_password, $userToUpdate->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Old password is incorrect'
            ], 401);
        }

        // Check if new password is same as old password
        if (Hash::check($request->new_password, $userToUpdate->password)) {
            return response()->json([
                'success' => false,
                'message' => 'New password cannot be the same as old password'
            ], 422);
        }

        // Update the password
        $userToUpdate->password = Hash::make($request->new_password);
        $userToUpdate->save();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/sales",
     *     summary="Get all users (Admin and Sales can access)",
     *     tags={"User Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         description="Filter by role",
     *         required=false,
     *         @OA\Schema(type="string", enum={"admin", "sales"})
     *     ),
     *     @OA\Parameter(
     *         name="isActive",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of users",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="total", type="integer", example=10),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getAllUsers(Request $request)
    {
        $query = User::query();

        // Filter by role if provided
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by active status if provided
        if ($request->has('isActive')) {
            $query->where('isActive', $request->boolean('isActive'));
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'total' => $users->count(),
            'data' => $users
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/sales/{id}",
     *     summary="Get user by ID",
     *     tags={"User Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User details"
     *     ),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function getUserById($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    protected function respondWithToken($token, $mataData = null)
    {
        $response = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => auth()->user()
        ];

        if ($mataData) {
            $response['mata_data'] = $mataData;
        }

        return response()->json($response);
    }
}