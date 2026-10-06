@extends('layouts.app')

@section('title', 'Akun Pelanggan')
@section('description', 'Pilih cara Anda melanjutkan ke layanan HOMCUTS.')

@section('content')
    <section class="border-b border-ink bg-cream px-6 pb-16 pt-20 md:px-[6vw] md:pb-20 md:pt-28">
        <div class="max-w-3xl">
            <p class="section-kicker">AKUN PELANGGAN</p>
            <h1 class="page-title">Masuk sesuai<br><em>cara Anda.</em></h1>
            <p class="mt-7 max-w-xl font-display text-lg leading-relaxed text-muted">Simpan data Anda untuk booking dan pesanan yang lebih cepat, atau lanjutkan sebagai tamu tanpa membuat akun.</p>
            @if (session('status'))
                <div class="mt-6 max-w-xl border border-sage bg-sage/15 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif
        </div>
    </section>

    <section class="grid gap-5 px-6 py-16 md:px-[6vw] md:py-24 lg:grid-cols-3">
        <article class="flex flex-col border border-ink bg-paper p-7 shadow-[8px_8px_0_#9faa8d]">
            <span class="text-3xl text-orange">01</span>
            <h2 class="mt-10 font-display text-4xl">Masuk</h2>
            <p class="mt-4 flex-1 text-sm leading-6 text-muted">Gunakan akun yang sudah ada. Nama, WhatsApp, dan email akan diisi otomatis ketika Anda booking atau membuat pesanan.</p>
            <a class="btn-primary mt-8" href="{{ route('customer.login') }}">Masuk ke akun <span>→</span></a>
        </article>

        <article class="flex flex-col border border-ink bg-ink p-7 text-paper shadow-[8px_8px_0_#e45f29]">
            <span class="text-3xl text-orange">02</span>
            <h2 class="mt-10 font-display text-4xl">Daftar akun</h2>
            <p class="mt-4 flex-1 text-sm leading-6 text-white/60">Buat akun pelanggan sekali saja. Riwayat booking dan pesanan Anda juga tersedia dalam satu halaman.</p>
            <a class="mt-8 inline-flex min-h-12 items-center justify-between border border-paper px-5 text-[9px] font-black uppercase tracking-[.13em] text-paper transition hover:border-orange hover:bg-orange" href="{{ route('customer.register') }}">Buat akun <span class="ml-8 text-sm">→</span></a>
        </article>

        <article class="flex flex-col border border-ink bg-paper p-7 shadow-[8px_8px_0_#dfd9cd]">
            <span class="text-3xl text-orange">03</span>
            <h2 class="mt-10 font-display text-4xl">Sebagai tamu</h2>
            <p class="mt-4 flex-1 text-sm leading-6 text-muted">Tidak ingin membuat akun? Anda tetap dapat booking atau memesan produk dengan mengisi data pada formulir.</p>
            <a class="btn-primary mt-8" href="{{ route('booking') }}">Lanjut booking <span>→</span></a>
        </article>
    </section>
@endsection
