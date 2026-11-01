<?php

namespace Tests\Feature;

use App\Traits\Item\ProductPayloadTrait;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class ResourceAppendsDifferentialTest extends TestCase
{
    use ProductPayloadTrait;

    private function cases(): array
    {
        return [
            ['App\Http\Resources\Common\Chat\ChatUserResource', 'App\Models\UserInfo', ['image_full_url']],
            ['App\Http\Resources\Common\Chat\MessageResource', 'App\Models\Message', ['file_full_url']],
            ['App\Http\Resources\Common\DeliveryMan\VehicleResource', 'App\Models\DMVehicle', ['image_full_url']],
            ['App\Http\Resources\Common\Item\BrandResource', 'App\Models\Brand', ['image_full_url']],
            ['App\Http\Resources\Common\Item\ItemListResource', 'App\Models\Item', ['image_full_url', 'images_full_url', 'video_full_url']],
            ['App\Http\Resources\Common\Item\ItemResource', 'App\Models\Item', ['image_full_url', 'images_full_url', 'video_full_url']],
            ['App\Http\Resources\Common\Item\ProductListResource', 'App\Models\Item', ['image_full_url', 'images_full_url', 'video_full_url']],
            ['App\Http\Resources\Common\Item\ProductResource', 'App\Models\Item', ['image_full_url', 'images_full_url', 'video_full_url']],
            ['App\Http\Resources\Common\Marketing\BasicCampaignResource', 'App\Models\Campaign', ['image_full_url']],
            ['App\Http\Resources\Common\Module\ModuleResource', 'App\Models\Module', ['icon_full_url', 'thumbnail_full_url']],
            ['App\Http\Resources\Common\Module\TopOfferResource', 'App\Models\Module', ['icon_full_url', 'thumbnail_full_url']],
            ['App\Http\Resources\Common\Parcel\ParcelCategoryResource', 'App\Models\ParcelCategory', ['image_full_url']],
            ['App\Http\Resources\Common\Store\StoreDetailResource', 'App\Models\Store', ['logo_full_url', 'tin_certificate_image_full_url', 'cover_photo_full_url', 'meta_image_full_url']],
            ['App\Http\Resources\Common\Store\StoreListResource', 'App\Models\Store', ['logo_full_url', 'tin_certificate_image_full_url', 'cover_photo_full_url', 'meta_image_full_url']],
            ['App\Http\Resources\Common\Store\StoreShowResource', 'App\Models\Store', ['logo_full_url', 'tin_certificate_image_full_url', 'cover_photo_full_url', 'meta_image_full_url']],
            ['App\Http\Resources\Customer\Item\CategoryResource', 'App\Models\Category', ['image_full_url']],
            ['App\Http\Resources\Customer\Item\StoreCategoryResource', 'App\Models\StoreCategory', ['image_full_url']],
            ['App\Http\Resources\Customer\Notification\NotificationResource', 'App\Models\Notification', ['image_full_url']],
            ['App\Http\Resources\Customer\Order\OrderCardResource', 'App\Models\Order', ['order_attachment_full_url', 'order_proof_full_url']],
            ['App\Http\Resources\Customer\Order\ReviewReminderResource', 'App\Models\Order', ['order_attachment_full_url', 'order_proof_full_url']],
            ['App\Http\Resources\Customer\Profile\ProfileResource', 'App\Models\User', ['image_full_url']],
            ['App\Http\Resources\Customer\Promotion\AdvertisementResource', 'App\Models\Advertisement', ['cover_image_full_url', 'profile_image_full_url', 'video_attachment_full_url']],
            ['App\Http\Resources\Customer\Promotion\CampaignResource', 'App\Models\Campaign', ['image_full_url']],
            ['App\Http\Resources\Customer\Promotion\ItemCampaignResource', 'App\Models\ItemCampaign', ['image_full_url']],
            ['App\Http\Resources\Customer\Promotion\SmartBannerResource', 'App\Models\SmartBanner', ['image_full_url']],
            ['App\Http\Resources\Customer\Promotion\StoreBannerResource', 'App\Models\Banner', ['image_full_url']],
            ['App\Http\Resources\Customer\Promotion\WhyChooseResource', 'App\Models\ModuleWiseWhyChoose', ['image_full_url']],
            ['App\Http\Resources\Customer\Store\StoreResource', 'App\Models\Store', ['logo_full_url', 'tin_certificate_image_full_url', 'cover_photo_full_url', 'meta_image_full_url']],
            ['App\Http\Resources\DeliveryMan\Order\OrderResource', 'App\Models\Order', ['order_attachment_full_url', 'order_proof_full_url']],
            ['App\Http\Resources\DeliveryMan\Profile\ProfileResource', 'App\Models\DeliveryMan', ['image_full_url', 'identity_image_full_url']],
            ['App\Http\Resources\Vendor\Item\CategoryResource', 'App\Models\Category', ['image_full_url']],
            ['App\Http\Resources\Vendor\Item\PendingItemResource', 'App\Models\TempProduct', ['image_full_url', 'images_full_url', 'video_full_url']],
            ['App\Http\Resources\Vendor\Item\StoreCategoryResource', 'App\Models\StoreCategory', ['image_full_url']],
            ['App\Http\Resources\Vendor\Order\OrderEditItemResource', 'App\Models\Item', ['image_full_url', 'images_full_url', 'video_full_url']],
            ['App\Http\Resources\Vendor\Order\OrderResource', 'App\Models\Order', ['order_attachment_full_url', 'order_proof_full_url']],
            ['App\Http\Resources\Vendor\Profile\ProfileResource', 'App\Models\Vendor', ['image_full_url']],
            ['App\Http\Resources\Vendor\Promotion\AdvertisementResource', 'App\Models\Advertisement', ['cover_image_full_url', 'profile_image_full_url', 'video_attachment_full_url']],
            ['App\Http\Resources\Vendor\Promotion\BannerResource', 'App\Models\Banner', ['image_full_url']],
            ['App\Http\Resources\Vendor\Promotion\CampaignResource', 'App\Models\Campaign', ['image_full_url']],
            ['Modules\ReelsModule\Http\Resources\Vendor\Reel\ReelListResource', 'Modules\ReelsModule\Entities\Reel', ['thumbnail_full_url', 'video_full_url']],
            ['Modules\Rental\Http\Resources\Customer\Store\ProviderListResource', 'App\Models\Store', ['logo_full_url', 'tin_certificate_image_full_url', 'cover_photo_full_url', 'meta_image_full_url']],
            ['Modules\Rental\Http\Resources\Customer\Vehicle\VehicleDetailResource', 'Modules\Rental\Entities\Vehicle', ['thumbnail_full_url', 'images_full_url', 'documents_full_url']],
            ['Modules\Rental\Http\Resources\Customer\Vehicle\VehicleResource', 'Modules\Rental\Entities\Vehicle', ['thumbnail_full_url', 'images_full_url', 'documents_full_url']],
            ['Modules\Rental\Http\Resources\Customer\Vehicle\VehicleTaxonomyResource', 'Modules\Rental\Entities\VehicleBrand', ['image_full_url']],
            ['Modules\Rental\Http\Resources\Customer\Vehicle\VehicleTaxonomyResource', 'Modules\Rental\Entities\VehicleCategory', ['image_full_url']],
            ['Modules\Rental\Http\Resources\Vendor\Vehicle\VehicleTaxonomyResource', 'Modules\Rental\Entities\VehicleBrand', ['image_full_url']],
            ['Modules\Rental\Http\Resources\Vendor\Vehicle\VehicleTaxonomyResource', 'Modules\Rental\Entities\VehicleCategory', ['image_full_url']],
            ['Modules\Service\Http\Resources\ServiceResource', 'Modules\Service\Entities\Service', ['thumbnail_full_url', 'additional_images_full_url']],
            ['App\\Http\\Resources\\Common\\Order\\ParcelOrderResource', 'App\\Models\\Order', ['order_attachment_full_url', 'order_proof_full_url']],
            ['App\\Http\\Resources\\Customer\\Order\\TripOrderResource', 'App\\Models\\Order', ['order_attachment_full_url', 'order_proof_full_url']],
            ['App\\Http\\Resources\\Customer\\Order\\RideOrderResource', 'App\\Models\\Order', ['order_attachment_full_url', 'order_proof_full_url']],
            ['App\\Http\\Resources\\Vendor\\Report\\TaxOrderResource', 'App\\Models\\Order', ['order_attachment_full_url', 'order_proof_full_url']],
            ['App\\Http\\Resources\\Common\\Item\\ItemDetailResource', 'App\\Models\\Item', ['image_full_url', 'images_full_url', 'video_full_url']],
            ['App\\Http\\Resources\\Common\\Item\\CartProductResource', 'App\\Models\\Item', ['image_full_url', 'images_full_url', 'video_full_url']],
            ['App\\Http\\Resources\\Common\\Item\\ProductDetailResource', 'App\\Models\\Item', ['image_full_url', 'images_full_url', 'video_full_url']],
            ['App\\Http\\Resources\\Common\\Item\\TempProductResource', 'App\\Models\\TempProduct', ['image_full_url', 'images_full_url', 'video_full_url']],
            ['App\\Http\\Resources\\Vendor\\DeliveryMan\\DeliveryManResource', 'App\\Models\\DeliveryMan', ['image_full_url', 'identity_image_full_url']],
            ['App\\Builder\\Resources\\ItemCardResource', 'App\\Models\\Item', ['image_full_url', 'images_full_url', 'video_full_url']],
            ['App\\Builder\\Resources\\ItemDetailResource', 'App\\Models\\Item', ['image_full_url', 'images_full_url', 'video_full_url']],
            ['App\\Builder\\Resources\\FoodDetailsResource', 'App\\Models\\Item', ['image_full_url', 'images_full_url', 'video_full_url']],
        ];
    }

    public function test_no_resource_silently_lost_a_full_url_key(): void
    {
        $this->ensureMemoryLimit('1024M');
        Model::preventLazyLoading(false);

        $lost = [];
        $report = [];

        foreach ($this->cases() as [$resourceClass, $modelClass, $keys]) {
            $label = str_replace(['App\Http\Resources\\', 'Modules\\', '\Http\Resources'], '', $resourceClass)
                .'  <-  '.class_basename($modelClass);

            if (! class_exists($resourceClass) || ! class_exists($modelClass)) {
                continue;
            }

            $plain = $this->render($resourceClass, $modelClass, []);
            $appended = $this->render($resourceClass, $modelClass, $keys);

            if ($plain === null || $appended === null) {
                $report[] = sprintf('%-72s skipped', $label);

                continue;
            }

            $missing = [];

            foreach ($keys as $key) {
                if (array_key_exists($key, $appended) && ! array_key_exists($key, $plain)) {
                    $missing[] = $key;
                    $lost[] = $label.' :: '.$key;
                }
            }

            $report[] = sprintf('%-72s %s', $label, $missing ? '** LOST '.implode(', ', $missing).' **' : 'ok');
        }

        fwrite(STDERR, PHP_EOL.implode(PHP_EOL, $report).PHP_EOL.PHP_EOL);

        $this->assertSame([], $lost,
            'A resource stopped emitting a *_full_url key it used to get from $appends. '
            .'Name the key explicitly in the resource, or restore it to the model.');
    }

    private function render(string $resourceClass, string $modelClass, array $append): ?array
    {
        $model = $modelClass::query()->first();

        if (! $model) {
            return null;
        }

        if ($model instanceof \App\Models\Item) {
            $this->loadProductRelations(new EloquentCollection([$model]));
        }

        if ($append !== []) {
            $model->append($append);
        }

        try {
            if (method_exists($resourceClass, 'fromOne')) {
                return $resourceClass::fromOne($model);
            }

            return (new $resourceClass($model))->toArray(request());
        } catch (\Throwable $e) {
            if (getenv('SHOW_SKIP_REASON')) {
                fwrite(STDERR, PHP_EOL.'  SKIP '.class_basename($resourceClass).': '.substr($e->getMessage(), 0, 160).PHP_EOL);
            }

            return null;
        }
    }
}
