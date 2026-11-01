@php($sbd_sub = $store?->store_sub_update_application)
@php($sbd_expiry = $sbd_sub?->expiry_date ? \Carbon\Carbon::parse($sbd_sub->expiry_date)->startOfDay() : null)
@php($sbd_days_left = $sbd_expiry ? (int) now()->startOfDay()->diffInDays($sbd_expiry, false) : null)
@php($sbd_validity = (int) ($sbd_sub?->last_transcations?->validity ?? $sbd_sub?->validity))
@php($sbd_uses = (int) $sbd_sub?->total_package_renewed + 1)
@php($sbd_price = (float) $sbd_sub?->package?->price)
@php($sbd_warning_days = (int) \App\CentralLogics\Helpers::get_business_settings('subscription_deadline_warning_days', false))
@php($sbd_expired = $sbd_sub?->status != 1 || ($sbd_days_left !== null && $sbd_days_left < 0))
@php($sbd_expiry_tone = $sbd_expired ? 'is-danger' : ($sbd_days_left !== null && $sbd_days_left <= $sbd_warning_days ? 'is-warn' : ''))
@php($sbd_elapsed = ($sbd_validity > 0 && $sbd_days_left !== null) ? min(100, max(0, (int) round(($sbd_validity - $sbd_days_left) / $sbd_validity * 100))) : 100)

<div class="sbd-stats">
    <div class="sbd-stat {{ $sbd_expiry_tone }}">
        <span class="sbd-stat__icon"><i class="tio-calendar-month"></i></span>
        <div class="sbd-stat__text">
            <span class="sbd-stat__label">{{ translate('Expire date') }}</span>
            <p class="sbd-stat__value">{{ $sbd_expiry ? \App\CentralLogics\Helpers::date_format($sbd_expiry) : '—' }}</p>
            @if ($sbd_days_left !== null)
                <span class="sbd-stat__meta">
                    @if ($sbd_days_left > 1)
                        {{ translate('Expires') }} {{ $sbd_expiry->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                    @elseif ($sbd_days_left === 1)
                        {{ translate('Expires tomorrow') }}
                    @elseif ($sbd_days_left === 0)
                        {{ translate('Expires today') }}
                    @elseif ($sbd_days_left === -1)
                        {{ translate('Expired yesterday') }}
                    @else
                        {{ translate('Expired') }} {{ $sbd_expiry->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                    @endif
                </span>
                <span class="sbd-bar" aria-hidden="true"><span style="--sbd-fill: {{ $sbd_elapsed }}%"></span></span>
            @endif
        </div>
    </div>

    <div class="sbd-stat">
        <span class="sbd-stat__icon"><i class="tio-receipt-outlined"></i></span>
        <div class="sbd-stat__text">
            <span class="sbd-stat__label">{{ translate('Total bill') }}</span>
            <p class="sbd-stat__value">{{ \App\CentralLogics\Helpers::format_currency($sbd_price * $sbd_uses) }}</p>
            <span class="sbd-stat__meta">{{ \App\CentralLogics\Helpers::format_currency($sbd_price) }} × {{ $sbd_uses }}</span>
        </div>
    </div>

    <div class="sbd-stat">
        <span class="sbd-stat__icon"><i class="tio-repeat"></i></span>
        <div class="sbd-stat__text">
            <span class="sbd-stat__label">{{ translate('Number of uses') }}</span>
            <p class="sbd-stat__value">{{ $sbd_uses }}</p>
            @if ($sbd_validity > 0)
                <span class="sbd-stat__meta">{{ translate('Validity') }}: {{ \Carbon\CarbonInterval::days($sbd_validity)->forHumans(['skip' => ['week']]) }}</span>
            @endif
        </div>
    </div>
</div>
