<?php

namespace App\Policies;

use App\Models\BranchBlock;
use App\Models\User;

/**
 * Staff close or block bays at their own branches; closures for every
 * branch are the owner's.
 */
class BranchBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BranchBlock $block): bool
    {
        return $block->branch_id === null || $user->canManageBranch($block->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->isOwner() || $user->branchIds() !== [];
    }

    public function update(User $user, BranchBlock $block): bool
    {
        return $block->branch_id === null ? $user->isOwner() : $user->canManageBranch($block->branch_id);
    }

    public function delete(User $user, BranchBlock $block): bool
    {
        return $this->update($user, $block);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isOwner();
    }
}
