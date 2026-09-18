@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    <section class="grid min-h-[calc(100vh-76px)] border-b border-ink lg:grid-cols-[58%_42%]">
        <div class="flex min-h-[620px] flex-col justify-center px-6 py-16 md:px-[6vw] md:py-[8vh]">
            <p class="eyebrow"><span></span> HOMCUTS · BULUROKENG</p>
            <h1 class="my-8 font-display text-[clamp(3.8rem,7.4vw,7.2rem)] font-medium leading-[.86] tracking-[-.06em]">
                Potongan tajam.<br><em class="font-normal text-orange">Percaya diri tenang.</em>
            </h1>
            <p class="max-w-xl font-display text-lg leading-relaxed text-muted">Potongan presisi, perawatan yang teliti, dan produk pilihan untuk menjaga penampilan Anda tetap rapi.</p>
            <div class="mt-8 flex flex-col items-start gap-5 sm:flex-row sm:items-center sm:gap-8">
                <a class="btn-primary" href="{{ route('booking') }}">Buat booking <span>↗</span></a>
                <a class="link-button" href="{{ route('shop') }}">Lihat produk <span>→</span></a>
            </div>
            <div class="mt-auto flex flex-wrap gap-5 pt-12 text-[9px] uppercase tracking-[.09em] text-muted">
                <span><b class="text-ink">@homcuts_</b> Instagram</span>
                <span><b class="text-ink">0882-0207-03600</b> WhatsApp</span>
                <span><b class="text-ink">Selasa—Minggu</b> 07:00—22:00</span>
            </div>
        </div>
        <div class="relative min-h-[560px] overflow-hidden border-t border-ink bg-sage lg:min-h-[620px] lg:border-l lg:border-t-0">
            <img src="{{ asset('homcuts-storefront.png') }}" alt="Tampak depan barbershop HOMCUTS di Bulurokeng" class="h-full w-full object-cover object-center">
            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink/75 to-transparent p-7 pt-28 text-right text-[9px] font-black tracking-[.2em] text-white">RAFLI &amp; APPINK<br>HOMCUTS TEAM</div>
        </div>
    </section>

    @if ($featuredProducts->isNotEmpty())
        <div class="product-marquee border-b border-ink bg-ink text-paper" aria-label="Produk pilihan HOMCUTS">
            <div class="product-marquee__track">
                @foreach ([false, true] as $duplicate)
                    <div class="product-marquee__group" @if($duplicate) aria-hidden="true" @endif>
                        @foreach ($featuredProducts as $product)
                            <span class="product-marquee__item">
                                <img src="{{ asset($product->image_path ?: 'og.png') }}" alt="{{ $duplicate ? '' : 'Foto '.$product->name }}" loading="{{ $duplicate ? 'lazy' : 'eager' }}">
                                <span>{{ strtoupper($product->name) }}</span>
                                <i aria-hidden="true">✦</i>
                            </span>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <section class="grid gap-12 border-b border-ink px-6 py-24 md:px-[6vw] lg:grid-cols-[.8fr_1.2fr] lg:py-28">
        <div>
            <p class="section-kicker">CARA KAMI BEKERJA</p>
            <h2 class="section-title">Tampil cakep<br><em>di mana aja.</em></h2>
            <p class="max-w-md font-display text-[17px] leading-relaxed text-muted">Kursi yang nyaman, konsultasi yang jujur, dan potongan yang disesuaikan dengan karakter rambut Anda.</p>
            <a class="link-button mt-7" href="{{ route('about') }}">Baca cerita kami →</a>
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
            <div><p class="section-kicker">TANGAN DI BALIK KARYA</p><h2 class="section-title mb-0">Kenali <em>capster Anda.</em></h2></div>
            <a class="link-button" href="{{ route('booking') }}">Pilih capster →</a>
        </div>
        <div class="grid gap-10 md:grid-cols-3 md:gap-4">
            @foreach ($artists as $index => $artist)
                <article>
                    <div class="relative h-80 overflow-hidden border border-ink bg-cover md:h-[410px]" style="background-image:url('{{ asset($artist->image_path ?: 'og.png') }}');background-position:{{ $artist->image_position }};background-size:{{ $artist->image_size }}">
                        <div class="absolute inset-0 bg-gradient-to-t from-ink/45 via-transparent to-transparent"></div>
                        <small class="absolute right-4 top-4 font-display italic">0{{ $index + 1 }}</small>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <div><h3 class="font-display text-2xl">{{ $artist['name'] }}</h3><p class="mt-1 text-[8px] uppercase tracking-[.08em] text-muted">{{ $artist['role'] }}</p></div>
                        <a class="link-button" href="{{ route('booking', ['artist' => $artist['slug']]) }}">Booking ↗</a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="grid border-y border-ink bg-cream lg:grid-cols-2">
        <div class="min-h-[440px] bg-cover bg-center lg:border-r lg:border-ink" style="background-image:url('{{ asset($featuredGallery?->image_path ?? 'haircut-gallery.png') }}');background-position:{{ $featuredGallery?->position ?? '50% 50%' }};background-size:{{ $featuredGallery?->image_size ?? 'cover' }}"></div>
        <div class="flex flex-col justify-center px-6 py-20 md:px-[6vw]">
            <p class="section-kicker">POTONGAN NYATA · PELANGGAN NYATA</p>
            <h2 class="section-title">Temukan<br><em>gaya berikutnya.</em></h2>
            <p class="max-w-md font-display text-base leading-relaxed text-muted">Lihat hasil potongan pelanggan kami, dari tekstur yang mudah dirawat sampai bentuk klasik yang tak lekang waktu.</p>
            <a class="btn-primary mt-8 w-max" href="{{ route('gallery') }}">Lihat galeri <span>↗</span></a>
        </div>
    </section>
@endsection
