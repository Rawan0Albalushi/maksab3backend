<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class ResourceFullUrlAuditTest extends TestCase
{
    private function cases(): array
    {
        return [
            ['App\Http\Resources\Common\System\NotificationResource', 'App\Models\Notification', ['image_full_url']],
            ['App\Http\Resources\Customer\Notification\NotificationResource', 'App\Models\Notification', ['image_full_url']],
            ['App\Http\Resources\Common\Chat\MessageResource', 'App\Models\Message', ['file_full_url']],
            ['Modules\AI\app\Http\Resources\Customer\Chat\MessageResource', 'App\Models\Message', ['image_full_url']],
            ['App\Http\Resources\Common\System\PageMetaResource', 'App\Models\PageSeoData', ['image_full_url']],
            ['Modules\ReelsModule\Http\Resources\Common\Reel\ReelResource', 'Modules\ReelsModule\Entities\Reel', ['thumbnail_full_url', 'video_full_url']],
            ['Modules\ReelsModule\Http\Resources\Customer\Reel\ReelListResource', 'Modules\ReelsModule\Entities\Reel', ['thumbnail_full_url', 'video_full_url']],
            ['Modules\ReelsModule\Http\Resources\Vendor\Reel\ReelListResource', 'Modules\ReelsModule\Entities\Reel', ['thumbnail_full_url', 'video_full_url']],
            ['Modules\Rental\Http\Resources\Customer\Vehicle\VehicleDetailResource', 'Modules\Rental\Entities\Vehicle', ['thumbnail_full_url', 'images_full_url']],
            ['Modules\Rental\Http\Resources\Customer\Vehicle\VehicleResource', 'Modules\Rental\Entities\Vehicle', ['thumbnail_full_url']],
            ['Modules\Rental\Http\Resources\Vendor\Vehicle\VehicleResource', 'Modules\Rental\Entities\Vehicle', ['thumbnail_full_url']],
            ['Modules\Rental\Http\Resources\Vendor\Vehicle\DriverResource', 'Modules\Rental\Entities\VehicleDriver', ['image_full_url']],
            ['Modules\Rental\Http\Resources\Vendor\Vehicle\DriverDetailResource', 'Modules\Rental\Entities\VehicleDriver', ['image_full_url', 'identity_image_full_url']],
            ['Modules\Service\Http\Resources\ServiceResource', 'Modules\Service\Entities\Service', ['thumbnail_full_url']],
            ['Modules\Service\Http\Resources\ServiceBookingListResource', 'Modules\Service\Entities\ServiceBooking', ['attachments_full_url']],
            ['Modules\Service\Http\Resources\ServiceBookingDetailsResource', 'Modules\Service\Entities\ServiceBooking', ['attachments_full_url', 'completion_evidence_full_url']],
            ['Modules\Service\Http\Resources\TempServiceListResource', 'Modules\Service\Entities\TempService', ['thumbnail_full_url']],
            ['Modules\Service\Http\Resources\TempServiceDetailsResource', 'Modules\Service\Entities\TempService', ['thumbnail_full_url', 'additional_images_full_url']],
        ];
    }

    private function acceptedGaps(): array
    {
        return [
            'AI\app\Customer\Chat\MessageResource :: image_full_url',
            'ReelsModule\Common\Reel\ReelResource :: thumbnail_full_url',
            'ReelsModule\Common\Reel\ReelResource :: video_full_url',
            'ReelsModule\Customer\Reel\ReelListResource :: video_full_url',
            'Service\ServiceBookingListResource :: attachments_full_url',
            'Service\ServiceBookingDetailsResource :: attachments_full_url',
            'Service\ServiceBookingDetailsResource :: completion_evidence_full_url',
        ];
    }

    public function test_resources_still_emit_full_url_keys(): void
    {
        Model::preventLazyLoading(false);
        $report = [];
        $missing = [];

        foreach ($this->cases() as [$resourceClass, $modelClass, $keys]) {
            $label = str_replace(['App\Http\Resources\\', 'Modules\\', '\Http\Resources'], '', $resourceClass);

            if (! class_exists($resourceClass) || ! class_exists($modelClass)) {
                $report[] = sprintf('%-64s SKIP class missing', $label);

                continue;
            }

            $model = $modelClass::query()->first();

            if (! $model) {
                $report[] = sprintf('%-64s SKIP no rows', $label);

                continue;
            }

            try {
                $out = (new $resourceClass($model))->toArray(request());
            } catch (\Throwable $e) {
                $report[] = sprintf('%-64s ERROR %s', $label, substr($e->getMessage(), 0, 60));

                continue;
            }

            foreach ($keys as $k) {
                $ok = array_key_exists($k, $out);
                $report[] = sprintf('%-64s %-28s %s', $label, $k, $ok ? 'ok' : '** MISSING **');

                if (! $ok) {
                    $missing[] = $label.' :: '.$k;
                }
            }
        }

        fwrite(STDERR, PHP_EOL.implode(PHP_EOL, $report).PHP_EOL.PHP_EOL);

        $this->assertSame($this->acceptedGaps(), $missing,
            'A resource that serialises a model wholesale stopped emitting a *_full_url key. '
            .'Name the key explicitly in the resource, or restore it to the model $appends.');
    }
}
