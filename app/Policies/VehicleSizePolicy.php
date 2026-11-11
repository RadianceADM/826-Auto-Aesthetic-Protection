<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleSize;

/**
 * The shop's setup is the owner's: branch staff can't change it.
 */
class VehicleSizePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, VehicleSize $record): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, VehicleSize $record): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, VehicleSize $record): bool
    {
        return $user->isOwner();
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
