<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;

/**
 * The store's answer to an invitation the admin sent. Shared by BOGO and Happy Hour -- the two
 * answer the same question with the same two words, and a second identical class would only
 * invite them to drift.
 *
 * The panel puts the decision in the URL; the app puts it in the body. Same rule either way.
 */
class PromotionRespondRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => 'required|string|in:approved,rejected',
            'rejection_reason' => 'nullable|string|max:255',
        ];
    }

    public function decision(): string
    {
        return (string) $this->input('status');
    }

    public function rejectionReason(): ?string
    {
        return $this->input('rejection_reason');
    }
}
