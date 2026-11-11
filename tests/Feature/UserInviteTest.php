<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Mail\Invitation;
use App\Models\Branch;
use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class UserInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owners_manage_users_and_email_settings(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/email-settings')->assertForbidden();
        $this->get('/admin/bookings')->assertOk();

        $this->actingAs(User::factory()->owner()->create());

        $this->get('/admin/users')->assertOk();
        $this->get('/admin/email-settings')->assertOk();
    }

    public function test_an_owner_invites_branch_staff_by_email(): void
    {
        Mail::fake();
        $cainta = Branch::factory()->create();
        Branch::factory()->create();
        MailSetting::instance()->update(['address' => 'hello@826auto.test', 'password' => 'mailbox-secret']);
        $owner = User::factory()->owner()->create(['name' => 'Dran']);

        $this->actingAs($owner);

        Livewire::test(ListUsers::class)
            ->callAction('invite', data: ['name' => 'Team Mate', 'email' => 'Mate@Example.com', 'branches' => [$cainta->id]])
            ->assertHasNoActionErrors();

        $member = User::where('email', 'mate@example.com')->sole();
        $this->assertFalse($member->isOwner());
        $this->assertTrue($member->isPendingInvite());
        $this->assertSame([$cainta->id], $member->branchIds());

        Mail::assertSent(Invitation::class, fn (Invitation $mail) => $mail->hasTo('mate@example.com')
            && $mail->invitedBy === 'Dran'
            && str_contains($mail->setPasswordUrl, '/admin/password-reset/reset')
            && $mail->expiresInHours === 24);
    }

    public function test_no_account_is_made_when_the_invite_cannot_be_emailed(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->owner()->create());

        Livewire::test(ListUsers::class)
            ->callAction('invite', data: ['name' => 'Team Mate', 'email' => 'mate@example.com', 'branches' => [Branch::factory()->create()->id]])
            ->assertNotified('Invite not sent');

        $this->assertFalse(User::where('email', 'mate@example.com')->exists());
        Mail::assertNothingSent();
    }

    public function test_choosing_a_password_from_the_invite_activates_the_account(): void
    {
        $member = User::factory()->unverified()->create(['invited_at' => now()]);
        $this->assertTrue($member->isPendingInvite());

        event(new PasswordReset($member));

        $this->assertFalse($member->fresh()->isPendingInvite());
    }

    public function test_the_last_owner_and_yourself_cannot_be_removed(): void
    {
        $owner = User::factory()->owner()->create();
        $member = User::factory()->create();

        $this->actingAs($owner);

        $this->assertFalse(UserResource::canDelete($owner));
        $this->assertTrue(UserResource::canDelete($member));
    }
}
