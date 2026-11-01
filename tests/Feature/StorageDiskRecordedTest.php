<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Review;
use App\Support\Storage\FileStorage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageDiskRecordedTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Helpers::businessUpdateOrInsert(['key' => 'local_storage'], ['value' => 1]);
        Helpers::clearBusinessSettingsCache();
        parent::tearDown();
    }

    public function test_every_model_that_reads_a_disk_also_records_one(): void
    {
        $offenders = [];

        foreach (array_merge(
            glob(base_path('app/Models/*.php')),
            glob(base_path('Modules/*/Entities/*.php')),
            glob(base_path('Modules/*/app/Entities/*.php')),
            glob(base_path('Modules/*/app/Models/*.php')),
            glob(base_path('Modules/*/Entities/*/*.php')),
        ) as $file) {
            if (str_contains($file, 'Modules/RideShare/')) {
                continue;
            }

            $source = file_get_contents($file);

            if (! str_contains($source, 'storageFullUrl(')) {
                continue;
            }

            if (str_contains($source, 'recordStorageDisk') || str_contains($source, "table('storages')")) {
                continue;
            }

            $offenders[] = str_replace(base_path().'/', '', $file);
        }

        $this->assertSame([], $offenders,
            'these read their file back through the storages table but never write a row, '
                .'so with S3 on the lookup misses and the URL falls back to the public disk: '
                .implode(', ', $offenders));
    }

    public function test_no_model_hardcodes_the_public_disk_for_an_image_url(): void
    {
        $offenders = [];

        foreach (array_merge(
            glob(base_path('app/Models/*.php')),
            glob(base_path('app/Traits/Model/*.php')),
            glob(base_path('Modules/*/Entities/*.php')),
            glob(base_path('Modules/*/app/Entities/*.php')),
            glob(base_path('Modules/*/app/Models/*.php')),
            glob(base_path('Modules/*/Entities/*/*.php')),
        ) as $file) {
            if (str_contains($file, 'RideShare') || str_contains($file, 'HasStorageRelationTrait')) {
                continue;
            }

            $source = file_get_contents($file);

            if (preg_match("/get_full_url\([^)]*,\s*'public'\s*[,)]/", $source)) {
                $offenders[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders,
            'these build an image URL against the public disk whatever the setting says, '
                .'so the file is unreachable once s3 is on: '.implode(', ', $offenders));
    }

    public function test_no_model_reads_a_storage_key_it_never_records(): void
    {
        $offenders = [];

        foreach (array_merge(
            glob(base_path('app/Models/*.php')),
            glob(base_path('Modules/*/Entities/*.php')),
            glob(base_path('Modules/*/app/Entities/*.php')),
            glob(base_path('Modules/*/app/Models/*.php')),
            glob(base_path('Modules/*/Entities/*/*.php')),
        ) as $file) {
            if (str_contains($file, 'Modules/RideShare/')) {
                continue;
            }

            $source = file_get_contents($file);

            preg_match_all("/storageFullUrl\(\s*'[^']*'\s*,\s*'([^']+)'/", $source, $read);
            preg_match_all("/getStorageDiskByKey\(\s*\\$\w+(?:->\w+)*\s*,\s*'([^']+)'/", $source, $byKey);
            $read[1] = array_merge($read[1], $byKey[1]);

            if (! $read[1]) {
                continue;
            }

            preg_match_all("/recordStorageDisk\(\s*\\$\w+\s*,\s*'[^']*'\s*,\s*'([^']+)'/", $source, $viaHelper);
            preg_match_all("/'key'\s*=>\s*'([^']+)'/", $source, $viaLiteral);

            // A loop over its own key list writes every key it names, so the names it loops count.
            preg_match_all('/foreach\s*\(\s*\[([^\]]*)\]\s*as\s*\$key/', $source, $viaLoop);
            $looped = [];
            foreach ($viaLoop[1] as $list) {
                preg_match_all("/'([^']+)'/", $list, $names);
                $looped = array_merge($looped, $names[1]);
            }

            $written = array_merge($viaHelper[1], $viaLiteral[1], $looped);
            $missing = array_values(array_diff(array_unique($read[1]), $written));

            if ($missing) {
                $offenders[] = str_replace(base_path().'/', '', $file).' ('.implode(', ', $missing).')';
            }
        }

        $this->assertSame([], $offenders,
            'these read a storage key nothing ever writes, so that one image falls back to '
                .'the public disk even where the model records its others: '.implode('; ', $offenders));
    }

    public function test_recording_the_disk_is_not_copy_pasted_into_models(): void
    {
        $offenders = [];

        foreach (array_merge(
            glob(base_path('app/Models/*.php')),
            glob(base_path('app/Traits/Model/*.php')),
            glob(base_path('Modules/*/Entities/*.php')),
            glob(base_path('Modules/*/app/Entities/*.php')),
            glob(base_path('Modules/*/app/Models/*.php')),
        ) as $file) {
            if (str_contains($file, 'RideShare') || str_contains($file, 'HasStorageRelationTrait')) {
                continue;
            }

            if (str_contains(file_get_contents($file), "DB::table('storages')->updateOrInsert")) {
                $offenders[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders,
            'recordStorageDisk() exists so this block is written once; copies drift and are '
                .'how a key gets missed: '.implode(', ', $offenders));
    }

    public function test_no_url_is_built_against_the_local_storage_path(): void
    {
        $settled = [
            'app/Builder/MediaUrlResolver.php',
            'app/CentralLogics/Helpers.php',
            'app/Http/Resources/Common/Marketing/FlutterLandingPageResource.php',
            'app/Library/Notification.php',
            'resources/views/admin-views/brand/index.blade.php',
            'resources/views/admin-views/common-condition/edit.blade.php',
            'resources/views/admin-views/other-banners/parcel-video.blade.php',
        ];

        $offenders = [];

        foreach (['app', 'Modules', 'resources/views'] as $root) {
            $walk = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($root), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($walk as $file) {
                $path = str_replace(base_path().'/', '', $file->getPathname());

                if (str_contains($path, 'RideShare') || ! str_ends_with($path, '.php')) {
                    continue;
                }

                if (in_array($path, $settled, true)) {
                    continue;
                }

                foreach (file($file->getPathname()) as $number => $line) {
                    if (preg_match('/^\s*(\/\/|\*|#)/', $line)) {
                        continue;
                    }

                    if (preg_match('/(asset|url)\s*\(\s*["\']([^"\']*)?storage\/app\/public/', $line)) {
                        $offenders[] = $path.':'.($number + 1);
                    }
                }
            }
        }

        $this->assertSame([], $offenders,
            'these hardcode the local storage path instead of the disk the file went to, '
                .'so the image 404s once s3 is on: '.implode(', ', $offenders));
    }

    public function test_every_single_file_url_accessor_goes_through_the_shared_helper(): void
    {
        $ownsAnotherModelsFile = [
            'app/Traits/Promotion/DescribesFrozenItemLine.php',
            'app/Models/UserFile.php',
        ];

        $offenders = [];

        foreach (array_merge(
            glob(base_path('app/Models/*.php')),
            glob(base_path('app/Traits/Model/*.php')),
            glob(base_path('Modules/*/Entities/*.php')),
            glob(base_path('Modules/*/Entities/*/*.php')),
            glob(base_path('Modules/*/app/Entities/*.php')),
            glob(base_path('Modules/*/app/Models/*.php')),
        ) as $file) {
            $path = str_replace(base_path().'/', '', $file);

            if (str_contains($path, 'RideShare') || in_array($path, $ownsAnotherModelsFile, true)) {
                continue;
            }

            $lines = file($file);

            foreach ($lines as $number => $line) {
                if (! preg_match('/function\s+get(\w+)FullUrlAttribute/', $line, $name)) {
                    continue;
                }

                $body = '';

                for ($cursor = $number; $cursor < min($number + 30, count($lines)); $cursor++) {
                    $body .= $lines[$cursor];

                    if ($cursor > $number && preg_match('/^\s{4}\}/', $lines[$cursor])) {
                        break;
                    }
                }

                if (str_contains($body, 'foreach') || str_contains($body, 'storageFullUrl(')) {
                    continue;
                }

                if (str_contains($body, 'get_full_url(')) {
                    $offenders[] = $path.':'.($number + 1).' get'.$name[1].'FullUrl';
                }
            }
        }

        $this->assertSame([], $offenders,
            'a single-file url accessor must read its disk through $this->storageFullUrl(), '
                .'not build the url itself: '.implode(', ', $offenders));
    }

    public function test_an_uploaded_attachment_is_stored_and_read_back_on_the_same_disk(): void
    {
        $file = UploadedFile::fake()->image('probe.png');

        $names = FileStorage::uploadAttachments('review', [$file]);

        $this->assertCount(1, $names);
        $this->assertStringNotContainsString('/', $names[0],
            'attachments must be stored as bare filenames -- a dir-prefixed value makes the reader build review/review/<file>');
        $this->assertTrue(Storage::disk(FileStorage::getDisk())->exists('review/'.$names[0]),
            'the attachment did not land in the review folder of the active disk');

        $review = Review::first();

        if (! $review) {
            Storage::disk(FileStorage::getDisk())->delete('review/'.$names[0]);
            $this->markTestSkipped('no review row to attach to');
        }

        $review->attachment = json_encode($names);
        $review->save();

        $this->assertSame(FileStorage::getDisk(), DB::table('storages')
            ->where('data_type', Review::class)
            ->where('data_id', $review->id)
            ->where('key', 'attachment')
            ->value('value'), 'saving an attachment must record which disk it went to');

        DB::table('storages')
            ->where('data_type', Review::class)->where('data_id', $review->id)->where('key', 'attachment')
            ->update(['value' => 's3']);

        $urls = Review::withStorage()->find($review->id)->attachment_full_url;

        $this->assertStringContainsString('s3', $urls[0], 'the url must follow the recorded disk');

        $review->attachment = json_encode(['review/legacy-prefixed.png']);
        $review->save();

        DB::table('storages')
            ->where('data_type', Review::class)->where('data_id', $review->id)->where('key', 'attachment')
            ->update(['value' => 's3']);

        $legacy = Review::withStorage()->find($review->id)->attachment_full_url;

        $this->assertStringNotContainsString('review/review/', $legacy[0],
            'rows written before the fix hold review/<file> and must not be prefixed twice');

        Storage::disk(FileStorage::getDisk())->delete('review/'.$names[0]);
    }

    public function test_a_saved_image_records_the_disk_it_went_to(): void
    {
        Helpers::businessUpdateOrInsert(['key' => 'local_storage'], ['value' => 0]);
        Helpers::clearBusinessSettingsCache();

        $this->assertSame('s3', FileStorage::getDisk(), 's3 must be the active disk for this check');

        foreach ([
            [\Modules\Rental\Entities\VehicleBrand::class, 'image', 'image_full_url'],
            [\Modules\Rental\Entities\VehicleCategory::class, 'image', 'image_full_url'],
            [\Modules\Rental\Entities\VehicleDriver::class, 'image', 'image_full_url'],
            [\Modules\Service\Entities\ServiceCampaign::class, 'thumbnail', 'thumbnail_full_url'],
            [\App\Models\Bundle::class, 'image', 'image_full_url'],
        ] as [$class, $column, $accessor]) {
            $model = $class::withoutGlobalScopes()->first();

            if (! $model) {
                continue;
            }

            $model->{$column} = 'disk-probe-'.uniqid().'.png';
            $model->save();

            $row = DB::table('storages')
                ->where('data_type', $class)->where('data_id', $model->id)->first();

            $name = class_basename($class);

            $this->assertNotNull($row, "{$name} saved an image without recording which disk it went to");
            $this->assertSame('s3', $row->value, "{$name} recorded the wrong disk");

            $this->assertStringContainsString('s3', (string) $model->fresh()->{$accessor},
                "{$name} still builds a public URL while s3 is on");
        }
    }
}
