@extends('layouts.app')

@section('title', 'Booking')
@section('description', 'Book a service or choose your preferred barber at Brass & Blade in Denpasar.')

@section('content')
    @php
        $initialArtist = old('artist_id', $selectedArtist ?: 'made');
        $initialMode = old('booking_type', $selectedArtist ? 'artist' : 'service');
    @endphp

    <section class="border-b border-ink bg-cream px-6 pb-16 pt-20 md:px-[6vw] md:pb-20 md:pt-28">
        <div class="grid items-end gap-8 lg:grid-cols-[1.4fr_.6fr]">
            <div><p class="section-kicker">01 / APPOINTMENTS</p><h1 class="page-title">Your chair<br><em>is waiting.</em></h1></div>
            <p class="max-w-md font-display text-lg leading-relaxed text-muted">Reserve in under a minute. Book the next available chair or choose the artist who knows your style.</p>
        </div>
    </section>

    <section class="grid gap-12 px-6 py-20 md:px-[6vw] lg:grid-cols-[1.25fr_.75fr] lg:py-24">
        <div class="border border-ink bg-paper shadow-[10px_10px_0_#9faa8d]">
            <form action="{{ route('bookings.store') }}" method="POST" class="p-5 md:p-8">
                @csrf
                <input id="booking-type" type="hidden" name="booking_type" value="{{ $initialMode }}">
                <div class="-mx-5 -mt-5 mb-7 grid grid-cols-2 border-b border-ink md:-mx-8 md:-mt-8">
                    <button class="booking-tab" data-booking-mode="service" type="button">01 · Quick booking</button>
                    <button class="booking-tab border-l border-ink" data-booking-mode="artist" type="button">02 · Book by barber</button>
                </div>

                <fieldset id="artist-options" class="mb-6 hidden">
                    <legend class="field-label mb-3">Choose your artist</legend>
                    <div class="grid gap-2 md:grid-cols-3">
                        @foreach ($artists as $artist)
                            <label class="artist-option cursor-pointer border border-ink/20 p-2 has-checked:border-ink has-checked:bg-cream">
                                <input class="sr-only" type="radio" name="artist_id" value="{{ $artist['id'] }}" @checked($initialArtist === $artist['id'])>
                                <span class="flex items-center gap-2">
                                    <span class="grid size-10 shrink-0 place-items-center font-display text-sm italic" style="background: {{ $artist['color'] }}">{{ $artist['initials'] }}</span>
                                    <span class="min-w-0"><b class="block truncate text-[10px]">{{ $artist['name'] }}</b><small class="block truncate text-[7px] text-muted">{{ $artist['role'] }}</small></span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <label class="field-label" for="service_id">Choose a service</label>
                <select class="form-control mt-2" id="service_id" name="service_id" required>
                    @foreach ($services as $service)
                        <option value="{{ $service['id'] }}" @selected(old('service_id') === $service['id'])>{{ $service['name'] }} — {{ $service['details'] }}</option>
                    @endforeach
                </select>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label class="field-label" for="appointment_date">Date</label><input class="form-control mt-2" id="appointment_date" name="appointment_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('appointment_date', now()->addDay()->toDateString()) }}" required></div>
                    <div><label class="field-label" for="appointment_time">Available time</label><select class="form-control mt-2" id="appointment_time" name="appointment_time"><option>09:30</option><option>10:30</option><option>13:30</option><option>16:00</option><option>18:30</option></select></div>
                    <div><label class="field-label" for="name">Your name</label><input class="form-control mt-2" id="name" name="name" value="{{ old('name') }}" placeholder="Full name" required></div>
                    <div><label class="field-label" for="phone">WhatsApp number</label><input class="form-control mt-2" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+62 812 3456 7890" required></div>
                </div>
                @if ($errors->any())
                    <div class="mt-4 border border-orange bg-orange/10 p-3 text-xs text-orange">{{ $errors->first() }}</div>
                @endif
                <button class="btn-primary mt-5 w-full" type="submit">Request appointment <span>↗</span></button>
                <p class="mt-4 text-center text-[8px] text-muted">No payment required · Free cancellation up to 3 hours before</p>
            </form>
        </div>

        <aside>
            <p class="section-kicker">YOUR BARBERS</p>
            <h2 class="mb-8 mt-3 font-display text-4xl tracking-[-.04em]">Choose the right hands.</h2>
            <div class="border-t border-ink">
                @foreach ($artists as $artist)
                    <button class="grid w-full grid-cols-[56px_1fr_auto] items-center gap-4 border-b border-ink/20 py-5 text-left" type="button" data-book-artist="{{ $artist['id'] }}">
                        <span class="grid size-14 place-items-center font-display italic" style="background: {{ $artist['color'] }}">{{ $artist['initials'] }}</span>
                        <span><b class="block font-display text-xl">{{ $artist['name'] }}</b><small class="text-[8px] uppercase tracking-[.08em] text-muted">{{ $artist['role'] }}</small></span>
                        <span class="text-orange">→</span>
                    </button>
                @endforeach
            </div>
            <div class="mt-10 flex gap-4 border-t border-ink/20 pt-6 text-[11px] leading-relaxed text-muted">
                <span class="text-2xl text-orange">◎</span>
                <p><b class="text-[10px] tracking-[.08em] text-ink">BRASS &amp; BLADE — RENON</b><br>Jl. Cok Agung Tresna No. 27, Denpasar</p>
            </div>
        </aside>
    </section>
@endsection
