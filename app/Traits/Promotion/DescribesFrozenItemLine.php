<?php

namespace App\Traits\Promotion;

use App\CentralLogics\Helpers;
use App\Models\Item;
use App\Support\Storage\FileStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Service\Entities\Service;

trait DescribesFrozenItemLine
{
    private ?string $resolvedImageDisk = null;

    public function variationLabels(): array
    {
        if ($this->service_id ?? null) {
            return $this->serviceVariationLabels();
        }

        $labels = [];

        foreach ((array) $this->variations as $group) {
            foreach ((array) data_get($group, 'values.label', []) as $label) {
                $labels[] = $label;
            }

            if ($type = data_get($group, 'type')) {
                $labels[] = $type;
            }
        }

        return $labels;
    }

    public function variationDisplayLines(): array
    {
        if ($this->service_id ?? null) {
            return $this->serviceVariationLabels();
        }

        $lines = [];

        foreach ((array) $this->variations as $group) {
            if ($type = data_get($group, 'type')) {
                $lines[] = implode(' / ', array_map('trim', explode('-', (string) $type)));

                continue;
            }

            $labels = (array) data_get($group, 'values.label', []);

            if ($labels) {
                $lines[] = trim(data_get($group, 'name').' : '.implode(', ', $labels));
            }
        }

        return $lines;
    }

    /**
     * A service line's chosen variant, in the flat {variant_key,name,price,...} shape
     * ProvidesStoreItemPicker::storeServicePickerOptions() hands the picker and
     * BundleService::syncItems() freezes verbatim into `variations` -- see
     * HandlesFrozenLines::serviceVariantKey(), which reads variations[0] the same way.
     *
     * The variant's own `name` is already the whole label an admin gave that option (e.g.
     * "Quater", "Premium"), unlike an item's variation groups, so it needs no "group : value"
     * composition -- it is simply displayed as-is. No variant selected (a service with no
     * variant groups at all) returns no lines, same as an item with none.
     */
    private function serviceVariationLabels(): array
    {
        $variations = (array) $this->variations;
        $first = array_key_exists('variant_key', $variations) ? $variations : ($variations[0] ?? null);

        $name = is_array($first) ? trim((string) ($first['name'] ?? '')) : '';

        return $name !== '' ? [$name] : [];
    }

    public function getVariationLabelAttribute(): string
    {
        return implode(', ', $this->variationLabels());
    }

    public function addOnDisplayLines(array $addOnNames): array
    {
        $lines = [];

        foreach ((array) $this->add_on_ids as $index => $id) {
            if (! isset($addOnNames[$id])) {
                continue;
            }

            $lines[] = $addOnNames[$id].' ('.((int) (($this->add_on_qtys[$index] ?? 1)) ?: 1).')';
        }

        return $lines;
    }

    public function getItemImageFullUrlAttribute(): ?string
    {
        [$folder, $relation] = ($this->service_id ?? null) ? ['service', 'service'] : ['product', 'item'];

        $disk = $this->sourceImageDisk($relation);

        if (! $this->frozenImageMissing($folder, $disk)) {
            return Helpers::get_full_url($folder, $this->item_image, $disk, $folder);
        }

        // The snapshot's file is gone. Lines frozen before copy-on-freeze landed never owned a
        // copy -- they just recorded the live item's filename -- and replacing that item's image
        // deletes the old file (FileStorage::update() deletes-then-uploads), so the line is left
        // pointing at a 404. Helpers::get_full_url() answers null on an api/* request, which is a
        // blank tile in the apps. Showing whatever the item looks like today beats showing
        // nothing; name and price stay frozen, because those are what the bundle was priced on.
        [$liveImage, $liveDisk] = $this->liveSourceImage($relation);

        if (! $liveImage) {
            return Helpers::get_full_url($folder, $this->item_image, $disk, $folder);
        }

        return Helpers::get_full_url($folder, $liveImage, $liveDisk, $folder);
    }

    /**
     * Whether the frozen filename no longer resolves to a file we can serve.
     *
     * s3 is taken on trust: get_full_url() hands back an s3 URL without a HEAD, and probing every
     * line over the network to render one bundle card is not worth it.
     */
    private function frozenImageMissing(string $folder, string $disk): bool
    {
        if (blank($this->item_image)) {
            return true;
        }

        if ($disk === 's3') {
            return false;
        }

        return ! Storage::disk('public')->exists($folder.'/'.$this->item_image);
    }

    /**
     * The live item's/service's current image and the disk holding it, as [filename, disk].
     *
     * Global scopes are dropped on purpose: a customer request carries store and zone scopes that
     * would hide the row and turn the fallback into another blank tile, and this is a read of one
     * already-referenced id, not a listing.
     */
    private function liveSourceImage(string $relation): array
    {
        if ($relation === 'service') {
            $service = $this->relationLoaded('service')
                ? $this->service
                : ($this->service_id ? Service::withoutGlobalScopes()->with('storage')->find($this->service_id) : null);

            return [
                $service?->getRawOriginal('thumbnail'),
                FileStorage::getStorageDiskByKey($service, 'thumbnail', 'public'),
            ];
        }

        $item = $this->relationLoaded('item')
            ? $this->item
            : ($this->item_id ? Item::withoutGlobalScopes()->with('storage')->find($this->item_id) : null);

        return [
            $item?->getRawOriginal('image'),
            FileStorage::getStorageDiskByKey($item, 'image', 'public'),
        ];
    }

    private function sourceImageDisk(string $relation): string
    {
        if ($this->resolvedImageDisk !== null) {
            return $this->resolvedImageDisk;
        }

        // The line owns a physical copy of the image (see BundleService::syncItems() /
        // HandlesBogoEnrollment::syncEnrollmentItems()), so its own storage record -- if one was
        // written for it -- is authoritative. Rows frozen before that copy-on-freeze fix have no
        // such record and still share the live item's/service's file, so they fall through to the
        // relation lookup below.
        $ownDisk = DB::table('storages')
            ->where('data_type', static::class)
            ->where('data_id', $this->id)
            ->where('key', 'image')
            ->value('value');

        if ($ownDisk) {
            return $this->resolvedImageDisk = $ownDisk;
        }

        $key = $relation === 'service' ? 'thumbnail' : 'image';

        if ($this->relationLoaded($relation) && $this->{$relation}?->relationLoaded('storage')) {
            return $this->resolvedImageDisk = FileStorage::getStorageDiskByKey($this->{$relation}, $key, 'public');
        }

        $id = $relation === 'service' ? $this->service_id : $this->item_id;

        if (! $id) {
            return $this->resolvedImageDisk = 'public';
        }

        return $this->resolvedImageDisk = DB::table('storages')
            ->where('data_type', $relation === 'service' ? Service::class : Item::class)
            ->where('data_id', $id)
            ->where('key', $key)
            ->value('value') ?? 'public';
    }
}
