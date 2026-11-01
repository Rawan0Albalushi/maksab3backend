<?php

namespace App\Http\Requests\Admin;

use App\CentralLogics\Helpers;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base for the Delivery Management setup forms that live in a right-hand off-canvas drawer —
 * Area, Zip Code, Weight and Dimension.
 *
 * WHY THESE NEED THEIR OWN BASE. Those drawers are submitted by jQuery, and the handler reads
 * `data.errors` (an array of `{code, message}`) and `data.success` (a string). Laravel's default
 * behaviour for a failed FormRequest on an AJAX request is a 422 carrying `{message, errors:{}}`
 * — a different shape entirely, which that handler cannot read, so a rejected save reported
 * nothing at all and simply re-enabled the button.
 *
 * `Helpers::error_processor()` produces the shape the repo's other AJAX admin forms already use
 * (VendorController, AccountTransactionController and others), so this is the existing convention
 * rather than a new one.
 *
 * A non-AJAX post — no JavaScript, or a direct submit — still gets Laravel's redirect-back-with-
 * errors, which is what the architecture contract asks of panel forms.
 */
abstract class OffcanvasFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        if (! $this->ajax()) {
            parent::failedValidation($validator);

            return;
        }

        throw new HttpResponseException(
            response()->json(['errors' => Helpers::error_processor($validator)]),
        );
    }
}
