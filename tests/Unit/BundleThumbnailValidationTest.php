<?php

namespace Tests\Unit;

use App\Http\Requests\Vendor\Promotion\BundleStoreRequest;
use App\Rules\ImageFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * The bundle thumbnail is validated by the shared ImageFile rule and by nothing else: every
 * platform image format is accepted and no aspect ratio is imposed. BundleStoreRequest is shared
 * by the vendor and admin bundle controllers, so this holds for both panels.
 */
class BundleThumbnailValidationTest extends TestCase
{
    private function imageRules(): array
    {
        return (new BundleStoreRequest())->rules()['image'];
    }

    private function imageFile(string $format, int $width, int $height): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bundle_thumb').'.'.$format;
        $canvas = imagecreatetruecolor($width, $height);

        match ($format) {
            'gif' => imagegif($canvas, $path),
            'jpg' => imagejpeg($canvas, $path),
            'webp' => imagewebp($canvas, $path),
            default => imagepng($canvas, $path),
        };

        return new UploadedFile($path, basename($path), 'image/'.$format, null, true);
    }

    private function fails(UploadedFile $file): bool
    {
        return Validator::make(['image' => $file], ['image' => $this->imageRules()])->fails();
    }

    public function test_the_thumbnail_is_validated_by_the_image_file_rule_alone(): void
    {
        $rules = $this->imageRules();

        $this->assertCount(1, $rules, 'no rule may be bolted on beside ImageFile');
        $this->assertInstanceOf(ImageFile::class, $rules[0]);
    }

    public function test_no_aspect_ratio_is_imposed(): void
    {
        $this->assertFalse($this->fails($this->imageFile('png', 100, 100)));
        $this->assertFalse($this->fails($this->imageFile('png', 200, 100)),
            'a wide thumbnail is accepted: the ratio requirement was removed');
        $this->assertFalse($this->fails($this->imageFile('png', 100, 400)),
            'and a tall one');
    }

    public function test_every_platform_format_is_accepted(): void
    {
        foreach (explode(',', IMAGE_FORMAT_FOR_VALIDATION) as $format) {
            $format = trim($format);

            $this->assertFalse($this->fails($this->imageFile($format, 120, 120)),
                $format.' is in IMAGE_FORMAT_FOR_VALIDATION, so it must pass');
        }
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bundle_thumb').'.txt';
        file_put_contents($path, 'not an image');

        $this->assertTrue($this->fails(new UploadedFile($path, basename($path), 'text/plain', null, true)));
    }

    public function test_the_thumbnail_is_required_on_create(): void
    {
        $this->assertTrue(
            Validator::make(['image' => null], ['image' => $this->imageRules()])->fails(),
            'a new bundle carries no image to fall back on',
        );
    }

    public function test_an_edit_may_leave_the_existing_thumbnail_alone(): void
    {
        $this->assertFalse(
            Validator::make(['image' => null], ['image' => ImageFile::rules('nullable')])->fails(),
        );
    }
}
