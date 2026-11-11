<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * House rules for every Filament screen:
 *
 * - Dropdowns (fields and table filters) and date pickers use Filament's
 *   own styled pickers, never the browser's native ones.
 * - Tables use a single page size, so there's no native "Per page" select.
 * - Every button has an icon. Filament's built-in buttons (Save, Create,
 *   Cancel, Delete, Apply filters, Sign in…) get one from ICONS, and a
 *   modal's submit button takes the icon of the button that opened it.
 *   An icon set on an action in code always wins.
 */
final class PanelDefaults
{
    /**
     * Icons for Filament's built-in buttons, by action name.
     *
     * @var array<string, Heroicon>
     */
    public const ICONS = [
        'create' => Heroicon::OutlinedPlus,
        'add' => Heroicon::OutlinedPlus,
        'collapseAll' => Heroicon::OutlinedChevronDoubleUp,
        'expandAll' => Heroicon::OutlinedChevronDoubleDown,
        'createAnother' => Heroicon::OutlinedPlusCircle,
        'save' => Heroicon::OutlinedCheck,
        'cancel' => Heroicon::OutlinedXMark,
        'view' => Heroicon::OutlinedEye,
        'edit' => Heroicon::OutlinedPencilSquare,
        'delete' => Heroicon::OutlinedTrash,
        'forceDelete' => Heroicon::OutlinedTrash,
        'restore' => Heroicon::OutlinedArrowUturnLeft,
        'replicate' => Heroicon::OutlinedDocumentDuplicate,
        'attach' => Heroicon::OutlinedLink,
        'associate' => Heroicon::OutlinedLink,
        'detach' => Heroicon::OutlinedLinkSlash,
        'dissociate' => Heroicon::OutlinedLinkSlash,
        'import' => Heroicon::OutlinedArrowUpTray,
        'export' => Heroicon::OutlinedArrowDownTray,
        'applyFilters' => Heroicon::OutlinedFunnel,
        'resetFilters' => Heroicon::OutlinedArrowPath,
        'applyTableColumnManager' => Heroicon::OutlinedCheck,
        'resetColumnManager' => Heroicon::OutlinedArrowPath,
        'authenticate' => Heroicon::OutlinedArrowRightEndOnRectangle,
        'login' => Heroicon::OutlinedArrowLeft,
        'request' => Heroicon::OutlinedEnvelope,
        'resetPassword' => Heroicon::OutlinedKey,
        'register' => Heroicon::OutlinedUserPlus,
        'resend' => Heroicon::OutlinedArrowPath,
    ];

    public static function apply(): void
    {
        Select::configureUsing(fn (Select $select) => $select->native(false));
        SelectFilter::configureUsing(fn (SelectFilter $filter) => $filter->native(false));
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker->native(false));

        // One page size per table: with a single option Filament shows no
        // "Per page" dropdown, which is the browser's native select
        Table::configureUsing(fn (Table $table) => $table->paginated([25]));

        Action::configureUsing(function (Action $owner): void {
            if ($icon = self::ICONS[$owner->getName()] ?? null) {
                $owner->icon($icon);
            }

            $owner->modalSubmitAction(fn (Action $action): Action => $action->icon(
                $action->getIcon()
                    ?? $owner->getIcon(default: $owner->getTable() ? $owner->getTableIcon() : $owner->getGroupedIcon())
                    ?? Heroicon::OutlinedCheck,
            ));
        });
    }
}
