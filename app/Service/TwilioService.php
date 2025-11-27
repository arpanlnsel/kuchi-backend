<?php

namespace App\Service;

use Twilio\Rest\Client;
use Exception;

class TwilioService
{
    protected $twilio;
    protected $from;

    public function __construct()
    {
        $sid    = config('services.twilio.sid');
        $token  = config('services.twilio.auth_token');  // FIXED
        $this->from = config('services.twilio.phone_number'); // FIXED

        $this->twilio = new Client($sid, $token);
    }

    /**
     * Generate a 6-digit OTP
     */
    public static function generateOTP()
    {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send OTP via SMS
     */
    public function sendOTP($to, $otp)
    {
        try {

            $message = $this->twilio->messages->create(
                $to,
                [
                    'from' => $this->from, // FIXED
                    'body' => "Your Kuchi verification code is: {$otp}. Valid for 5 minutes."
                ]
            );

            return [
                'success' => true,
                'message_sid' => $message->sid,
            ];

        } catch (Exception $e) {
            \Log::error('Twilio SMS Error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
