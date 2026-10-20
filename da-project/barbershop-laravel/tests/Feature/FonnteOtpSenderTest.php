<?php

namespace Tests\Feature;

use App\Services\CustomerOtpSender;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FonnteOtpSenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['customer_verification.fonnte_token' => 'device-token', 'customer_verification.expires_minutes' => 10]);
        Http::preventStrayRequests();
    }

    public function test_target_is_normalized_for_indonesian_and_international_numbers(): void
    {
        Http::fake(['api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['123']], 200)]);
        foreach (['081234567890' => '6281234567890', '+60123456789' => '60123456789'] as $input => $target) {
            app(CustomerOtpSender::class)->send('phone', $input, '123456');
            Http::assertSent(fn ($request) => $request['target'] === $target
                && $request['countryCode'] === '0'
                && $request->hasHeader('Authorization', 'device-token')
                && str_contains($request['message'], '123456'));
        }
        Http::assertSentCount(2);
    }

    public function test_provider_rejections_malformed_responses_and_http_errors_fail_closed(): void
    {
        $cases = [
            [['status' => false, 'reason' => 'invalid token'], 200],
            [['Status' => false, 'reason' => 'invalid token'], 200],
            [['status' => true], 200],
            [['status' => 'true', 'id' => ['123']], 200],
            [['status' => true, 'id' => []], 200],
            [['status' => true, 'id' => ['123']], 503],
            ['not json', 200],
        ];
        foreach ($cases as [$body, $status]) {
            Http::fake(['api.fonnte.com/send' => Http::response($body, $status)]);
            try {
                app(CustomerOtpSender::class)->send('phone', '+6281234567890', '123456');
                $this->fail('Rejected or invalid responses must not be accepted.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Fonnte did not accept the WhatsApp OTP request.', $exception->getMessage());
            }
        }
    }

    public function test_connection_failure_is_not_retried(): void
    {
        $requests = 0;
        Http::fake(function () use (&$requests) {
            $requests++;
            throw new ConnectionException('Timed out');
        });
        try {
            app(CustomerOtpSender::class)->send('phone', '+6281234567890', '123456');
            $this->fail('Connection failure must propagate to the verification service.');
        } catch (ConnectionException) {
            $this->assertSame(1, $requests);
        }
    }

    public function test_missing_token_and_invalid_destination_never_issue_requests(): void
    {
        foreach (['' => '+6281234567890', 'device-token' => '6281234567890,6289999999999'] as $token => $destination) {
            config(['customer_verification.fonnte_token' => $token]);
            try {
                app(CustomerOtpSender::class)->send('phone', $destination, '123456');
                $this->fail('Missing credentials or multiple recipients must be rejected.');
            } catch (RuntimeException) {
                Http::assertNothingSent();
            }
        }
    }
}
