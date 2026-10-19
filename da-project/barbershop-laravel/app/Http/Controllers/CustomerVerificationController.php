<?php

namespace App\Http\Controllers;

use App\Models\CustomerVerificationCode;
use App\Services\CustomerVerificationService;
use App\Support\CustomerContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerVerificationController extends Controller
{
    public function __construct(private readonly CustomerVerificationService $verification) {}

    public function show(Request $request): View|RedirectResponse
    {
        $customer = $request->user('customer');
        if ($customer->hasVerifiedContacts()) {
            return to_route('customer.dashboard');
        }

        return view('customer.verify', [
            'verificationCodes' => CustomerVerificationCode::query()->where('customer_id', $customer->id)->where('channel', 'email')->get()->keyBy('channel'),
        ]);
    }

    public function send(Request $request, string $channel): RedirectResponse
    {
        $sent = $this->verification->send($request->user('customer'), $channel);

        return to_route('customer.verification.notice')->with('status', $sent
            ? 'Kode baru telah dikirim. Gunakan kode terbaru; kode sebelumnya tidak berlaku.'
            : 'Kontak ini sudah terverifikasi.');
    }

    public function verify(Request $request, string $channel): RedirectResponse
    {
        $field = $channel.'_code';
        $data = $request->validate([$field => ['required', 'string', 'regex:/\A[0-9]{6}\z/']], [
            $field.'.required' => 'Masukkan kode verifikasi.',
            $field.'.regex' => 'Kode verifikasi harus terdiri dari 6 angka.',
        ]);
        $this->verification->verify($request->user('customer'), $channel, $data[$field]);

        $request->session()->regenerate();

        return to_route('customer.dashboard')->with('success', 'Email terverifikasi. Akun Anda siap digunakan.');
    }

    public function updateContacts(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        CustomerContact::normalizeRequest($request);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150', Rule::unique('customers', 'email')->ignore($customer->id)],
            'phone' => CustomerContact::phoneRules(),
            'current_password' => ['required', 'current_password:customer'],
        ], ['phone.regex' => 'Gunakan nomor HP valid, misalnya 081234567890 atau +6281234567890.']);

        DB::transaction(function () use ($customer, $data): void {
            $customer = $customer->newQuery()->lockForUpdate()->findOrFail($customer->id);
            $customer->fill(['email' => $data['email'], 'phone' => $data['phone']])->save();
        }, 3);

        $customer->refresh();

        return to_route('customer.verification.notice')
            ->with('status', 'Kontak disimpan. Tekan Kirim kode jika email belum terverifikasi.');
    }
}
