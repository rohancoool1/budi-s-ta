<?php

use App\Http\Controllers\Admin\AccountController as AdminAccountController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PosController as AdminPosController;
use App\Http\Controllers\Admin\ResourceController as AdminResourceController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Support\AdminResources;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function (): void {
    Route::get('/login', [AdminAuthController::class, 'create'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'store'])->middleware(['guest', 'throttle:5,1'])->name('admin.login.store');

    Route::middleware(['auth', 'admin'])->name('admin.')->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
        Route::get('/account', [AdminAccountController::class, 'edit'])->name('account.edit');
        Route::put('/account', [AdminAccountController::class, 'update'])->name('account.update');
        Route::get('/pos', [AdminPosController::class, 'create'])->name('pos.create');
        Route::post('/pos', [AdminPosController::class, 'store'])->name('pos.store');
        Route::get('/pos/check-availability', [AdminPosController::class, 'availability'])->name('pos.availability');
        Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [AdminNotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [AdminNotificationController::class, 'read'])->name('notifications.read');
        Route::post('/orders/{order}/confirm-cash', [AdminPaymentController::class, 'confirmCash'])->name('orders.confirm-cash');
        Route::get('/bookings/check-availability', [AdminResourceController::class, 'bookingAvailability'])->name('bookings.availability');

        Route::get('/{resource}', [AdminResourceController::class, 'index'])->whereIn('resource', AdminResources::keys())->name('resources.index');
        Route::get('/{resource}/create', [AdminResourceController::class, 'create'])->whereIn('resource', AdminResources::keys())->name('resources.create');
        Route::post('/{resource}', [AdminResourceController::class, 'store'])->whereIn('resource', AdminResources::keys())->name('resources.store');
        Route::get('/{resource}/{record}/edit', [AdminResourceController::class, 'edit'])->whereIn('resource', AdminResources::keys())->whereNumber('record')->name('resources.edit');
        Route::put('/{resource}/{record}', [AdminResourceController::class, 'update'])->whereIn('resource', AdminResources::keys())->whereNumber('record')->name('resources.update');
        Route::delete('/{resource}/{record}', [AdminResourceController::class, 'destroy'])->whereIn('resource', AdminResources::keys())->whereNumber('record')->name('resources.destroy');
    });
});

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/booking', [PageController::class, 'booking'])->name('booking');
Route::get('/shop', [PageController::class, 'shop'])->name('shop');
Route::get('/gallery', [PageController::class, 'gallery'])->name('gallery');

Route::view('/about', 'about')->name('about');
Route::view('/contact', 'contact')->name('contact');

Route::post('/bookings', [BookingController::class, 'store'])->middleware('throttle:10,1')->name('bookings.store');
Route::get('/bookings/availability', [BookingController::class, 'availability'])->middleware('throttle:120,1')->name('bookings.availability');
Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:10,1')->name('orders.store');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
Route::get('/payments/{payment}/status', [PaymentController::class, 'status'])->middleware('throttle:120,1')->name('payments.status');
