@php
    $wdr_pending = $summary[0] ?? null;
    $wdr_approved = $summary[1] ?? null;
    $wdr_denied = $summary[2] ?? null;
    $wdr_pending_count = $wdr_pending->requests ?? 0;
@endphp

<div class="wdr-stats">
    <div class="wdr-stat">
        <span class="wdr-stat__icon"><i class="tio-receipt-outlined"></i></span>
        <span class="wdr-stat__text">
            <span class="wdr-stat__value">{{ $summary->sum('requests') }}</span>
            <span class="wdr-stat__label">{{ $lead_label }}</span>
        </span>
    </div>
    <div class="wdr-stat wdr-stat--pending {{ $wdr_pending_count > 0 ? 'is-active' : '' }}">
        <span class="wdr-stat__icon"><i class="tio-time"></i></span>
        <span class="wdr-stat__text">
            <span class="wdr-stat__value">{{ \App\CentralLogics\Helpers::format_currency((float) ($wdr_pending->amount ?? 0)) }}</span>
            <span class="wdr-stat__label">{{ translate('Waiting on you') }} &middot; {{ $request_count($wdr_pending_count) }}</span>
        </span>
    </div>
    <div class="wdr-stat wdr-stat--ok">
        <span class="wdr-stat__icon"><i class="tio-checkmark-circle-outlined"></i></span>
        <span class="wdr-stat__text">
            <span class="wdr-stat__value">{{ \App\CentralLogics\Helpers::format_currency((float) ($wdr_approved->amount ?? 0)) }}</span>
            <span class="wdr-stat__label">{{ translate('Approved') }} &middot; {{ $request_count($wdr_approved->requests ?? 0) }}</span>
        </span>
    </div>
    <div class="wdr-stat wdr-stat--void">
        <span class="wdr-stat__icon"><i class="tio-clear-circle-outlined"></i></span>
        <span class="wdr-stat__text">
            <span class="wdr-stat__value">{{ \App\CentralLogics\Helpers::format_currency((float) ($wdr_denied->amount ?? 0)) }}</span>
            <span class="wdr-stat__label">{{ translate('Denied') }} &middot; {{ $request_count($wdr_denied->requests ?? 0) }}</span>
        </span>
    </div>
</div>
