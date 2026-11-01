<?php

namespace Modules\ReelsModule\Http\Requests\Vendor\Reel;

class ReelListRequest extends ReelRequest
{
    private const ALLOWED_STATUSES = ['all', 'live', 'upcoming', 'expired', 'deactivated'];

    private const ALLOWED_SORTS = 'latest,oldest,most_viewed,most_liked';

    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:1',
            'status' => 'nullable',
            'sort_by' => 'nullable|string|in:' . self::ALLOWED_SORTS,
            'search' => 'nullable|string|max:255',
        ];
    }

    public function filters(): array
    {
        return array_merge(parent::filters(), [
            'statuses' => $this->statuses(),
            'sort_by' => $this->input('sort_by', 'latest'),
            'search' => $this->input('search'),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('status') && empty($this->statuses())) {
                $validator->errors()->add('status', 'Invalid status filter');
            }
        });
    }

    private function statuses(): array
    {
        $status = $this->input('status');

        if (is_null($status)) {
            return [];
        }

        $values = match (true) {
            is_array($status) => $status,
            is_string($status) => explode(',', $status),
            default => [$status],
        };

        return array_values(array_filter(
            array_map('trim', array_filter($values, 'is_scalar')),
            fn ($value) => in_array(strtolower($value), self::ALLOWED_STATUSES, true)
        ));
    }
}
