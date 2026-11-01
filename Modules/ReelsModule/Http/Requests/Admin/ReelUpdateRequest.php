<?php

namespace Modules\ReelsModule\Http\Requests\Admin;

use App\Rules\VideoFile;
use App\Rules\ImageFile;
use App\CentralLogics\Helpers;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Modules\ReelsModule\Entities\Reel;

class ReelUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxUploadSizeMb = max(1, (int) (Helpers::get_business_settings('reels_max_upload_size_mb') ?: 15));

        return [
            'store_id' => 'required|exists:stores,id',
            'description.0' => 'required|string|max:200',
            'description.*' => 'nullable|string|max:200',
            'lang' => 'required|array',
            'lang.*' => 'required|string',
            'thumbnail' => ImageFile::rules('nullable'),
            'video' => VideoFile::rules('nullable', ($maxUploadSizeMb * 1024)),
            'product_id' => 'nullable|integer',
            'order_now_button' => 'nullable|in:1',
            'is_always_visible' => 'nullable|in:1',
            'dates' => 'required_without:is_always_visible|nullable|string',
        ];
    }

    public function messages(): array
    {
        $maxUploadSizeMb = max(1, (int) (Helpers::get_business_settings('reels_max_upload_size_mb') ?: 15));
        $storeLabelLower = Helpers::getStoreLabelByModuleType(config('module.current_module_type'), true);

        return [
            'store_id.required' => 'Please select a ' . $storeLabelLower,
            'store_id.exists' => 'Please select a valid ' . $storeLabelLower,
            'description.0.required' => translate('messages.Default description is required'),
            'dates.required_without' => translate('messages.Please select reel visibility duration or choose always visible'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateUploadQuantity($validator);
            $this->validateVideoDuration($validator);

            if ($this->boolean('is_always_visible') || !$this->filled('dates')) {
                return;
            }

            try {
                [$startDate, $endDate] = array_map('trim', explode(' - ', $this->dates));
                $startDate = Carbon::createFromFormat('m/d/Y', $startDate)->startOfDay();
                $endDate = Carbon::createFromFormat('m/d/Y', $endDate)->endOfDay();
            } catch (\Throwable $th) {
                $validator->errors()->add('dates', translate('messages.Please select a valid date range'));
                return;
            }

            if ($startDate < Carbon::today()) {
                $validator->errors()->add('dates', translate('messages.Start date must be greater than or equal to today'));
            }

            if ($endDate < $startDate) {
                $validator->errors()->add('dates', translate('messages.End date must be greater than start date'));
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        $response = response()->json(['errors' => Helpers::error_processor($validator)]);

        throw new ValidationException($validator, $response);
    }

    private function validateUploadQuantity($validator): void
    {
        if (!Helpers::get_business_settings('vendor_can_upload_reels')) {
            return;
        }

        if ((int) (Helpers::get_business_settings('reels_upload_limit_unlimited') ?? 1) === 1) {
            return;
        }

        $storeId = (int) $this->input('store_id');
        $limit = (int) (Helpers::get_business_settings('reels_upload_limit') ?? 0);
        $limitType = Helpers::get_business_settings('reels_upload_limit_type') ?? 'week';
        $reelId = (int) $this->route('id');

        if (!$storeId || $limit < 1) {
            return;
        }

        [$startDate, $endDate] = $this->getUploadLimitWindow($limitType);

        $existingCount = Reel::moduleWise()
            ->where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($reelId > 0, fn ($query) => $query->where('id', '!=', $reelId))
            ->count();

        if ($existingCount >= $limit) {
            $validator->errors()->add(
                'store_id',
                translate('messages.This store has already reached its reel upload limit for this period.') . ' ' . translate('messages.Upload limit') . ': ' . $limit . ' / ' . ($limitType === 'month' ? translate('messages.month') : translate('messages.week'))
            );
        }
    }

    private function validateVideoDuration($validator): void
    {
        if (!$this->hasFile('video')) {
            return;
        }

        $maxDuration = max(1, (int) (Helpers::get_business_settings('reels_max_duration') ?? 30));
        $durationUnit = Helpers::get_business_settings('reels_max_duration_unit') ?? 'min';
        $durationSeconds = $this->extractVideoDurationInSeconds($this->file('video'));

        if ($durationSeconds === null) {
            return;
        }

        $maxDurationSeconds = $durationUnit === 'hour' ? $maxDuration * 3600 : $maxDuration * 60;

        if ($durationSeconds > $maxDurationSeconds) {
            $validator->errors()->add(
                'video',
                translate('messages.Reel video is too long.') . ' ' . translate('messages.Maximum duration') . ': ' . $maxDuration . ' ' . ($durationUnit === 'hour' ? translate('messages.Hour') : translate('messages.minutes'))
            );
        }
    }

    private function getUploadLimitWindow(string $limitType): array
    {
        return $limitType === 'month'
            ? [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]
            : [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()];
    }

    private function extractVideoDurationInSeconds(?UploadedFile $file): ?float
    {
        if (!$file instanceof UploadedFile) {
            return null;
        }

        $filePath = $file->getRealPath();
        if (!$filePath) {
            return null;
        }

        if (function_exists('shell_exec')) {
            $ffprobePath = trim((string) @shell_exec('command -v ffprobe'));

            if ($ffprobePath !== '') {
                $command = $ffprobePath . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($filePath) . ' 2>/dev/null';
                $duration = trim((string) @shell_exec($command));

                if (is_numeric($duration)) {
                    return (float) $duration;
                }
            }
        }

        return null;
    }
}
