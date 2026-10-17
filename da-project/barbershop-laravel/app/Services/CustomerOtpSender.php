<?php

namespace App\Services;

use App\Mail\CustomerVerificationMail;
use App\Support\CustomerContact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class CustomerOtpSender
{
    public function assertConfigured(string $channel): void
    {
        if ($channel === 'email') {
            $mailer = config('customer_verification.email_mailer');
            // Never silently treat a log/array delivery as verification email.
            if (config("mail.mailers.{$mailer}.transport") !== 'smtp'
                || ! config("mail.mailers.{$mailer}.host") || ! config('mail.from.address')) {
                throw new RuntimeException('OTP email requires an SMTP mailer.');
            }

            return;
        }

        $settings = config('customer_verification.twilio');
        if (config('customer_verification.sms_driver') !== 'twilio'
            || ! preg_match('/\AAC[0-9a-fA-F]{32}\z/', (string) $settings['account_sid'])
            || ! $settings['auth_token']
            || (! $settings['from'] && ! $settings['messaging_service_sid'])) {
            throw new RuntimeException('OTP SMS requires a configured Twilio sender.');
        }
    }

    public function send(string $channel, string $destination, string $code): void
    {
        $this->assertConfigured($channel);
        $minutes = (int) config('customer_verification.expires_minutes');

        if ($channel === 'email') {
            Mail::mailer(config('customer_verification.email_mailer'))
                ->to($destination)->send(new CustomerVerificationMail($code, $minutes));

            return;
        }

        $settings = config('customer_verification.twilio');
        $destination = CustomerContact::normalizePhone($destination);
        if (! preg_match('/\A\+[1-9][0-9]{7,14}\z/', $destination)) {
            throw new RuntimeException('OTP SMS requires a valid international phone number.');
        }
        $sender = $settings['messaging_service_sid']
            ? ['MessagingServiceSid' => $settings['messaging_service_sid']]
            : ['From' => $settings['from']];
        $response = Http::asForm()
            ->withBasicAuth($settings['account_sid'], $settings['auth_token'])
            ->connectTimeout(5)->timeout(15)
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.$settings['account_sid'].'/Messages.json', [
                ...$sender,
                'To' => $destination,
                'Body' => "Kode verifikasi HOMCUTS: {$code}. Berlaku {$minutes} menit. Jangan bagikan kode ini.",
            ]);

        // Do not throw an HTTP exception that could log the SMS body/OTP.
        if (! $response->successful() || ! $response->json('sid')
            || in_array($response->json('status'), ['failed', 'undelivered'], true)) {
            throw new RuntimeException('OTP SMS provider rejected the request.');
        }
    }
}
