<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Support\ImagePipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ImagePipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function png(string $path, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 79, 163));
        ob_start();
        imagepng($image);
        Storage::disk('public')->put($path, (string) ob_get_clean());

        return $path;
    }

    /**
     * @return array{0: int, 1: int, mime: string}
     */
    private function info(string $path): array
    {
        return getimagesizefromstring(Storage::disk('public')->get($path));
    }

    public function test_masters_and_renditions_have_the_right_sizes_and_formats(): void
    {
        $pipeline = app(ImagePipeline::class);
        $master = $pipeline->normalize($this->png('projects/shot.png', 2880, 1800));
        $pipeline->buildRenditions($master);

        $this->assertSame('projects/shot.webp', $master);
        $this->assertSame([2400, 1500, 'image/webp'], [$this->info($master)[0], $this->info($master)[1], $this->info($master)['mime']]);

        foreach (['sm' => 480, 'md' => 960, 'lg' => 1600, 'thumb' => 200] as $size => $width) {
            $this->assertSame($width, $this->info("projects/shot-{$size}.webp")[0], "{$size} width");
        }

        $this->assertSame([1200, 'image/jpeg'], [$this->info('projects/shot-og.jpg')[0], $this->info('projects/shot-og.jpg')['mime']]);
    }

    public function test_small_images_are_never_upscaled(): void
    {
        $pipeline = app(ImagePipeline::class);
        $master = $pipeline->normalize($this->png('projects/phone.png', 390, 844));
        $pipeline->buildRenditions($master);

        $this->assertSame(390, $this->info($master)[0]);
        $this->assertSame(390, $this->info('projects/phone-lg.webp')[0]);
        $this->assertSame(200, $this->info('projects/phone-thumb.webp')[0]);
    }

    public function test_huge_images_are_refused_and_the_upload_is_kept(): void
    {
        // A tiny PNG that claims to be 10000×10000 (100 MP)
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        Storage::disk('public')->put('projects/bomb.png', pack('C*', 0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A)
            .$chunk('IHDR', pack('NNCCCCC', 10000, 10000, 8, 2, 0, 0, 0))
            .$chunk('IEND', ''));

        try {
            app(ImagePipeline::class)->normalize('projects/bomb.png');
            $this->fail('A 100 MP image should be refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('too large', $e->getMessage());
        }

        $photo = Photo::factory()->create(['image' => 'projects/bomb.png']);

        $this->assertSame('projects/bomb.png', $photo->image);
    }
}
