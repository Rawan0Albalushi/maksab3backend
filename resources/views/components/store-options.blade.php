@props([
    'stores' => [],
    'selected' => null,
    'placeholder' => null,
    'includeAll' => false,
    'allLabel' => null,
    'allValue' => 'all',
])


@if (!is_null($placeholder))
    <option value="" disabled {{ in_array((string) $selected, ['', null], true) ? 'selected' : '' }}>{{ $placeholder }}</option>
@endif

@if ($includeAll)
    <option value="{{ $allValue }}" {{ (string) $selected === (string) $allValue ? 'selected' : '' }}>
        {{ $allLabel ?? translate('All') }}
    </option>
@endif

@foreach ($stores as $store)
    <option value="{{ $store->id }}" data-verified="{{ (int) $store->verified_seller }}"
        {{ (string) $selected === (string) $store->id ? 'selected' : '' }}>
        {{ $store->name }}
    </option>
@endforeach
