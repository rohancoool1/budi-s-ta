<?php

use App\Services\PaymentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payments:expire', function (): void {
    $count = app(PaymentService::class)->expireDuePayments(500);
    $this->info("{$count} pembayaran kedaluwarsa diproses.");
})->purpose('Membatalkan pembayaran yang kedaluwarsa dan melepaskan slot atau stok');

Schedule::command('payments:expire')->everyMinute()->withoutOverlapping();
