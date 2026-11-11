<?php

use App\Http\Controllers\AdminFileController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\GuestAuthController;
use App\Http\Controllers\HomeRequestController;
use App\Http\Controllers\MyBookingsController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

// Site pages: one React shell, with per-page meta tags
Route::controller(SiteController::class)->group(function () {
    Route::get('/', 'home');
    Route::get('/services', 'services');
    Route::get('/services/{slug}', 'service')->where('slug', '[a-z0-9-]+');
    Route::get('/branches', 'branches');
    Route::get('/branches/{slug}', 'branch')->where('slug', '[a-z0-9-]+');
    Route::get('/gallery', 'gallery');
    Route::get('/book', 'book');
    Route::get('/home-service', 'homeService');
    Route::get('/bookings', 'myBookings');
    Route::get('/bookings/{booking}', 'myBooking')->whereNumber('booking');
});

// Sign in with Google, for customers (never the admin)
Route::controller(GuestAuthController::class)->group(function () {
    Route::get('/auth/google', 'redirect')->middleware('throttle:google')->name('auth.google');
    Route::get('/auth/google/callback', 'callback')->middleware('throttle:google')->name('auth.google.callback');
    Route::post('/auth/logout', 'logout')->name('auth.logout');
    // Local development only (404 anywhere else): a test customer without Google
    Route::get('/auth/local', 'local')->name('auth.local');
});

// Open days and drop-off times for the picked services
Route::prefix('api/availability')->controller(AvailabilityController::class)->middleware('throttle:availability')->group(function () {
    Route::get('/', 'month')->name('availability.month');
    Route::get('/day', 'day')->name('availability.day');
});

Route::post('/api/bookings', [ReservationController::class, 'store'])->middleware('throttle:bookings')->name('bookings.store');
Route::post('/api/home-requests', [HomeRequestController::class, 'store'])->middleware('throttle:bookings')->name('home-requests.store');

// My bookings: paying the reservation fee, cancelling, moving
Route::prefix('api/my-bookings')->controller(MyBookingsController::class)->group(function () {
    Route::get('/', 'index')->middleware('throttle:my-bookings')->name('my-bookings.index');
    Route::get('/{booking}', 'show')->whereNumber('booking')->middleware('throttle:my-bookings')->name('my-bookings.show');
    Route::get('/{booking}/proof', 'proof')->whereNumber('booking')->middleware('throttle:my-bookings')->name('my-bookings.proof');
    Route::post('/{booking}/payment', 'payment')->whereNumber('booking')->middleware('throttle:payments')->name('my-bookings.payment');
    Route::post('/{booking}/cancel', 'cancel')->whereNumber('booking')->middleware('throttle:bookings')->name('my-bookings.cancel');
    Route::post('/{booking}/reschedule', 'reschedule')->whereNumber('booking')->middleware('throttle:bookings')->name('my-bookings.reschedule');
});

// The team's view of a payment screenshot (signed in to /admin)
Route::get('/admin-files/bookings/{booking}/proof', [AdminFileController::class, 'proof'])
    ->whereNumber('booking')
    ->name('admin.bookings.proof');

Route::permanentRedirect('/booking', '/book');
Route::permanentRedirect('/reserve', '/book');
Route::permanentRedirect('/prices', '/services');
Route::permanentRedirect('/locations', '/branches');

// For search engines (public/robots.txt would shadow this route)
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);

Route::fallback([SiteController::class, 'notFound']);
