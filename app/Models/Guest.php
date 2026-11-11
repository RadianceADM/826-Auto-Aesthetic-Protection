<?php

namespace App\Models;

use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A customer who signed in with Google to reserve and follow their
 * bookings. Separate from App\Models\User (the admin's team), with its
 * own "guest" auth guard, so a guest can never open /admin.
 */
class Guest extends Authenticatable
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory;

    protected $fillable = [
        'google_id',
        'name',
        'email',
        'avatar_url',
        'phone',
        'last_signed_in_at',
    ];

    protected $hidden = [
        'google_id',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'last_signed_in_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Shape used by the React site (window.APP826.auth.guest).
     *
     * @return array{name: string, email: string, avatar: string|null}
     */
    public function toSite(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar_url,
        ];
    }
}
