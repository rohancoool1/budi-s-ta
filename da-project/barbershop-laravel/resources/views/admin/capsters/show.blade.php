@extends('admin.layouts.app')

@section('title', 'Detail Capster — '.$barber->name)

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a class="text-xs underline underline-offset-4" href="{{ route('admin.resources.index', ['resource' => 'capsters']) }}">← Kembali ke daftar capster</a>
            <p class="mt-5 text-[9px] font-black uppercase tracking-[.2em] text-orange">Detail capster</p>
            <h1 class="mt-2 font-display text-4xl">{{ $barber->name }}</h1>
            <p class="mt-2 text-sm text-muted">{{ $barber->role }} · {{ $barber->is_active ? 'Aktif' : 'Nonaktif' }}</p>
        </div>
        <a class="btn-primary" href="{{ route('admin.resources.edit', ['resource' => 'capsters', 'record' => $barber]) }}">Kelola capster <span>→</span></a>
    </div>

    <section class="mb-6 border border-ink/15 bg-paper p-5" aria-label="Profil capster">
        <p class="text-sm leading-7">{{ $barber->bio ?: 'Belum ada deskripsi capster.' }}</p>
        <p class="mt-3 text-xs text-muted">Jam kerja: {{ $barber->work_start_time ? substr($barber->work_start_time, 0, 5) : '—' }}–{{ $barber->work_end_time ? substr($barber->work_end_time, 0, 5) : '—' }} ({{ config('app.timezone') }})</p>
    </section>

    <form id="capster-filters" method="GET" action="{{ route('admin.capsters.show', $barber) }}" class="mb-6 border border-ink/15 bg-paper p-5">
        <h2 class="font-display text-2xl">Filter periode</h2>
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">
        @if ($errors->any())
            <div class="mt-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
        @endif
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="field-label mb-2" for="filter-date">Tanggal</label>
                <input id="filter-date" name="date" type="date" min="1900-01-01" max="9998-12-31" class="form-control w-full" value="{{ $filters['date'] }}">
            </div>
            <div>
                <label class="field-label mb-2" for="period">Periode</label>
                <select id="period" name="period" class="form-control w-full">
                    @foreach (['day' => 'Berdasarkan hari', 'month' => 'Berdasarkan bulan', 'year' => 'Berdasarkan tahun'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['period'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-4">
            <button type="submit" class="btn-primary">Terapkan filter <span>→</span></button>
            <a class="link-button" href="{{ route('admin.capsters.show', $barber) }}">Reset semua filter</a>
        </div>
        <p class="mt-3 text-xs leading-6 text-muted">Pilih tanggal, lalu tampilkan berdasarkan hari, bulan, atau tahun dari tanggal tersebut. Kosongkan tanggal untuk semua riwayat. Zona waktu: {{ config('app.timezone') }}.</p>
    </form>
    <p class="mb-4 text-sm font-bold">Periode: {{ $periodLabel }}</p>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (['completed' => 'Layanan selesai', 'transactions' => 'Transaksi layanan selesai', 'pending' => 'Layanan belum selesai', 'cancelled' => 'Layanan dibatalkan'] as $key => $label)
            <div class="border border-ink/15 bg-paper p-5">
                <p class="text-xs text-muted">{{ $label }}</p>
                <p class="mt-3 font-display text-4xl">{{ number_format($summary[$key], 0, ',', '.') }}</p>
            </div>
        @endforeach
    </div>
    <p class="mb-6 text-xs leading-6 text-muted">Ringkasan sesuai periode aktif (semua halaman): jumlah layanan mengikuti kuantitas item layanan pada transaksi capster ini. Hanya transaksi berstatus selesai dihitung sebagai pekerjaan selesai; produk tidak dihitung. Booking dan transaksi kasir dihitung satu kali dari catatan transaksi.</p>

    <section class="mb-6 border border-ink/15 bg-paper p-5">
        <h2 class="font-display text-2xl">Layanan yang telah dikerjakan</h2>
        <div class="mt-4 flex flex-wrap gap-3">
            @forelse ($serviceTotals as $service)
                <span class="border border-ink/15 px-3 py-2 text-sm">{{ $service->product_name }} · <strong>{{ number_format($service->completed_quantity, 0, ',', '.') }}</strong></span>
            @empty
                <p class="text-sm text-muted">Tidak ada layanan selesai yang cocok dengan filter.</p>
            @endforelse
        </div>
    </section>

    <section class="overflow-hidden border border-ink/15 bg-paper">
        <div class="p-5">
            <h2 class="font-display text-2xl">Riwayat layanan</h2>
            <p class="mt-2 text-xs leading-6 text-muted">{{ $history->total() }} catatan layanan · klik judul kolom untuk mengubah urutan. Jadwal ditampilkan dalam zona waktu {{ config('app.timezone') }}. Waktu penyelesaian aktual belum dicatat oleh sistem.</p>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-data-table w-full border-collapse text-left text-xs">
                <thead class="bg-ink text-white">
                    <tr>
                        @foreach (['date' => 'Jadwal layanan', 'customer' => 'Pelanggan', 'service' => 'Layanan', 'quantity' => 'Jumlah', 'source' => 'Sumber', 'status' => 'Status', 'payment' => 'Pembayaran', 'transaction' => 'Transaksi'] as $key => $heading)
                            @php
                                $active = $sort === $key;
                                $nextDirection = $active && $direction === 'asc' ? 'desc' : 'asc';
                                $sortUrl = route('admin.capsters.show', ['barber' => $barber, ...$filters, 'sort' => $key, 'direction' => $nextDirection]);
                            @endphp
                            <th class="px-3 py-4" aria-sort="{{ $active ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="inline-flex items-center gap-2 hover:text-orange" href="{{ $sortUrl }}" aria-label="Urutkan {{ $heading }} {{ $nextDirection === 'asc' ? 'menaik' : 'menurun' }}">
                                    <span>{{ $heading }}</span>
                                    <span aria-hidden="true" class="{{ $active ? 'text-orange' : 'text-white/50' }}">{{ $active ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    @forelse ($history as $item)
                        @php
                            $order = $item->order;
                            $start = $order->service_starts_at ?? $order->booking?->starts_at;
                            $end = $order->service_ends_at ?? $order->booking?->ends_at;
                        @endphp
                        <tr>
                            <td class="px-3 py-4" data-label="Jadwal layanan">
                                @if ($start)
                                    {{ $start->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    @if ($end)<span class="block text-muted">Sampai {{ $end->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>@endif
                                @else
                                    <span>Jadwal tidak tercatat</span>
                                    <span class="block text-muted">Transaksi dibuat {{ $order->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-4" data-label="Pelanggan">{{ $order->customer_name }}</td>
                            <td class="px-3 py-4" data-label="Layanan">{{ $item->product_name }}</td>
                            <td class="px-3 py-4" data-label="Jumlah">{{ $item->quantity }}</td>
                            <td class="px-3 py-4" data-label="Sumber">{{ $order->booking_id || $order->channel === 'booking' ? 'Booking' : ($order->channel === 'cashier' ? 'Walk-in / Kasir' : 'Aplikasi') }}</td>
                            <td class="px-3 py-4" data-label="Status">{{ ['completed' => 'Selesai', 'pending' => 'Belum selesai', 'cancelled' => 'Dibatalkan'][$order->status] ?? $order->status }}</td>
                            <td class="px-3 py-4" data-label="Pembayaran">{{ ['paid' => 'Lunas', 'partial' => 'DP', 'unpaid' => 'Belum dibayar', 'refunded' => 'Dikembalikan'][$order->payment_status] ?? $order->payment_status }}</td>
                            <td class="px-3 py-4" data-label="Transaksi"><a class="font-bold text-orange underline underline-offset-4" href="{{ route('admin.resources.edit', ['resource' => 'orders', 'record' => $order]) }}">#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center text-muted">Belum ada riwayat layanan untuk capster ini atau tidak ada hasil yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($history->hasPages())<div class="border-t border-ink/15 p-5">{{ $history->links() }}</div>@endif
    </section>
@endsection
