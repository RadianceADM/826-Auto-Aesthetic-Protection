<?php

namespace Tests\Feature;

use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Support\PanelDefaults;
use App\Models\Booking;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->owner()->create());
    }

    public function test_dropdowns_and_date_pickers_are_never_the_browsers_own(): void
    {
        Livewire::test(CreateBooking::class)
            ->assertFormFieldExists('branch_id', fn (Select $field) => ! $field->isNative())
            ->assertFormFieldExists('starts_at', fn (DateTimePicker $field) => ! $field->isNative());

        $table = Livewire::test(ListBookings::class)->instance()->getTable();

        $this->assertFalse($table->getFilter('kind')->isNative());
        $this->assertFalse($table->getFilter('status')->isNative());
        $this->assertFalse(Select::make('anything')->isNative());

        // One page size, so Filament shows no native "Per page" select
        $this->assertSame([25], $table->getPaginationPageOptions());
    }

    public function test_filaments_own_buttons_get_icons(): void
    {
        $booking = Booking::factory()->create();

        Livewire::test(ListBookings::class)
            ->assertActionHasIcon('create', Heroicon::OutlinedPlus);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHasIcon('view', Heroicon::OutlinedEye)
            ->assertActionHasIcon('delete', Heroicon::OutlinedTrash);

        foreach (PanelDefaults::ICONS as $name => $icon) {
            $this->assertSame($icon, Action::make($name)->getIcon(), $name);
        }

        // An icon set in code wins
        $this->assertSame(Heroicon::OutlinedNoSymbol, Action::make('create')->icon(Heroicon::OutlinedNoSymbol)->getIcon());
    }

    public function test_a_modal_submit_button_takes_the_icon_of_the_button_that_opened_it(): void
    {
        $booking = Booking::factory()->status('payment_review')->create(['amount_due' => 500]);

        $page = Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->mountAction('markPaid');

        $this->assertSame(Heroicon::OutlinedBanknotes, $page->instance()->getMountedAction()->getModalSubmitAction()->getIcon());
        $this->assertSame(Heroicon::OutlinedXMark, $page->instance()->getMountedAction()->getModalCancelAction()->getIcon());
        $this->assertSame(Heroicon::OutlinedCheck, Action::make('anything')->getModalSubmitAction()->getIcon());
    }
}
