<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Traits\System\ValidationPresenceTrait;

class StrongPassword implements ValidationRule
{
    use ValidationPresenceTrait;

    public const MIN_LENGTH = 8;

    public function __construct(private bool $uncompromised = false, private bool $strict = true) {}

    public static function rules(string $presence = 'required', array $extra = [], bool $uncompromised = false): array
    {
        return [...self::presenceRules($presence), new self($uncompromised), ...$extra];
    }

    public static function basicRules(string $presence = 'required', array $extra = []): array
    {
        return [...self::presenceRules($presence), new self(strict: false), ...$extra];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $validator = Validator::make(
            [$attribute => $value],
            [$attribute => $this->strength()],
            $this->messages($attribute)
        );

        foreach ($validator->errors()->get($attribute) as $message) {
            $fail($message);
        }

        if ($this->strict && is_string($value) && $this->hasBlankOrControlCharacter($value)) {
            $fail(translate('messages.The password cannot contain white spaces'));
        }
    }

    private function hasBlankOrControlCharacter(string $value): bool
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            return true;
        }

        return (bool) preg_match('/[\s\p{Z}\p{Cc}\p{Cf}]/u', $value);
    }

    private function strength(): Password
    {
        $rule = Password::min(self::MIN_LENGTH);

        if (! $this->strict) {
            return $rule;
        }

        $rule = $rule->mixedCase()->letters()->numbers()->symbols();

        return $this->uncompromised ? $rule->uncompromised() : $rule;
    }

    private function messages(string $attribute): array
    {
        return [
            $attribute.'.min' => translate('messages.The password is too short.').' '.translate('messages.Minimum characters').': '.self::MIN_LENGTH,
            $attribute.'.mixed' => translate('messages.The password must contain both uppercase and lowercase letters'),
            $attribute.'.letters' => translate('messages.The password must contain letters'),
            $attribute.'.numbers' => translate('messages.The password must contain numbers'),
            $attribute.'.symbols' => translate('messages.The password must contain symbols'),
            $attribute.'.uncompromised' => translate('messages.Password is compromised'),
        ];
    }
}
