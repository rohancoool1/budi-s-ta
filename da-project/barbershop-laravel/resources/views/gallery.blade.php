@extends('layouts.app')

@section('title', 'Hair Gallery')
@section('description', 'Explore real Brass & Blade haircuts, hairstyle names, and client testimonials.')

@section('content')
    <section class="grid border-b border-ink lg:grid-cols-[.85fr_1.15fr]">
        <div class="flex flex-col justify-center px-6 py-20 md:px-[6vw] md:py-28">
            <p class="section-kicker">03 / OUR WORK</p>
            <h1 class="page-title">Cuts that<br><em>speak quietly.</em></h1>
            <p class="max-w-md font-display text-lg leading-relaxed text-muted">Real clients, wearable styles, and the details that make each cut feel personal.</p>
        </div>
        <div class="min-h-[420px] border-t border-ink bg-cover bg-center lg:border-l lg:border-t-0" style="background-image: url('{{ asset('haircut-gallery.png') }}')"></div>
    </section>

    <section class="px-6 py-20 md:px-[6vw] md:py-28">
        <div class="mb-14 grid gap-6 lg:grid-cols-[1fr_.6fr] lg:items-end">
            <div><p class="section-kicker">CLIENT NOTES</p><h2 class="section-title mb-0">The look.<br><em>In their words.</em></h2></div>
            <p class="font-display text-base leading-relaxed text-muted">Every appointment starts with a conversation. These are some of the results our clients came back to tell us about.</p>
        </div>

        <div class="grid gap-x-5 gap-y-14 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($gallery as $item)
                <article>
                    <div class="gallery-photo aspect-square border border-ink/20" style="background-image: url('{{ asset('haircut-gallery.png') }}'); background-position: {{ $item['position'] }}" role="img" aria-label="{{ $item['style'] }} haircut"></div>
                    <div class="border-b border-ink py-5">
                        <div class="mb-5 flex items-start justify-between gap-4">
                            <div><span class="text-[8px] font-black uppercase tracking-[.14em] text-orange">LOOK 0{{ $loop->iteration }}</span><h2 class="mt-2 font-display text-3xl">{{ $item['style'] }}</h2></div>
                            <span class="font-display text-2xl text-sage">“</span>
                        </div>
                        <blockquote class="font-display text-[15px] italic leading-relaxed text-muted">{{ $item['quote'] }}</blockquote>
                        <p class="mt-4 text-[8px] font-black uppercase tracking-[.12em]">— {{ $item['client'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="flex flex-col items-start justify-between gap-8 border-t border-ink bg-sage px-6 py-16 md:flex-row md:items-center md:px-[6vw]">
        <div><p class="section-kicker !text-ink">READY FOR YOUR VERSION?</p><h2 class="mt-3 font-display text-4xl tracking-[-.04em]">Bring a reference. Leave with your cut.</h2></div>
        <a class="btn-primary shrink-0" href="{{ route('booking') }}">Book your chair <span>↗</span></a>
    </section>
@endsection
