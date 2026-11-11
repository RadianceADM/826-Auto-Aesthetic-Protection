<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_owner'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invited_at' => 'datetime',
            'password' => 'hashed',
            'is_owner' => 'boolean',
        ];
    }

    /**
     * @var list<int>|null
     */
    private ?array $branchIdCache = null;

    /**
     * Only 826's team has accounts (there is no public sign-up): owners
     * invite team members from /admin → Users.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Owners see every branch and manage the shop (branches, services,
     * prices), the settings and the users. Everyone else is branch staff.
     */
    public function isOwner(): bool
    {
        return (bool) $this->is_owner;
    }

    /**
     * The branches a staff member works at.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * @return list<int>
     */
    public function branchIds(): array
    {
        return $this->branchIdCache ??= $this->branches()->pluck('branches.id')->map(fn ($id) => (int) $id)->all();
    }

    public function canManageBranch(int|Branch|null $branch): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        $id = $branch instanceof Branch ? $branch->getKey() : $branch;

        return $id !== null && in_array((int) $id, $this->branchIds(), true);
    }

    public function forgetBranchIds(): void
    {
        $this->branchIdCache = null;
    }

    /**
     * Invited, but hasn't chosen a password from the link yet.
     */
    public function isPendingInvite(): bool
    {
        return $this->invited_at !== null && $this->email_verified_at === null;
    }
}
