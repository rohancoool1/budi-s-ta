<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('customers', 'email')->ignore($customer->id)],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $customer->update($data);

        return to_route('customer.dashboard')->with('success', 'Data akun pelanggan diperbarui.');
    }
}
