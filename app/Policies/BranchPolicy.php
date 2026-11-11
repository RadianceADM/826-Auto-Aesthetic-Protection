<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

/**
 * The shop's setup is the owner's: branch staff can't change it.
 */
class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, Branch $record): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Branch $record): bool
    {
        return $user->isOwner();
    }

    /**
     * A branch with bookings stays (set it to Draft to hide it).
     */
    public function delete(User $user, Branch $record): bool
    {
        return $user->isOwner() && ! $record->bookings()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function reorder(User $user): bool
    {
        return $user->isOwner();
    }
}
