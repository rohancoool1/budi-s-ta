@extends('admin.layouts.app')

@section('title', ($record ? 'Kelola ' : 'Tambah ').ucfirst($definition['singular']))

@section('content')
    <div class="mb-7 flex items-start justify-between gap-5">
        <div>
            <a class="text-[8px] font-black uppercase tracking-[.13em] text-muted" href="{{ route('admin.resources.index', ['resource' => $resource]) }}">← Kembali ke {{ strtolower($definition['label']) }}</a>
            <h1 class="mt-4 font-display text-5xl tracking-[-.045em]">{{ $record ? 'Kelola' : 'Tambah' }} {{ $definition['singular'] }}</h1>
            <p class="mt-2 text-sm text-muted">{{ $definition['description'] }}</p>
        </div>
        @if ($record)
            <div class="flex flex-col items-end gap-2">
                <span class="hidden border border-ink/15 bg-paper px-3 py-2 text-[8px] font-black uppercase tracking-[.12em] text-muted sm:block">Data #{{ $record->id }}</span>
                @if ($resource === 'bookings')
                    @if ($record->transaction)
                        <a class="border border-orange bg-orange px-3 py-2 text-[8px] font-black uppercase tracking-[.12em] text-white" href="{{ route('admin.resources.edit', ['resource' => 'orders', 'record' => $record->transaction]) }}">Buka transaksi →</a>
                    @endif
                @endif
            </div>
        @endif
    </div>

    @if ($errors->any())
        <div class="mb-6 border border-red-300 bg-red-50 px-5 py-4 text-sm text-red-800">
            <b>Periksa kembali kolom yang bermasalah.</b>
            <ul class="mt-2 list-inside list-disc text-xs leading-6">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($resource === 'orders' && $record)
        <section class="mb-6 grid gap-px border border-ink/15 bg-ink/15 md:grid-cols-2 xl:grid-cols-4">
            <div class="bg-paper p-5"><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Pelanggan</p><p class="mt-2 text-sm font-bold">{{ $record->customer_name }}</p><p class="mt-1 text-xs text-muted">{{ $record->phone ?: 'Tanpa nomor telepon' }}@if($record->email)<br>{{ $record->email }}@endif</p></div>
            <div class="bg-paper p-5"><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Kategori</p><p class="mt-2 text-sm font-bold">{{ $record->booking_id || $record->channel === 'booking' ? 'Booking' : ($record->channel === 'cashier' ? 'Walk-in / Kasir' : 'Pesanan aplikasi') }}</p><p class="mt-1 text-xs text-muted">{{ $record->cashier?->name ?: 'Dibuat pelanggan' }}</p>@if($record->queue_code)<p class="mt-3 text-[8px] font-black uppercase tracking-[.12em] text-muted">Nomor antrean</p><p class="mt-1 font-display text-3xl text-orange">{{ $record->queue_code }}</p>@endif @if($record->service_starts_at)<p class="mt-2 text-[9px] font-bold">{{ $record->service_starts_at->translatedFormat('d M Y, H:i') }}–{{ $record->service_ends_at?->format('H:i') }}</p>@endif</div>
            <div class="bg-paper p-5"><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Pembayaran</p><p class="mt-2 text-sm font-bold uppercase">{{ ['cash' => 'Tunai', 'qris' => 'QRIS (riwayat)', 'transfer' => 'Transfer bank (riwayat)'][$record->payment_method] ?? $record->payment_method }}</p><p class="mt-1"><span data-live-payment-order="{{ $record->id }}" class="inline-flex border px-2 py-1 text-[8px] font-black uppercase tracking-[.1em] {{ $record->payment_status === 'paid' ? 'border-green-600/40 bg-green-50 text-green-700' : 'border-orange/40 bg-orange/10 text-orange' }}">{{ $record->payment_status === 'paid' ? 'Lunas' : 'Belum dibayar' }}</span></p></div>
            <div class="bg-paper p-5"><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Total transaksi</p><p class="mt-2 font-display text-3xl">Rp {{ number_format($record->total, 0, ',', '.') }}</p>@if($record->discount)<p class="mt-1 text-xs text-muted">Diskon Rp {{ number_format($record->discount, 0, ',', '.') }}</p>@endif</div>
        </section>
        <section class="mb-6 overflow-hidden border border-ink/15 bg-paper">
            <div class="border-b border-ink/10 px-5 py-4"><h2 class="font-display text-2xl">Rincian transaksi</h2></div>
            <div class="divide-y divide-ink/10">
                @foreach ($record->items as $item)
                    <div class="grid grid-cols-[1fr_auto] gap-4 px-5 py-4 text-xs"><div><b>{{ $item->product_name }}</b><p class="mt-1 text-muted">{{ $item->item_type === 'service' ? 'Layanan oleh '.($item->barber?->name ?? 'barber') : 'Produk' }} · {{ $item->quantity }} × Rp {{ number_format($item->unit_price, 0, ',', '.') }}</p></div><b>Rp {{ number_format($item->line_total, 0, ',', '.') }}</b></div>
                @endforeach
            </div>
        </section>
        @if ($record->payment_status !== 'paid' && $record->payment_method === 'cash' && $record->status !== 'cancelled' && $record->latestPayment?->status === 'pending')
            <section class="mb-6 flex flex-col justify-between gap-4 border border-orange/40 bg-orange/10 p-5 sm:flex-row sm:items-center">
                <div>
                    <p class="text-[8px] font-black uppercase tracking-[.13em] text-orange">Menunggu pembayaran tunai</p>
                    <p class="mt-2 text-sm font-bold">Konfirmasi transaksi setelah uang dari pelanggan benar-benar diterima.</p>
                    <p class="mt-1 text-[10px] text-muted">Setelah dikonfirmasi, pesanan produk otomatis menjadi siap diambil dan booking otomatis menjadi aktif.</p>
                </div>
                <form method="POST" action="{{ route('admin.orders.confirm-cash', $record) }}" data-cash-confirm-form data-confirm-payment-for="{{ $record->id }}">
                    @csrf
                    <button class="btn-primary" type="submit">Konfirmasi uang diterima <span>✓</span></button>
                </form>
            </section>
        @endif
    @endif

    @if ($resource === 'bookings' && $record && $record->transaction)
        <section class="mb-6 grid gap-px border border-ink/15 bg-ink/15 sm:grid-cols-2 xl:grid-cols-5">
            <div class="bg-paper p-5"><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Nomor antrean</p><p class="mt-2 font-display text-4xl text-orange">{{ $record->transaction->queue_code ?: '—' }}</p><p class="mt-1 text-[9px] text-muted">{{ $record->transaction->queue_date?->translatedFormat('d M Y') }}</p></div>
            <div class="bg-paper p-5"><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Barber</p><p class="mt-2 text-sm font-bold">{{ $record->barber?->name ?: $record->artist_id }}</p></div>
            <div class="bg-paper p-5"><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Harga layanan</p><p class="mt-2 font-display text-3xl">Rp {{ number_format($record->transaction->total, 0, ',', '.') }}</p></div>
            <div class="bg-paper p-5"><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Pembayaran</p><p class="mt-2"><span data-live-payment-order="{{ $record->transaction->id }}" class="inline-flex border px-2 py-1 text-[8px] font-black uppercase tracking-[.1em] {{ $record->transaction->payment_status === 'paid' ? 'border-green-600/40 bg-green-50 text-green-700' : 'border-orange/40 bg-orange/10 text-orange' }}">{{ $record->transaction->payment_status === 'paid' ? 'Lunas' : 'Belum dibayar' }}</span></p><p class="mt-2 text-xs text-muted">Status booking: <b data-live-booking="{{ $record->id }}">{{ ['pending' => 'Menunggu pembayaran', 'confirmed' => 'Dikonfirmasi', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$record->status] ?? $record->status }}</b></p></div>
            <div class="bg-paper p-5"><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Transaksi terkait</p><a class="mt-2 inline-block text-sm font-bold underline" href="{{ route('admin.resources.edit', ['resource' => 'orders', 'record' => $record->transaction]) }}">#{{ str_pad($record->transaction->id, 5, '0', STR_PAD_LEFT) }} →</a></div>
        </section>
        @if ($record->transaction->payment_status !== 'paid' && $record->transaction->payment_method === 'cash' && $record->transaction->status !== 'cancelled' && $record->transaction->latestPayment?->status === 'pending')
            <section class="mb-6 flex flex-col justify-between gap-4 border border-orange/40 bg-orange/10 p-5 sm:flex-row sm:items-center">
                <div><p class="text-[8px] font-black uppercase tracking-[.13em] text-orange">Menunggu pembayaran</p><p class="mt-2 text-sm font-bold">Konfirmasi hanya setelah uang tunai diterima.</p></div>
                <form method="POST" action="{{ route('admin.orders.confirm-cash', $record->transaction) }}" data-cash-confirm-form data-confirm-payment-for="{{ $record->transaction->id }}">
                    @csrf
                    <button class="btn-primary" type="submit">Konfirmasi uang diterima <span>✓</span></button>
                </form>
            </section>
        @endif
    @endif

    @if ($resource === 'messages' && $record)
        <section class="mb-6 border border-ink/15 bg-paper p-6">
            <div class="grid gap-5 sm:grid-cols-3"><div><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Dari</p><p class="mt-2 text-sm font-bold">{{ $record->name }}</p></div><div><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Kontak</p><p class="mt-2 text-xs">{{ $record->email }}<br>{{ $record->phone ?: 'Tanpa nomor telepon' }}</p></div><div><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Subjek</p><p class="mt-2 text-sm capitalize">{{ $record->subject }}</p></div></div>
            <div class="mt-6 border-t border-ink/10 pt-6"><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Pesan</p><p class="mt-3 whitespace-pre-line text-sm leading-7">{{ $record->message }}</p></div>
        </section>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ $record ? route('admin.resources.update', ['resource' => $resource, 'record' => $record]) : route('admin.resources.store', ['resource' => $resource]) }}" class="border border-ink/15 bg-paper p-5 sm:p-7" @if($resource === 'bookings') data-admin-booking-form data-availability-url="{{ route('admin.bookings.availability') }}" data-booking-id="{{ $record?->id }}" @endif>
        @csrf
        @if ($record) @method('PUT') @endif
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($definition['fields'] as $field)
                @php
                    $storedValue = $record ? data_get($record, $field['name']) : ($field['default'] ?? '');
                    if (($field['type'] ?? '') === 'date' && $storedValue instanceof \Carbon\CarbonInterface) $storedValue = $storedValue->format('Y-m-d');
                    if (($field['type'] ?? '') === 'time' && $storedValue) $storedValue = substr((string) $storedValue, 0, 5);
                    $value = old($field['name'], $storedValue);
                @endphp
                <div class="{{ $field['wide'] ?? false ? 'md:col-span-2' : '' }}">
                    @if ($field['type'] === 'file')
                        <label class="field-label mb-2" for="{{ $field['name'] }}">{{ $field['label'] }}</label>
                        @if ($record && data_get($record, $field['stores_to']))
                            <div class="mb-3 h-48 max-w-sm border border-ink/15 bg-cover bg-center" style="background-image:url('{{ asset(data_get($record, $field['stores_to'])) }}');background-position:{{ data_get($record, $resource === 'gallery' ? 'position' : 'image_position', '50% 50%') }};background-size:{{ data_get($record, 'image_size', 'cover') }}"></div>
                            <p class="mb-3 text-[9px] text-muted">Pilih foto baru hanya jika ingin mengganti foto saat ini.</p>
                        @endif
                        <input class="form-control bg-white py-3" id="{{ $field['name'] }}" name="{{ $field['name'] }}" type="file" accept="{{ $field['accept'] ?? 'image/*' }}" @required(($field['required_on_create'] ?? false) && ! $record)>
                    @elseif ($field['type'] === 'checkbox')
                        <label class="flex min-h-12 items-center gap-3 border border-ink/20 px-4 text-xs font-bold">
                            <input class="size-4 accent-orange" name="{{ $field['name'] }}" type="checkbox" value="1" @checked((bool) $value)>
                            {{ $field['label'] }}
                        </label>
                    @else
                        <label class="field-label mb-2" for="{{ $field['name'] }}">{{ $field['label'] }}</label>
                        @if ($field['type'] === 'textarea')
                            <textarea class="form-control min-h-32 bg-white py-3" id="{{ $field['name'] }}" name="{{ $field['name'] }}">{{ $value }}</textarea>
                        @elseif ($field['type'] === 'select')
                            <select class="form-control bg-white" id="{{ $field['name'] }}" name="{{ $field['name'] }}">
                                @if (array_key_exists('placeholder', $field)) <option value="">{{ $field['placeholder'] }}</option> @endif
                                @foreach ($field['options'] as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue) @disabled($resource === 'orders' && $record?->payment_status === 'paid' && $optionValue === 'cancelled' && $record?->status !== 'cancelled')>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        @else
                            <input class="form-control bg-white {{ $field['type'] === 'color' ? 'p-1' : '' }}" id="{{ $field['name'] }}" name="{{ $field['name'] }}" type="{{ $field['type'] }}" value="{{ $value }}" @if(isset($field['min'])) min="{{ $field['min'] }}" @endif @if(isset($field['max'])) max="{{ $field['max'] }}" @endif @if(isset($field['step'])) step="{{ $field['step'] }}" @endif @if($field['type'] === 'time') lang="id-ID" @endif>
                        @endif
                    @endif
                    @error($field['name']) <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
        @if ($resource === 'bookings')
            <div id="admin-booking-availability" class="mt-6 hidden border px-4 py-3 text-xs" role="status" aria-live="polite"></div>
        @endif
        <div class="mt-8 flex flex-wrap items-center gap-3 border-t border-ink/10 pt-6">
            <button class="btn-primary" type="submit">{{ $record ? 'Simpan perubahan' : 'Buat '. $definition['singular'] }} <span>→</span></button>
            <a class="border border-ink px-5 py-4 text-[8px] font-black uppercase tracking-[.12em]" href="{{ route('admin.resources.index', ['resource' => $resource]) }}">Batal</a>
        </div>
</form>
@endsection

@if ($resource === 'bookings')
    @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-admin-booking-form]');
            const message = document.querySelector('#admin-booking-availability');
            const watched = ['booking_type', 'artist_id', 'service_id', 'appointment_date', 'appointment_time'];
            const submit = form.querySelector('[type="submit"]');
            let timer;
            const check = () => {
                clearTimeout(timer);
                timer = setTimeout(async () => {
                    const values = Object.fromEntries(watched.map((name) => [name, form.elements[name]?.value || '']));
                    if (!values.service_id || !values.appointment_date || !values.appointment_time || (values.booking_type === 'artist' && !values.artist_id)) {
                        message.classList.add('hidden');
                        submit.disabled = false;
                        return;
                    }
                    const params = new URLSearchParams(values);
                    if (form.dataset.bookingId) params.set('ignore_booking', form.dataset.bookingId);
                    message.className = 'mt-6 border border-ink/20 bg-cream px-4 py-3 text-xs text-muted';
                    message.textContent = 'Memeriksa bentrok jadwal…';
                    submit.disabled = true;
                    try {
                        const response = await fetch(`${form.dataset.availabilityUrl}?${params}`, { headers: { Accept: 'application/json' } });
                        const result = await response.json();
                        if (!response.ok) {
                            message.className = 'mt-6 border border-red-300 bg-red-50 px-4 py-3 text-xs text-red-800';
                            message.textContent = Object.values(result.errors || {}).flat()[0] || result.message || 'Slot tidak tersedia.';
                            return;
                        }
                        message.className = 'mt-6 border border-sage bg-sage/15 px-4 py-3 text-xs text-green-800';
                        message.textContent = result.message;
                        submit.disabled = false;
                    } catch (_) {
                        message.className = 'mt-6 border border-orange/40 bg-orange/10 px-4 py-3 text-xs text-orange';
                        message.textContent = 'Jadwal tetap akan diperiksa kembali saat disimpan.';
                        submit.disabled = false;
                    }
                }, 300);
            };
            watched.forEach((name) => form.elements[name]?.addEventListener('change', check));
            watched.forEach((name) => form.elements[name]?.addEventListener('input', check));
            check();
        });
        </script>
    @endpush
@endif
