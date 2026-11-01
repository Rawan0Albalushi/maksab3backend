@php
    $tiles = [
        [
            'group' => 'status',
            'value' => '1',
            'icon' => 'tio-checkmark-circle-outlined',
            'tone' => 'ok',
            'label' => translate('messages.Active'),
            'count' => $summary['active'],
        ],
        [
            'group' => 'status',
            'value' => '0',
            'icon' => 'tio-invisible',
            'tone' => 'off',
            'label' => translate('messages.Inactive'),
            'count' => $summary['inactive'],
        ],
        [
            'group' => 'priority',
            'value' => '2',
            'icon' => 'tio-arrow-upward',
            'tone' => 'info',
            'label' => translate('messages.High priority'),
            'count' => $summary['high_priority'],
        ],
        [
            'group' => 'usage',
            'value' => 'empty',
            'icon' => 'tio-folder-outlined',
            'tone' => 'warn',
            'label' => translate('messages.Without items'),
            'count' => $summary['empty'],
        ],
    ];
@endphp

<div class="card stc-summary mb-3">
    <div class="stc-figure">
        <span class="stc-figure__value" id="itemCount">{{ number_format($summary['total']) }}</span>
        <span class="stc-figure__label">{{ \App\CentralLogics\Helpers::moduleStoreLabel() . ' ' . translate('Categories') }}</span>
        @if($summary['stores'] > 0)
            <span class="stc-figure__meta">
                <i class="tio-shop-outlined"></i>
                {{ translate('messages.Stores') }}: {{ number_format($summary['stores']) }}
            </span>
        @endif
    </div>

    <div class="stc-tiles">
        @foreach($tiles as $tile)
            @php($active = (string) ($filters[$tile['group']] ?? '') === $tile['value'])
            <a class="stc-tile stc-tile--{{ $tile['tone'] }}{{ $active ? ' is-active' : '' }}"
               @if($active) aria-current="true" @endif
               href="{{ request()->fullUrlWithQuery([$tile['group'] => $active ? null : $tile['value'], 'page' => null]) }}"
               title="{{ $active ? translate('messages.Clear this filter') : translate('messages.Show only these categories') }}">
                <span class="stc-tile__icon"><i class="{{ $tile['icon'] }}"></i></span>
                <span class="stc-tile__body">
                    <span class="stc-tile__value">{{ number_format($tile['count']) }}</span>
                    <span class="stc-tile__label">{{ $tile['label'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</div>
