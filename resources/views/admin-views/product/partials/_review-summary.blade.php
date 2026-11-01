@php
    $tiles = [
        [
            'group' => 'visibility',
            'value' => 'visible',
            'icon' => 'tio-visible-outlined',
            'tone' => 'ok',
            'label' => translate('messages.Visible'),
            'count' => $summary['visible'],
        ],
        [
            'group' => 'visibility',
            'value' => 'hidden',
            'icon' => 'tio-invisible',
            'tone' => 'off',
            'label' => translate('messages.Hidden'),
            'count' => $summary['hidden'],
        ],
        [
            'group' => 'reply',
            'value' => 'replied',
            'icon' => 'tio-comment-outlined',
            'tone' => 'info',
            'label' => translate('messages.Replied'),
            'count' => $summary['replied'],
        ],
        [
            'group' => 'reply',
            'value' => 'awaiting',
            'icon' => 'tio-message-outlined',
            'tone' => 'warn',
            'label' => translate('messages.Awaiting reply'),
            'count' => $summary['awaiting'],
        ],
    ];
@endphp

<div class="card rvw-summary mb-3">
    <div class="rvw-score">
        <div class="rvw-score__figure">
            <span class="rvw-score__value">{{ number_format($summary['average'], 1) }}</span>
            <span class="rvw-score__scale">/5</span>
        </div>
        @include('admin-views.product.partials._rating-stars', ['rating' => $summary['average'], 'stars_size' => 'lg'])
        <span class="rvw-score__meta">
            <span class="rvw-score__total" id="itemCount">{{ number_format($summary['total']) }}</span>
            {{ translate('messages.Reviews') }}
        </span>
    </div>

    <ul class="rvw-bars">
        @foreach($summary['breakdown'] as $star => $bar)
            @php($active = in_array($star, $filters['rating']))
            @php($rating_query = $active ? array_values(array_diff($filters['rating'], [$star])) : array_merge($filters['rating'], [$star]))
            <li>
                <a class="rvw-bar{{ $active ? ' is-active' : '' }}" @if($active) aria-current="true" @endif
                   href="{{ request()->fullUrlWithQuery(['rating' => $rating_query ?: null, 'page' => null]) }}"
                   title="{{ $active ? translate('messages.Clear this rating filter') : translate('messages.Show only these reviews') }}">
                    <span class="rvw-bar__label">{{ $star }} <i class="tio-star"></i></span>
                    <span class="rvw-bar__track">
                        <span class="rvw-bar__fill" style="inline-size: {{ $bar['percent'] }}%"></span>
                    </span>
                    <span class="rvw-bar__count">{{ number_format($bar['count']) }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="rvw-tiles">
        @foreach($tiles as $tile)
            @php($active = in_array($tile['value'], $filters[$tile['group']]))
            @php($tile_query = $active ? null : [$tile['value']])
            <a class="rvw-tile rvw-tile--{{ $tile['tone'] }}{{ $active ? ' is-active' : '' }}"
               @if($active) aria-current="true" @endif
               href="{{ request()->fullUrlWithQuery([$tile['group'] => $tile_query, 'page' => null]) }}">
                <span class="rvw-tile__icon"><i class="{{ $tile['icon'] }}"></i></span>
                <span class="rvw-tile__body">
                    <span class="rvw-tile__value">{{ number_format($tile['count']) }}</span>
                    <span class="rvw-tile__label">{{ $tile['label'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</div>
