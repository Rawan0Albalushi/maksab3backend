<?php

namespace App\Http\Requests\Customer\Cart;

use App\Http\Requests\BaseRequest;

/**
 * The three bundle verbs on the cart: add, re-quantify, remove.
 *
 * One class because they are one operation on one thing at three quantities, and the owner
 * resolution is identical. Which fields are required is decided by the verb rather than by three
 * near-identical classes that would drift.
 *
 * A bundle is addressed by `bundle_id` (the enrolment -- this store's terms for the offer) when
 * it is added, and by `bogo_group_id` (this copy of it, in this cart) once it is in.
 */
class CartBogoRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'guest_id' => $this->user ? 'nullable' : 'required',
            'bundle_id' => $this->isAdding() ? 'required' : 'nullable',
            'bogo_group_id' => $this->isAdding() ? 'nullable' : 'required|string',
            // Nullable on every verb: an add defaults to one bundle, and a remove takes the group
            // whole regardless of how many copies it holds.
            'quantity' => 'nullable|integer|min:1',
            'contact_person_number' => 'nullable|string|max:30',
        ];
    }

    /** An add carries the enrolment id; the other two carry the group already in the cart. */
    public function isAdding(): bool
    {
        return $this->filled('bundle_id') && ! $this->filled('bogo_group_id');
    }

    public function payload(): array
    {
        return [
            'bundle_id' => $this->input('bundle_id'),
            'bogo_group_id' => $this->input('bogo_group_id'),
            'quantity' => (int) ($this->input('quantity') ?: 1),
            // A guest's redemption history is keyed by phone, since there is no account to hang
            // it on. Optional: without one the per-customer cap simply cannot be enforced.
            'phone' => $this->input('contact_person_number'),
        ];
    }
}
