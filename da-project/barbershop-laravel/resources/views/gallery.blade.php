@extends('layouts.app')

@section('title', 'Galeri Rambut')
@section('description', 'Lihat koleksi hasil potongan dan referensi model rambut dari HOMCUTS.')

@section('content')
    <section class="grid border-b border-ink lg:grid-cols-[.85fr_1.15fr]">
        <div class="flex flex-col justify-center px-6 py-20 md:px-[6vw] md:py-28">
            <p class="section-kicker">03 / KARYA KAMI</p>
            <h1 class="page-title">Potongan yang<br><em>berbicara tenang.</em></h1>
            <p class="max-w-md font-display text-lg leading-relaxed text-muted">Koleksi hasil potongan, referensi gaya, dan detail yang dapat Anda bawa saat berkonsultasi dengan barber.</p>
        </div>
        <div class="min-h-[420px] border-t border-ink bg-cover bg-center lg:border-l lg:border-t-0" style="background-image:url('{{ asset($gallery->first()?->image_path ?? 'haircut-gallery.png') }}');background-position:{{ $gallery->first()?->position ?? '50% 50%' }};background-size:{{ $gallery->first()?->image_size ?? 'cover' }}"></div>
    </section>

    <section class="px-6 py-20 md:px-[6vw] md:py-28">
        <div class="mb-14 grid gap-6 lg:grid-cols-[1fr_.6fr] lg:items-end">
            <div><p class="section-kicker">REFERENSI GAYA</p><h2 class="section-title mb-0">Temukan bentuk.<br><em>Yang paling Anda.</em></h2></div>
            <p class="font-display text-base leading-relaxed text-muted">Satu nama gaya dapat tampil pada beberapa foto karena hasil akhirnya menyesuaikan tekstur rambut, bentuk kepala, dan cara penataan.</p>
        </div>

        <div class="grid gap-x-5 gap-y-14 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($gallery as $item)
                <article class="flex h-full flex-col">
                    <div class="gallery-photo aspect-square border border-ink/20" style="background-image:url('{{ asset($item['image_path']) }}');background-position:{{ $item['position'] }};background-size:{{ $item['image_size'] }}" role="img" aria-label="Potongan rambut {{ $item['style'] }}"></div>
                    <div class="flex flex-1 flex-col border-b border-ink py-5">
                        <div class="mb-5 flex items-start justify-between gap-4">
                            <div><span class="text-[8px] font-black uppercase tracking-[.14em] text-orange">GAYA {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h2 class="mt-2 font-display text-3xl">{{ $item['style'] }}</h2></div>
                            <span class="font-display text-2xl text-sage">✦</span>
                        </div>
                        <p class="flex-1 font-display text-[15px] leading-relaxed text-muted">{{ $item['quote'] }}</p>
                        <div class="mt-4 flex items-center justify-between gap-4 text-[8px] font-black uppercase tracking-[.12em]"><span>{{ $item['client'] }}</span>@if($item->barber)<span class="text-muted">Dikerjakan oleh {{ $item->barber->name }}</span>@endif</div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="flex flex-col items-start justify-between gap-8 border-t border-ink bg-sage px-6 py-16 md:flex-row md:items-center md:px-[6vw]">
        <div><p class="section-kicker !text-ink">SIAP UNTUK VERSI ANDA?</p><h2 class="mt-3 font-display text-4xl tracking-[-.04em]">Bawa referensi. Pulang dengan gaya Anda.</h2></div>
        <a class="btn-primary shrink-0" href="{{ route('booking') }}">Buat booking <span>↗</span></a>
    </section>
@endsection
