<?php

namespace App\Support;

use App\Mail\Invitation;
use App\Models\MailSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Team accounts: created by an owner, who emails them a link to choose
 * their own password (the account starts with a random one nobody knows).
 */
class Invitations
{
    /**
     * If the email can't be sent, no account is made.
     */
    public function invite(string $name, string $email, ?User $invitedBy = null): User
    {
        $this->ensureEmailIsSetUp();

        return DB::transaction(function () use ($name, $email, $invitedBy) {
            $user = User::create([
                'name' => $name,
                'email' => strtolower(trim($email)),
                // Never used: they choose their own from the link
                'password' => Str::password(40),
            ]);

            $this->send($user, $invitedBy);

            return $user;
        });
    }

    /**
     * Emails a fresh set-password link (older links stop working).
     */
    public function send(User $user, ?User $invitedBy = null): void
    {
        $this->ensureEmailIsSetUp();

        $panel = Filament::getPanel('admin');
        $broker = $panel->getAuthPasswordBroker() ?? config('auth.defaults.passwords');
        $hours = (int) ceil(config("auth.passwords.{$broker}.expire", 60) / 60);

        $token = Password::broker($broker)->createToken($user);

        Mail::to($user->email)->send(new Invitation($user, $panel->getResetPasswordUrl($token, $user), $hours, $invitedBy?->name));

        $user->forceFill(['invited_at' => now()])->save();
    }

    private function ensureEmailIsSetUp(): void
    {
        if (! MailSetting::instance()->isConfigured()) {
            throw new RuntimeException('Email is not set up yet, so the invite can’t be sent. Add the mailbox password in Email settings first.');
        }
    }
}
