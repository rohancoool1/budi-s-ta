@extends('layouts.app')

@section('title', 'Contact Us')
@section('description', 'Contact Brass & Blade in Renon, Denpasar for bookings, products, and general questions.')

@section('content')
    <section class="border-b border-ink bg-cream px-6 pb-16 pt-20 md:px-[6vw] md:pb-20 md:pt-28">
        <div class="grid items-end gap-8 lg:grid-cols-[1.4fr_.6fr]">
            <div><p class="section-kicker">05 / CONTACT US</p><h1 class="page-title">Let’s talk<br><em>good hair.</em></h1></div>
            <p class="max-w-md font-display text-lg leading-relaxed text-muted">Questions about a cut, a product, or your next visit? Send us a note and we’ll point you in the right direction.</p>
        </div>
    </section>

    <section class="grid border-b border-ink lg:grid-cols-[.8fr_1.2fr]">
        <aside class="bg-sage px-6 py-16 md:px-[6vw] md:py-20 lg:border-r lg:border-ink">
            <p class="section-kicker !text-ink">BRASS &amp; BLADE — RENON</p>
            <h2 class="my-6 font-display text-4xl tracking-[-.04em]">Come by the shop.</h2>
            <div class="space-y-8 border-t border-ink pt-8 text-[11px] leading-relaxed">
                <div><b class="field-label mb-2">Address</b><p>Jl. Cok Agung Tresna No. 27<br>Renon, Denpasar, Bali</p></div>
                <div><b class="field-label mb-2">Call or WhatsApp</b><a class="link-button" href="tel:+6281234567890">+62 812 3456 7890 ↗</a></div>
                <div><b class="field-label mb-2">Email</b><a class="link-button normal-case" href="mailto:hello@brassandblade.id">hello@brassandblade.id ↗</a></div>
                <div><b class="field-label mb-2">Opening hours</b><p>Tuesday—Friday · 09:00—20:00<br>Saturday—Sunday · 09:00—18:00<br>Monday · Closed</p></div>
            </div>
            <div class="relative mt-12 grid min-h-56 place-items-center overflow-hidden border border-ink bg-paper/35">
                <div class="absolute inset-0 opacity-30" style="background-image: linear-gradient(#171714 1px, transparent 1px), linear-gradient(90deg, #171714 1px, transparent 1px); background-size: 38px 38px"></div>
                <div class="relative grid size-16 place-items-center rounded-full bg-orange font-display text-2xl text-white shadow-xl">B</div>
            </div>
        </aside>

        <div class="px-6 py-16 md:px-[6vw] md:py-20">
            <p class="section-kicker">SEND A MESSAGE</p>
            <h2 class="mb-9 mt-3 font-display text-4xl tracking-[-.04em]">How can we help?</h2>
            <form action="{{ route('contact.store') }}" method="POST" class="grid gap-5 sm:grid-cols-2">
                @csrf
                <div><label class="field-label" for="contact-name">Your name</label><input class="form-control mt-2" id="contact-name" name="name" value="{{ old('name') }}" required></div>
                <div><label class="field-label" for="contact-email">Email</label><input class="form-control mt-2" id="contact-email" name="email" type="email" value="{{ old('email') }}" required></div>
                <div><label class="field-label" for="contact-phone">WhatsApp <span class="text-muted">(optional)</span></label><input class="form-control mt-2" id="contact-phone" name="phone" value="{{ old('phone') }}"></div>
                <div><label class="field-label" for="subject">What is this about?</label><select class="form-control mt-2" id="subject" name="subject"><option value="general">General question</option><option value="booking">Booking help</option><option value="product">Product advice</option><option value="collaboration">Collaboration</option></select></div>
                <div class="sm:col-span-2"><label class="field-label" for="message">Your message</label><textarea class="form-control mt-2 min-h-40 py-3" id="message" name="message" placeholder="Tell us what you need…" required>{{ old('message') }}</textarea></div>
                @if ($errors->any())
                    <div class="border border-orange bg-orange/10 p-3 text-xs text-orange sm:col-span-2">{{ $errors->first() }}</div>
                @endif
                <button class="btn-primary sm:col-span-2" type="submit">Send message <span>↗</span></button>
            </form>
        </div>
    </section>
@endsection
