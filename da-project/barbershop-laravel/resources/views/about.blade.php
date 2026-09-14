@extends('layouts.app')

@section('title', 'Tentang Kami')
@section('description', 'Kenali HOMCUTS, barbershop di Bulurokeng, Makassar dengan layanan ramah dan pengerjaan yang teliti.')

@section('content')
    <section class="grid min-h-[650px] border-b border-ink lg:grid-cols-2">
        <div class="flex flex-col justify-center px-6 py-20 md:px-[6vw] md:py-28">
            <p class="section-kicker">04 / TENTANG KAMI</p>
            <h1 class="page-title">Barbershop dengan<br><em>suasana lebih tenang.</em></h1>
            <p class="max-w-lg font-display text-lg leading-relaxed text-muted">HOMCUTS hadir agar setiap pelanggan mendapat potongan yang sesuai: konsultasi yang jujur, pengerjaan yang teliti, dan hasil yang mudah dirawat setelah pulang.</p>
        </div>
        <div class="relative min-h-[500px] overflow-hidden border-t border-ink bg-sage lg:border-l lg:border-t-0">
            <img src="{{ asset('homcuts-storefront.png') }}" alt="Tampak depan barbershop HOMCUTS di Bulurokeng" class="h-full w-full object-cover object-center">
            <div class="absolute bottom-6 left-6 border border-paper/60 bg-ink/80 px-5 py-4 text-paper backdrop-blur"><span class="text-[8px] font-black tracking-[.16em]">BULUROKENG · MAKASSAR</span></div>
        </div>
    </section>

    <section class="grid gap-14 px-6 py-24 md:px-[6vw] lg:grid-cols-[.7fr_1.3fr] lg:py-28">
        <div><p class="section-kicker">KENAL LEBIH DEKAT</p><h2 class="section-title">Dekat tempatnya.<br><em>Serius hasilnya.</em></h2></div>
        <div class="grid gap-8 font-display text-lg leading-relaxed text-muted md:grid-cols-2">
            <p>Berada di Jl. Ir. Sutami, Bulurokeng, HOMCUTS menjadi tempat untuk potong rambut, memilih gaya, dan mendapatkan saran perawatan yang praktis.</p>
            <p>Rafli dan Appink hadir sebagai bagian dari tim HOMCUTS dengan prinsip yang sama: dengarkan sebelum memotong, kerjakan setiap detail, dan berikan hasil yang cocok untuk keseharian pelanggan.</p>
        </div>
    </section>

    <section class="grid border-y border-ink bg-cream md:grid-cols-3">
        @foreach ([['Dengarkan dahulu','Rutinitas, pola rambut, dan referensi Anda menjadi dasar rencana potongan.'],['Potong dengan tujuan','Setiap garis dan lapisan dirancang agar bentuk rambut tetap baik saat tumbuh.'],['Ajarkan penataannya','Anda pulang dengan memahami cara menata ulang gaya tersebut sendiri.']] as $value)
            <article class="border-b border-ink p-10 last:border-b-0 md:border-b-0 md:border-r md:last:border-r-0 md:p-[5vw]">
                <span class="font-display text-sm italic text-orange">0{{ $loop->iteration }}</span>
                <h2 class="my-5 font-display text-3xl">{{ $value[0] }}</h2>
                <p class="text-[11px] leading-relaxed text-muted">{{ $value[1] }}</p>
            </article>
        @endforeach
    </section>

    <section class="px-6 py-24 md:px-[6vw] md:py-28">
        <div class="grid items-start gap-12 lg:grid-cols-[.9fr_1.1fr]">
            <div><p class="section-kicker">PENGALAMAN HOMCUTS</p><h2 class="section-title">Datang nyaman.<br><em>Pulang lebih rapi.</em></h2><a class="btn-primary mt-4" href="{{ route('booking') }}">Temui kami di kursi barber <span>↗</span></a></div>
            <div class="border-t border-ink">
                @foreach ([['01','Pilih layanan dan barber yang paling sesuai dengan kebutuhan Anda.'],['02','Booking waktu kunjungan atau datang langsung untuk dilayani melalui kasir.'],['03','Diskusikan referensi dan karakter rambut sebelum proses dimulai.'],['04','Pulang dengan potongan rapi serta arahan penataan yang mudah diikuti.']] as $event)
                    <div class="grid grid-cols-[72px_1fr] gap-5 border-b border-ink/20 py-6"><b class="font-display text-xl text-orange">{{ $event[0] }}</b><p class="font-display text-base text-muted">{{ $event[1] }}</p></div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
