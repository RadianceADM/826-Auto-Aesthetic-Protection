<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Models\SiteSetting;
use App\Support\ImagePipeline;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adds photos straight from a folder (e.g. a Google Drive download), without
 * uploading them one batch at a time. --hero makes the first one the big
 * picture on the Home page instead.
 */
#[Signature('826:import-photos
    {paths* : Image files or folders of images}
    {--category=detailing : coating, ppf, tint, detailing or shop}
    {--featured : Feature them on the Home page}
    {--hero : Use the first image as the Home page picture (Site settings)}')]
#[Description('Import photos of 826\'s work from files or folders')]
class ImportPhotos extends Command
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function handle(): int
    {
        $files = $this->collectFiles($this->argument('paths'));

        if ($files === []) {
            $this->warn('No images found.');

            return self::FAILURE;
        }

        if ($this->option('hero')) {
            return $this->importHero($files[0]);
        }

        $category = (string) $this->option('category');

        if (! array_key_exists($category, Photo::CATEGORIES)) {
            $this->error('Category must be one of: '.implode(', ', array_keys(Photo::CATEGORIES)));

            return self::FAILURE;
        }

        $sortOrder = (int) Photo::max('sort_order');
        $imported = 0;

        $this->withProgressBar($files, function (string $file) use ($category, &$sortOrder, &$imported) {
            $path = $this->store($file, 'photos');

            try {
                $photo = Photo::create([
                    'image' => $path,
                    'category' => $category,
                    'is_featured' => (bool) $this->option('featured'),
                    'sort_order' => ++$sortOrder,
                ]);
            } catch (Throwable $e) {
                Storage::disk('public')->delete($path);
                $this->newLine();
                $this->warn("Skipped {$file}: {$e->getMessage()}");

                return;
            }

            // Upload processing never fails a save; say so if this one fell back to the original
            if (! ImagePipeline::isWebp($photo->image)) {
                $this->newLine();
                $this->warn("Imported {$file} without WebP versions (see the log).");
            }

            $imported++;
        });

        $this->newLine(2);
        $this->info("Imported {$imported} of ".count($files).' photos.');

        return self::SUCCESS;
    }

    private function importHero(string $file): int
    {
        $settings = SiteSetting::instance();
        $settings->update(['hero_image' => $this->store($file, 'site')]);

        if (! ImagePipeline::isWebp($settings->hero_image)) {
            $this->warn("Set {$file} as the Home page picture, without WebP versions (see the log).");

            return self::SUCCESS;
        }

        $this->info("Set {$file} as the Home page picture.");

        return self::SUCCESS;
    }

    /**
     * Copies a file onto the public disk under a random name.
     */
    private function store(string $file, string $directory): string
    {
        $path = $directory.'/'.Str::random(40).'.'.strtolower(pathinfo($file, PATHINFO_EXTENSION));
        Storage::disk('public')->put($path, File::get($file));

        return $path;
    }

    /**
     * @param  array<string>  $paths
     * @return list<string>
     */
    private function collectFiles(array $paths): array
    {
        return collect($paths)
            ->flatMap(fn (string $path) => is_dir($path)
                ? collect(File::files($path))->map(fn ($file) => $file->getPathname())->sort()->values()
                : [$path])
            ->filter(fn (string $path) => is_file($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true))
            ->values()
            ->all();
    }
}
