@extends('layouts.app')

@section('title', 'Booking')
@section('description', 'Booking layanan atau pilih barber favorit Anda di HOMCUTS Bulurokeng.')

@section('content')
    @php
        $initialArtist = old('artist_id', $selectedArtist ?: 'made');
        $initialMode = old('booking_type', $selectedArtist ? 'artist' : 'service');
    @endphp

    <section class="border-b border-ink bg-cream px-6 pb-16 pt-20 md:px-[6vw] md:pb-20 md:pt-28">
        <div class="grid items-end gap-8 lg:grid-cols-[1.4fr_.6fr]">
            <div><p class="section-kicker">01 / BOOKING</p><h1 class="page-title">Kursi Anda<br><em>sudah menunggu.</em></h1></div>
            <p class="max-w-md font-display text-lg leading-relaxed text-muted">Pesan dalam waktu kurang dari satu menit. Pilih barber yang tersedia atau barber yang paling mengenal gaya Anda.</p>
        </div>
    </section>

    <section class="grid gap-12 px-6 py-20 md:px-[6vw] lg:grid-cols-[1.25fr_.75fr] lg:py-24">
        <div class="border border-ink bg-paper shadow-[10px_10px_0_#9faa8d]">
            <form action="{{ route('bookings.store') }}" method="POST" class="p-5 md:p-8" data-booking-form data-availability-url="{{ route('bookings.availability') }}">
                @csrf
                <input id="booking-type" type="hidden" name="booking_type" value="{{ $initialMode }}">
                <div class="-mx-5 -mt-5 mb-7 grid grid-cols-2 border-b border-ink md:-mx-8 md:-mt-8" role="tablist" aria-label="Jenis booking">
                    <button class="booking-tab" data-booking-mode="service" type="button" role="tab" aria-controls="artist-options">01 · Booking cepat</button>
                    <button class="booking-tab border-l border-ink" data-booking-mode="artist" type="button" role="tab" aria-controls="artist-options">02 · Pilih barber</button>
                </div>

                <fieldset id="artist-options" class="mb-6 hidden">
                    <legend class="field-label mb-3">Pilih barber Anda</legend>
                    <div class="grid gap-2 md:grid-cols-3">
                        @foreach ($artists as $artist)
                            <label class="artist-option cursor-pointer border border-ink/20 p-2 has-checked:border-ink has-checked:bg-cream">
                                <input class="sr-only" type="radio" name="artist_id" value="{{ $artist['slug'] }}" @checked($initialArtist === $artist['slug'])>
                                <span class="flex items-center gap-2">
                                    <span class="size-10 shrink-0 bg-cover bg-center" style="background-image:url('{{ asset($artist->image_path ?: 'og.png') }}');background-position:{{ $artist->image_position }};background-size:{{ $artist->image_size }}"></span>
                                    <span class="min-w-0"><b class="block truncate text-[10px]">{{ $artist['name'] }}</b><small class="block truncate text-[7px] text-muted">{{ $artist['role'] }}</small></span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <label class="field-label" for="service_id">Pilih layanan</label>
                <select class="form-control mt-2" id="service_id" name="service_id" required>
                    @foreach ($services as $service)
                        <option value="{{ $service['slug'] }}" data-duration="{{ $service->duration_minutes }}" @selected(old('service_id') === $service['slug'])>{{ $service['name'] }} — {{ $service['details'] }}</option>
                    @endforeach
                </select>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label class="field-label" for="appointment_date">Tanggal</label><input class="form-control mt-2" id="appointment_date" name="appointment_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('appointment_date', now()->addDay()->toDateString()) }}" required></div>
                    <div><label class="field-label" for="appointment_time">Pilih waktu (mulai 07.00)</label><input class="form-control mt-2" id="appointment_time" name="appointment_time" type="time" min="07:00" max="21:30" step="60" lang="id-ID" value="{{ old('appointment_time', '09:30') }}" required><p id="booking-time-help" class="mt-2 text-[8px] leading-relaxed text-muted">Waktu terakhir menyesuaikan durasi layanan dan jam kerja barber agar selesai sebelum 22.00.</p></div>
                    <div><label class="field-label" for="name">Nama Anda</label><input class="form-control mt-2" id="name" name="name" value="{{ old('name') }}" placeholder="Nama lengkap" required></div>
                    <div><label class="field-label" for="phone">Nomor WhatsApp</label><input class="form-control mt-2" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+62 812 3456 7890" required></div>
                </div>
                <div id="booking-availability" class="mt-4 hidden border px-3 py-3 text-xs" role="status" aria-live="polite"></div>
                <input type="hidden" name="payment_method" value="cash">
                <div class="mt-5 border border-ink/15 bg-cream p-4">
                    <p class="field-label">Pembayaran tunai di kasir</p>
                    <p class="mt-2 text-[9px] leading-relaxed text-muted">Booking ditahan {{ config('payments.booking_cash_expiry_minutes') }} menit. Kasir akan mengonfirmasi booking setelah uang tunai diterima.</p>
                </div>
                @if ($errors->any())
                    <div class="mt-4 border border-orange bg-orange/10 p-3 text-xs text-orange">{{ $errors->first() }}</div>
                @endif
                <button class="btn-primary mt-5 w-full" type="submit">Buat booking <span>↗</span></button>
                <p class="mt-4 text-center text-[8px] text-muted">Bayar langsung di kasir · Slot aktif setelah pembayaran dikonfirmasi</p>
            </form>
        </div>

        <aside>
            <p class="section-kicker">BARBER ANDA</p>
            <h2 class="mb-8 mt-3 font-display text-4xl tracking-[-.04em]">Pilih tangan yang tepat.</h2>
            <div class="border-t border-ink">
                @foreach ($artists as $artist)
                    <button class="grid w-full grid-cols-[56px_1fr_auto] items-center gap-4 border-b border-ink/20 py-5 text-left" type="button" data-book-artist="{{ $artist['slug'] }}">
                        <span class="size-14 bg-cover bg-center" style="background-image:url('{{ asset($artist->image_path ?: 'og.png') }}');background-position:{{ $artist->image_position }};background-size:{{ $artist->image_size }}"></span>
                        <span><b class="block font-display text-xl">{{ $artist['name'] }}</b><small class="text-[8px] uppercase tracking-[.08em] text-muted">{{ $artist['role'] }}</small></span>
                        <span class="text-orange">→</span>
                    </button>
                @endforeach
            </div>
            <div class="mt-10 flex gap-4 border-t border-ink/20 pt-6 text-[11px] leading-relaxed text-muted">
                <span class="text-2xl text-orange">◎</span>
                <p><b class="text-[10px] tracking-[.08em] text-ink">{{ strtoupper($siteSettings->get('shop_name', 'HOMCUTS')) }}</b><br>{{ $siteSettings->get('address_line_1', 'Jl. Ir. Sutami') }}<br>{{ $siteSettings->get('address_line_2', 'Bulurokeng, Makassar') }}</p>
            </div>
        </aside>
    </section>
@endsection
