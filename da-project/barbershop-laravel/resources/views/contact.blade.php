@extends('layouts.app')

@section('title', 'Hubungi Kami')
@section('description', 'Hubungi HOMCUTS di Jl. Ir. Sutami, Bulurokeng, Makassar untuk booking, produk, dan pertanyaan umum.')

@section('content')
    <section class="border-b border-ink bg-cream px-6 pb-16 pt-20 md:px-[6vw] md:pb-20 md:pt-28">
        <div class="grid items-end gap-8 lg:grid-cols-[1.4fr_.6fr]">
            <div><p class="section-kicker">05 / HUBUNGI KAMI</p><h1 class="page-title">Mari bicara<br><em>tentang rambut.</em></h1></div>
            <p class="max-w-md font-display text-lg leading-relaxed text-muted">Punya pertanyaan tentang potongan, produk, atau kunjungan berikutnya? Kirim pesan dan kami akan membantu Anda.</p>
        </div>
    </section>

    <section class="grid border-b border-ink lg:grid-cols-[.8fr_1.2fr]">
        <aside class="bg-sage px-6 py-16 md:px-[6vw] md:py-20 lg:border-r lg:border-ink">
            <p class="section-kicker !text-ink">{{ strtoupper($siteSettings->get('shop_name', 'HOMCUTS')) }}</p>
            <h2 class="my-6 font-display text-4xl tracking-[-.04em]">Kunjungi barbershop kami.</h2>
            <div class="space-y-8 border-t border-ink pt-8 text-[11px] leading-relaxed">
                <div><b class="field-label mb-2">Alamat</b><p>{{ $siteSettings->get('address_line_1') }}<br>{{ $siteSettings->get('address_line_2') }}</p></div>
                <div><b class="field-label mb-2">Telepon atau WhatsApp</b><a class="link-button" href="https://wa.me/{{ ltrim($siteSettings->get('phone_link', '+62882020703600'), '+') }}" target="_blank" rel="noopener">{{ $siteSettings->get('phone', '0882-0207-03600') }} ↗</a></div>
                <div><b class="field-label mb-2">Media sosial</b><a class="link-button normal-case" href="{{ $siteSettings->get('instagram_url', 'https://instagram.com/homcuts_') }}" target="_blank" rel="noopener">Instagram {{ $siteSettings->get('instagram_handle', '@homcuts_') }} ↗</a><br><a class="link-button normal-case mt-2" href="{{ $siteSettings->get('tiktok_url', 'https://www.tiktok.com/@homcuts') }}" target="_blank" rel="noopener">TikTok {{ $siteSettings->get('tiktok_handle', '@homcuts') }} ↗</a></div>
                @if ($siteSettings->get('email'))<div><b class="field-label mb-2">Email</b><a class="link-button normal-case" href="mailto:{{ $siteSettings->get('email') }}">{{ $siteSettings->get('email') }} ↗</a></div>@endif
                <div><b class="field-label mb-2">Jam buka</b><p>{{ $siteSettings->get('hours_weekday') }}<br>{{ $siteSettings->get('hours_weekend') }}<br>{{ $siteSettings->get('hours_closed') }}</p></div>
            </div>
            <div class="relative mt-12 grid min-h-56 place-items-center overflow-hidden border border-ink bg-paper/35">
                <div class="absolute inset-0 opacity-30" style="background-image: linear-gradient(#171714 1px, transparent 1px), linear-gradient(90deg, #171714 1px, transparent 1px); background-size: 38px 38px"></div>
                <img class="relative size-32 object-contain" src="{{ asset('homcuts-logo-transparent.png') }}" alt="Logo HOMCUTS">
            </div>
        </aside>

        <div class="px-6 py-16 md:px-[6vw] md:py-20">
            <p class="section-kicker">KIRIM PESAN</p>
            <h2 class="mb-9 mt-3 font-display text-4xl tracking-[-.04em]">Ada yang bisa kami bantu?</h2>
            <form action="{{ route('contact.store') }}" method="POST" class="grid gap-5 sm:grid-cols-2">
                @csrf
                <div><label class="field-label" for="contact-name">Nama Anda</label><input class="form-control mt-2" id="contact-name" name="name" value="{{ old('name') }}" required></div>
                <div><label class="field-label" for="contact-email">Email</label><input class="form-control mt-2" id="contact-email" name="email" type="email" value="{{ old('email') }}" required></div>
                <div><label class="field-label" for="contact-phone">WhatsApp <span class="text-muted">(opsional)</span></label><input class="form-control mt-2" id="contact-phone" name="phone" value="{{ old('phone') }}"></div>
                <div><label class="field-label" for="subject">Topik pesan</label><select class="form-control mt-2" id="subject" name="subject"><option value="general" @selected(old('subject') === 'general')>Pertanyaan umum</option><option value="booking" @selected(old('subject') === 'booking')>Bantuan booking</option><option value="product" @selected(old('subject') === 'product')>Saran produk</option><option value="collaboration" @selected(old('subject') === 'collaboration')>Kolaborasi</option></select></div>
                <div class="sm:col-span-2"><label class="field-label" for="message">Pesan Anda</label><textarea class="form-control mt-2 min-h-40 py-3" id="message" name="message" placeholder="Ceritakan kebutuhan Anda…" required>{{ old('message') }}</textarea></div>
                @if ($errors->any())
                    <div class="border border-orange bg-orange/10 p-3 text-xs text-orange sm:col-span-2">{{ $errors->first() }}</div>
                @endif
                <button class="btn-primary sm:col-span-2" type="submit">Kirim pesan <span>↗</span></button>
            </form>
        </div>
    </section>
@endsection
