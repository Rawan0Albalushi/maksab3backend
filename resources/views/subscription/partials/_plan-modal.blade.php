@php($spm_commission_enabled = \App\CentralLogics\Helpers::commission_check())
@php($spm_on_commission = $store->store_business_model == 'commission')
@php($spm_is_rental = $store?->module?->module_type == 'rental' && addon_published_status('Rental'))
@php($spm_is_service = $isServiceModule ?? false)
@php($spm_rate = $store->comission ?? $admin_commission)
@php($spm_count = $packages->count() + ($spm_commission_enabled ? 1 : 0))

<div class="modal fade" id="plan-modal" tabindex="-1" aria-labelledby="plan-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content spm">
            <div class="modal-header spm-head">
                <div class="spm-head__text">
                    <h2 class="spm-head__title" id="plan-modal-title">{{ translate('Change subscription plan') }}</h2>
                    <p class="spm-head__desc">{{ translate('Choose the plan that fits your business. Renew your current plan or switch to another one.') }}</p>
                </div>
                <button type="button" class="close spm-head__close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body spm-body">
                <div class="spm-plans" style="--spm-count: {{ max($spm_count, 1) }}">
                    @if ($spm_commission_enabled)
                        <article class="spm-plan {{ $spm_on_commission ? 'is-current' : '' }}">
                            @if ($spm_on_commission)
                                <span class="spm-plan__badge"><i class="tio-checkmark-circle"></i> {{ translate('Current plan') }}</span>
                            @endif
                            <h3 class="spm-plan__name">{{ translate('Commission base') }}</h3>
                            <div class="spm-plan__price">
                                <span class="spm-plan__amount">{{ $spm_rate }}%</span>
                                <span class="spm-plan__period">
                                    {{ $orderOrTrip == 'trip' ? translate('Commission per trip') : ($orderOrTrip == 'booking' ? translate('Commission per booking') : translate('Commission per order')) }}
                                </span>
                            </div>
                            <p class="spm-plan__desc">
                                @if ($orderOrTrip == 'trip')
                                    {{ translate('Pay a commission on each trip instead of a fixed fee, with every feature included.') }}
                                @elseif ($orderOrTrip == 'booking')
                                    {{ translate('Pay a commission on each booking instead of a fixed fee, with every feature included.') }}
                                @else
                                    {{ translate('Pay a commission on each order instead of a fixed fee, with every feature included.') }}
                                @endif
                            </p>
                            <ul class="spm-plan__features">
                                <li class="spm-feat"><i class="tio-checkmark-circle" aria-hidden="true"></i> <span>{{ translate('All features included') }}</span></li>
                            </ul>
                            <div class="spm-plan__foot">
                                @if ($spm_on_commission)
                                    <button type="button" class="btn btn--reset" disabled><i class="tio-checkmark-circle-outlined"></i> {{ translate('Current plan') }}</button>
                                @else
                                    @php($spm_cash_backs = \App\CentralLogics\Helpers::calculateSubscriptionRefundAmount(store: $store, return_data: true))
                                    <button type="button" data-url="{{ route($routePrefix.'.switchToCommission', $store->id) }}"
                                        data-message="{{ translate('You want to migrate to commission.') }} {{ data_get($spm_cash_backs, 'back_amount') > 0 ? translate('You will get').' '.\App\CentralLogics\Helpers::format_currency(data_get($spm_cash_backs, 'back_amount')).' '.translate('To your wallet for remaining').' '.data_get($spm_cash_backs, 'days').' '.translate('messages.Days subscription plan') : '' }}"
                                        class="btn btn-outline-primary shift_to_commission"><i class="tio-sync"></i> {{ translate('Shift in this plan') }}</button>
                                @endif
                            </div>
                        </article>
                    @endif

                    @foreach ($packages as $package)
                        @php($spm_is_current = !$spm_on_commission && $store?->store_sub_update_application?->package_id == $package->id)
                        @php($spm_features = array_values(array_filter([
                            ($showPos ?? true) ? ['label' => translate('messages.POS'), 'on' => (bool) $package->pos] : null,
                            ['label' => translate('Mobile app'), 'on' => (bool) $package->mobile_app],
                            ['label' => translate('messages.Chatting options'), 'on' => (bool) $package->chat],
                            ['label' => translate('Review section'), 'on' => (bool) $package->review],
                            ($showSelfDelivery ?? true) ? ['label' => translate('messages.Self delivery'), 'on' => (bool) $package->self_delivery] : null,
                        ])))
                        <article class="spm-plan {{ $spm_is_current ? 'is-current' : '' }}">
                            @if ($spm_is_current)
                                <span class="spm-plan__badge"><i class="tio-checkmark-circle"></i> {{ translate('Current plan') }}</span>
                            @endif
                            <h3 class="spm-plan__name">{{ $package->package_name }}</h3>
                            <div class="spm-plan__price">
                                <span class="spm-plan__amount">{{ \App\CentralLogics\Helpers::format_currency($package->price) }}</span>
                                <span class="spm-plan__period">/ {{ $package->validity }} {{ translate('messages.days') }}</span>
                            </div>
                            <ul class="spm-plan__features">
                                <li class="spm-feat">
                                    <i class="tio-checkmark-circle" aria-hidden="true"></i>
                                    @if ($package->max_order == 'unlimited')
                                        <span>{{ $spm_is_rental ? translate('Unlimited trips') : ($spm_is_service ? translate('Unlimited bookings') : translate('Unlimited orders')) }}</span>
                                    @else
                                        <span>{{ $package->max_order }} {{ $spm_is_rental ? translate('messages.Trips') : ($spm_is_service ? translate('messages.Bookings') : translate('messages.Orders')) }}</span>
                                    @endif
                                </li>
                                <li class="spm-feat">
                                    <i class="tio-checkmark-circle" aria-hidden="true"></i>
                                    @if ($package->max_product == 'unlimited')
                                        <span>{{ $spm_is_service ? translate('Unlimited service uploads') : translate('messages.Unlimited uploads') }}</span>
                                    @else
                                        <span>{{ $package->max_product }} {{ $spm_is_service ? translate('Service uploads') : translate('messages.uploads') }}</span>
                                    @endif
                                </li>
                                @foreach ($spm_features as $spm_feature)
                                    <li class="spm-feat {{ $spm_feature['on'] ? '' : 'is-off' }}">
                                        <i class="{{ $spm_feature['on'] ? 'tio-checkmark-circle' : 'tio-clear-circle' }}" aria-hidden="true"></i>
                                        <span>{{ $spm_feature['label'] }}</span>
                                        <span class="sr-only">{{ $spm_feature['on'] ? translate('Included') : translate('Not included') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="spm-plan__foot">
                                <button type="button" data-id="{{ $package->id }}" data-url="{{ route($routePrefix.'.packageView', [$package->id, $store->id]) }}"
                                    class="btn {{ $spm_is_current ? 'btn--primary' : 'btn-outline-primary' }} package_detail">
                                    <i class="{{ $spm_is_current ? 'tio-autorenew' : 'tio-sync' }}"></i>
                                    {{ $spm_is_current ? translate('messages.Renew') : translate('messages.Shift in this plan') }}
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
