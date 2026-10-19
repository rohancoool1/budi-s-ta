@extends('layouts.app')

@section('title', 'Verifikasi Akun Pelanggan')
@section('description', 'Verifikasi email akun HOMCUTS Anda.')

@section('content')
    <section class="min-h-[calc(100vh-76px)] bg-cream px-6 py-16 md:px-[6vw] md:py-24">
        <div class="mx-auto max-w-4xl border border-ink bg-paper p-7 shadow-[12px_12px_0_#9faa8d] md:p-12">
            <p class="section-kicker">AKTIVASI AKUN PELANGGAN</p>
            <h1 class="mt-4 font-display text-5xl tracking-[-.045em]">Verifikasi email Anda.</h1>
            <p class="mt-4 text-sm leading-7 text-muted">Verifikasi email sebelum akun dapat dipakai untuk booking dan pesanan. Masukkan kode yang diterima melalui email. Nomor HP tidak memerlukan verifikasi.</p>
            <p class="mt-2 text-xs leading-6 text-muted">Kode berlaku {{ config('customer_verification.expires_minutes') }} menit, maksimal {{ config('customer_verification.max_attempts') }} percobaan. Kirim ulang tersedia setelah {{ config('customer_verification.resend_seconds') }} detik.</p>

            @if (session('status'))
                <div class="mt-6 border border-sage bg-sage/15 p-4 text-sm" role="status">{{ session('status') }}</div>
            @endif

            <div class="mt-8 grid gap-6">
                @foreach (['email' => 'Email'] as $channel => $label)
                    @php
                        $verified = $customer->{$channel.'_verified_at'} !== null;
                        $codeRecord = $verificationCodes->get($channel);
                        $retryAt = $codeRecord?->issued_at?->copy()->addSeconds((int) config('customer_verification.resend_seconds'));
                        $waiting = $retryAt && $retryAt->isFuture();
                    @endphp
                    <section class="border border-ink/20 p-5" aria-label="Verifikasi {{ $label }}">
                        <p class="field-label">{{ $label }}</p>
                        <p class="mt-3 break-all text-sm font-bold">{{ $customer->{$channel} }}</p>
                        @if ($verified)
                            <p class="mt-5 border border-sage bg-sage/15 p-3 text-sm" role="status">✓ Sudah terverifikasi</p>
                        @else
                            <p class="mt-3 text-xs text-orange">Belum terverifikasi</p>
                            @if ($codeRecord?->sent_at && $codeRecord->expires_at->isFuture())
                                <p class="mt-2 text-xs text-muted">Kode dikirim pukul {{ $codeRecord->sent_at->format('H:i') }}. Periksa kotak masuk atau folder spam.</p>
                            @endif
                            <form class="mt-5 space-y-3" method="POST" action="{{ route('customer.verification.verify', ['channel' => $channel]) }}">
                                @csrf
                                <label class="field-label" for="{{ $channel }}_code">Kode OTP email</label>
                                <input class="form-control w-full bg-white text-lg tracking-[.3em]" id="{{ $channel }}_code" name="{{ $channel }}_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" placeholder="000000" aria-describedby="{{ $channel }}-error" required>
                                @error($channel.'_code') <p class="text-xs leading-6 text-red-700" id="{{ $channel }}-error" role="alert">{{ $message }}</p> @enderror
                                <button class="btn-primary w-full" type="submit">Verifikasi email <span>→</span></button>
                            </form>
                            <form class="mt-4" method="POST" action="{{ route('customer.verification.send', ['channel' => $channel]) }}">
                                @csrf
                                <button class="link-button disabled:cursor-wait disabled:opacity-50" type="submit" data-otp-resend-at="{{ $retryAt?->toIso8601String() }}" @disabled($waiting)>{{ $waiting ? 'Tunggu sebelum kirim ulang' : 'Kirim kode / kirim ulang' }}</button>
                            </form>
                        @endif
                    </section>
                @endforeach
            </div>

            <details class="mt-8 border-t border-ink/20 pt-6" @if($errors->hasAny(['email', 'phone', 'current_password'])) open @endif>
                <summary class="cursor-pointer text-sm font-bold">Email atau nomor HP salah? Perbaiki kontak</summary>
                <p class="mt-3 text-xs leading-6 text-muted">Hanya perubahan email yang memerlukan verifikasi ulang. Masukkan kata sandi akun untuk menyimpan perubahan.</p>
                <form class="mt-5 grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('customer.verification.contacts') }}">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="field-label mb-2" for="verification-email">Email</label>
                        <input class="form-control w-full" id="verification-email" name="email" type="email" value="{{ old('email', $customer->email) }}" required>
                        @error('email') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label mb-2" for="verification-phone">Nomor HP</label>
                        <input class="form-control w-full" id="verification-phone" name="phone" type="tel" value="{{ old('phone', $customer->phone) }}" required>
                        @error('phone') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label mb-2" for="verification-password">Kata sandi akun</label>
                        <input class="form-control w-full" id="verification-password" name="current_password" type="password" autocomplete="current-password" required>
                        @error('current_password') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <button class="btn-primary sm:col-span-2" type="submit">Simpan kontak <span>→</span></button>
                </form>
            </details>

            <form class="mt-7" method="POST" action="{{ route('customer.logout') }}">
                @csrf
                <button class="link-button" type="submit">Keluar dari akun</button>
            </form>
        </div>
    </section>
@endsection
