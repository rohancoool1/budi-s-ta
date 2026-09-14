<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', 'in:general,booking,product,collaboration'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        ContactMessage::create([...$data, 'status' => 'new']);

        return to_route('contact')->with('contact_success', 'Terima kasih sudah menghubungi kami. Tim kami akan membalas secepatnya.');
    }
}
