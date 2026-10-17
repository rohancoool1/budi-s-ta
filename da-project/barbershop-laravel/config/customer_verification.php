<?php

return [
    'expires_minutes' => 10,
    'resend_seconds' => 60,
    'max_attempts' => 5,
    'sends_per_hour' => 5,
    // Email uses a dedicated SMTP mailer; it never falls back to a log transport.
    'email_mailer' => env('CUSTOMER_OTP_MAILER', 'smtp'),
    'sms_driver' => env('CUSTOMER_OTP_SMS_DRIVER', 'disabled'),
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
    ],
];
