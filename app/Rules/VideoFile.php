<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use App\Traits\System\ValidationPresenceTrait;

class VideoFile implements ValidationRule
{
    use ValidationPresenceTrait;

    public bool $implicit = true;

    public function __construct(private bool $required = false, private ?int $maxKilobytes = null) {}

    public static function rules(mixed $presence = 'nullable', ?int $maxKilobytes = null): array
    {
        if ($presence === 'required' || $presence === 'nullable') {
            return [new self($presence === 'required', $maxKilobytes)];
        }

        return [...self::presenceRules($presence), new self(false, $maxKilobytes)];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            if ($this->required) {
                $fail(translate('messages.Video is required'));
            }

            return;
        }

        $max = $this->maxKilobytes ?? PRODUCT_VIDEO_MAX_FILE_SIZE * 1024;

        $validator = Validator::make(
            [$attribute => $value],
            [$attribute => ['file', 'mimes:'.VIDEO_FORMAT, 'max:'.$max]],
            [
                $attribute.'.file' => translate('messages.Video must be a valid video file'),
                $attribute.'.mimes' => translate('messages.Video must be in format').': '.VIDEO_FORMAT,
                $attribute.'.max' => translate('messages.Video must be less than').' '.round($max / 1024, 2).'mb',
            ]
        );

        foreach ($validator->errors()->get($attribute) as $message) {
            $fail($message);
        }
    }
}
