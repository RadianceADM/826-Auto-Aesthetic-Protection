<?php

namespace App\Policies;

use App\Models\ServiceCategory;
use App\Models\User;

/**
 * The shop's setup is the owner's: branch staff can't change it.
 */
class ServiceCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, ServiceCategory $record): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, ServiceCategory $record): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, ServiceCategory $record): bool
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
