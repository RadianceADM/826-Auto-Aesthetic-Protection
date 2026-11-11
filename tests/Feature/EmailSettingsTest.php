<?php

namespace Tests\Feature;

use App\Models\MailSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mailbox_starts_without_an_address(): void
    {
        $settings = MailSetting::instance();

        $this->assertNull($settings->address);
        $this->assertFalse($settings->isConfigured());
    }

    public function test_the_mailbox_password_is_stored_encrypted_and_never_shown(): void
    {
        MailSetting::instance()->update(['address' => 'hello@826auto.test', 'password' => 'mailbox-secret']);

        $this->assertNotSame('mailbox-secret', DB::table('mail_settings')->value('password'));
        $this->assertSame('mailbox-secret', MailSetting::instance()->password);
        $this->assertArrayNotHasKey('password', MailSetting::instance()->toArray());
    }

    public function test_mail_goes_out_through_the_mailbox_once_it_has_a_password(): void
    {
        MailSetting::applyToConfig();
        $this->assertNotSame('smtp', config('mail.default'));

        MailSetting::instance()->update(['address' => 'hello@826auto.test', 'password' => 'mailbox-secret']);
        MailSetting::applyToConfig();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('smtp.hostinger.com', config('mail.mailers.smtp.host'));
        $this->assertSame('hello@826auto.test', config('mail.mailers.smtp.username'));
        $this->assertSame('hello@826auto.test', config('mail.from.address'));
    }
}
