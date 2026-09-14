@extends('admin.layouts.app')

@section('title', $definition['label'])

@section('content')
    <div class="mb-7 flex flex-col justify-between gap-5 md:flex-row md:items-end">
        <div>
            <p class="text-[9px] font-black uppercase tracking-[.2em] text-orange">Pengelolaan data</p>
            <h1 class="mt-2 font-display text-5xl tracking-[-.045em]">{{ $definition['label'] }}</h1>
            <p class="mt-2 max-w-xl text-sm leading-6 text-muted">{{ $definition['description'] }}</p>
        </div>
        @if ($definition['allow_create'] ?? true)
            <a class="btn-primary" href="{{ route('admin.resources.create', ['resource' => $resource]) }}">Tambah {{ $definition['singular'] }} <span>＋</span></a>
        @elseif ($resource === 'orders')
            <a class="btn-primary" href="{{ route('admin.pos.create') }}">Buat transaksi <span>＋</span></a>
        @endif
    </div>

    <div class="overflow-hidden border border-ink/15 bg-paper" data-live-region="resource-table">
        <div class="admin-table-wrap">
            <table class="admin-data-table w-full border-collapse text-left">
                <thead class="bg-ink text-white">
                    <tr>
                        @foreach ($definition['columns'] as $column)
                            @php
                                $sortKey = $column['sort'] ?? $column['key'];
                                $activeSort = request('sort', $definition['order'][0]);
                                $activeDirection = request('direction', $definition['order'][1]);
                                $isActiveSort = $activeSort === $sortKey;
                                $nextDirection = $isActiveSort && $activeDirection === 'asc' ? 'desc' : 'asc';
                                $sortUrl = request()->fullUrlWithQuery(['sort' => $sortKey, 'direction' => $nextDirection, 'page' => 1]);
                            @endphp
                            <th class="px-3 py-4 text-[8px] font-black uppercase tracking-[.1em] 2xl:px-4" aria-sort="{{ $isActiveSort ? ($activeDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="group inline-flex w-full items-center gap-2 hover:text-orange" href="{{ $sortUrl }}" data-admin-sort data-sort-region="resource-table" aria-label="Urutkan berdasarkan {{ strtolower($column['label']) }} {{ $nextDirection === 'asc' ? 'menaik' : 'menurun' }}">
                                    <span>{{ $column['label'] }}</span>
                                    <span class="text-[12px] {{ $isActiveSort ? 'text-orange' : 'text-white/45 group-hover:text-orange' }}" aria-hidden="true">{{ $isActiveSort ? ($activeDirection === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                        @endforeach
                        <th class="px-3 py-4 text-right text-[8px] font-black uppercase tracking-[.1em] 2xl:px-4">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    @forelse ($records as $record)
                        <tr class="hover:bg-cream/70">
                            @foreach ($definition['columns'] as $column)
                                @php
                                    $value = data_get($record, $column['key']);
                                    $format = $column['format'] ?? null;
                                @endphp
                                <td class="max-w-xs break-words px-3 py-4 text-xs 2xl:px-4" data-label="{{ $column['label'] }}">
                                    @if ($format === 'money')
                                        Rp {{ number_format((int) $value, 0, ',', '.') }}
                                    @elseif ($format === 'boolean')
                                        <span class="inline-flex border px-2 py-1 text-[8px] font-black uppercase tracking-[.1em] {{ $value ? 'border-sage bg-sage/20' : 'border-ink/20 text-muted' }}">{{ $value ? 'Ya' : 'Tidak' }}</span>
                                    @elseif ($format === 'status')
                                        @php
                                            $statusLabel = match (true) {
                                                $value === 'pending' && $resource === 'bookings' => 'Menunggu pembayaran',
                                                $value === 'confirmed' && $resource === 'bookings' => 'Dikonfirmasi',
                                                $value === 'pending' && $resource === 'orders' && $record->booking_id => 'Akan datang',
                                                $value === 'pending' && $resource === 'orders' && $record->payment_status === 'unpaid' => 'Menunggu bayar',
                                                default => ['pending' => 'Menunggu', 'ready' => 'Siap diambil', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', 'new' => 'Baru', 'in_progress' => 'Ditangani', 'replied' => 'Dibalas', 'archived' => 'Diarsipkan'][$value] ?? str_replace('_', ' ', (string) $value),
                                            };
                                        @endphp
                                        <span @if($resource === 'bookings') data-live-booking="{{ $record->id }}" @elseif($resource === 'orders') data-live-order="{{ $record->id }}" @endif class="inline-flex border border-orange/40 bg-orange/10 px-2 py-1 text-[8px] font-black uppercase tracking-[.1em] text-orange">{{ $statusLabel }}</span>
                                    @elseif ($format === 'payment')
                                        <span data-live-payment-order="{{ $resource === 'bookings' ? $record->transaction?->id : $record->id }}" class="inline-flex border px-2 py-1 text-[8px] font-black uppercase tracking-[.1em] {{ $value === 'paid' ? 'border-green-600/40 bg-green-50 text-green-700' : 'border-orange/40 bg-orange/10 text-orange' }}">{{ ['unpaid' => 'Belum dibayar', 'paid' => 'Lunas', 'refunded' => 'Dikembalikan'][$value] ?? ($value ?: 'Belum dibuat') }}</span>
                                    @elseif ($format === 'source')
                                        {{ $record->booking_id || $value === 'booking' ? 'Booking' : ($value === 'cashier' ? 'Walk-in / Kasir' : 'Pesanan aplikasi') }}
                                    @elseif ($format === 'transaction_type')
                                        {{ ['service' => 'Layanan', 'product' => 'Produk', 'mixed' => 'Layanan + produk'][$value] ?? $value }}
                                    @elseif ($format === 'date' && $value)
                                        {{ $value instanceof \Carbon\CarbonInterface ? $value->translatedFormat('d M Y') : \Carbon\Carbon::parse($value)->translatedFormat('d M Y') }}
                                    @elseif ($format === 'datetime' && $value)
                                        {{ $value->translatedFormat('d M Y, H:i') }}
                                    @elseif ($format === 'time')
                                        {{ substr((string) $value, 0, 5) }}
                                    @elseif ($column['key'] === 'id' && $resource === 'orders')
                                        #{{ str_pad($value, 5, '0', STR_PAD_LEFT) }}
                                    @else
                                        {{ \Illuminate\Support\Str::limit((string) ($value ?? '—'), 70) }}
                                    @endif
                                </td>
                            @endforeach
                            <td class="admin-table-actions px-3 py-4 2xl:px-4" data-label="Tindakan">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @php
                                        $paymentOrder = $resource === 'bookings' ? $record->transaction : ($resource === 'orders' ? $record : null);
                                        $canConfirmCash = $paymentOrder
                                            && $paymentOrder->payment_status !== 'paid'
                                            && $paymentOrder->payment_method === 'cash'
                                            && $paymentOrder->status !== 'cancelled'
                                            && $paymentOrder->latestPayment?->status === 'pending';
                                    @endphp
                                    @if ($canConfirmCash)
                                        <form method="POST" action="{{ route('admin.orders.confirm-cash', $paymentOrder) }}" data-cash-confirm-form data-confirm-payment-for="{{ $paymentOrder->id }}">
                                            @csrf
                                            <button class="border border-orange bg-orange px-3 py-2 text-[8px] font-black uppercase tracking-[.1em] text-white" type="submit">Konfirmasi bayar</button>
                                        </form>
                                    @endif
                                    <a class="border border-ink px-3 py-2 text-[8px] font-black uppercase tracking-[.1em] hover:bg-ink hover:text-white" href="{{ route('admin.resources.edit', ['resource' => $resource, 'record' => $record]) }}">Kelola</a>
                                    @if ($definition['allow_delete'] ?? true)
                                        <form method="POST" action="{{ route('admin.resources.destroy', ['resource' => $resource, 'record' => $record]) }}" onsubmit="return confirm('Hapus {{ $definition['singular'] }} ini? Tindakan ini tidak dapat dibatalkan.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="border border-red-300 px-3 py-2 text-[8px] font-black uppercase tracking-[.1em] text-red-700 hover:bg-red-700 hover:text-white" type="submit">Hapus</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="admin-table-empty px-5 py-16 text-center text-sm text-muted" colspan="{{ count($definition['columns']) + 1 }}">Data tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($records->hasPages())
            <div class="border-t border-ink/10 p-5">{{ $records->links() }}</div>
        @endif
    </div>
@endsection
