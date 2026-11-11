<?php

namespace App\Models;

use App\Models\Concerns\HasImageRenditions;
use App\Support\ImagePipeline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * 826's tagline, intro, hero picture and main contact links: a single row
 * edited in /admin → Site settings, cached until the next save. Branches
 * keep their own address, hours and phone. Anything left blank is hidden on
 * the site, so nothing is ever made up.
 */
class SiteSetting extends Model
{
    use HasImageRenditions;

    private const CACHE_KEY = 'app826.site-settings';

    protected $fillable = [
        'tagline',
        'intro',
        'hero_image',
        'og_image',
        'phone',
        'contact_email',
        'facebook_url',
        'instagram_url',
        'messenger_url',
        'google_client_id',
        'google_client_secret',
    ];

    /**
     * The Google client secret never leaves the server: not in forms, not on the site.
     *
     * @var list<string>
     */
    protected $hidden = ['google_client_secret'];

    protected function casts(): array
    {
        return [
            'google_client_secret' => 'encrypted',
        ];
    }

    public function imageAttributes(): array
    {
        return ['hero_image', 'og_image'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function instance(): self
    {
        return static::query()->oldest('id')->first() ?? static::create()->refresh();
    }

    /**
     * The settings for this request, from the cache (plain attributes,
     * rebuilt as a persisted row).
     */
    public static function current(): self
    {
        $attributes = Cache::rememberForever(self::CACHE_KEY, fn () => static::instance()->getAttributes());

        return (new static)->newFromBuilder($attributes);
    }

    /**
     * "0948-804-2284" → "+639488042284", for tel: links.
     */
    public function phoneHref(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->phone);

        return match (true) {
            $digits === '' => null,
            str_starts_with($digits, '63') => "+{$digits}",
            str_starts_with($digits, '0') => '+63'.substr($digits, 1),
            default => $digits,
        };
    }

    /**
     * Shape used by the React site (window.APP826.settings).
     *
     * @return array<string, mixed>
     */
    public function toSite(): array
    {
        $pipeline = app(ImagePipeline::class);

        return [
            'tagline' => $this->tagline,
            'intro' => $this->intro,
            'hero' => $this->hero_image ? [
                ...$pipeline->urls($this->hero_image),
                'full' => $pipeline->url($this->hero_image, 'lg'),
            ] : null,
            'phone' => $this->phone,
            'phoneHref' => $this->phoneHref(),
            'email' => $this->contact_email,
            'facebook' => $this->facebook_url,
            'instagram' => $this->instagram_url,
            'messenger' => $this->messenger_url,
        ];
    }

    /**
     * The link-preview picture uploaded in the admin, if any.
     */
    public function shareImageUrl(): ?string
    {
        return $this->og_image ? app(ImagePipeline::class)->url($this->og_image, 'og') : null;
    }
}
