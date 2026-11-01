<?php

namespace Modules\ReelsModule\Http\Requests\Vendor\Reel;

use App\CentralLogics\Helpers;
use App\Models\Vendor;
use App\Models\VendorEmployee;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Modules\ReelsModule\Entities\Reel;

class ReelStoreRequest extends ReelRequest
{
    protected const DATE_FORMAT = 'm/d/Y';

    private const DEFAULT_MAX_UPLOAD_MB = 15;

    private const DEFAULT_MAX_DURATION = 30;

    public function rules(): array
    {
        return [
            'description' => 'required|string|max:1000',
            'translations' => 'nullable|json',
            'thumbnail' => $this->imageRule('required'),
            'video' => $this->videoRule('required', $this->maxUploadSizeMb() * 1024),
            'is_always_visible' => 'nullable|in:1',
            'dates' => 'required_without:is_always_visible|nullable|string',
            'status' => 'nullable|boolean',
            'product_id' => 'nullable|integer',
            'order_now_button' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => translate('messages.Default description is required'),
            'translations.json' => 'JSON',
            'product_id.integer' => translate('Product ID must be an integer'),
            'order_now_button.boolean' => translate('messages.Status must be boolean'),
            'is_always_visible.in' => translate('messages.Always visible must be turned on'),
            'dates.required_without' => translate('messages.Please select reel visibility duration or choose always visible'),
            'dates.string' => translate('messages.Dates must be string'),
            'status.boolean' => translate('messages.Status must be boolean'),
        ];
    }

    public function payload(): array
    {
        [$startDate, $endDate] = $this->dateRange();

        return [
            'store_id' => $this->vendorStoreId($this),
            'description' => $this->input('description'),
            'translations' => $this->translations(),
            'product_id' => $this->input('product_id'),
            'order_now_button' => $this->boolean('order_now_button'),
            'is_always_visible' => $this->boolean('is_always_visible'),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'thumbnail' => $this->file('thumbnail'),
            'video' => $this->file('video'),
            'created_by_id' => $this->creator()?->id,
            'created_by_type' => $this->creator() instanceof VendorEmployee ? VendorEmployee::class : Vendor::class,
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateUploadQuantity($validator);
            $this->validateVideoDuration($validator);
            $this->validateTranslations($validator);
            $this->validateDateRange($validator);
        });
    }

    protected function excludedReelId(): ?int
    {
        return null;
    }

    protected function translations(): array
    {
        if (! $this->filled('translations')) {
            return [];
        }

        $translations = json_decode($this->input('translations'), true);

        return is_array($translations) && count($translations) >= 1 ? $translations : [];
    }

    protected function maxUploadSizeMb(): int
    {
        return max(1, (int) (Helpers::get_business_settings('reels_max_upload_size_mb') ?: self::DEFAULT_MAX_UPLOAD_MB));
    }

    private function creator(): Vendor|VendorEmployee|null
    {
        return $this->input('vendor_employee') ?? $this->input('vendor');
    }

    private function dateRange(): array
    {
        if ($this->boolean('is_always_visible') || ! $this->filled('dates')) {
            return [null, null];
        }

        [$startDate, $endDate] = array_map('trim', explode(' - ', $this->input('dates')));

        return [
            Carbon::createFromFormat(self::DATE_FORMAT, $startDate)->startOfDay(),
            Carbon::createFromFormat(self::DATE_FORMAT, $endDate)->endOfDay(),
        ];
    }

    private function validateTranslations($validator): void
    {
        if ($this->filled('translations') && empty($this->translations())) {
            $validator->errors()->add('translations', translate('messages.Description in english is required'));
        }
    }

    private function validateDateRange($validator): void
    {
        if ($this->boolean('is_always_visible') || ! $this->filled('dates')) {
            return;
        }

        try {
            [$startDate, $endDate] = $this->dateRange();
        } catch (\Throwable $exception) {
            $validator->errors()->add('dates', translate('messages.Please select a valid date range'));

            return;
        }

        if ($startDate < Carbon::today()) {
            $validator->errors()->add('dates', translate('messages.Start date must be greater than or equal to today'));
        }

        if ($endDate < $startDate) {
            $validator->errors()->add('dates', translate('messages.End date must be greater than start date'));
        }
    }

    private function validateUploadQuantity($validator): void
    {
        if ((int) (Helpers::get_business_settings('reels_upload_limit_unlimited') ?? 1) === 1) {
            return;
        }

        $storeId = $this->vendorStoreId($this);
        $limit = (int) (Helpers::get_business_settings('reels_upload_limit') ?? 0);
        $limitType = Helpers::get_business_settings('reels_upload_limit_type') ?? 'week';

        if (! $storeId || $limit < 1) {
            return;
        }

        $existingCount = Reel::where('store_id', $storeId)
            ->whereBetween('created_at', $this->uploadLimitWindow($limitType))
            ->when($this->excludedReelId(), fn ($query, $reelId) => $query->where('id', '!=', $reelId))
            ->count();

        if ($existingCount >= $limit) {
            $validator->errors()->add(
                'video',
                translate('messages.This store has already reached its reel upload limit for this period.') . ' ' . translate('messages.Upload limit') . ': ' . $limit . ' / ' . ($limitType === 'month' ? translate('messages.month') : translate('messages.week'))
            );
        }
    }

    private function validateVideoDuration($validator): void
    {
        if (! $this->hasFile('video')) {
            return;
        }

        $maxDuration = max(1, (int) (Helpers::get_business_settings('reels_max_duration') ?? self::DEFAULT_MAX_DURATION));
        $durationUnit = Helpers::get_business_settings('reels_max_duration_unit') ?? 'min';
        $durationSeconds = $this->videoDurationInSeconds($this->file('video'));

        if ($durationSeconds === null) {
            return;
        }

        if ($durationSeconds > ($durationUnit === 'hour' ? $maxDuration * 3600 : $maxDuration * 60)) {
            $validator->errors()->add(
                'video',
                translate('messages.Reel video is too long.') . ' ' . translate('messages.Maximum duration') . ': ' . $maxDuration . ' ' . ($durationUnit === 'hour' ? translate('messages.Hour') : translate('messages.minutes'))
            );
        }
    }

    private function uploadLimitWindow(string $limitType): array
    {
        return $limitType === 'month'
            ? [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]
            : [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()];
    }

    private function videoDurationInSeconds(?UploadedFile $file): ?float
    {
        $filePath = $file?->getRealPath();

        if (! $filePath || ! function_exists('shell_exec')) {
            return null;
        }

        $ffprobePath = trim((string) @shell_exec('command -v ffprobe'));

        if ($ffprobePath === '') {
            return null;
        }

        $duration = trim((string) @shell_exec($ffprobePath
            . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
            . escapeshellarg($filePath) . ' 2>/dev/null'));

        return is_numeric($duration) ? (float) $duration : null;
    }
}
