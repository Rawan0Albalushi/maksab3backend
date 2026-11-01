@php($sbd_sub = $store?->store_sub_update_application)
@php($sbd_is_rental = $store?->module?->module_type == 'rental' && addon_published_status('Rental'))
@php($sbd_is_service = $isServiceModule ?? false)
@php($sbd_expiry = $sbd_sub?->expiry_date ? \Carbon\Carbon::parse($sbd_sub->expiry_date)->startOfDay() : null)
@php($sbd_days_left = $sbd_expiry ? max(0, (int) now()->startOfDay()->diffInDays($sbd_expiry, false)) : 0)
@php($sbd_is_active = $sbd_sub?->status == 1)
@php($sbd_price = $sbd_sub?->last_transcations?->price ?? $sbd_sub?->price)
@php($sbd_validity = $sbd_sub?->last_transcations?->validity ?? $sbd_sub?->validity)
@php($sbd_order_total = (int) $sbd_sub?->package?->max_order)
@php($sbd_order_left = (int) $sbd_sub?->max_order)
@php($sbd_upload_total = (int) $sbd_sub?->max_product)
@php($sbd_upload_left = max($sbd_upload_total - (int) $store?->items_count, 0))
@php($sbd_meters = [
    [
        'label' => $sbd_is_rental ? translate('messages.Trips') : ($sbd_is_service ? translate('messages.Bookings') : translate('messages.Orders')),
        'unlimited' => $sbd_sub?->max_order == 'unlimited',
        'total' => max($sbd_order_total, $sbd_order_left),
        'left' => $sbd_order_left,
    ],
    [
        'label' => $sbd_is_rental ? translate('messages.Upload') : ($sbd_is_service ? translate('Service upload') : translate('Product upload')),
        'unlimited' => $sbd_sub?->max_product == 'unlimited',
        'total' => $sbd_upload_total,
        'left' => $sbd_upload_left,
    ],
])
@php($sbd_features = array_values(array_filter([
    ($showPos ?? true) ? ['label' => translate('messages.POS'), 'on' => $sbd_sub?->pos == 1] : null,
    ['label' => translate('Mobile app'), 'on' => $sbd_sub?->mobile_app == 1],
    ($showSelfDelivery ?? true) ? ['label' => translate('messages.Self delivery'), 'on' => $sbd_sub?->self_delivery == 1] : null,
    ['label' => translate('messages.review'), 'on' => $sbd_sub?->review == 1],
    ['label' => translate('messages.Chat'), 'on' => $sbd_sub?->chat == 1],
])))

<div class="tps-card sbd-plan {{ $sbd_is_active ? '' : 'is-danger' }}">
    <div class="tps-card__head">
        <span class="tps-card__brand"><i class="tio-crown"></i></span>
        <div class="tps-card__titles">
            <h2 class="tps-card__title sbd-plan__name">
                <span>{{ $sbd_sub?->package?->package_name }}</span>
                @if ($showStatusBadge ?? false)
                    @if ($store?->status == 0 && $store?->vendor?->status == 0)
                        <span class="tps-pill tps-pill--info">{{ translate('Approval pending') }}</span>
                    @elseif ($sbd_is_active)
                        <span class="tps-pill tps-pill--on">{{ translate('Active') }}</span>
                    @else
                        <span class="tps-pill tps-pill--danger">{{ translate('Expired') }}</span>
                    @endif
                    @if ($sbd_sub?->is_canceled)
                        <span class="tps-pill tps-pill--warn">{{ translate('Canceled') }}</span>
                    @elseif ($sbd_sub?->is_trial)
                        <span class="tps-pill tps-pill--warn">{{ translate('Trial') }}</span>
                    @endif
                @endif
            </h2>
            @if ($sbd_sub?->package?->text)
                <p class="tps-card__subtitle">{{ $sbd_sub->package->text }}</p>
            @endif
        </div>
        <div class="tps-card__aside sbd-plan__price">
            <span class="sbd-plan__amount">{{ \App\CentralLogics\Helpers::format_currency($sbd_price) }}</span>
            <span class="sbd-plan__period">/ {{ $sbd_validity }} {{ translate('messages.days') }}</span>
        </div>
    </div>

    <div class="tps-card__body sbd-plan__body">
        <section>
            <h3 class="sbd-section__title">{{ translate('Usage') }}</h3>
            <div class="sbd-meters">
                @foreach ($sbd_meters as $sbd_meter)
                    @php($sbd_used_pct = $sbd_meter['total'] > 0 ? (int) round(($sbd_meter['total'] - $sbd_meter['left']) / $sbd_meter['total'] * 100) : 0)
                    <div class="sbd-meter {{ $sbd_meter['unlimited'] ? 'is-unlimited' : ($sbd_meter['left'] <= 0 ? 'is-danger' : ($sbd_used_pct >= 80 ? 'is-warn' : '')) }}">
                        <div class="sbd-meter__top">
                            <span class="sbd-meter__label">{{ $sbd_meter['label'] }}</span>
                            <span class="sbd-meter__value">
                                {{ $sbd_meter['unlimited'] ? translate('Unlimited') : translate('Left') . ': ' . $sbd_meter['left'] . '/' . $sbd_meter['total'] }}
                            </span>
                        </div>
                        <span class="sbd-bar" role="progressbar" aria-label="{{ $sbd_meter['label'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $sbd_meter['unlimited'] ? 0 : $sbd_used_pct }}">
                            <span style="--sbd-fill: {{ $sbd_meter['unlimited'] ? 100 : $sbd_used_pct }}%"></span>
                        </span>
                    </div>
                @endforeach
            </div>
        </section>

        <section>
            <h3 class="sbd-section__title">{{ translate('Plan features') }}</h3>
            <ul class="sbd-features">
                @foreach ($sbd_features as $sbd_feature)
                    <li class="sbd-feature {{ $sbd_feature['on'] ? '' : 'is-off' }}">
                        <i class="{{ $sbd_feature['on'] ? 'tio-checkmark-circle' : 'tio-clear-circle' }}" aria-hidden="true"></i>
                        <span>{{ $sbd_feature['label'] }}</span>
                        <span class="sr-only">{{ $sbd_feature['on'] ? translate('Included') : translate('Not included') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <div class="tps-card__foot">
        @if (!$sbd_is_active)
            <span class="tps-foot-note sbd-note is-danger"><i class="tio-info"></i> {{ translate('This plan has expired. Renew it or choose another plan to keep the business running.') }}</span>
        @elseif ($sbd_sub?->is_canceled == 1)
            <span class="tps-foot-note sbd-note is-warn"><i class="tio-info"></i> {{ translate('This plan is canceled and stays active until it expires.') }}</span>
        @endif
        @if ($sbd_sub?->is_canceled == 0 && $sbd_is_active)
            <button type="button" data-url="{{ route($routePrefix.'.cancelSubscription', $store?->id) }}"
                data-message="{{ translate('If you cancel the subscription, after') }} {{ $sbd_days_left }} {{ translate('Days the vendor will no longer be able to run the business before subscribe a new plan.') }}"
                class="btn btn-outline-danger status_change_alert"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel subscription') }}</button>
        @endif
        <button type="button" data-toggle="modal" data-target="#plan-modal" class="btn btn--primary"><i class="tio-sync"></i> {{ translate('Change / renew subscription plan') }}</button>
    </div>
</div>
