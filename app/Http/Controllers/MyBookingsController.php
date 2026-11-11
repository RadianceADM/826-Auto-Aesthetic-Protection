<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsBookingInput;
use App\Models\Booking;
use App\Support\Notifier;
use App\Support\PaymentProof;
use App\Support\Reservations;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * "My bookings": a customer's reservations and requests, paying the
 * reservation fee (a GCash or Maya screenshot for the branch to check),
 * and cancelling or moving a booking within the rules. A booking opens for
 * its signed-in customer, or with its private key.
 */
class MyBookingsController extends Controller
{
    use ReadsBookingInput;

    /**
     * GET /api/my-bookings: the signed-in customer's bookings, soonest first.
     */
    public function index(): JsonResponse
    {
        $guest = $this->guest();
        abort_unless($guest, 401);

        $bookings = $guest->bookings()
            ->with(['items', 'branch'])
            ->orderByRaw('coalesce(starts_at, preferred_date) desc')
            ->latest('id')
            ->get();

        return response()->json(['bookings' => $bookings->map->toCustomer()->values()])->header('Cache-Control', 'no-store');
    }

    /**
     * GET /api/my-bookings/{booking}?key=…
     */
    public function show(Request $request, int $booking): JsonResponse
    {
        $record = $this->openableBooking($request, $booking);

        return response()->json(['booking' => $record->load(['items', 'branch'])->toCustomer($this->key($request))])->header('Cache-Control', 'no-store');
    }

    /**
     * POST /api/my-bookings/{booking}/payment?key=…: the screenshot of the
     * reservation fee. Checked under the branch's lock, so a hold that runs
     * out can't be paid after its bay went to someone else.
     */
    public function payment(Request $request, int $booking): JsonResponse
    {
        $record = $this->openableBooking($request, $booking);
        $key = $this->key($request);

        $data = $request->validate([
            'method' => ['required', Rule::in(array_column($record->paymentMethods(), 'key'))],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'method.required' => 'Choose how you paid.',
            'method.in' => 'Choose GCash or Maya.',
            'proof.required' => 'Add a screenshot of your payment.',
            'proof.mimes' => 'Use a JPG, PNG or WebP screenshot.',
            'proof.max' => 'That picture is too big. Use one under 8 MB.',
            'proof.uploaded' => 'That picture didn’t upload. Try again, or use a smaller one.',
        ]);

        if (config('app.demo')) {
            $preview = $record->load(['items', 'branch'])->toCustomer($key);
            $preview['stage'] = 'payment_review';
            $preview['payment'] = [...$preview['payment'], 'method' => $data['method'], 'submittedAt' => now()->toIso8601String(), 'rejectedReason' => null, 'proofUrl' => null];

            return response()->json(['booking' => $preview, 'demo' => true]);
        }

        try {
            $result = Reservations::underLock($record->branch_id, function () use ($record, $request, $data) {
                $record->refresh();

                if ($record->stage() === 'expired') {
                    return response()->json(['message' => 'Your hold ran out, so the slot opened up. Book another time.'], 409);
                }

                if (! $record->isAwaitingPayment()) {
                    return response()->json(['message' => 'This booking isn’t waiting for a payment.'], 409);
                }

                try {
                    $path = PaymentProof::store($record, $request->file('proof'));
                } catch (Throwable) {
                    throw ValidationException::withMessages(['proof' => 'We couldn’t read that picture. Try a screenshot in JPG or PNG.']);
                }

                $previous = $record->payment_proof;

                $record->update([
                    'status' => 'payment_review',
                    'payment_method' => $data['method'],
                    'payment_proof' => $path,
                    'payment_submitted_at' => now(),
                    'payment_rejected_reason' => null,
                    'read_at' => null,
                ]);

                PaymentProof::delete($previous);

                return null;
            });
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'That didn’t go through. Try again in a moment.'], 409);
        }

        if ($result !== null) {
            return $result;
        }

        Notifier::team($record, 'payment');

        return response()->json(['booking' => $record->refresh()->load(['items', 'branch'])->toCustomer($key)]);
    }

    /**
     * POST /api/my-bookings/{booking}/cancel?key=…
     */
    public function cancel(Request $request, int $booking): JsonResponse
    {
        $record = $this->openableBooking($request, $booking);

        if (! $record->canBeCancelledByCustomer()) {
            return response()->json(['message' => 'This booking can’t be cancelled online anymore. Call or message the branch.'], 409);
        }

        if (! config('app.demo')) {
            Reservations::cancel($record, 'guest', $request->string('reason')->limit(250)->toString() ?: null);
            Notifier::team($record, 'cancelled');
            Notifier::customer($record, 'cancelled');
        }

        return response()->json(['booking' => $record->refresh()->load(['items', 'branch'])->toCustomer($this->key($request))]);
    }

    /**
     * POST /api/my-bookings/{booking}/reschedule?key=… with date and time.
     */
    public function reschedule(Request $request, int $booking): JsonResponse
    {
        $record = $this->openableBooking($request, $booking);
        $start = $this->startFrom($request->input('date'), $request->input('time'));

        if (config('app.demo')) {
            return response()->json(['booking' => $record->load(['items', 'branch'])->toCustomer($this->key($request)), 'demo' => true]);
        }

        try {
            Reservations::reschedule($record, $start);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Lots of people are booking right now. Try again in a moment.'], 409);
        }

        Notifier::team($record, 'rescheduled');
        Notifier::customer($record, 'rescheduled');

        return response()->json(['booking' => $record->refresh()->load(['items', 'branch'])->toCustomer($this->key($request))]);
    }

    /**
     * GET /api/my-bookings/{booking}/proof?key=…: the customer's own screenshot.
     */
    public function proof(Request $request, int $booking): StreamedResponse
    {
        return self::streamProof($this->openableBooking($request, $booking));
    }

    /**
     * The screenshot from the private disk; never cached by a shared cache.
     */
    public static function streamProof(Booking $booking): StreamedResponse
    {
        abort_unless($booking->payment_proof && Storage::disk(Booking::PROOF_DISK)->exists($booking->payment_proof), 404);

        $type = match (strtolower(pathinfo($booking->payment_proof, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/webp',
        };

        return Storage::disk(Booking::PROOF_DISK)->response($booking->payment_proof, 'payment.'.pathinfo($booking->payment_proof, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function key(Request $request): ?string
    {
        $key = $request->input('key', $request->query('key'));

        return is_string($key) ? $key : null;
    }
}
