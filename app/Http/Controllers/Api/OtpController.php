<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Otp;
use App\Models\MataData;
use App\Service\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="OTP Authentication",
 *     description="OTP-based authentication endpoints"
 * )
 */
class OtpController extends Controller
{
    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    /**
     * @OA\Post(
     *     path="/api/auth/otp/send",
     *     summary="Send OTP to phone number",
     *     tags={"OTP Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone_no"},
     *             @OA\Property(property="phone_no", type="string", example="+919876543210", description="Phone number with country code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP sent successfully to your phone number"),
     *             @OA\Property(property="phone_no", type="string", example="+919876543210"),
     *             @OA\Property(property="expires_in", type="string", example="5 minutes")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Failed to send OTP")
     * )
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_no' => 'required|string|regex:/^\+[1-9]\d{1,14}$/',
        ], [
            'phone_no.regex' => 'Phone number must be in international format (e.g., +919876543210)'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $phoneNo = $request->phone_no;

            // Invalidate all previous OTPs for this phone number
            Otp::where('phone_no', $phoneNo)
                ->where('is_verified', false)
                ->update(['is_verified' => true]);

            // Generate new OTP
            $otpCode = TwilioService::generateOTP();
            $expiresAt = now()->addMinutes(5);

            // Save OTP to database
            $otp = Otp::create([
                'phone_no' => $phoneNo,
                'otp' => $otpCode,
                'expires_at' => $expiresAt,
                'is_verified' => false,
            ]);

            // Send OTP via Twilio
            $result = $this->twilioService->sendOTP($phoneNo, $otpCode);

            // Check if sending was successful
            if (!$result['success']) {
                DB::rollBack();
                \Log::error('Twilio Error: ' . ($result['error'] ?? 'Unknown error'));

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send OTP. Please try again.',
                    'error' => $result['error'] ?? 'SMS service error'
                ], 500);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully to your phone number',
                'phone_no' => $phoneNo,
                'expires_in' => '5 minutes'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Send OTP Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending OTP. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/otp/verify",
     *     summary="Verify OTP and login user",
     *     tags={"OTP Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone_no","otp"},
     *             @OA\Property(property="phone_no", type="string", example="+919876543210", description="Phone number with country code"),
     *             @OA\Property(property="otp", type="string", example="123456", description="6-digit OTP code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP verified and user logged in successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="existing_user", type="boolean", example=true),
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer"),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="mata_data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid or expired OTP"),
     *     @OA\Response(response=403, description="Account is inactive"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_no' => 'required|string|regex:/^\+[1-9]\d{1,14}$/',
            'otp' => 'required|string|size:6',
        ], [
            'phone_no.regex' => 'Phone number must be in international format (e.g., +919876543210)',
            'otp.size' => 'OTP must be 6 digits'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $phoneNo = $request->phone_no;
            $otpCode = $request->otp;

            // Find the latest OTP for this phone number
            $otpRecord = Otp::where('phone_no', $phoneNo)
                ->where('is_verified', false)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$otpRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'No OTP found for this phone number. Please request a new OTP.'
                ], 400);
            }

            // Validate OTP
            if (!$otpRecord->isValid($otpCode)) {
                if ($otpRecord->isExpired()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'OTP has expired. Please request a new OTP.'
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP. Please try again.'
                ], 400);
            }

            // Mark OTP as verified
            $otpRecord->is_verified = true;
            $otpRecord->save();

            // Check if user exists
            $user = User::where('phone_no', $phoneNo)->first();
            $existingUser = $user !== null;

            if (!$user) {
                // Create new user with minimal data
                $user = User::create([
                    'name' => 'User', // Default name, can be updated later
                    'phone_no' => $phoneNo,
                    'role' => 'user',
                    'isActive' => true,
                    'phone_verified_at' => now(),
                    'password' => bcrypt(uniqid()), // Random password for phone-only users
                ]);
            } else {
                // Update phone_verified_at for existing user
                $user->phone_verified_at = now();
                $user->save();
            }

            // Check if user is active
            if (!$user->isActive) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact support.'
                ], 403);
            }

            // Update OTP record with user_id
            $otpRecord->user_id = $user->id;
            $otpRecord->save();

            // Generate JWT token
            $token = auth()->login($user);

            // Save metadata
            $mataData = $this->saveMataData($request, $user->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'existing_user' => $existingUser,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60,
                'user' => $user,
                'mata_data' => $mataData
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Verify OTP Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during verification. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/otp/resend",
     *     summary="Resend OTP to phone number",
     *     tags={"OTP Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone_no"},
     *             @OA\Property(property="phone_no", type="string", example="+919876543210", description="Phone number with country code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP resent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP resent successfully"),
     *             @OA\Property(property="phone_no", type="string", example="+919876543210")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=429, description="Too many requests")
     * )
     */
    public function resendOtp(Request $request)
    {
        // Check rate limiting - only allow resend after 60 seconds
        $lastOtp = Otp::where('phone_no', $request->phone_no)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastOtp && $lastOtp->created_at->addSeconds(60) > now()) {
            $waitTime = $lastOtp->created_at->addSeconds(60)->diffInSeconds(now());
            return response()->json([
                'success' => false,
                'message' => "Please wait {$waitTime} seconds before requesting a new OTP"
            ], 429);
        }

        // Reuse sendOtp method
        return $this->sendOtp($request);
    }

    /**
     * Save mata data on login
     */
    private function saveMataData(Request $request, $userId)
    {
        $deviceName = $request->input('device_name', $this->getDeviceNameFromUserAgent($request));
        $deviceType = $request->input('device_type', $this->getDeviceTypeFromUserAgent($request));

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

        if (
            stripos($userAgent, 'Mobile') !== false ||
            stripos($userAgent, 'iPhone') !== false ||
            stripos($userAgent, 'Android') !== false
        ) {
            return 'mobile';
        } elseif (
            stripos($userAgent, 'Tablet') !== false ||
            stripos($userAgent, 'iPad') !== false
        ) {
            return 'tablet';
        }

        return 'desktop';
    }
}