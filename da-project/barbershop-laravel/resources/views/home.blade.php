@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <section class="grid min-h-[calc(100vh-76px)] border-b border-ink lg:grid-cols-[58%_42%]">
        <div class="flex min-h-[620px] flex-col justify-center px-6 py-16 md:px-[6vw] md:py-[8vh]">
            <p class="eyebrow"><span></span> EST. 2018 · DENPASAR</p>
            <h1 class="my-8 font-display text-[clamp(3.8rem,7.4vw,7.2rem)] font-medium leading-[.86] tracking-[-.06em]">
                Sharp cuts.<br><em class="font-normal text-orange">Quiet confidence.</em>
            </h1>
            <p class="max-w-xl font-display text-lg leading-relaxed text-muted">Precision barbering, considered grooming, and the right products to keep your look dialled in.</p>
            <div class="mt-8 flex flex-col items-start gap-5 sm:flex-row sm:items-center sm:gap-8">
                <a class="btn-primary" href="{{ route('booking') }}">Book an appointment <span>↗</span></a>
                <a class="link-button" href="{{ route('shop') }}">Explore the shop <span>→</span></a>
            </div>
            <div class="mt-auto flex flex-wrap gap-5 pt-12 text-[9px] uppercase tracking-[.09em] text-muted">
                <span><b class="text-ink">4.9</b> ★ Google rating</span>
                <span><b class="text-ink">2,400+</b> cuts delivered</span>
                <span><b class="text-ink">Tue—Sun</b> 09:00—20:00</span>
            </div>
        </div>
        <div class="relative min-h-[560px] overflow-hidden border-t border-ink bg-sage lg:min-h-[620px] lg:border-l lg:border-t-0">
            <img src="{{ asset('og.png') }}" alt="Brass and Blade premium barber chair" class="h-full w-full object-cover object-center">
            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink/75 to-transparent p-7 pt-28 text-right text-[9px] font-black tracking-[.2em] text-white">THE SIGNATURE<br>EXPERIENCE</div>
        </div>
    </section>

    <div class="flex min-h-14 items-center justify-start gap-6 overflow-hidden whitespace-nowrap bg-ink px-8 text-[9px] font-black tracking-[.18em] text-paper md:justify-around">
        <span>CLASSIC CUTS</span><i class="text-orange">✦</i><span>MODERN FADES</span><i class="text-orange">✦</i><span>HOT TOWEL SHAVES</span><i class="text-orange">✦</i><span>BEARD DESIGN</span>
    </div>

    <section class="grid gap-12 border-b border-ink px-6 py-24 md:px-[6vw] lg:grid-cols-[.8fr_1.2fr] lg:py-28">
        <div>
            <p class="section-kicker">THE BRASS &amp; BLADE WAY</p>
            <h2 class="section-title">Built around<br><em>your ritual.</em></h2>
            <p class="max-w-md font-display text-[17px] leading-relaxed text-muted">A calm chair, an honest consultation, and a cut designed for the way your hair actually behaves.</p>
            <a class="link-button mt-7" href="{{ route('about') }}">Read our story →</a>
        </div>
        <div class="grid border border-ink sm:grid-cols-2">
            @foreach ($services as $service)
                <article class="border-b border-ink p-7 sm:odd:border-r [&:nth-last-child(-n+2)]:sm:border-b-0 last:border-b-0">
                    <span class="font-display text-sm italic text-orange">0{{ $loop->iteration }}</span>
                    <h3 class="mb-2 mt-8 font-display text-2xl">{{ $service['name'] }}</h3>
                    <p class="text-[9px] uppercase tracking-[.09em] text-muted">{{ $service['details'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="px-6 py-24 md:px-[6vw]">
        <div class="mb-12 flex flex-col items-start justify-between gap-6 md:flex-row md:items-end">
            <div><p class="section-kicker">THE HANDS BEHIND THE WORK</p><h2 class="section-title mb-0">Meet your <em>artists.</em></h2></div>
            <a class="link-button" href="{{ route('booking') }}">Choose your barber →</a>
        </div>
        <div class="grid gap-10 md:grid-cols-3 md:gap-4">
            @foreach ($artists as $index => $artist)
                <article>
                    <div class="relative grid h-80 place-items-center overflow-hidden border border-ink md:h-[410px]" style="background: {{ $artist['color'] }}">
                        <div class="absolute size-64 rounded-full border border-ink/20"></div>
                        <span class="relative font-display text-7xl italic tracking-[-.08em]">{{ $artist['initials'] }}</span>
                        <small class="absolute right-4 top-4 font-display italic">0{{ $index + 1 }}</small>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <div><h3 class="font-display text-2xl">{{ $artist['name'] }}</h3><p class="mt-1 text-[8px] uppercase tracking-[.08em] text-muted">{{ $artist['role'] }}</p></div>
                        <a class="link-button" href="{{ route('booking', ['artist' => $artist['id']]) }}">Book ↗</a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="grid border-y border-ink bg-cream lg:grid-cols-2">
        <div class="min-h-[440px] bg-cover bg-center lg:border-r lg:border-ink" style="background-image: url('{{ asset('haircut-gallery.png') }}')"></div>
        <div class="flex flex-col justify-center px-6 py-20 md:px-[6vw]">
            <p class="section-kicker">REAL CUTS · REAL CLIENTS</p>
            <h2 class="section-title">Find your<br><em>next look.</em></h2>
            <p class="max-w-md font-display text-base leading-relaxed text-muted">Explore the cuts our clients wear every day, from low-maintenance texture to timeless shape.</p>
            <a class="btn-primary mt-8 w-max" href="{{ route('gallery') }}">View the gallery <span>↗</span></a>
        </div>
    </section>
@endsection
