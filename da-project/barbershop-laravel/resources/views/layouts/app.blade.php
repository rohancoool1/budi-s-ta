<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Premium barber appointments and grooming essentials in Denpasar.')">
    <meta property="og:title" content="@yield('title', 'Brass & Blade Barbershop')">
    <meta property="og:description" content="Sharp cuts. Quiet confidence. Book your artist and shop barber-approved essentials.">
    <meta property="og:image" content="{{ url('/og.png') }}">
    <title>@yield('title', 'Brass & Blade') · Brass &amp; Blade</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper text-ink antialiased selection:bg-orange selection:text-white">
    @php
        $navItems = [
            ['route' => 'home', 'label' => 'Home'],
            ['route' => 'booking', 'label' => 'Booking'],
            ['route' => 'shop', 'label' => 'Product shop'],
            ['route' => 'gallery', 'label' => 'Gallery'],
            ['route' => 'about', 'label' => 'About us'],
            ['route' => 'contact', 'label' => 'Contact'],
        ];
    @endphp

    <header class="sticky top-0 z-40 border-b border-ink/20 bg-paper/95 backdrop-blur">
        <div class="grid h-[76px] grid-cols-[1fr_auto_1fr] items-center px-5 md:px-[5vw]">
            <a href="{{ route('home') }}" class="flex w-max items-center gap-3" aria-label="Brass and Blade home">
                <span class="grid size-9 place-items-center bg-ink font-display text-xl italic text-paper">B</span>
                <span class="hidden text-xs font-black tracking-[.16em] sm:block">BRASS &amp; BLADE</span>
            </a>

            <nav class="hidden h-full items-center justify-center gap-7 lg:flex" aria-label="Main navigation">
                @foreach ($navItems as $item)
                    <a class="nav-link {{ request()->routeIs($item['route']) ? 'is-active' : '' }}" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>

            <div class="flex items-center justify-self-end gap-3">
                <button id="open-cart" class="text-[9px] font-black uppercase tracking-[.12em]" type="button">
                    Bag <span id="cart-count" class="ml-1 inline-grid min-w-7 place-items-center bg-ink px-2 py-2 text-[9px] text-white">00</span>
                </button>
                <button id="mobile-menu-button" class="grid size-9 place-items-center border border-ink lg:hidden" type="button" aria-controls="mobile-menu" aria-expanded="false" aria-label="Open navigation">
                    <span class="text-lg leading-none">≡</span>
                </button>
            </div>
        </div>

        <nav id="mobile-menu" class="hidden border-t border-ink bg-paper px-5 py-3 lg:hidden" aria-label="Mobile navigation">
            @foreach ($navItems as $item)
                <a class="flex items-center justify-between border-b border-ink/15 py-4 text-[10px] font-black uppercase tracking-[.12em] last:border-b-0" href="{{ route($item['route']) }}">
                    {{ $item['label'] }} <span class="text-orange">{{ request()->routeIs($item['route']) ? '●' : '→' }}</span>
                </a>
            @endforeach
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="grid gap-12 bg-ink px-6 py-20 text-paper md:grid-cols-2 md:px-[6vw] lg:grid-cols-4">
        <div>
            <span class="grid size-10 place-items-center bg-paper font-display text-xl italic text-ink">B</span>
            <h2 class="mt-7 font-display text-5xl">Stay sharp.</h2>
            <p class="mt-2 font-display italic text-white/55">Good cuts. Better days.</p>
        </div>
        <div class="footer-column"><b>VISIT</b><p>Jl. Cok Agung Tresna No. 27<br>Renon, Denpasar</p><a href="{{ route('contact') }}">Get directions →</a></div>
        <div class="footer-column"><b>HOURS</b><p>Tue—Fri 09:00—20:00<br>Sat—Sun 09:00—18:00<br>Monday closed</p><a href="{{ route('booking') }}">Reserve a chair →</a></div>
        <div class="footer-column"><b>EXPLORE</b><a href="{{ route('gallery') }}">Hair gallery ↗</a><a href="{{ route('about') }}">Our story ↗</a><a href="{{ route('shop') }}">Product shop ↗</a></div>
    </footer>

    @include('partials.cart')

    <div id="cart-toast" class="fixed bottom-5 left-1/2 z-[65] hidden -translate-x-1/2 bg-ink px-5 py-3 text-[9px] font-black uppercase tracking-[.12em] text-white" role="status">Added to your bag</div>

    @php
        $successMessage = session('booking_success') ?? session('order_success') ?? session('contact_success');
        $successKind = session('booking_success') ? 'booking' : (session('order_success') ? 'order' : 'contact');
    @endphp
    @if ($successMessage)
        <div id="success-modal" class="fixed inset-0 z-[80] grid place-items-center bg-black/70 p-4 backdrop-blur-sm" data-clear-cart="{{ $successKind === 'order' ? 'true' : 'false' }}">
            <div class="relative w-full max-w-lg border border-ink bg-paper p-8 shadow-[12px_12px_0_#9faa8d] md:p-12" role="dialog" aria-modal="true" aria-labelledby="success-title">
                <button id="close-success" class="absolute right-4 top-4 grid size-10 place-items-center border border-ink font-display text-2xl" type="button" aria-label="Close confirmation">×</button>
                <span class="mb-8 grid size-16 place-items-center rounded-full bg-sage font-display text-3xl">✓</span>
                <p class="section-kicker">{{ ['booking' => 'APPOINTMENT REQUESTED', 'order' => 'ORDER CONFIRMED', 'contact' => 'MESSAGE RECEIVED'][$successKind] }}</p>
                <h2 id="success-title" class="mb-4 mt-3 font-display text-5xl tracking-[-.04em]">{{ ['booking' => 'You’re on the books.', 'order' => 'Sharp choice.', 'contact' => 'We’ll be in touch.'][$successKind] }}</h2>
                <p class="font-display text-base leading-relaxed text-muted">{{ $successMessage }}</p>
                <button id="success-action" class="btn-primary mt-7" type="button">Done <span>→</span></button>
            </div>
        </div>
    @endif
</body>
</html>
