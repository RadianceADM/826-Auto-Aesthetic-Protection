<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private files for 826's team: customers' payment screenshots, only for
 * the staff of that booking's branch (and owners).
 */
class AdminFileController extends Controller
{
    public function proof(Booking $booking): StreamedResponse
    {
        abort_unless(Auth::guard('web')->check(), 403);
        Gate::forUser(Auth::guard('web')->user())->authorize('view', $booking);

        return MyBookingsController::streamProof($booking);
    }
}
