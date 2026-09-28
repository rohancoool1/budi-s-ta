@extends('admin.layouts.app')

@section('title', 'Kasir POS')

@section('content')
    @php
        $posErrors = $errors->getBag('pos');
    @endphp

    <div class="mb-8 flex flex-col justify-between gap-5 xl:flex-row xl:items-end">
        <div>
            <p class="text-[9px] font-black uppercase tracking-[.2em] text-orange">Sistem kasir</p>
            <h1 class="mt-2 font-display text-5xl tracking-[-.045em] sm:text-6xl">Kasir &amp; transaksi.</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">Buat transaksi layanan untuk menerbitkan nomor antrean walk-in. Pelanggan mengambil antrean, menerima layanan, lalu membayar tunai setelah selesai.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a class="border border-ink bg-paper px-5 py-4 text-[8px] font-black uppercase tracking-[.12em]" href="{{ route('admin.resources.index', ['resource' => 'orders', 'tab' => 'booking']) }}">Kelola booking aktif →</a>
            <a class="border border-ink bg-paper px-5 py-4 text-[8px] font-black uppercase tracking-[.12em]" href="{{ route('admin.resources.index', ['resource' => 'orders']) }}">Transaksi →</a>
        </div>
    </div>

    @if ($posErrors->any())
        <div class="mb-6 border border-red-300 bg-red-50 px-5 py-4 text-sm text-red-800">
            <b>Transaksi belum dapat disimpan.</b>
            <ul class="mt-2 list-inside list-disc text-xs leading-6">@foreach ($posErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-8 2xl:grid-cols-[1fr_360px]">
        <form id="pos-form" method="POST" action="{{ route('admin.pos.store') }}" class="space-y-6" data-pos-form data-availability-url="{{ route('admin.pos.availability') }}" data-service-duration="{{ $serviceDurationMinutes }}" data-open-time="{{ $storeOpenTime }}" data-close-time="{{ $storeCloseTime }}" data-latest-time="{{ $latestBookingTime }}">
            @csrf
            <section class="border border-ink/15 bg-paper p-5 sm:p-7">
                <div class="mb-6 flex items-center justify-between border-b border-ink/10 pb-4"><div><p class="text-[8px] font-black uppercase tracking-[.14em] text-orange">01 · Pelanggan</p><h2 class="mt-1 font-display text-2xl">Pelanggan walk-in</h2></div><span class="text-2xl text-sage">◎</span></div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="field-label mb-2" for="customer_name">Nama pelanggan</label><input class="form-control bg-white" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" placeholder="Pelanggan Walk-in"></div>
                    <div><label class="field-label mb-2" for="phone">WhatsApp <span class="text-muted">(opsional)</span></label><input class="form-control bg-white" id="phone" name="phone" value="{{ old('phone') }}"></div>
                </div>
            </section>

            <nav class="grid grid-cols-2 border border-ink bg-paper" aria-label="Pilihan item transaksi">
                <button class="bg-ink px-5 py-4 text-[9px] font-black uppercase tracking-[.13em] text-white" type="button" data-pos-tab="service" aria-selected="true">Layanan capster</button>
                <button class="px-5 py-4 text-[9px] font-black uppercase tracking-[.13em]" type="button" data-pos-tab="product" aria-selected="false">Produk</button>
            </nav>

            <section class="border border-ink/15 bg-paper p-5 sm:p-7" data-pos-panel="service">
                <div class="mb-6 flex items-center justify-between border-b border-ink/10 pb-4"><div><p class="text-[8px] font-black uppercase tracking-[.14em] text-orange">02 · Layanan</p><h2 class="mt-1 font-display text-2xl">Tambahkan jasa capster</h2></div><span class="text-2xl text-sage">✂</span></div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="field-label mb-2" for="service_id">Layanan <span class="text-muted">(opsional)</span></label><select class="form-control bg-white" id="service_id" name="service_id"><option value="">Tidak ada layanan</option>@foreach($services as $service)<option value="{{ $service->id }}" data-price="{{ $service->price }}" @selected((string) old('service_id') === (string) $service->id)>{{ $service->name }} — Rp {{ number_format($service->price, 0, ',', '.') }}</option>@endforeach</select></div>
                    <div><label class="field-label mb-2" for="barber_id">Capster yang melayani</label><select class="form-control bg-white" id="barber_id" name="barber_id"><option value="">Pilih capster</option>@foreach($barbers as $barber)<option value="{{ $barber->id }}" @selected((string) old('barber_id') === (string) $barber->id)>{{ $barber->name }}</option>@endforeach</select></div>
                    <div><p class="field-label mb-2">Tanggal layanan</p><div class="border border-ink/15 bg-cream px-4 py-3 text-xs font-bold">{{ now()->translatedFormat('d F Y') }} · Hari ini</div></div>
                    <div><label class="field-label mb-2" for="service_time">Waktu mulai</label><input class="form-control bg-white" id="service_time" name="service_time" type="time" min="{{ $storeOpenTime }}" max="{{ $latestBookingTime }}" step="60" value="{{ old('service_time', now()->format('H:i')) }}" data-use-current-time="{{ old('service_time') ? 'false' : 'true' }}"><p id="pos-time-help" class="mt-2 text-[8px] leading-relaxed text-muted">Otomatis memakai waktu saat ini dan tetap dapat diubah. Toko beroperasi {{ str_replace(':', '.', $storeOpenTime) }}–{{ str_replace(':', '.', $storeCloseTime) }}; estimasi layanan {{ $serviceDurationMinutes }} menit.</p></div>
                </div>
                <div id="pos-availability" class="mt-4 hidden border px-3 py-3 text-xs" role="status" aria-live="polite"></div>
            </section>

            <section class="hidden border border-ink/15 bg-paper p-5 sm:p-7" data-pos-panel="product">
                <div class="mb-6 flex items-center justify-between border-b border-ink/10 pb-4"><div><p class="text-[8px] font-black uppercase tracking-[.14em] text-orange">03 · Produk</p><h2 class="mt-1 font-display text-2xl">Tambahkan belanja produk</h2></div><span class="text-2xl text-sage">＋</span></div>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($products as $product)
                        <label class="grid grid-cols-[64px_1fr_58px] items-center gap-3 border border-ink/15 p-2 {{ $product->stock < 1 ? 'opacity-45' : '' }}">
                            <span class="h-16 bg-cover bg-center" style="background-image:url('{{ asset($product->image_path) }}');background-position:{{ $product->image_position }};background-size:{{ $product->image_size }}"></span>
                            <span class="min-w-0"><b class="block truncate text-[10px]">{{ $product->name }}</b><small class="mt-1 block text-[8px] text-muted">Rp {{ number_format($product->price, 0, ',', '.') }} · tersisa {{ $product->stock }}</small></span>
                            <input class="form-control min-h-10 px-2 text-center" name="products[{{ $product->id }}]" type="number" min="0" max="{{ $product->stock }}" value="{{ old('products.'.$product->id, 0) }}" data-product-price="{{ $product->price }}" {{ $product->stock < 1 ? 'disabled' : '' }} aria-label="Jumlah {{ $product->name }}">
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="border border-ink/15 bg-paper p-5 sm:p-7">
                <div class="mb-6 border-b border-ink/10 pb-4"><p class="text-[8px] font-black uppercase tracking-[.14em] text-orange">04 · Pembayaran</p><h2 class="mt-1 font-display text-2xl">Terbitkan antrean</h2></div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div><input type="hidden" name="payment_method" value="cash"><p class="field-label mb-2">Metode pembayaran</p><div class="border border-ink/15 bg-white px-4 py-3 text-xs font-bold">Tunai — dibayar setelah layanan selesai</div><p class="mt-2 text-[9px] leading-relaxed text-muted">Jika terdapat layanan, nomor antrean dibuat otomatis. Transaksi tetap belum lunas sampai kasir menerima uang.</p></div>
                    <div><label class="field-label mb-2" for="discount">Diskon (Rp)</label><input class="form-control bg-white" id="discount" name="discount" type="number" min="0" value="{{ old('discount', 0) }}"></div>
                    <div class="md:col-span-2"><label class="field-label mb-2" for="notes">Catatan internal</label><textarea class="form-control min-h-24 bg-white py-3" id="notes" name="notes">{{ old('notes') }}</textarea></div>
                </div>
                <div class="mt-7 flex flex-col items-stretch justify-between gap-5 border-t border-ink/10 pt-6 sm:flex-row sm:items-center">
                    <div><p class="text-[8px] font-black uppercase tracking-[.13em] text-muted">Perkiraan total</p><p id="pos-total" class="mt-1 font-display text-4xl">Rp 0</p></div>
                    <button class="btn-primary" type="submit">Buat antrean / transaksi <span>→</span></button>
                </div>
            </section>
        </form>

        <aside class="h-max border border-ink/15 bg-paper 2xl:sticky 2xl:top-6" data-live-region="pos-pending-payments">
            <div class="border-b border-ink/10 p-5"><p class="text-[8px] font-black uppercase tracking-[.14em] text-orange">Perlu tindakan kasir</p><h2 class="mt-1 font-display text-2xl">Menunggu pembayaran</h2><p class="mt-2 text-[9px] leading-4 text-muted">Hanya transaksi yang dibuat langsung melalui Kasir POS.</p></div>
            <div class="divide-y divide-ink/10">
                @forelse ($recentSales as $sale)
                    <div class="p-5" data-pending-payment-card="{{ $sale->id }}">
                        <div class="flex items-center justify-between gap-3"><a class="text-xs font-bold underline" href="{{ route('admin.resources.edit', ['resource' => $sale->booking_id ? 'bookings' : 'orders', 'record' => $sale->booking_id ?: $sale]) }}">#{{ str_pad($sale->id, 5, '0', STR_PAD_LEFT) }}</a><span data-live-order="{{ $sale->id }}" class="border border-orange/40 bg-orange/10 px-2 py-1 text-[8px] font-black uppercase tracking-[.1em] text-orange">{{ $sale->workflow_label }}</span></div>
                        @if($sale->queue_code)<p class="mt-2 font-display text-3xl text-orange">Antrean {{ $sale->queue_code }}</p>@endif
                        @if($sale->service_starts_at)<p class="mt-1 text-[9px] font-bold">{{ $sale->service_starts_at->format('H:i') }}–{{ $sale->service_ends_at?->format('H:i') }}</p>@endif
                        <p class="mt-2 truncate text-xs font-bold">{{ $sale->customer_name }}</p>
                        <p class="mt-1 text-[9px] text-muted">{{ $sale->booking_id ? 'Booking' : ($sale->channel === 'online' ? 'Pesanan produk' : 'Walk-in / Kasir') }} · Rp {{ number_format($sale->total, 0, ',', '.') }}</p>
                        <form class="mt-3" method="POST" action="{{ route('admin.orders.confirm-cash', $sale) }}" data-cash-confirm-form data-confirm-payment-for="{{ $sale->id }}">
                            @csrf
                            <button class="w-full border border-orange bg-orange px-3 py-2 text-[8px] font-black uppercase tracking-[.1em] text-white" type="submit">Konfirmasi uang diterima</button>
                        </form>
                    </div>
                @empty
                    <p class="p-8 text-center text-xs text-muted">Tidak ada pembayaran yang menunggu.</p>
                @endforelse
            </div>
        </aside>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabs = [...document.querySelectorAll('[data-pos-tab]')];
    const panels = [...document.querySelectorAll('[data-pos-panel]')];
    const service = document.querySelector('#service_id');
    const barber = document.querySelector('#barber_id');
    const serviceTime = document.querySelector('#service_time');
    const availability = document.querySelector('#pos-availability');
    const form = document.querySelector('[data-pos-form]');
    const submit = form.querySelector('[type="submit"]');
    const discount = document.querySelector('#discount');
    const total = document.querySelector('#pos-total');
    const productInputs = [...document.querySelectorAll('[data-product-price]')];
    const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    let availabilityTimer;
    let availabilityRequest;

    if (serviceTime.dataset.useCurrentTime === 'true') {
        const now = new Date();
        serviceTime.value = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    }

    const selectTab = (name) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.posTab === name;
            tab.classList.toggle('bg-ink', active);
            tab.classList.toggle('text-white', active);
            tab.setAttribute('aria-selected', String(active));
        });
        panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.posPanel !== name));
    };

    const updateTotal = () => {
        const servicePrice = Number(service.selectedOptions[0]?.dataset.price || 0);
        const productTotal = productInputs.reduce((sum, input) => sum + Number(input.value || 0) * Number(input.dataset.productPrice), 0);
        total.textContent = money.format(Math.max(0, servicePrice + productTotal - Number(discount.value || 0)));
    };

    const updateTimeLimit = () => {
        const opening = form.dataset.openTime || '07:00';
        const latest = form.dataset.latestTime || '21:15';
        serviceTime.min = opening;
        serviceTime.max = latest;
        serviceTime.required = Boolean(service.value);
        barber.required = Boolean(service.value);
    };

    const checkAvailability = () => {
        updateTimeLimit();
        if (!service.value) {
            availability.classList.add('hidden');
            submit.disabled = false;
            return;
        }
        if (!barber.value || !serviceTime.value) {
            availability.className = 'mt-4 border border-orange/40 bg-orange/10 px-3 py-3 text-xs text-orange';
            availability.textContent = 'Pilih capster dan waktu mulai untuk memeriksa jadwal.';
            submit.disabled = true;
            return;
        }

        clearTimeout(availabilityTimer);
        availabilityTimer = setTimeout(async () => {
            availabilityRequest?.abort();
            availabilityRequest = new AbortController();
            availability.className = 'mt-4 border border-ink/20 bg-cream px-3 py-3 text-xs text-muted';
            availability.textContent = 'Memeriksa kalender booking dan walk-in…';
            submit.disabled = true;
            const params = new URLSearchParams({
                service_id: service.value,
                barber_id: barber.value,
                service_time: serviceTime.value,
            });

            try {
                const response = await fetch(`${form.dataset.availabilityUrl}?${params}`, {
                    headers: { Accept: 'application/json' },
                    signal: availabilityRequest.signal,
                });
                const result = await response.json();
                if (!response.ok) {
                    availability.className = 'mt-4 border border-red-300 bg-red-50 px-3 py-3 text-xs text-red-800';
                    availability.textContent = Object.values(result.errors || {}).flat()[0] || result.message || 'Slot tidak tersedia.';
                    submit.disabled = true;
                    return;
                }
                availability.className = 'mt-4 border border-sage bg-sage/15 px-3 py-3 text-xs text-green-800';
                availability.textContent = result.message;
                submit.disabled = false;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    availability.className = 'mt-4 border border-orange/40 bg-orange/10 px-3 py-3 text-xs text-orange';
                    availability.textContent = 'Pemeriksaan cepat tidak tersedia. Jadwal tetap diperiksa ketika transaksi disimpan.';
                    submit.disabled = false;
                }
            }
        }, 300);
    };

    service.addEventListener('change', () => { updateTotal(); checkAvailability(); });
    barber.addEventListener('change', checkAvailability);
    serviceTime.addEventListener('change', checkAvailability);
    serviceTime.addEventListener('input', checkAvailability);
    discount.addEventListener('input', updateTotal);
    productInputs.forEach((input) => input.addEventListener('input', updateTotal));
    tabs.forEach((tab) => tab.addEventListener('click', () => selectTab(tab.dataset.posTab)));
    selectTab(@js(collect(old('products', []))->filter(fn ($quantity) => (int) $quantity > 0)->isNotEmpty() && ! old('service_id') ? 'product' : 'service'));
    updateTotal();
    checkAvailability();
});
</script>
@endpush
