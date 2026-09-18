@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div data-live-region="dashboard-content">
    <div class="mb-9 flex flex-col justify-between gap-5 md:flex-row md:items-end">
        <div>
            <p class="text-[9px] font-black uppercase tracking-[.2em] text-orange">Ringkasan operasional</p>
            <h1 class="mt-2 font-display text-5xl tracking-[-.045em] sm:text-6xl">Halo, {{ str(auth()->user()->name)->before(' ') }}.</h1>
            <p class="mt-3 text-sm text-muted">Berikut kondisi terbaru bisnis barbershop Anda.</p>
        </div>
        <p class="text-[9px] font-black uppercase tracking-[.13em] text-muted">{{ now()->translatedFormat('l, d F Y') }}</p>
    </div>

    <section class="grid gap-px border border-ink/15 bg-ink/15 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($metrics as $metric)
            <a href="{{ route('admin.resources.index', array_merge(['resource' => $metric['resource']], $metric['query'] ?? [])) }}" class="group bg-paper p-6 transition hover:bg-cream">
                <div class="flex items-start justify-between">
                    <p class="text-[9px] font-black uppercase tracking-[.14em] text-muted">{{ $metric['label'] }}</p>
                    <span class="text-orange transition group-hover:translate-x-1">→</span>
                </div>
                <p class="mt-8 font-display {{ ($metric['format'] ?? null) === 'money' ? 'text-3xl' : 'text-6xl' }} tracking-[-.06em]">{{ ($metric['format'] ?? null) === 'money' ? 'Rp '.number_format($metric['value'], 0, ',', '.') : str_pad($metric['value'], 2, '0', STR_PAD_LEFT) }}</p>
            </a>
        @endforeach
    </section>

    <section class="mt-8 border border-ink/15 bg-paper">
        <div class="flex flex-col justify-between gap-4 border-b border-ink/15 p-5 sm:flex-row sm:items-center">
            <div><p class="text-[9px] font-black uppercase tracking-[.15em] text-orange">Pekerjaan tertunda</p><h2 class="mt-1 font-display text-2xl">Booking yang belum selesai</h2></div>
            <a class="text-[8px] font-black uppercase tracking-[.12em]" href="{{ route('admin.resources.index', ['resource' => 'bookings', 'status' => 'active']) }}">Kelola semua booking →</a>
        </div>
        <div class="divide-y divide-ink/10 md:grid md:grid-cols-2 md:divide-y-0">
            @forelse ($activeBookings as $booking)
                <a href="{{ route('admin.resources.edit', ['resource' => 'bookings', 'record' => $booking]) }}" class="flex items-center justify-between gap-4 border-b border-ink/10 p-5 hover:bg-cream md:odd:border-r">
                    <div><p class="text-sm font-bold">{{ $booking->name }}</p><p class="mt-1 text-[10px] text-muted">{{ $booking->appointment_date->translatedFormat('d M Y') }} · {{ substr($booking->appointment_time, 0, 5) }} · {{ $booking->service?->name ?? $booking->service_id }} · {{ $booking->barber?->name }}</p></div>
                    <span data-live-payment-order="{{ $booking->transaction?->id }}" class="shrink-0 border px-2 py-1 text-[8px] font-black uppercase tracking-[.1em] {{ $booking->transaction?->payment_status === 'paid' ? 'border-green-600/40 bg-green-50 text-green-700' : 'border-orange/40 bg-orange/10 text-orange' }}">{{ $booking->transaction?->payment_status === 'paid' ? 'Lunas' : 'Belum dibayar' }}</span>
                </a>
            @empty
                <p class="p-8 text-center text-sm text-muted md:col-span-2">Semua booking sudah diselesaikan.</p>
            @endforelse
        </div>
    </section>

    <section class="mt-8 overflow-hidden border border-ink/15 bg-paper">
        <div class="flex flex-col justify-between gap-4 border-b border-ink/15 p-5 sm:flex-row sm:items-center">
            <div><p class="text-[9px] font-black uppercase tracking-[.15em] text-orange">Keuangan terpadu</p><h2 class="mt-1 font-display text-2xl">Transaksi terbaru</h2><p class="mt-1 text-[10px] text-muted">Booking langsung masuk saat dibuat bersama walk-in dan pesanan aplikasi.</p></div>
            <div class="flex items-center gap-4">
                <span class="hidden text-[8px] font-black uppercase tracking-[.12em] text-orange" data-sort-loading>Mengurutkan…</span>
                <a class="text-[8px] font-black uppercase tracking-[.12em]" href="{{ route('admin.resources.index', ['resource' => 'orders']) }}">Lihat seluruh riwayat →</a>
            </div>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-data-table w-full border-collapse text-left">
                <thead class="bg-cream">
                    <tr>
                        @foreach ([
                            'created_at' => 'Waktu',
                            'channel' => 'Kategori',
                            'customer_name' => 'Pelanggan',
                            'barber_name' => 'Capster',
                            'transaction_type' => 'Jenis',
                            'status' => 'Proses',
                            'payment_status' => 'Pembayaran',
                            'total' => 'Total',
                        ] as $sortKey => $label)
                            @php
                                $activeSort = request('transaction_sort', 'created_at');
                                $activeDirection = request('transaction_direction', 'desc');
                                $isActiveSort = $activeSort === $sortKey;
                                $nextDirection = $isActiveSort && $activeDirection === 'asc' ? 'desc' : 'asc';
                                $sortUrl = request()->fullUrlWithQuery(['transaction_sort' => $sortKey, 'transaction_direction' => $nextDirection]);
                            @endphp
                            <th class="px-3 py-3 text-[8px] font-black uppercase tracking-[.1em] 2xl:px-4" aria-sort="{{ $isActiveSort ? ($activeDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="group inline-flex w-full items-center gap-2 hover:text-orange" href="{{ $sortUrl }}" data-admin-sort data-sort-region="dashboard-content" aria-label="Urutkan berdasarkan {{ strtolower($label) }} {{ $nextDirection === 'asc' ? 'menaik' : 'menurun' }}">
                                    <span>{{ $label }}</span>
                                    <span class="text-[12px] {{ $isActiveSort ? 'text-orange' : 'text-ink/35 group-hover:text-orange' }}" aria-hidden="true">{{ $isActiveSort ? ($activeDirection === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    @forelse ($recentOrders as $order)
                        <tr class="hover:bg-cream/70">
                            <td class="px-3 py-4 text-[10px] 2xl:px-4" data-label="Waktu">{{ $order->created_at->translatedFormat('d M Y, H:i') }}</td>
                            <td class="px-3 py-4 text-[10px] font-bold 2xl:px-4" data-label="Kategori">{{ $order->booking_id || $order->channel === 'booking' ? 'Booking' : ($order->channel === 'cashier' ? 'Walk-in / Kasir' : 'Pesanan aplikasi') }}</td>
                            <td class="px-3 py-4 2xl:px-4" data-label="Pelanggan"><a class="text-xs font-bold underline decoration-ink/20 underline-offset-4" href="{{ route('admin.resources.edit', ['resource' => 'orders', 'record' => $order]) }}">#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }} · {{ $order->customer_name }}</a></td>
                            <td class="px-3 py-4 text-[10px] font-bold 2xl:px-4" data-label="Capster">{{ $order->barber_name ?? '—' }}</td>
                            <td class="px-3 py-4 text-[10px] 2xl:px-4" data-label="Jenis">{{ ['service' => 'Layanan', 'product' => 'Produk', 'mixed' => 'Layanan + produk'][$order->transaction_type] ?? $order->transaction_type }}</td>
                            <td class="px-3 py-4 2xl:px-4" data-label="Proses"><span data-live-order="{{ $order->id }}" class="text-[8px] font-black uppercase tracking-[.1em]">{{ $order->status === 'pending' && $order->booking_id ? 'Akan datang' : ($order->status === 'pending' && $order->payment_status === 'unpaid' ? 'Menunggu bayar' : (['pending' => 'Menunggu', 'ready' => 'Siap diambil', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$order->status] ?? $order->status)) }}</span></td>
                            <td class="px-3 py-4 2xl:px-4" data-label="Pembayaran"><span data-live-payment-order="{{ $order->id }}" class="inline-flex border px-2 py-1 text-[8px] font-black uppercase tracking-[.1em] {{ $order->payment_status === 'paid' ? 'border-green-600/40 bg-green-50 text-green-700' : 'border-orange/40 bg-orange/10 text-orange' }}">{{ ['unpaid' => 'Belum dibayar', 'paid' => 'Lunas', 'refunded' => 'Dikembalikan'][$order->payment_status] ?? $order->payment_status }}</span></td>
                            <td class="px-3 py-4 font-display text-lg 2xl:px-4" data-label="Total">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="admin-table-empty p-8 text-center text-sm text-muted" colspan="8">Belum ada transaksi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    </div>
@endsection
