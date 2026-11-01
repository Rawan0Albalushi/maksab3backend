<?php

namespace App\Traits\Report;

trait ExportRowFormatTrait
{
    public static function formatExportItems($items, $moduleType)
    {
        $storage = [];

        foreach ($items as $item) {
            $categoryId = 0;
            $subCategoryId = 0;

            foreach (json_decode($item->category_ids, true) as $key => $category) {
                match (true) {
                    $key == 0 => $categoryId = $category['id'],
                    $key == 1 => $subCategoryId = $category['id'],
                    default => null,
                };
            }

            $addOns = json_decode($item->add_ons, true);
            $variations = json_decode($item->variations, true);
            $foodVariations = json_decode($item->food_variations, true);
            $choiceOptions = json_decode($item->choice_options, true);
            $attributes = json_decode($item->attributes, true);

            $row = [
                'Id' => $item->id,
                'Name' => $item->name,
                'Description' => $item->description,
                'Image' => $item->image,
                'Images' => $item->images,
                'CategoryId' => $categoryId,
                'SubCategoryId' => $subCategoryId,
                'UnitId' => $item->unit_id,
                'Stock' => $item->stock,
                'Price' => $item->price,
                'Discount' => $item->discount,
                'DiscountType' => $item->discount_type,
                'AvailableTimeStarts' => $item->available_time_starts,
                'AvailableTimeEnds' => $item->available_time_ends,
                'Variations' => $moduleType == 'food'
                    ? (! empty($foodVariations) ? $item->food_variations : null)
                    : (! empty($variations) ? $item->variations : null),
                'ChoiceOptions' => ! empty($choiceOptions) ? $item->choice_options : null,
                'AddOns' => ! empty($addOns) ? $item->add_ons : null,
                'Attributes' => ! empty($attributes) ? $item->attributes : null,
                'StoreId' => $item->store_id,
                'ModuleId' => $item->module_id,
                'Status' => $item->status == 1 ? 'active' : 'inactive',
                'Veg' => $item->veg == 1 ? 'yes' : 'no',
                'Recommended' => $item->recommended == 1 ? 'yes' : 'no',
            ];

            $storage[] = array_merge($row, match (true) {
                $moduleType === 'pharmacy' => [
                    'IsPrescriptionRequired' => $item?->pharmacy_item_details?->is_prescription_required ?? 0,
                    'CommonConditions' => $item?->pharmacy_item_details?->common_condition_id ?? 0,
                    'IsBasic' => $item?->pharmacy_item_details?->is_basic ?? 0,
                    'UnitValue' => $item?->pharmacy_item_details?->unit_value,
                    'Manufacturer' => $item?->pharmacy_item_details?->manufacturer,
                ],
                in_array($moduleType, ['ecommerce', 'grocery'], true) => [
                    'BrandId' => $item?->ecommerce_item_details?->brand_id ?? 0,
                ],
                default => [],
            });
        }

        return $storage;
    }

    public static function formatExportVehicles($vehicles, $moduleType): array
    {
        $storage = [];

        foreach ($vehicles as $vehicle) {
            $storage[] = [
                'Id' => $vehicle->id,
                'Name' => $vehicle->name,
                'Description' => $vehicle->description ?? null,
                'Thumbnail' => $vehicle->thumbnail ?? null,
                'Images' => $vehicle->images ?? null,
                'ZoneId' => $vehicle->zone_id ?? null,
                'ProviderId' => $vehicle->provider_id ?? null,
                'BrandId' => $vehicle->brand_id ?? null,
                'CategoryId' => $vehicle->category_id ?? null,
                'Model' => $vehicle->model ?? null,
                'Type' => $vehicle->type ?? null,
                'EngineCapacity' => $vehicle->engine_capacity ?? null,
                'EnginePower' => $vehicle->engine_power ?? null,
                'SeatingCapacity' => $vehicle->seating_capacity ?? null,
                'AirCondition' => $vehicle->air_condition ?? 0,
                'FuelType' => $vehicle->fuel_type ?? null,
                'TransmissionType' => $vehicle->transmission_type ?? null,
                'MultipleVehicles' => $vehicle->multiple_vehicles ?? 0,
                'TripHourly' => $vehicle->trip_hourly ?? 0,
                'TripDistance' => $vehicle->trip_distance ?? 0,
                'TripDayWise' => $vehicle->trip_day_wise ?? 0,
                'HourlyPrice' => $vehicle->hourly_price ?? 0.00,
                'DayWisePrice' => $vehicle->day_wise_price ?? 0.00,
                'DistancePrice' => $vehicle->distance_price ?? 0.00,
                'DiscountType' => $vehicle->discount_type ?? null,
                'DiscountPrice' => $vehicle->discount_price ?? 0.00,
                'Tag' => $vehicle->tag ?? null,
                'Documents' => $vehicle->documents ?? null,
                'Status' => $vehicle->status ?? 1,
                'NewTag' => $vehicle->new_tag ?? 1,
            ];
        }

        return $storage;
    }
}
