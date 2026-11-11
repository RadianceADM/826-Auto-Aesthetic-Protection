<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Branch staff only ever query their own branches' records (owners see
 * all). Filament resolves list rows, view and edit pages, bulk actions and
 * global search through this query, so another branch's record is a 404.
 */
trait ScopedToStaffBranches
{
    /**
     * Whether records for every branch (branch_id null) are shown to staff too.
     */
    protected static function includesEveryBranchRecords(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->isOwner()) {
            return $query;
        }

        $ids = $user?->branchIds() ?? [];
        $column = $query->getModel()->qualifyColumn('branch_id');

        return $query->where(fn (Builder $scope) => $scope
            ->whereIn($column, $ids)
            ->when(static::includesEveryBranchRecords(), fn (Builder $everyBranch) => $everyBranch->orWhereNull($column)));
    }
}
