<?php

namespace App\Http\Controllers;

use App\Support\CustomerContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function show(Request $request): View
    {
        $customer = $request->user('customer');

        return view('customer.dashboard', [
            'bookings' => $customer->bookings()
                ->with(['barber', 'service', 'transaction.latestPayment'])
                ->latest('appointment_date')
                ->latest('appointment_time')
                ->get(),
            'orders' => $customer->orders()
                ->whereNull('booking_id')
                ->with(['items', 'latestPayment'])
                ->latest()
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        CustomerContact::normalizeRequest($request);
        $contactChanged = $request->input('email') !== $customer->email
            || $request->input('phone') !== $customer->phone;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('customers', 'email')->ignore($customer->id)],
            'phone' => CustomerContact::phoneRules(),
            'current_password' => [$contactChanged ? 'required' : 'nullable', 'current_password:customer'],
        ], ['phone.regex' => 'Gunakan nomor HP valid, misalnya 081234567890 atau +6281234567890.']);

        unset($data['current_password']);
        DB::transaction(function () use ($customer, $data): void {
            $customer->newQuery()->lockForUpdate()->findOrFail($customer->id)->fill($data)->save();
        }, 3);

        if (! $customer->refresh()->hasVerifiedContacts()) {
            return to_route('customer.verification.notice')
                ->with('status', 'Kontak diperbarui. Kirim dan verifikasi kode untuk kontak baru Anda.');
        }

        return to_route('customer.dashboard')->with('success', 'Data akun pelanggan diperbarui.');
    }
}
