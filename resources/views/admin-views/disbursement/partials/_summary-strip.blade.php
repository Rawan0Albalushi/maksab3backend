@php
    $sdb_pending = $payout_summary['pending'] ?? null;
    $sdb_completed = $payout_summary['completed'] ?? null;
    $sdb_canceled = $payout_summary['canceled'] ?? null;
@endphp

<div class="sdb-stats">
    <div class="sdb-stat">
        <span class="sdb-stat__icon"><i class="tio-layers-outlined"></i></span>
        <span class="sdb-stat__text">
            <span class="sdb-stat__value">{{ $lead_value }}</span>
            <span class="sdb-stat__label">{{ $lead_label }}</span>
        </span>
    </div>
    <div class="sdb-stat sdb-stat--pending">
        <span class="sdb-stat__icon"><i class="tio-time"></i></span>
        <span class="sdb-stat__text">
            <span class="sdb-stat__value">{{ \App\CentralLogics\Helpers::format_currency((float) ($sdb_pending?->amount ?? 0)) }}</span>
            <span class="sdb-stat__label">{{ translate('Awaiting release') }} &middot; {{ $payout_count($sdb_pending?->payouts ?? 0) }}</span>
        </span>
    </div>
    <div class="sdb-stat sdb-stat--done">
        <span class="sdb-stat__icon"><i class="tio-checkmark-circle-outlined"></i></span>
        <span class="sdb-stat__text">
            <span class="sdb-stat__value">{{ \App\CentralLogics\Helpers::format_currency((float) ($sdb_completed?->amount ?? 0)) }}</span>
            <span class="sdb-stat__label">{{ $released_label }} &middot; {{ $payout_count($sdb_completed?->payouts ?? 0) }}</span>
        </span>
    </div>
    <div class="sdb-stat sdb-stat--void">
        <span class="sdb-stat__icon"><i class="tio-clear-circle-outlined"></i></span>
        <span class="sdb-stat__text">
            <span class="sdb-stat__value">{{ \App\CentralLogics\Helpers::format_currency((float) ($sdb_canceled?->amount ?? 0)) }}</span>
            <span class="sdb-stat__label">{{ translate('Canceled') }} &middot; {{ $payout_count($sdb_canceled?->payouts ?? 0) }}</span>
        </span>
    </div>
</div>
