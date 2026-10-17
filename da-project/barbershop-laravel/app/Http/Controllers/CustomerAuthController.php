<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerVerificationService;
use App\Support\CustomerContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function __construct(private readonly CustomerVerificationService $verification) {}

    public function access(): RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return to_route('customer.dashboard');
        }

        return to_route('home');
    }

    public function createLogin(): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return to_route('customer.dashboard');
        }

        return view('customer.login');
    }

    public function storeLogin(Request $request): RedirectResponse
    {
        CustomerContact::normalizeRequest($request);
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw (new ValidationException($validator))->errorBag('customerLogin');
        }

        $credentials = $validator->validated();

        if (! Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ])->errorBag('customerLogin');
        }

        $request->session()->regenerate();

        return to_route($request->user('customer')->hasVerifiedContacts()
            ? 'customer.dashboard'
            : 'customer.verification.notice');
    }

    public function createRegister(): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return to_route('customer.dashboard');
        }

        return view('customer.register');
    }

    public function storeRegister(Request $request): RedirectResponse
    {
        CustomerContact::normalizeRequest($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('customers', 'email')],
            'phone' => CustomerContact::phoneRules(),
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(8)->letters()->numbers()],
            'password_confirmation' => ['required', 'string', 'same:password'],
        ], [
            'password.confirmed' => 'Kata sandi dan ulangi kata sandi harus sama.',
            'password_confirmation.required' => 'Ulangi kata sandi wajib diisi.',
            'password_confirmation.same' => 'Ulangi kata sandi harus sama persis dengan kata sandi.',
            'phone.regex' => 'Gunakan nomor HP valid, misalnya 081234567890 atau +6281234567890.',
        ]);

        unset($data['password_confirmation']);
        $customer = Customer::create($data);
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        $errors = $this->verification->sendMissing($customer);

        $response = to_route('customer.verification.notice')
            ->with('status', 'Akun tersimpan. Verifikasi email dan nomor HP untuk mengaktifkan akun Anda.');

        return $errors ? $response->withErrors($errors) : $response;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return to_route('home')->with('status', 'Anda sudah keluar dari akun pelanggan.');
    }
}
