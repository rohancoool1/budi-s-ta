@extends('layouts.app')

@section('title', 'Pembayaran #'.$payment->order_id)
@section('description', 'Informasi pembayaran tunai transaksi HOMCUTS.')

@section('content')
    @php
        $isBooking = (bool) $payment->order->booking_id;
        $isProductOrder = $payment->order->channel === 'online' && $payment->order->transaction_type === 'product';
        $statusLabels = [
            'pending' => 'Menunggu pembayaran',
            'paid' => 'Pembayaran berhasil',
            'expired' => 'Waktu pembayaran habis',
            'failed' => 'Pembayaran gagal',
            'cancelled' => 'Pembayaran dibatalkan',
            'review' => 'Perlu pemeriksaan admin',
            'refunded' => 'Dana dikembalikan',
        ];
    @endphp

    <section class="min-h-[calc(100vh-76px)] bg-cream px-4 py-10 md:px-8 md:py-16">
        <div id="payment-screen" class="mx-auto grid max-w-5xl overflow-hidden border border-ink bg-paper shadow-[12px_12px_0_#9faa8d] lg:grid-cols-[.9fr_1.1fr]"
             data-status-url="{{ route('payments.status', $payment) }}"
             data-current-status="{{ $payment->status }}"
             data-is-booking="{{ $isBooking ? 'true' : 'false' }}"
             data-is-product-order="{{ $isProductOrder ? 'true' : 'false' }}"
             data-booking-status="{{ $payment->order->booking?->status }}"
             data-schedule-changed-at="{{ $payment->order->booking?->schedule_changed_at?->toIso8601String() }}"
             data-expires-at="{{ $payment->expires_at?->toIso8601String() }}"
             data-clear-product-cart="{{ $isProductOrder ? 'true' : 'false' }}">
            <aside class="bg-ink p-7 text-paper md:p-10">
                <p class="text-[9px] font-black uppercase tracking-[.2em] text-orange">Transaksi #{{ str_pad($payment->order_id, 5, '0', STR_PAD_LEFT) }}</p>
                <h1 class="mt-5 font-display text-5xl leading-[.95] tracking-[-.05em]">{{ $isBooking ? 'Amankan jadwal Anda.' : 'Selesaikan pesanan Anda.' }}</h1>
                <div class="mt-10 space-y-4 border-t border-white/20 pt-6 text-xs">
                    <div class="flex justify-between gap-5"><span class="text-white/55">Pelanggan</span><b class="text-right">{{ $payment->order->customer_name }}</b></div>
                    <div class="flex justify-between gap-5"><span class="text-white/55">Kategori</span><b>{{ $isBooking ? 'Booking capster' : ($payment->order->channel === 'cashier' ? 'Kasir / walk-in' : 'Pesanan produk') }}</b></div>
                    @if ($payment->order->queue_code)
                        <div class="flex items-center justify-between gap-5 border-y border-white/20 py-4"><span class="text-white/55">Nomor antrean</span><b id="booking-queue" class="font-display text-4xl text-orange">{{ $payment->order->queue_code }}</b></div>
                    @endif
                    @if ($isBooking)
                        <div class="flex justify-between gap-5"><span class="text-white/55">Jadwal</span><b id="booking-schedule" class="text-right">{{ $payment->order->booking?->starts_at?->translatedFormat('d M Y, H:i') ?? 'Jadwal lama' }}@if($payment->order->booking?->ends_at)–{{ $payment->order->booking->ends_at->format('H:i') }}@endif</b></div>
                        <div class="flex justify-between gap-5"><span class="text-white/55">Capster</span><b id="booking-barber">{{ $payment->order->booking?->barber?->name ?? $payment->order->booking?->artist_id ?? 'Belum ditentukan' }}</b></div>
                        <div class="flex justify-between gap-5"><span class="text-white/55">Layanan</span><b id="booking-service" class="text-right">{{ $payment->order->booking?->service?->name ?? $payment->order->booking?->service_id }}</b></div>
                    @endif
                    <div class="flex justify-between gap-5 border-t border-white/20 pt-4"><span class="text-white/55">Total</span><b class="font-display text-2xl">Rp {{ number_format($payment->amount, 0, ',', '.') }}</b></div>
                </div>
                <p class="mt-10 break-all text-[8px] uppercase tracking-[.08em] text-white/35">Kode: {{ $payment->reference }}</p>
            </aside>

            <div class="p-7 md:p-10">
                @if (session('success'))
                    <div class="mb-5 border border-sage bg-sage/20 px-4 py-3 text-xs">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-5 border border-red-300 bg-red-50 px-4 py-3 text-xs text-red-800">{{ session('error') }}</div>
                @endif

                @if ($isBooking)
                    <div id="booking-schedule-notice" class="mb-5 border border-orange/40 bg-orange/10 px-4 py-3 text-xs leading-6 text-orange {{ $payment->order->booking?->schedule_changed_at ? '' : 'hidden' }}" role="status" aria-live="polite">
                        <b>Jadwal booking diperbarui oleh admin.</b> Periksa kembali jadwal, capster, dan nomor antrean terbaru pada halaman ini.
                    </div>
                @endif

                <div class="flex items-start justify-between gap-5">
                    <div><p class="section-kicker">{{ $payment->method === 'cash' ? 'TUNAI' : strtoupper($payment->method).' · RIWAYAT' }}</p><h2 id="payment-title" class="mt-2 font-display text-3xl">{{ $statusLabels[$payment->status] ?? $payment->status }}</h2></div>
                    <span id="payment-status-badge" class="border px-3 py-2 text-[8px] font-black uppercase tracking-[.1em] {{ $payment->status === 'paid' ? 'border-green-600/40 bg-green-50 text-green-700' : 'border-orange/40 bg-orange/10 text-orange' }}">{{ $statusLabels[$payment->status] ?? $payment->status }}</span>
                </div>

                <div id="payment-pending" class="{{ $payment->status === 'pending' ? '' : 'hidden' }}">
                    <div class="mt-8 border border-ink/15 bg-cream p-6">
                        <p class="text-3xl">◎</p>
                        <h3 class="mt-4 font-display text-2xl">Bayar tunai di kasir.</h3>
                        <p class="mt-2 text-xs leading-6 text-muted">
                            @if($payment->order->queue_code)
                                Tunjukkan kode transaksi dan nomor antrean di atas.
                            @else
                                Tunjukkan kode transaksi di atas.
                            @endif
                            Kasir akan mengonfirmasi transaksi setelah uang diterima. Status@if($isBooking) dan informasi booking@endif pada halaman ini diperbarui otomatis.
                        </p>
                    </div>

                    @if ($payment->expires_at)
                        <div class="mt-6 flex items-center justify-between border-y border-ink/15 py-4"><span class="text-[9px] font-black uppercase tracking-[.12em] text-muted">Sisa waktu</span><b id="payment-countdown" class="font-display text-2xl">--:--</b></div>
                    @endif

                </div>

                <div id="payment-finished" class="{{ $payment->status === 'pending' ? 'hidden' : '' }} mt-8">
                    <div id="payment-result-icon" class="grid size-16 place-items-center rounded-full {{ $payment->status === 'paid' ? 'bg-sage' : 'bg-orange/15 text-orange' }} font-display text-3xl">{{ $payment->status === 'paid' ? '✓' : '!' }}</div>
                    <p id="payment-result-message" class="mt-5 text-sm leading-7 text-muted">
                        @if ($payment->status === 'paid' && $isBooking)
                            Booking sudah aktif. Datang sesuai jadwal yang tercantum.
                        @elseif ($payment->status === 'paid' && $isProductOrder)
                            Pembayaran lunas dan pesanan siap diambil di barbershop.
                        @elseif ($payment->status === 'paid')
                            Pembayaran telah tercatat di transaksi kasir.
                        @else
                            Transaksi tidak lagi aktif. Silakan buat pesanan baru atau hubungi admin.
                        @endif
                    </p>
                    <a class="btn-primary mt-6" href="{{ $isBooking ? route('booking') : ($isProductOrder ? route('shop') : (auth()->check() ? route('admin.resources.edit', ['resource' => 'orders', 'record' => $payment->order]) : route('home'))) }}">{{ $isBooking ? 'Kembali ke booking' : ($isProductOrder ? 'Kembali ke toko' : 'Lihat transaksi') }} <span>→</span></a>
                </div>
            </div>
        </div>
    </section>
@endsection
