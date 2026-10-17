@extends('layouts.app')

@section('title', 'Daftar Pelanggan')
@section('description', 'Buat akun pelanggan HOMCUTS.')

@section('content')
    <section class="min-h-[calc(100vh-76px)] bg-cream px-6 py-16 md:px-[6vw] md:py-24">
        <div class="mx-auto max-w-2xl border border-ink bg-paper p-8 shadow-[12px_12px_0_#9faa8d] md:p-12">
            <a class="text-[9px] font-black uppercase tracking-[.12em] text-muted" href="{{ route('home') }}">← Kembali ke toko</a>
            <p class="section-kicker mt-8">AKUN PELANGGAN</p>
            <h1 class="mt-3 font-display text-5xl tracking-[-.05em]">Buat akun Anda.</h1>
            <p class="mt-3 text-sm leading-6 text-muted">Setelah mendaftar, verifikasi email dan nomor HP melalui kode OTP email dan SMS. Data akun dipakai untuk mengisi booking dan pesanan secara otomatis.</p>
            <form class="mt-9 grid gap-5 sm:grid-cols-2" method="POST" action="{{ route('customer.register.store') }}" data-customer-register>
                @csrf
                <div class="sm:col-span-2">
                    <label class="field-label mb-2" for="name">Nama lengkap</label>
                    <input class="form-control bg-white" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>
                    @error('name') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label mb-2" for="phone">Nomor HP penerima SMS</label>
                    <input class="form-control bg-white" id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" placeholder="081234567890" required>
                    @error('phone') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label mb-2" for="email">Alamat email</label>
                    <input class="form-control bg-white" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                    @error('email') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label mb-2" for="password">Kata sandi</label>
                    <input class="form-control bg-white" id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                    <p class="mt-2 text-xs text-muted">Minimal 8 karakter, berisi huruf dan angka.</p>
                    @error('password') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label mb-2" for="password_confirmation">Ulangi kata sandi</label>
                    <input class="form-control bg-white" id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="password-confirmation-error" required>
                    <p class="mt-2 text-xs text-red-700" id="password-confirmation-error" aria-live="polite">{{ $errors->first('password_confirmation') }}</p>
                </div>
                <button class="btn-primary mt-2 w-full sm:col-span-2" type="submit">Buat akun <span>→</span></button>
            </form>
            <p class="mt-8 text-sm text-muted">Sudah punya akun? <a class="border-b border-current font-bold text-ink" href="{{ route('customer.login') }}">Masuk</a>.</p>
        </div>
    </section>
@endsection
