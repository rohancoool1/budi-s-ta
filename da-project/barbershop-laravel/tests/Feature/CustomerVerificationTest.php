<?php

namespace Tests\Feature;

use App\Mail\CustomerVerificationMail;
use App\Models\Customer;
use App\Models\CustomerVerificationCode;
use App\Models\Order;
use App\Models\Product;
use App\Services\CustomerVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'customer_verification.email_mailer' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.from.address' => 'homcuts@example.test',
        ]);
        Mail::fake();
        Http::preventStrayRequests();

    }

    public function test_mismatched_password_confirmation_never_creates_an_account_or_sends_otp(): void
    {
        $this->from(route('customer.register'))->post(route('customer.register.store'), [
            ...$this->registration(), 'password_confirmation' => 'Different456',
        ])->assertRedirect(route('customer.register'))->assertSessionHasErrors(['password', 'password_confirmation']);
        $this->assertDatabaseCount('customers', 0);
        $this->assertGuest('customer');
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_missing_confirmation_and_json_bypass_are_rejected_by_server(): void
    {
        $data = $this->registration();
        unset($data['password_confirmation']);
        $this->postJson(route('customer.register.store'), $data)->assertUnprocessable()
            ->assertJsonValidationErrors(['password', 'password_confirmation']);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_registration_sends_only_email_and_keeps_account_unverified(): void
    {
        $customer = $this->registerCustomer();
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertSame('+6281234567890', $customer->phone);
        $this->assertTrue(Hash::check('ValidPass123', $customer->password));
        $this->assertFalse($customer->hasVerifiedContacts());
        Mail::assertSent(CustomerVerificationMail::class, fn ($mail) => $mail->hasTo($customer->email));
        Http::assertNothingSent();
        $this->assertDatabaseCount('customer_verification_codes', 1);
        $this->assertDatabaseMissing('customer_verification_codes', ['channel' => 'phone']);
        $record = CustomerVerificationCode::where('channel', 'email')->firstOrFail();
        $this->assertNotSame($this->emailCode(), $record->code_hash);
        $this->assertTrue(Hash::check($this->emailCode(), $record->code_hash));
        $this->get(route('customer.verification.notice'))->assertOk()->assertSee('Verifikasi email Anda.')->assertDontSee('phone_code')->assertDontSee('SMS');
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.verification.notice'));
    }

    public function test_email_alone_activates_account_without_phone_verification(): void
    {
        $customer = $this->registerCustomer();
        $this->verifyCode('email', $this->emailCode())->assertRedirect(route('customer.dashboard'));
        $this->assertNotNull($customer->fresh()->email_verified_at);
        $this->assertNull($customer->fresh()->phone_verified_at);
        $this->assertTrue($customer->fresh()->hasVerifiedContacts());
        $this->get(route('customer.dashboard'))->assertOk();
    }

    public function test_phone_otp_endpoints_are_disabled(): void
    {
        $this->registerCustomer();
        $this->verifyCode('phone', '123456')->assertNotFound();
        $this->post(route('customer.verification.send', ['channel' => 'phone']))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_phone_change_does_not_revoke_email_verification(): void
    {
        $customer = $this->verifiedCustomer();
        $verifiedAt = $customer->email_verified_at->toDateTimeString();
        $this->actingAs($customer, 'customer')->patch(route('customer.account.update'), [
            'name' => $customer->name, 'email' => $customer->email,
            'phone' => '081299999999', 'current_password' => 'ValidPass123',
        ])->assertRedirect(route('customer.dashboard'))->assertSessionHasNoErrors();
        $this->assertSame('+6281299999999', $customer->fresh()->phone);
        $this->assertSame($verifiedAt, $customer->fresh()->email_verified_at->toDateTimeString());
        $this->get(route('customer.dashboard'))->assertOk();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_five_wrong_attempts_lock_the_challenge_even_for_the_correct_code(): void
    {
        $customer = $this->registerCustomer();
        for ($i = 0; $i < 5; $i++) {
            $this->verifyCode('email', '000000')->assertSessionHasErrors('email_code');
        }
        $this->assertSame(5, CustomerVerificationCode::where('channel', 'email')->firstOrFail()->attempts);
        $this->verifyCode('email', $this->emailCode())->assertSessionHasErrors('email_code');
        $this->assertNull($customer->fresh()->email_verified_at);
        $this->assertNull(session()->getOldInput('email_code'));
    }

    public function test_expired_and_consumed_codes_cannot_be_used(): void
    {
        $customer = $this->registerCustomer();
        $this->verifyCode('email', $this->emailCode())->assertSessionHasNoErrors();
        $verifiedAt = $customer->fresh()->email_verified_at->toDateTimeString();
        $this->verifyCode('email', $this->emailCode())->assertSessionHasErrors('email_code');
        $this->assertSame($verifiedAt, $customer->fresh()->email_verified_at->toDateTimeString());
    }

    public function test_expired_email_code_cannot_activate_account(): void
    {
        $customer = $this->registerCustomer();
        $this->travel(11)->minutes();
        $this->verifyCode('email', $this->emailCode())->assertSessionHasErrors('email_code');
        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_resend_enforces_cooldown_and_invalidates_previous_code(): void
    {
        $this->registerCustomer();
        $oldCode = $this->emailCode();
        $this->post(route('customer.verification.send', ['channel' => 'email']))->assertSessionHasErrors('email_code');
        Mail::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->post(route('customer.verification.send', ['channel' => 'email']))->assertSessionHasNoErrors();
        Mail::assertSentCount(2);
        $record = CustomerVerificationCode::where('channel', 'email')->firstOrFail();
        $this->assertTrue(Hash::check($this->emailCode(), $record->code_hash));
        $this->assertNotSame($oldCode, $this->emailCode());
        $this->verifyCode('email', $oldCode)->assertSessionHasErrors('email_code');
        $this->verifyCode('email', $this->emailCode())->assertSessionHasNoErrors();
    }

    public function test_resend_limit_survives_new_challenges(): void
    {
        $this->registerCustomer();
        for ($i = 0; $i < 4; $i++) {
            $this->travel(61)->seconds();
            $this->post(route('customer.verification.send', ['channel' => 'email']))->assertSessionHasNoErrors();
        }
        $this->travel(61)->seconds();
        $this->post(route('customer.verification.send', ['channel' => 'email']))->assertSessionHasErrors('email_code');
        Mail::assertSentCount(5);
    }

    public function test_codes_cannot_verify_another_account(): void
    {
        $first = $this->registerCustomer();
        $other = $this->customer(['email' => 'second@example.test', 'phone' => '+6289999999999']);
        $this->actingAs($other, 'customer');
        $this->verifyCode('email', $this->emailCode())->assertSessionHasErrors('email_code');
        $this->assertNull($first->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_unverified_customer_cannot_checkout_or_book_but_guest_checkout_still_works(): void
    {
        $customer = $this->registerCustomer();
        $product = Product::where('is_active', true)->where('stock', '>', 0)->firstOrFail();
        $payload = ['name' => 'Guest', 'phone' => '081234567890', 'payment_method' => 'cash',
            'cart_json' => json_encode([['id' => $product->id, 'quantity' => 1]])];
        $this->post(route('orders.store'), $payload)->assertRedirect(route('customer.verification.notice'));
        $this->postJson(route('bookings.store'), [])->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
        $this->post(route('customer.logout'))->assertRedirect(route('home'));
        $this->assertGuest('customer');
        $this->post(route('orders.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
        $this->assertNull(Order::firstOrFail()->customer_id);
    }

    public function test_login_rejects_wrong_password_and_routes_unverified_accounts_to_verification(): void
    {
        $customer = $this->customer();
        $this->post(route('customer.login.store'), ['email' => $customer->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email', null, 'customerLogin');
        $this->assertGuest('customer');
        $this->post(route('customer.login.store'), ['email' => strtoupper($customer->email), 'password' => 'ValidPass123'])
            ->assertRedirect(route('customer.verification.notice'));
    }

    public function test_verified_login_does_not_follow_an_admin_intended_url(): void
    {
        $customer = $this->verifiedCustomer();
        $this->withSession(['url.intended' => route('admin.dashboard')])->post(route('customer.login.store'), [
            'email' => $customer->email, 'password' => 'ValidPass123', 'remember' => '1',
        ])->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
    }

    public function test_contact_change_requires_password_and_revokes_only_changed_verification(): void
    {
        $customer = $this->verifiedCustomer();
        $payload = ['name' => $customer->name, 'email' => 'new@example.test', 'phone' => $customer->phone];
        $this->actingAs($customer, 'customer')->patch(route('customer.account.update'), $payload)
            ->assertSessionHasErrors('current_password');
        $this->assertSame($customer->email, $customer->fresh()->email);
        $this->patch(route('customer.account.update'), [...$payload, 'current_password' => 'ValidPass123'])
            ->assertRedirect(route('customer.verification.notice'));
        $this->assertNull($customer->fresh()->email_verified_at);
        $this->assertNull($customer->fresh()->phone_verified_at);
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.verification.notice'));
    }

    public function test_correcting_pending_contact_deletes_its_old_code_and_requires_a_new_one(): void
    {
        $customer = $this->registerCustomer();
        $oldCode = $this->emailCode();
        $this->patch(route('customer.verification.contacts'), [
            'email' => 'corrected@example.test', 'phone' => $customer->phone, 'current_password' => 'ValidPass123',
        ])->assertRedirect(route('customer.verification.notice'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('customer_verification_codes', ['channel' => 'email']);
        $this->verifyCode('email', $oldCode)->assertSessionHasErrors('email_code');
        $this->post(route('customer.verification.send', ['channel' => 'email']))->assertSessionHasNoErrors();
        $this->verifyCode('email', $this->emailCode())->assertSessionHasNoErrors();
    }

    public function test_delivery_failure_is_visible_and_does_not_activate_or_delete_account(): void
    {
        Mail::shouldReceive('mailer')->once()->with('smtp')->andReturnSelf();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Delivery failed'));
        $this->post(route('customer.register.store'), $this->registration())
            ->assertRedirect(route('customer.verification.notice'))->assertSessionHasErrors('email_code');
        $customer = Customer::firstOrFail();
        $this->assertFalse($customer->hasVerifiedContacts());
        $record = CustomerVerificationCode::where('channel', 'email')->firstOrFail();
        $this->assertNull($record->sent_at);
        $this->assertSame('', $record->code_hash);
        Http::assertNothingSent();
    }

    public function test_log_mailer_cannot_pretend_to_deliver_a_verification_email(): void
    {
        config(['customer_verification.email_mailer' => 'log']);
        $this->post(route('customer.register.store'), $this->registration())->assertSessionHasErrors('email_code');
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('customer_verification_codes', ['channel' => 'email']);
    }

    public function test_guest_cannot_send_or_verify_codes_and_unknown_channel_is_rejected(): void
    {
        $this->post(route('customer.verification.send', ['channel' => 'email']))->assertRedirect(route('customer.login'));
        $this->verifyCode('email', '123456')->assertRedirect(route('customer.login'));
        $this->actingAs($this->customer(), 'customer')->post('/pelanggan/verifikasi/other/kirim')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_duplicate_email_and_invalid_phone_are_rejected_without_delivery(): void
    {
        $this->customer();
        $this->postJson(route('customer.register.store'), [...$this->registration(), 'phone' => 'abc'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'phone']);
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_customer_history_is_scoped_to_the_signed_in_account(): void
    {
        $customer = $this->verifiedCustomer();
        $other = $this->customer(['email' => 'other@example.test']);
        foreach ([$customer, $other] as $owner) {
            $order = Order::create([
                'customer_id' => $owner->id, 'customer_name' => $owner->name, 'phone' => $owner->phone,
                'payment_method' => 'cash', 'payment_status' => 'unpaid', 'channel' => 'online',
                'transaction_type' => 'product', 'subtotal' => 10000, 'total' => 10000, 'status' => 'pending',
            ]);
            $order->items()->create(['item_type' => 'product', 'product_name' => 'Private item '.$owner->id,
                'unit_price' => 10000, 'quantity' => 1, 'line_total' => 10000]);
        }
        $this->actingAs($customer, 'customer')->get(route('customer.dashboard'))->assertOk()
            ->assertSee('Private item '.$customer->id)->assertDontSee('Private item '.$other->id);
    }

    private function registerCustomer(): Customer
    {
        $this->post(route('customer.register.store'), $this->registration())
            ->assertRedirect(route('customer.verification.notice'))->assertSessionHasNoErrors();

        return Customer::firstOrFail();
    }

    private function registration(): array
    {
        return ['name' => 'Pelanggan Uji', 'email' => 'customer@example.test', 'phone' => '081234567890',
            'password' => 'ValidPass123', 'password_confirmation' => 'ValidPass123'];
    }

    private function customer(array $overrides = []): Customer
    {
        return Customer::create([...$this->registration(), 'phone' => '+6281234567890', ...$overrides]);
    }

    private function verifiedCustomer(): Customer
    {
        $customer = $this->customer();
        $customer->forceFill(['email_verified_at' => now()])->save();

        return $customer;
    }

    private function emailCode(): string
    {
        return Mail::sent(CustomerVerificationMail::class)->last()->code;
    }

    private function verifyCode(string $channel, string $code)
    {
        return $this->post(route('customer.verification.verify', ['channel' => $channel]), [$channel.'_code' => $code]);
    }
}
