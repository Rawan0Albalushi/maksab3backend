<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use App\Traits\System\ValidationPresenceTrait;

class PhoneNumber implements ValidationRule
{
    use ValidationPresenceTrait;

    public const PATTERN = '/^([0-9\s\-\+\(\)]*)$/';

    public const MIN_LENGTH = 10;

    public const MAX_LENGTH = 20;

    public function __construct(
        private ?int $min = self::MIN_LENGTH,
        private ?int $max = self::MAX_LENGTH
    ) {}

    public static function rules(
        string $presence = 'required',
        mixed $unique = null,
        ?int $min = self::MIN_LENGTH,
        ?int $max = self::MAX_LENGTH
    ): array {
        $rules = [...self::presenceRules($presence), new self($min, $max)];

        if ($unique !== null) {
            $rules[] = is_string($unique) ? 'unique:'.$unique : $unique;
        }

        return $rules;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $rules = ['regex:'.self::PATTERN];

        if ($this->min !== null) {
            $rules[] = 'min:'.$this->min;
        }

        if ($this->max !== null) {
            $rules[] = 'max:'.$this->max;
        }

        $validator = Validator::make([$attribute => $value], [$attribute => $rules], [
            $attribute.'.regex' => translate('messages.Please enter a valid phone number.'),
            $attribute.'.min' => translate('messages.The phone number is too short.').' '.translate('messages.Minimum characters').': '.$this->min,
            $attribute.'.max' => translate('messages.The phone number is too long.').' '.translate('messages.Character limit').': '.$this->max,
        ]);

        foreach ($validator->errors()->get($attribute) as $message) {
            $fail($message);
        }
    }
}
