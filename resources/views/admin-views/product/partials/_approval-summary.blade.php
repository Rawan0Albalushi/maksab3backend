@php
    $tiles = [
        [
            'group' => 'status',
            'value' => 'pending',
            'icon' => 'tio-hourglass-outlined',
            'tone' => 'warn',
            'label' => translate('messages.Awaiting decision'),
            'count' => $summary['pending'],
        ],
        [
            'group' => 'status',
            'value' => 'rejected',
            'icon' => 'tio-remove-circle-outlined',
            'tone' => 'danger',
            'label' => translate('messages.Denied'),
            'count' => $summary['rejected'],
        ],
        [
            'group' => 'kind',
            'value' => 'new',
            'icon' => 'tio-add-circle',
            'tone' => 'info',
            'label' => translate('messages.Brand new items'),
            'count' => $summary['new'],
        ],
        [
            'group' => 'kind',
            'value' => 'update',
            'icon' => 'tio-edit',
            'tone' => 'ok',
            'label' => translate('messages.Edits to live items'),
            'count' => $summary['update'],
        ],
    ];
@endphp

<div class="card itm-summary mb-3">
    <div class="itm-figure">
        <span class="itm-figure__value" id="itemCount">{{ number_format($summary['total']) }}</span>
        <span class="itm-figure__label">{{ translate('messages.Requests in queue') }}</span>
        @if($summary['oldest_pending_days'])
            <span class="itm-figure__meta itm-figure__meta--warn">
                <i class="tio-time"></i>
                {{ translate('messages.Longest wait') }}: {{ \Carbon\CarbonInterval::days($summary['oldest_pending_days'])->forHumans(['skip' => 'week']) }}
            </span>
        @endif
    </div>

    <div class="itm-tiles">
        @foreach($tiles as $tile)
            @php($active = in_array($tile['value'], $filters[$tile['group']]))
            @php($group_query = $active
                ? array_values(array_diff($filters[$tile['group']], [$tile['value']]))
                : array_merge($filters[$tile['group']], [$tile['value']]))
            <a class="itm-tile itm-tile--{{ $tile['tone'] }}{{ $active ? ' is-active' : '' }}"
               @if($active) aria-current="true" @endif
               href="{{ request()->fullUrlWithQuery([$tile['group'] => $group_query ?: null, 'page' => null]) }}"
               title="{{ $active ? translate('messages.Clear this filter') : translate('messages.Show only these requests') }}">
                <span class="itm-tile__icon"><i class="{{ $tile['icon'] }}"></i></span>
                <span class="itm-tile__body">
                    <span class="itm-tile__value">{{ number_format($tile['count']) }}</span>
                    <span class="itm-tile__label">{{ $tile['label'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</div>
