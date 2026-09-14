<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Layanan barber dan produk perawatan HOMCUTS di Bulurokeng, Makassar.')">
    <meta property="og:title" content="@yield('title', 'HOMCUTS Barbershop')">
    <meta property="og:description" content="Potongan presisi, layanan barber, dan produk perawatan di Bulurokeng, Makassar.">
    <meta property="og:image" content="{{ url('/homcuts-storefront.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('homcuts-logo-transparent.png') }}">
    <title>@yield('title', 'HOMCUTS') · HOMCUTS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper text-ink antialiased selection:bg-orange selection:text-white">
    @php
        $navItems = [
            ['route' => 'home', 'label' => 'Beranda'],
            ['route' => 'booking', 'label' => 'Booking'],
            ['route' => 'shop', 'label' => 'Toko produk'],
            ['route' => 'gallery', 'label' => 'Galeri'],
            ['route' => 'about', 'label' => 'Tentang kami'],
            ['route' => 'contact', 'label' => 'Kontak'],
        ];
    @endphp

    <header class="sticky top-0 z-40 border-b border-ink/20 bg-paper/95 backdrop-blur">
        <div class="grid h-[76px] grid-cols-[1fr_auto_1fr] items-center px-5 md:px-[5vw]">
            <a href="{{ route('home') }}" class="flex w-max items-center gap-3" aria-label="Beranda HOMCUTS">
                <img class="size-12 object-contain" src="{{ asset('homcuts-logo-transparent.png') }}" alt="Logo HOMCUTS">
                <span class="hidden text-xs font-black tracking-[.18em] sm:block">HOMCUTS</span>
            </a>

            <nav class="hidden h-full items-center justify-center gap-7 lg:flex" aria-label="Navigasi utama">
                @foreach ($navItems as $item)
                    <a class="nav-link {{ request()->routeIs($item['route']) ? 'is-active' : '' }}" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>

            <div class="flex items-center justify-self-end gap-3">
                <button id="open-cart" class="text-[9px] font-black uppercase tracking-[.12em]" type="button">
                    Keranjang <span id="cart-count" class="ml-1 inline-grid min-w-7 place-items-center bg-ink px-2 py-2 text-[9px] text-white">00</span>
                </button>
                <button id="mobile-menu-button" class="grid size-9 place-items-center border border-ink lg:hidden" type="button" aria-controls="mobile-menu" aria-expanded="false" aria-label="Buka navigasi">
                    <span class="text-lg leading-none">≡</span>
                </button>
            </div>
        </div>

        <nav id="mobile-menu" class="hidden border-t border-ink bg-paper px-5 py-3 lg:hidden" aria-label="Navigasi seluler">
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
            <img class="size-16 object-contain brightness-0 invert" src="{{ asset('homcuts-logo-transparent.png') }}" alt="Logo HOMCUTS">
            <h2 class="mt-7 font-display text-5xl">Tetap rapi.</h2>
            <p class="mt-2 font-display italic text-white/55">Potongan tepat. Hari lebih baik.</p>
        </div>
        <div class="footer-column"><b>KUNJUNGI</b><p>{{ $siteSettings->get('address_line_1', 'Jl. Ir. Sutami') }}<br>{{ $siteSettings->get('address_line_2', 'Bulurokeng, Makassar') }}</p><a href="{{ route('contact') }}">Lihat kontak →</a></div>
        <div class="footer-column"><b>JAM BUKA</b><p>{{ $siteSettings->get('hours_weekday', 'Selasa—Jumat 07:00—22:00') }}<br>{{ $siteSettings->get('hours_weekend', 'Sabtu—Minggu 07:00—22:00') }}<br>{{ $siteSettings->get('hours_closed', 'Senin tutup') }}</p><a href="{{ route('booking') }}">Booking kursi →</a></div>
        <div class="footer-column"><b>TERHUBUNG</b><a href="{{ $siteSettings->get('instagram_url', 'https://instagram.com/homcuts_') }}" target="_blank" rel="noopener">Instagram {{ $siteSettings->get('instagram_handle', '@homcuts_') }} ↗</a><a href="{{ $siteSettings->get('tiktok_url', 'https://www.tiktok.com/@homcuts') }}" target="_blank" rel="noopener">TikTok {{ $siteSettings->get('tiktok_handle', '@homcuts') }} ↗</a><a href="{{ route('gallery') }}">Galeri rambut ↗</a></div>
    </footer>

    @include('partials.cart')

    <script id="cart-product-catalog" type="application/json">@json($cartProductCatalog)</script>

    <div id="cart-toast" class="fixed bottom-5 left-1/2 z-[65] hidden -translate-x-1/2 bg-ink px-5 py-3 text-[9px] font-black uppercase tracking-[.12em] text-white" role="status">Ditambahkan ke keranjang</div>

    @php
        $successMessage = session('booking_success') ?? session('order_success') ?? session('contact_success');
        $successKind = session('booking_success') ? 'booking' : (session('order_success') ? 'order' : 'contact');
    @endphp
    @if ($successMessage)
        <div id="success-modal" class="fixed inset-0 z-[80] grid place-items-center bg-black/70 p-4 backdrop-blur-sm" data-clear-cart="{{ $successKind === 'order' ? 'true' : 'false' }}">
            <div class="relative w-full max-w-lg border border-ink bg-paper p-8 shadow-[12px_12px_0_#9faa8d] md:p-12" role="dialog" aria-modal="true" aria-labelledby="success-title">
                <button id="close-success" class="absolute right-4 top-4 grid size-10 place-items-center border border-ink font-display text-2xl" type="button" aria-label="Tutup konfirmasi">×</button>
                <span class="mb-8 grid size-16 place-items-center rounded-full bg-sage font-display text-3xl">✓</span>
                <p class="section-kicker">{{ ['booking' => 'PERMINTAAN BOOKING', 'order' => 'PESANAN TERSIMPAN', 'contact' => 'PESAN DITERIMA'][$successKind] }}</p>
                <h2 id="success-title" class="mb-4 mt-3 font-display text-5xl tracking-[-.04em]">{{ ['booking' => 'Jadwal Anda tercatat.', 'order' => 'Pilihan yang tepat.', 'contact' => 'Kami akan menghubungi Anda.'][$successKind] }}</h2>
                <p class="font-display text-base leading-relaxed text-muted">{{ $successMessage }}</p>
                <button id="success-action" class="btn-primary mt-7" type="button">Selesai <span>→</span></button>
            </div>
        </div>
    @endif
</body>
</html>
