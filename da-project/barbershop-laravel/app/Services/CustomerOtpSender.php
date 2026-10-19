<?php

namespace App\Services;

use App\Mail\CustomerVerificationMail;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class CustomerOtpSender
{
    public function assertConfigured(string $channel): void
    {
        abort_unless($channel === 'email', 404);
        $mailer = config('customer_verification.email_mailer');
        // Never silently treat a log/array delivery as verification email.
        if (config("mail.mailers.{$mailer}.transport") !== 'smtp'
            || ! config("mail.mailers.{$mailer}.host") || ! config('mail.from.address')) {
            throw new RuntimeException('OTP email requires an SMTP mailer.');
        }
    }

    public function send(string $channel, string $destination, string $code): void
    {
        $this->assertConfigured($channel);
        $minutes = (int) config('customer_verification.expires_minutes');
        Mail::mailer(config('customer_verification.email_mailer'))
            ->to($destination)->send(new CustomerVerificationMail($code, $minutes));
    }
}
