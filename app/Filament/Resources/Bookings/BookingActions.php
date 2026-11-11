<?php

namespace App\Filament\Resources\Bookings;

use App\Models\Booking;
use App\Models\PaymentSetting;
use App\Support\Notifier;
use App\Support\PaymentProof;
use App\Support\Reservations;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;

/**
 * What the team does with a booking, from the booking page and the
 * timeline: check the fee, check the car in and out, move or cancel it,
 * schedule a home visit, and reach the customer.
 */
final class BookingActions
{
    /**
     * The screenshot matches: the booking is confirmed.
     */
    public static function markPaid(): Action
    {
        return Action::make('markPaid')
            ->label(fn (Booking $record) => $record->status === 'payment_review' ? 'Payment checked: confirm' : 'Mark fee paid')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Booking $record) => in_array($record->stage(), ['awaiting_payment', 'payment_review'], true))
            ->modalHeading(fn (Booking $record) => "Confirm {$record->name}’s booking?")
            ->modalDescription(fn (Booking $record) => $record->status === 'payment_review'
                ? 'Check the screenshot against your GCash or Maya first. The customer gets an email that they’re booked.'
                : 'For a fee paid another way (at the counter, by bank). The customer gets an email that they’re booked.')
            ->fillForm(fn (Booking $record) => ['payment_note' => $record->payment_note])
            ->schema([
                TextInput::make('payment_note')
                    ->label('Payment note')
                    ->placeholder('GCash ref no.')
                    ->maxLength(255),
            ])
            ->modalSubmitActionLabel('Confirm')
            ->action(function (Booking $record, array $data) {
                $record->update([
                    'status' => 'confirmed',
                    'paid_at' => now(),
                    'payment_note' => $data['payment_note'] ?? null,
                    'hold_expires_at' => null,
                ]);
                Notifier::customer($record, 'paid');

                Notification::make()->success()->title('Booking confirmed')->send();
            });
    }

    /**
     * The screenshot doesn't match: the customer is asked for another and
     * gets a fresh hold.
     */
    public static function rejectProof(): Action
    {
        return Action::make('rejectProof')
            ->label('Ask for another screenshot')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (Booking $record) => $record->status === 'payment_review')
            ->modalDescription(fn () => 'The customer sees your reason and has '.PaymentSetting::instance()->holdMinutes().' minutes to upload again before the slot opens up. Their screenshot is deleted.')
            ->schema([
                TextInput::make('reason')
                    ->label('Why (the customer sees this)')
                    ->placeholder('The amount doesn’t match, the screenshot is cut off…')
                    ->required()
                    ->maxLength(255),
            ])
            ->modalSubmitActionLabel('Ask again')
            ->action(function (Booking $record, array $data) {
                PaymentProof::delete($record->payment_proof);
                $record->update([
                    'status' => 'awaiting_payment',
                    'payment_proof' => null,
                    'payment_submitted_at' => null,
                    'payment_rejected_reason' => $data['reason'],
                    'hold_expires_at' => $record->isOnsite() ? now()->addMinutes(PaymentSetting::instance()->holdMinutes()) : null,
                ]);
                Notifier::customer($record, 'rejected');

                Notification::make()->success()->title('Asked for another screenshot')->send();
            });
    }

    public static function checkIn(): Action
    {
        return Action::make('checkIn')
            ->label('Check in')
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->color('info')
            ->visible(fn (Booking $record) => $record->status === 'confirmed')
            ->requiresConfirmation()
            ->modalDescription(fn (Booking $record) => $record->isHome() ? 'The team is at the customer’s place and has started.' : 'The car is at the branch and the job has started.')
            ->action(fn (Booking $record) => $record->update(['status' => 'checked_in', 'read_at' => $record->read_at ?? now()]));
    }

    public static function markReady(): Action
    {
        return Action::make('markReady')
            ->label('Ready for pickup')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Booking $record) => $record->status === 'checked_in' && $record->isOnsite())
            ->requiresConfirmation()
            ->modalDescription('The customer gets an email that their car is ready.')
            ->action(function (Booking $record) {
                $record->update(['status' => 'ready']);
                Notifier::customer($record, 'ready');
            });
    }

    public static function complete(): Action
    {
        return Action::make('complete')
            ->label(fn (Booking $record) => $record->isHome() ? 'Done' : 'Picked up')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Booking $record) => in_array($record->status, ['ready', 'checked_in'], true) && ($record->status === 'ready' || $record->isHome()))
            ->requiresConfirmation()
            ->modalDescription('The bay is free again from now.')
            ->action(fn (Booking $record) => $record->update(['status' => 'completed']));
    }

    public static function noShow(): Action
    {
        return Action::make('noShow')
            ->label('No-show')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('gray')
            ->visible(fn (Booking $record) => $record->status === 'confirmed' && $record->starts_at?->isPast())
            ->requiresConfirmation()
            ->modalDescription('The customer didn’t come. The bay opens up for others.')
            ->action(fn (Booking $record) => $record->update(['status' => 'no_show']));
    }

    public static function cancel(): Action
    {
        return Action::make('cancelBooking')
            ->label(fn (Booking $record) => $record->status === 'requested' ? 'Decline' : 'Cancel booking')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (Booking $record) => $record->isOpen())
            ->modalDescription(fn (Booking $record) => $record->paid_at
                ? 'The bay opens up again. Sort out the reservation fee with the customer yourself.'
                : 'The bay opens up again. The customer gets an email.')
            ->schema([
                TextInput::make('reason')
                    ->label('Reason (the customer sees this)')
                    ->maxLength(250),
            ])
            ->modalSubmitActionLabel(fn (Booking $record) => $record->status === 'requested' ? 'Decline' : 'Cancel booking')
            ->action(function (Booking $record, array $data) {
                if ($record->status === 'requested') {
                    $record->update(['status' => 'declined', 'cancelled_by' => 'staff', 'cancel_reason' => $data['reason'] ?? null]);
                    Notifier::customer($record, 'declined');

                    return;
                }

                Reservations::cancel($record, 'staff', $data['reason'] ?? null);
                Notifier::customer($record, 'cancelled');
            });
    }

    /**
     * Moves an onsite booking (or sets a home visit's time) to another
     * drop-off, keeping its services and payment.
     */
    public static function reschedule(): Action
    {
        return Action::make('reschedule')
            ->label('Move')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->visible(fn (Booking $record) => $record->isOnsite() && in_array($record->status, ['awaiting_payment', 'payment_review', 'confirmed'], true) && $record->stage() !== 'expired')
            ->fillForm(fn (Booking $record) => ['starts_at' => $record->starts_at?->format('Y-m-d H:i:s')])
            ->schema([
                DateTimePicker::make('starts_at')
                    ->label('New drop-off')
                    ->seconds(false)
                    ->minutesStep(15)
                    ->displayFormat('D, M j, Y g:i A')
                    ->required()
                    ->helperText(fn (Booking $record) => 'Takes '.$record->length()->label().'. Ready-by moves with it.'),
                Toggle::make('force')
                    ->label('Book even if it’s full or outside hours'),
            ])
            ->modalSubmitActionLabel('Move')
            ->action(function (Booking $record, array $data, Action $action) {
                try {
                    Reservations::reschedule($record, CarbonImmutable::parse($data['starts_at'])->startOfMinute(), byCustomer: false, force: (bool) ($data['force'] ?? false));
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Not moved')->body(collect($e->errors())->flatten()->first())->send();
                    $action->halt();
                } catch (LockTimeoutException) {
                    Notification::make()->danger()->title('Busy, try again')->send();
                    $action->halt();
                }

                Notifier::customer($record->refresh(), 'rescheduled');
                Notification::make()->success()->title('Moved')->body($record->whenLabel())->send();
            });
    }

    /**
     * For a car that isn't ready on time: a later ready-by.
     */
    public static function extend(): Action
    {
        return Action::make('extend')
            ->label('Change ready-by')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->visible(fn (Booking $record) => $record->isOnsite() && in_array($record->status, ['confirmed', 'checked_in', 'ready'], true))
            ->fillForm(fn (Booking $record) => ['ends_at' => $record->ends_at?->format('Y-m-d H:i:s')])
            ->schema([
                DateTimePicker::make('ends_at')
                    ->label('Ready by')
                    ->seconds(false)
                    ->minutesStep(15)
                    ->displayFormat('D, M j, Y g:i A')
                    ->required()
                    ->after(fn (Booking $record) => $record->starts_at?->format('Y-m-d H:i:s')),
            ])
            ->action(fn (Booking $record, array $data) => $record->update(['ends_at' => CarbonImmutable::parse($data['ends_at'])->startOfMinute()]));
    }

    /**
     * A home-service request gets its date and time (and the fee, when on).
     */
    public static function scheduleHome(): Action
    {
        return Action::make('scheduleHome')
            ->label('Schedule')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('primary')
            ->visible(fn (Booking $record) => $record->isHome() && in_array($record->status, ['requested', 'awaiting_payment', 'confirmed'], true))
            ->fillForm(fn (Booking $record) => [
                'starts_at' => $record->starts_at?->format('Y-m-d H:i:s') ?? $record->preferred_date?->setTime(9, 0)->format('Y-m-d H:i:s'),
                'ends_at' => $record->ends_at?->format('Y-m-d H:i:s'),
                'ask_fee' => PaymentSetting::instance()->feeEnabled() && $record->status === 'requested',
            ])
            ->modalDescription('Agree on the time with the customer first. They get an email with it.')
            ->schema([
                DateTimePicker::make('starts_at')->label('Team arrives')->seconds(false)->minutesStep(15)->displayFormat('D, M j, Y g:i A')->required(),
                DateTimePicker::make('ends_at')->label('Done by')->seconds(false)->minutesStep(15)->displayFormat('D, M j, Y g:i A')->required()->after('starts_at'),
                Toggle::make('ask_fee')
                    ->label(fn () => 'Ask for the reservation fee ('.(PaymentSetting::instance()->feeLabel() ?? 'off in Payments').')')
                    ->visible(fn (Booking $record) => PaymentSetting::instance()->feeEnabled() && $record->status === 'requested'),
            ])
            ->modalSubmitActionLabel('Schedule')
            ->action(function (Booking $record, array $data) {
                $askFee = ($data['ask_fee'] ?? false) && $record->status === 'requested' && $record->total_price > 0;
                $payments = PaymentSetting::instance();

                $record->update([
                    'starts_at' => CarbonImmutable::parse($data['starts_at'])->startOfMinute(),
                    'ends_at' => CarbonImmutable::parse($data['ends_at'])->startOfMinute(),
                    'status' => $record->status === 'requested' ? ($askFee ? 'awaiting_payment' : 'confirmed') : $record->status,
                    'amount_due' => $askFee ? $payments->amountFor((int) $record->total_price) : $record->amount_due,
                    'hold_expires_at' => null,
                    'read_at' => $record->read_at ?? now(),
                ]);
                Notifier::customer($record, 'home_scheduled');

                Notification::make()->success()->title('Scheduled')->body($record->whenLabel())->send();
            });
    }

    public static function call(): Action
    {
        return Action::make('call')
            ->icon(Heroicon::OutlinedPhone)
            ->color('gray')
            ->url(fn (Booking $record) => BookingResource::phoneUrl($record->phone))
            ->visible(fn (Booking $record) => BookingResource::phoneUrl($record->phone) !== null);
    }

    public static function text(): Action
    {
        return Action::make('text')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->url(function (Booking $record) {
                $number = BookingResource::phoneUrl($record->phone);

                return $number === null ? null : 'sms:'.substr($number, 4).'?body='.rawurlencode("Hi {$record->name}, this is 826 {$record->branch?->name} about your booking {$record->reference}.");
            })
            ->visible(fn (Booking $record) => BookingResource::phoneUrl($record->phone) !== null);
    }

    public static function email(): Action
    {
        return Action::make('email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->url(fn (Booking $record) => $record->email ? 'mailto:'.$record->email.'?subject='.rawurlencode("Your 826 booking {$record->reference}") : null)
            ->visible(fn (Booking $record) => filled($record->email));
    }

    /**
     * The private booking link, to text the customer (it opens without signing in).
     */
    public static function link(): Action
    {
        return Action::make('customerLink')
            ->label('Copy booking link')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->modalHeading('Booking link')
            ->modalDescription('Text or message this to the customer. It opens their booking page, where they see the status and pay, without signing in. Keep it private.')
            ->schema([
                TextInput::make('url')
                    ->label('Link')
                    ->default(fn (Booking $record) => $record->customerUrl())
                    ->readOnly()
                    ->copyable(),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Done');
    }

    /**
     * Every status action, in the order they're offered.
     *
     * @return list<Action>
     */
    public static function workflow(): array
    {
        return [
            self::markPaid(),
            self::rejectProof(),
            self::scheduleHome(),
            self::checkIn(),
            self::markReady(),
            self::complete(),
        ];
    }
}
