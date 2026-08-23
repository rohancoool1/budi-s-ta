@extends('layouts.app')

@section('title', 'About Us')
@section('description', 'Meet Brass & Blade, an independent Denpasar barbershop built around calm service and considered craft.')

@section('content')
    <section class="grid min-h-[650px] border-b border-ink lg:grid-cols-2">
        <div class="flex flex-col justify-center px-6 py-20 md:px-[6vw] md:py-28">
            <p class="section-kicker">04 / ABOUT US</p>
            <h1 class="page-title">A quieter kind<br><em>of barbershop.</em></h1>
            <p class="max-w-lg font-display text-lg leading-relaxed text-muted">We opened Brass &amp; Blade to make grooming feel less rushed: honest advice, thoughtful work, and enough time to get the details right.</p>
        </div>
        <div class="relative min-h-[500px] overflow-hidden border-t border-ink bg-sage lg:border-l lg:border-t-0">
            <img src="{{ asset('og.png') }}" alt="Inside Brass and Blade barbershop" class="h-full w-full object-cover">
            <div class="absolute bottom-6 left-6 border border-paper/60 bg-ink/80 px-5 py-4 text-paper backdrop-blur"><span class="text-[8px] font-black tracking-[.16em]">DENPASAR · SINCE 2018</span></div>
        </div>
    </section>

    <section class="grid gap-14 px-6 py-24 md:px-[6vw] lg:grid-cols-[.7fr_1.3fr] lg:py-28">
        <div><p class="section-kicker">OUR BEGINNING</p><h2 class="section-title">Three chairs.<br><em>One belief.</em></h2></div>
        <div class="grid gap-8 font-display text-lg leading-relaxed text-muted md:grid-cols-2">
            <p>Founder Made Arta spent years learning how small details change the way a haircut grows out. In 2018, he brought that thinking home to Renon and opened a focused three-chair studio.</p>
            <p>The shop has grown, but the idea has not: listen before cutting, recommend only what works, and create a result that still feels right long after the first mirror check.</p>
        </div>
    </section>

    <section class="grid border-y border-ink bg-cream md:grid-cols-3">
        @foreach ([['Listen first','Your routine, hair pattern, and reference shape the plan.'],['Cut with purpose','Every line and layer should help the style grow out well.'],['Teach the finish','You leave knowing how to recreate the look at home.']] as $value)
            <article class="border-b border-ink p-10 last:border-b-0 md:border-b-0 md:border-r md:last:border-r-0 md:p-[5vw]">
                <span class="font-display text-sm italic text-orange">0{{ $loop->iteration }}</span>
                <h2 class="my-5 font-display text-3xl">{{ $value[0] }}</h2>
                <p class="text-[11px] leading-relaxed text-muted">{{ $value[1] }}</p>
            </article>
        @endforeach
    </section>

    <section class="px-6 py-24 md:px-[6vw] md:py-28">
        <div class="grid items-start gap-12 lg:grid-cols-[.9fr_1.1fr]">
            <div><p class="section-kicker">THE JOURNEY</p><h2 class="section-title">Still learning.<br><em>Still refining.</em></h2><a class="btn-primary mt-4" href="{{ route('booking') }}">Meet us in the chair <span>↗</span></a></div>
            <div class="border-t border-ink">
                @foreach ([['2018','Brass & Blade opens in Renon with three chairs.'],['2020','Our grooming line begins with Matte Clay and Beard Oil No. 02.'],['2023','The team grows to three specialist artists and 2,000 clients.'],['Today','Still independent, still detail-obsessed, and ready for your next cut.']] as $event)
                    <div class="grid grid-cols-[72px_1fr] gap-5 border-b border-ink/20 py-6"><b class="font-display text-xl text-orange">{{ $event[0] }}</b><p class="font-display text-base text-muted">{{ $event[1] }}</p></div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
