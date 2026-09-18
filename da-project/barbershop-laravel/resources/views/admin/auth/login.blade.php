<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" type="image/jpeg" href="{{ asset('homcuts-logo.jpg') }}">
    <title>Masuk admin · HOMCUTS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-ink text-paper antialiased">
    <main class="grid min-h-screen lg:grid-cols-[1.05fr_.95fr]">
        <section class="relative hidden overflow-hidden border-r border-white/10 p-14 lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -right-36 -top-40 size-[34rem] rounded-full border border-orange/30"></div>
            <div class="absolute -bottom-48 left-12 size-[30rem] rounded-full border border-sage/20"></div>
            <a href="{{ route('home') }}" class="relative flex w-max items-center gap-4">
                <img class="size-14 bg-paper object-contain" src="{{ asset('homcuts-logo.jpg') }}" alt="Logo HOMCUTS">
                <span class="text-xs font-black tracking-[.2em]">HOMCUTS</span>
            </a>
            <div class="relative max-w-xl">
                <p class="text-[10px] font-black uppercase tracking-[.24em] text-orange">Area pengelolaan privat</p>
                <h1 class="mt-5 font-display text-7xl leading-[.9] tracking-[-.055em]">Kelola bisnis.<br><em class="font-normal text-sage">Tetap tajam.</em></h1>
                <p class="mt-7 max-w-md text-sm leading-7 text-white/55">Kelola kasir, booking, transaksi, produk, capster, galeri, pesan, dan informasi situs dari satu tempat.</p>
            </div>
            <p class="relative text-[9px] font-bold uppercase tracking-[.14em] text-white/35">Khusus staf berwenang</p>
        </section>

        <section class="flex items-center justify-center bg-paper px-5 py-12 text-ink sm:px-10">
            <div class="w-full max-w-md">
                <a href="{{ route('home') }}" class="mb-14 flex w-max items-center gap-3 lg:hidden">
                    <img class="size-12 border border-ink object-contain" src="{{ asset('homcuts-logo.jpg') }}" alt="Logo HOMCUTS">
                    <span class="text-[10px] font-black tracking-[.18em]">HOMCUTS</span>
                </a>
                <p class="text-[9px] font-black uppercase tracking-[.2em] text-orange">Portal admin</p>
                <h2 class="mt-3 font-display text-5xl tracking-[-.045em]">Selamat datang.</h2>
                <p class="mt-3 text-sm leading-6 text-muted">Masuk menggunakan akun administrator.</p>

                @if (session('status'))
                    <div class="mt-6 border border-sage bg-sage/15 px-4 py-3 text-sm">{{ session('status') }}</div>
                @endif

                <form class="mt-9 space-y-5" method="POST" action="{{ route('admin.login.store') }}">
                    @csrf
                    <div>
                        <label class="field-label mb-2" for="email">Alamat email</label>
                        <input class="form-control bg-white" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required>
                        @error('email') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label mb-2" for="password">Kata sandi</label>
                        <input class="form-control bg-white" id="password" name="password" type="password" autocomplete="current-password" required>
                        @error('password') <p class="mt-2 text-xs text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-3 text-xs text-muted">
                        <input class="size-4 accent-orange" name="remember" type="checkbox" value="1">
                        Tetap masuk di perangkat ini
                    </label>
                    <button class="btn-primary w-full" type="submit">Masuk dengan aman <span>→</span></button>
                </form>
                <a class="mt-8 inline-block border-b border-ink/30 text-[9px] font-black uppercase tracking-[.12em]" href="{{ route('home') }}">← Kembali ke situs</a>
            </div>
        </section>
    </main>
</body>
</html>
