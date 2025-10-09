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
    
    // Debug: Check if user exists
    $user = User::where('email', $request->email)->first();
    
    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }
    
    // Debug: Check password
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
     *     summary="Logout user",
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
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="role", type="string")
     *         )
     *     )
     * )
     */
    public function me()
    {
        return response()->json(auth()->user());
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