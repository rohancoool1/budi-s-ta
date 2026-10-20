<?php

return [
    'expires_minutes' => 10,
    'resend_seconds' => 60,
    'max_attempts' => 5,
    'sends_per_hour' => 5,
    // Email uses a dedicated SMTP mailer; it never falls back to a log transport.
    'email_mailer' => env('CUSTOMER_OTP_MAILER', 'smtp'),
    // Device token from Fonnte; phone challenges are delivered via WhatsApp.
    'fonnte_token' => env('FONNTE_TOKEN'),
];
