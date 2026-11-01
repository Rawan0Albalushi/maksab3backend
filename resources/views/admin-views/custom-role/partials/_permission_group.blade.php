@php($groupId = 'rp-group-'.$group['key'])
@php($groupTotal = array_sum(array_map(fn ($card) => count($card['items']), $group['cards'])))
<div class="rp-group" data-rp-group>
    <div class="rp-group__head">
        <button type="button" class="rp-group__toggle" data-rp-group-toggle aria-expanded="true" aria-controls="{{ $groupId }}-body">
            <span class="rp-group__icon"><i class="{{ $group['icon'] }}"></i></span>
            <h4 class="rp-group__title">{{ $group['label'] }}</h4>
            <span class="rp-count" data-rp-group-count>0/{{ $groupTotal }}</span>
            <i class="tio-chevron-down rp-group__chevron"></i>
        </button>
        <label class="rp-check rp-check--master">
            <input type="checkbox" data-rp-group-all id="{{ $groupId }}-all">
            <span class="rp-check__label">{{ translate('Select all') }}</span>
        </label>
    </div>
    <div class="rp-group__body" id="{{ $groupId }}-body">
        @foreach ($group['cards'] as $card)
            @include('admin-views.custom-role.partials._permission_card', [
                'card' => $card,
                'cardId' => $groupId.'-'.$card['key'],
            ])
        @endforeach
    </div>
</div>
