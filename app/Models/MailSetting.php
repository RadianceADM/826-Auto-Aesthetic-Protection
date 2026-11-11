<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * The resort's mailbox: one row, edited by an owner in /admin → Email settings.
 * The password is stored encrypted with APP_KEY. The site sends through it
 * (SMTP): booking alerts and admin invites. Alerts go to notify_email, or
 * the mailbox itself when that is blank.
 */
class MailSetting extends Model
{
    protected $fillable = [
        'address',
        'password',
        'from_name',
        'notify_email',
        'smtp_host',
        'smtp_port',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'smtp_port' => 'integer',
        ];
    }

    /**
     * The row, created with Hostinger's server defaults if it is missing.
     * The address stays empty until an owner fills it in.
     */
    public static function instance(): self
    {
        return static::query()->oldest('id')->first()
            ?? static::create(['from_name' => config('app.name')]);
    }

    public function isConfigured(): bool
    {
        return filled($this->address) && filled($this->password);
    }

    /**
     * Where booking requests are emailed.
     */
    public function alertAddress(): ?string
    {
        return $this->notify_email ?: $this->address;
    }

    /**
     * Sends the app's email through the mailbox. Called when the mailer is
     * first used, so pages that send nothing don't query this table.
     */
    public static function applyToConfig(): void
    {
        try {
            $settings = static::query()->oldest('id')->first();
        } catch (QueryException) {
            // Not migrated yet
            return;
        }

        if (! $settings?->isConfigured()) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => $settings->smtp_port === 465 ? 'smtps' : 'smtp',
            'mail.mailers.smtp.host' => $settings->smtp_host,
            'mail.mailers.smtp.port' => $settings->smtp_port,
            'mail.mailers.smtp.username' => $settings->address,
            'mail.mailers.smtp.password' => $settings->password,
            'mail.from.address' => $settings->address,
            'mail.from.name' => $settings->from_name ?: config('app.name'),
        ]);
    }
}
