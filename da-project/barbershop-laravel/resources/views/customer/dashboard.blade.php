@extends('layouts.app')

@section('title', 'Akun '.$customer->name)
@section('description', 'Data akun, booking, dan pesanan pelanggan HOMCUTS.')

@section('content')
    <section class="border-b border-ink bg-cream px-6 pb-16 pt-20 md:px-[6vw] md:pb-20 md:pt-28">
        <p class="section-kicker">AKUN PELANGGAN</p>
        <div class="mt-5 flex flex-wrap items-end justify-between gap-8">
            <div>
                <h1 class="page-title mt-0">Halo, {{ $customer->name }}.</h1>
                <p class="mt-6 max-w-xl font-display text-lg leading-relaxed text-muted">Booking dan pesanan yang dibuat saat Anda masuk akan tampil di sini.</p>
            </div>
            <form method="POST" action="{{ route('customer.logout') }}">
                @csrf
                <button class="link-button" type="submit">Keluar dari akun →</button>
            </form>
        </div>
    </section>

    <section class="grid gap-12 px-6 py-16 md:px-[6vw] md:py-24 lg:grid-cols-[.7fr_1.3fr]">
        <aside>
            <div class="border border-ink bg-paper p-6 shadow-[8px_8px_0_#9faa8d] md:p-8">
                <p class="section-kicker">DATA AKUN</p>
                <h2 class="mt-3 font-display text-4xl">Data Anda</h2>
                @if (session('success'))
                    <div class="mt-6 border border-sage bg-sage/15 px-4 py-3 text-xs leading-6">{{ session('success') }}</div>
                @endif
                <form class="mt-7 space-y-5" method="POST" action="{{ route('customer.account.update') }}">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="field-label mb-2" for="name">Nama lengkap</label>
                        <input class="form-control" id="name" name="name" value="{{ old('name', $customer->name) }}" autocomplete="name" required>
                        @error('name') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label mb-2" for="phone">Nomor WhatsApp</label>
                        <input class="form-control" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}" autocomplete="tel" required>
                        @error('phone') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label mb-2" for="email">Alamat email</label>
                        <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $customer->email) }}" autocomplete="email" required>
                        @error('email') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <button class="btn-primary w-full" type="submit">Simpan perubahan <span>→</span></button>
                </form>
            </div>
        </aside>

        <div class="space-y-14">
            <section>
                <div class="flex items-end justify-between gap-5 border-b border-ink pb-4">
                    <div><p class="section-kicker">BOOKING</p><h2 class="mt-2 font-display text-4xl">Jadwal Anda</h2></div>
                    <a class="link-button" href="{{ route('booking') }}">Buat booking →</a>
                </div>
                <div class="divide-y divide-ink/15 border-t border-ink/15">
                    @forelse ($bookings as $booking)
                        @php($payment = $booking->transaction?->latestPayment)
                        <article class="grid gap-4 py-6 sm:grid-cols-[1fr_auto] sm:items-center">
                            <div>
                                <p class="text-[8px] font-black uppercase tracking-[.12em] text-orange">{{ $booking->workflow_label }}</p>
                                <h3 class="mt-2 font-display text-2xl">{{ $booking->service?->name ?? 'Layanan' }}</h3>
                                <p class="mt-2 text-xs leading-6 text-muted">{{ $booking->starts_at?->translatedFormat('l, d M Y · H:i') }}@if($booking->ends_at)–{{ $booking->ends_at->format('H:i') }}@endif · {{ $booking->barber?->name ?? 'Capster belum ditentukan' }}</p>
                            </div>
                            @if ($payment)
                                <a class="btn-primary" href="{{ route('payments.show', $payment) }}">Lihat status <span>→</span></a>
                            @endif
                        </article>
                    @empty
                        <div class="py-8 text-sm leading-6 text-muted">Belum ada booking dari akun ini.</div>
                    @endforelse
                </div>
            </section>

            <section>
                <div class="flex items-end justify-between gap-5 border-b border-ink pb-4">
                    <div><p class="section-kicker">PESANAN PRODUK</p><h2 class="mt-2 font-display text-4xl">Pesanan Anda</h2></div>
                    <a class="link-button" href="{{ route('shop') }}">Belanja produk →</a>
                </div>
                <div class="divide-y divide-ink/15 border-t border-ink/15">
                    @forelse ($orders as $order)
                        @php($payment = $order->latestPayment)
                        <article class="grid gap-4 py-6 sm:grid-cols-[1fr_auto] sm:items-center">
                            <div>
                                <p class="text-[8px] font-black uppercase tracking-[.12em] text-orange">{{ $order->workflow_label }}</p>
                                <h3 class="mt-2 font-display text-2xl">Pesanan #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</h3>
                                <p class="mt-2 text-xs leading-6 text-muted">{{ $order->items->pluck('product_name')->join(', ') }} · Rp {{ number_format($order->total, 0, ',', '.') }}</p>
                            </div>
                            @if ($payment)
                                <a class="btn-primary" href="{{ route('payments.show', $payment) }}">Lihat status <span>→</span></a>
                            @endif
                        </article>
                    @empty
                        <div class="py-8 text-sm leading-6 text-muted">Belum ada pesanan produk dari akun ini.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </section>
@endsection
