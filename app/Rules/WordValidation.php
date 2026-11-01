<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WordValidation implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key=explode(' ', $value);

        foreach ($key as $value) {
            if (strlen($value) > 30) {
                $fail('The :attribute must be a valid word.');
                return;
            }
        }


        if (preg_match('/(.)\1{5,}/', $value)) {
            $fail('The :attribute contains somany repeated characters.');
            return;
        }

        if (preg_match('/[a-zA-Z]{10,}/', $value)) {
            $fail('The :attribute contains invalid patterns.');
            return;
        }
    }
}
