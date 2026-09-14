@extends('admin.layouts.app')

@section('title', 'Akun saya')

@section('content')
    <div class="mb-7">
        <p class="text-[9px] font-black uppercase tracking-[.2em] text-orange">Keamanan</p>
        <h1 class="mt-2 font-display text-5xl tracking-[-.045em]">Akun admin saya</h1>
        <p class="mt-2 max-w-xl text-sm leading-6 text-muted">Perbarui identitas administrator atau ganti kata sandi sementara.</p>
    </div>

    @if ($errors->any())
        <div class="mb-6 border border-red-300 bg-red-50 px-5 py-4 text-sm text-red-800">
            <b>Periksa kembali kolom yang bermasalah.</b>
            <ul class="mt-2 list-inside list-disc text-xs leading-6">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.account.update') }}" class="max-w-3xl border border-ink/15 bg-paper p-5 sm:p-8">
        @csrf
        @method('PUT')
        <div class="grid gap-6 sm:grid-cols-2">
            <div><label class="field-label mb-2" for="name">Nama admin</label><input class="form-control bg-white" id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required></div>
            <div><label class="field-label mb-2" for="email">Alamat email</label><input class="form-control bg-white" id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required></div>
        </div>

        <div class="my-8 border-t border-ink/10"></div>
        <p class="text-[9px] font-black uppercase tracking-[.16em] text-orange">Ganti kata sandi</p>
        <p class="mt-2 text-xs leading-5 text-muted">Kosongkan bagian ini jika Anda hanya ingin mengubah nama atau email.</p>
        <div class="mt-5 grid gap-6 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="field-label mb-2" for="current_password">Kata sandi saat ini</label><input class="form-control bg-white" id="current_password" name="current_password" type="password" autocomplete="current-password"></div>
            <div><label class="field-label mb-2" for="password">Kata sandi baru</label><input class="form-control bg-white" id="password" name="password" type="password" autocomplete="new-password"></div>
            <div><label class="field-label mb-2" for="password_confirmation">Konfirmasi kata sandi baru</label><input class="form-control bg-white" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"></div>
        </div>
        <button class="btn-primary mt-8" type="submit">Simpan akun <span>→</span></button>
    </form>
@endsection
