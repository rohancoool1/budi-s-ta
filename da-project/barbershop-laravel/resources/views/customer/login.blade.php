@extends('layouts.app')

@section('title', 'Masuk Pelanggan')
@section('description', 'Masuk ke akun pelanggan HOMCUTS.')

@section('content')
    <section class="min-h-[calc(100vh-76px)] bg-cream px-6 py-16 md:px-[6vw] md:py-24">
        <div class="mx-auto grid max-w-5xl overflow-hidden border border-ink bg-paper shadow-[12px_12px_0_#9faa8d] lg:grid-cols-[.85fr_1.15fr]">
            <aside class="bg-ink p-8 text-paper md:p-12">
                <p class="section-kicker">AKUN PELANGGAN</p>
                <h1 class="mt-5 font-display text-5xl leading-[.94] tracking-[-.05em]">Lebih cepat<br><em class="text-orange">setiap kali datang.</em></h1>
                <p class="mt-7 text-sm leading-7 text-white/60">Saat masuk, data Anda langsung dipakai saat booking layanan dan checkout pesanan produk.</p>
            </aside>
            <div class="p-8 md:p-12">
                <a class="text-[9px] font-black uppercase tracking-[.12em] text-muted" href="{{ route('home') }}">← Kembali ke toko</a>
                <h2 class="mt-6 font-display text-5xl tracking-[-.045em]">Masuk akun.</h2>
                @if (session('status'))
                    <div class="mt-6 border border-sage bg-sage/15 px-4 py-3 text-sm">{{ session('status') }}</div>
                @endif
                <form class="mt-9 space-y-5" method="POST" action="{{ route('customer.login.store') }}">
                    @csrf
                    <div>
                        <label class="field-label mb-2" for="email">Alamat email</label>
                        <input class="form-control bg-white" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required>
                        @error('email', 'customerLogin') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label mb-2" for="password">Kata sandi</label>
                        <input class="form-control bg-white" id="password" name="password" type="password" autocomplete="current-password" required>
                        @error('password', 'customerLogin') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-3 text-xs text-muted">
                        <input class="size-4 accent-orange" name="remember" type="checkbox" value="1">
                        Tetap masuk di perangkat ini
                    </label>
                    <button class="btn-primary w-full" type="submit">Masuk <span>→</span></button>
                </form>
                <p class="mt-8 text-sm text-muted">Belum punya akun? <a class="border-b border-current font-bold text-ink" href="{{ route('customer.register') }}">Daftar sekarang</a>.</p>
            </div>
        </div>
    </section>
@endsection
