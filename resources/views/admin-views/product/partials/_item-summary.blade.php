@php
    $tiles = [
        [
            'group' => 'status',
            'value' => 'active',
            'icon' => 'tio-checkmark-circle-outlined',
            'tone' => 'ok',
            'label' => translate('messages.Active'),
            'count' => $summary['active'],
        ],
        [
            'group' => 'status',
            'value' => 'inactive',
            'icon' => 'tio-invisible',
            'tone' => 'off',
            'label' => translate('messages.Inactive'),
            'count' => $summary['inactive'],
        ],
    ];

    if ($has_stock) {
        $tiles[] = [
            'group' => 'stock',
            'value' => 'out',
            'icon' => 'tio-warning-outlined',
            'tone' => 'danger',
            'label' => translate('Out of stock'),
            'count' => $summary['out_of_stock'],
        ];
    }

    $tiles[] = [
        'group' => 'flag',
        'value' => 'discounted',
        'icon' => 'tio-label-outlined',
        'tone' => 'warn',
        'label' => translate('messages.On discount'),
        'count' => $summary['discounted'],
    ];

    $tiles[] = [
        'group' => 'flag',
        'value' => 'never_ordered',
        'icon' => 'tio-shopping-cart-outlined',
        'tone' => 'info',
        'label' => translate('messages.Never ordered'),
        'count' => $summary['never_ordered'],
    ];
@endphp

<div class="card itm-summary mb-3">
    <div class="itm-figure">
        <span class="itm-figure__value" id="itemCount">{{ number_format($summary['total']) }}</span>
        <span class="itm-figure__label">{{ translate('messages.Items in catalogue') }}</span>
        @if($summary['average_rating'] > 0)
            <span class="itm-figure__meta">
                <i class="tio-star"></i> <b>{{ number_format($summary['average_rating'], 1) }}</b>
                {{ translate('Average rating') }}
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
               title="{{ $active ? translate('messages.Clear this filter') : translate('messages.Show only these items') }}">
                <span class="itm-tile__icon"><i class="{{ $tile['icon'] }}"></i></span>
                <span class="itm-tile__body">
                    <span class="itm-tile__value">{{ number_format($tile['count']) }}</span>
                    <span class="itm-tile__label">{{ $tile['label'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</div>
