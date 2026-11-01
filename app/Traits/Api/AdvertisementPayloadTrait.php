<?php

namespace App\Traits\Api;

use Illuminate\Support\Carbon;

trait AdvertisementPayloadTrait
{
    use ApiRequestContextTrait;

    protected const DATE_RANGE_REGEX = 'regex:/^\d{1,2}\/\d{1,2}\/\d{4} - \d{1,2}\/\d{1,2}\/\d{4}$/';

    public function payload(): array
    {
        $rows = $this->translationRows($this);
        $isStorePromotion = $this->input('advertisement_type') === 'store_promotion';
        [$startDate, $endDate] = $this->dateRange();

        return [
            'store_id' => $this->vendorStoreId($this),
            'vendor_id' => $this->vendorId($this),
            'advertisement_type' => $this->input('advertisement_type'),
            'title' => $rows[0]['value'] ?? null,
            'description' => $rows[1]['value'] ?? null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_rating_active' => $isStorePromotion ? ($this->input('is_rating_active') ?? 0) : 0,
            'is_review_active' => $isStorePromotion ? ($this->input('is_review_active') ?? 0) : 0,
            'translations' => $rows,
            'cover_image' => $this->file('cover_image'),
            'profile_image' => $this->file('profile_image'),
            'video_attachment' => $this->file('video_attachment'),
        ];
    }

    protected function attachmentMessages(): array
    {
        $imageFormat = translate('messages.Image must be in format').': '.IMAGE_FORMAT;
        $imageSize = translate('messages.Image must be less than').' '.MAX_FILE_SIZE.'mb';

        return [
            'cover_image.mimes' => $imageFormat,
            'profile_image.mimes' => $imageFormat,
            'cover_image.max' => $imageSize,
            'profile_image.max' => $imageSize,
        ];
    }

    protected function dateRange(): array
    {
        $value = (string) $this->input('dates');

        if (! str_contains($value, ' - ')) {
            return [null, null];
        }

        [$start, $end] = explode(' - ', $value, 2);

        try {
            return [
                Carbon::createFromFormat('m/d/Y', trim($start))->startOfDay(),
                Carbon::createFromFormat('m/d/Y', trim($end))->endOfDay(),
            ];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    protected function validateDateOrder($validator): void
    {
        [$startDate, $endDate] = $this->dateRange();

        if ($startDate && $endDate && $endDate < $startDate) {
            $validator->errors()->add('date', translate('messages.End date must be greater than start date'));
        }
    }

    protected function validateTranslationRows($validator): void
    {
        if (count($this->translationRows($this)) < 1) {
            $validator->errors()->add('translations', translate('messages.Title and description in english is required'));
        }
    }
}
