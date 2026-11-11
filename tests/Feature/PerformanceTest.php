<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The site's speed settings (RadianceADM/blueprints PERFORMANCE_BLUEPRINT.md).
 */
class PerformanceTest extends TestCase
{
    public function test_every_preloaded_font_is_a_file_the_build_has(): void
    {
        preg_match_all('#node_modules/[^\'"]+\.woff2#', file_get_contents(resource_path('views/site.blade.php')), $fonts);

        $this->assertNotEmpty($fonts[0], 'The page shell preloads its fonts');

        foreach ($fonts[0] as $font) {
            // A font package that renames its files would otherwise drop the preload without a word
            $this->assertFileExists(base_path($font));
        }
    }

    public function test_the_build_is_cached_for_a_year_and_pictures_for_a_week(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));

        // Laravel's stock .htaccess has none of this, so an upgrade that copies it over shows up here
        $this->assertStringContainsString('RewriteRule ^build/assets/ - [E=CACHE_YEAR:1]', $htaccess);
        $this->assertStringContainsString('Header set Cache-Control "public, max-age=31536000, immutable" env=CACHE_YEAR', $htaccess);
        $this->assertStringContainsString('Header set Cache-Control "public, max-age=604800" env=CACHE_WEEK', $htaccess);
        $this->assertStringNotContainsString('<FilesMatch', $htaccess);
    }
}
