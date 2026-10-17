<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CustomerVerificationMail extends Mailable
{
    public function __construct(public readonly string $code, public readonly int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Kode verifikasi akun HOMCUTS');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.customer-verification');
    }
}
