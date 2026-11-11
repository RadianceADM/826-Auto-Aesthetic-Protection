<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

/**
 * Branch staff work with their own branches' bookings; owners with all.
 */
class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->canManageBranch($booking->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->isOwner() || $user->branchIds() !== [];
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->canManageBranch($booking->branch_id);
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->isOwner();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isOwner();
    }
}
