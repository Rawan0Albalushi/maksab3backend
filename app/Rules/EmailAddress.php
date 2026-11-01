<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use App\Traits\System\ValidationPresenceTrait;

class EmailAddress implements ValidationRule
{
    use ValidationPresenceTrait;

    public const MAX_LENGTH = 255;

    public function __construct(private ?int $max = self::MAX_LENGTH) {}

    public static function rules(
        string $presence = 'required',
        mixed $unique = null,
        ?int $max = self::MAX_LENGTH
    ): array {
        $rules = [...self::presenceRules($presence), new self($max)];

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

        $rules = ['email'];

        if ($this->max !== null) {
            $rules[] = 'max:'.$this->max;
        }

        $validator = Validator::make(
            [$attribute => $value],
            [$attribute => $rules],
            [
                $attribute.'.email' => translate('messages.Please enter a valid email address.'),
                $attribute.'.max' => translate('messages.The email address is too long.').' '.translate('messages.Character limit').': '.$this->max,
            ]
        );

        foreach ($validator->errors()->get($attribute) as $message) {
            $fail($message);
        }
    }
}
