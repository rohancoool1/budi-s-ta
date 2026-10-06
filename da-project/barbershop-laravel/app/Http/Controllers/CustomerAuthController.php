<?php

namespace App\Http\Controllers;

use App\Models\Customer;
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

        return redirect()->intended(route('customer.dashboard'));
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('customers', 'email')],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $customer = Customer::create($data);
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return to_route('customer.dashboard')
            ->with('success', 'Akun pelanggan berhasil dibuat. Data Anda akan terisi otomatis saat booking atau memesan produk.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return to_route('home')->with('status', 'Anda sudah keluar dari akun pelanggan.');
    }
}
