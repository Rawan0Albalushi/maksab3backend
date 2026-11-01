@extends('layouts.admin.app')

@section('title', translate('Delivery rule') . ' #' . $rule->id)

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/surge-price.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/surge-price.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/delivery-rule.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/delivery-rule.css')) }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h1 class="page-header-title mb-0">
                        {{ translate('Delivery rule') }} #{{ $rule->id }}</h1>
                    <p class="page-header-desc mb-0">{{ translate('What this rule charges, the modules it covers and whether it is the zone active rule.') }}</p>
                </div>

                {{-- align-items: stretch, so the three controls share one height rather than each
                     keeping its own — the status box carries its own padding and would otherwise
                     stand taller than the two buttons beside it. --}}
                <div class="rule-detail__actions">
                    {{-- A zone must always keep one active rule, so the active one
                         offers no delete. --}}
                    @if (!$rule->status)
                        <a href="javascript:" class="btn btn--danger rule-delete-btn" data-id="rule-{{ $rule->id }}">
                            <i class="tio-delete-outlined"></i> {{ translate('messages.Delete') }}
                        </a>
                    @endif

                    <span class="rule-status-pill">
                        {{ translate('messages.Status') }}
                        <label class="toggle-switch toggle-switch-sm mb-0 ml-2">
                            <input type="checkbox" class="toggle-switch-input rule-status-toggle"
                                {{ $rule->status ? 'checked' : '' }} data-id="{{ $rule->id }}"
                                data-status="{{ $rule->status }}" data-zone="{{ $rule->zone_id }}"
                                data-name="{{ $rule->name }}"
                                @if (!empty($lockedModuleNames))
                                    data-rule-locked="{{ implode(', ', $lockedModuleNames) }}"
                                @endif>
                            <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                        </label>
                    </span>

                    <a href="{{ route('admin.business-settings.zone.delivery-rule.edit', [$rule->id]) }}" class="btn btn--primary">
                        <i class="tio-edit"></i> {{ translate('messages.Edit') }}
                    </a>
                </div>
            </div>

            {{-- Outside the button row on purpose: a form is a block-level flex child and would
                 take a gap of its own, widening the space between Delete and Status. --}}
            @if (!$rule->status)
                <form action="{{ route('admin.business-settings.zone.delivery-rule.delete', [$rule->id]) }}" method="post"
                    id="rule-{{ $rule->id }}" class="d-none">@csrf @method('delete')</form>
            @endif
        </div>

        <div class="card mb-20">
            <div class="card-body">
                <h4 class="rule-detail__name">{{ $rule->name }}</h4>

                {{-- Zone and modules sit in their own inset panel, above a hairline; the meta row
                     runs beneath it. Both come from the design. --}}
                <div class="rule-detail__panel">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <span class="rule-detail__label">
                                <i class="tio-city"></i> {{ translate('messages.Delivery Zone') }}
                            </span>
                            <div class="rule-detail__value">{{ $zoneName }}</div>
                        </div>
                        <div class="col-md-7">
                            <span class="rule-detail__label">
                                <i class="tio-package"></i> {{ translate('messages.Module') }}
                            </span>
                            <div class="rule-detail__chips">
                                @forelse ($moduleNames as $moduleName)
                                    <span class="rule-detail__chip">{{ $moduleName }}</span>
                                @empty
                                    <span class="rule-detail__value">{{ translate('messages.N/A') }}</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rule-detail__meta">
                    @foreach ($metaItems as $item)
                        <div>
                            <span class="rule-detail__label">{{ $item['label'] }}</span>
                            <div class="rule-detail__value">{{ $item['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- The parcel tiers are TABS beside the delivery method, not cards stacked under it.
             The bar itself belongs to a parcel rule. Within it, each TIER's tab appears only when
             that tier has priced rows, so a tab never leads to an empty table. A rule that
             connects no parcel module has no bar at all, per the design. --}}
        @if ($showsParcelTiers)
            <div class="rule-tabs">
                <a href="javascript:" class="rule-tab is-active" data-tab="method">{{ translate('Delivery method') }}</a>
                @if ($showsWeightTab)
                    <a href="javascript:" class="rule-tab" data-tab="weight">{{ translate('Weight rule') }}</a>
                @endif
                @if ($showsDimensionTab)
                    <a href="javascript:" class="rule-tab" data-tab="dimension">{{ translate('Dimension rules') }}</a>
                @endif
            </div>
        @endif

        <div class="card rule-tab-pane" data-pane="method">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <h5 class="surge-section__title">
                            @switch($rule->pricing_method)
                                @case(\App\Models\DeliveryRule::METHOD_AREA)
                                    {{ translate('Area-wise delivery charges') }} @break
                                @case(\App\Models\DeliveryRule::METHOD_ZIP)
                                    {{ translate('Zip-code-wise delivery charges') }} @break
                                @case(\App\Models\DeliveryRule::METHOD_DISTANCE)
                                    {{ translate('Distance-based delivery charges') }} @break
                                @default
                                    {{ translate('Fixed delivery charge') }}
                            @endswitch
                        </h5>
                        <p class="surge-section__subtitle">
                            {{ $rule->pricing_method === \App\Models\DeliveryRule::METHOD_DISTANCE
                                ? translate('messages.Base delivery charges configured according to the delivery distance.')
                                : translate('messages.Base delivery charges configured for this rule.') }}
                        </p>
                        <div class="rule-hint">
                            <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                            {{-- Both design mocks print the weight and Dimension clause, on a
                                 parcel rule and a non-parcel one alike, so it is unconditional
                                 here rather than gated the way the create form gates it. --}}
                            <span>{{ $rule->pricing_method === \App\Models\DeliveryRule::METHOD_DISTANCE
                                ? translate('The delivery charge is calculated using the configured per-distance rate, minimum charge, and maximum charge. Additional vehicle, weight, and dimension charges are applied separately if configured.')
                                : translate('Customers are charged the configured base delivery fee based on the delivery area. Additional vehicle, weight, and dimension charges are applied separately if configured.') }}</span>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="rule-charge-table">
                            <table class="rule-charge-table__table">
                                <thead>
                                    <tr>
                                        <th class="rule-charge-table__sl">{{ translate('messages.SL') }}</th>
                                        <th>
                                            {{ $rule->usesChargeTable() ? ($isArea ? translate('Area name') : translate('Zip code')) : translate('messages.Name') }}
                                        </th>
                                        <th>
                                            {{ translate('Delivery charge') }}
                                            ({{ $currencySymbol }})
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if ($rule->usesChargeTable())
                                        @forelse ($chargeRows as $index => $chargeRow)
                                            <tr>
                                                <td class="rule-charge-table__sl">{{ $index + 1 }}</td>
                                                <td>{{ $chargeRow['label'] }}</td>
                                                <td>{{ $chargeRow['charge'] }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center">
                                                    {{ translate('No data found') }}</td>
                                            </tr>
                                        @endforelse
                                    @elseif ($rule->pricing_method === \App\Models\DeliveryRule::METHOD_DISTANCE)
                                        <tr>
                                            <td class="rule-charge-table__sl">1</td>
                                            {{-- The rate is entered and applied per configured distance
                                                 unit, so the label follows the setting instead of naming
                                                 kilometres outright - see the delivery rule service. --}}
                                            <td>{{ translate('Per unit delivery charge') }}
                                                ({{ $distanceUnitLabel }})</td>
                                            <td>{{ $perUnitCharge }}</td>
                                        </tr>
                                        <tr>
                                            <td class="rule-charge-table__sl">2</td>
                                            <td>{{ translate('messages.Maximum delivery charge') }}</td>
                                            <td>{{ $maximumCharge }}</td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td class="rule-charge-table__sl">1</td>
                                            <td>{{ translate('Delivery charge') }}</td>
                                            <td>{{ $fixedCharge }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        @if ($showsWeightTab)
            <div class="card rule-tab-pane d-none" data-pane="weight">
                <div class="card-body">
                    @include('admin-views.delivery-rule.partials._tier-detail', [
                        'title' => translate('Weight charges'),
                        'subtitle' => translate('messages.Additional delivery charges configured for each package weight range.'),
                        'hint' => translate('messages.The applicable weight charge is added to the base delivery charge when the package weight falls within the configured range.'),
                        'heading' => $weightRangeHeading,
                        'rows' => $weightTierRows,
                    ])
                </div>
            </div>

        @endif

        @if ($showsDimensionTab)
            <div class="card rule-tab-pane d-none" data-pane="dimension">
                <div class="card-body">
                    @include('admin-views.delivery-rule.partials._tier-detail', [
                        'title' => translate('Dimension charges'),
                        'subtitle' => translate('Additional delivery charges configured for each package dimension.'),
                        'hint' => translate('The applicable dimension charge is added to the base delivery charge based on the package dimension assigned during delivery.'),
                        'heading' => translate('Dimension name'),
                        'rows' => $dimensionTierRows,
                    ])
                </div>
            </div>
        @endif
    </div>

    @include('admin-views.delivery-rule.partials._status-modals')

    <div class="modal fade" id="rule-delete-modal">
        <div class="modal-dialog status-warning-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true" class="tio-clear"></span>
                    </button>
                </div>
                <div class="modal-body pb-5 pt-0">
                    <div class="max-349 mx-auto mb-20">
                        <div class="text-center">
                            <img class="mb-20" src="{{ asset('public/assets/admin/img/modal/delete-icon.png') }}" alt="">
                            <h5 class="modal-title mb-3">{{ translate('Want to delete this delivery rule?') }}</h5>
                        </div>
                        <div class="text-center">
                            <p>{{ translate('messages.Are you sure you want to delete this delivery rule & remove it permanently?') }}
                            </p>
                        </div>
                        <div class="btn--container justify-content-center">
                            <button type="button" class="btn btn--reset min-w-120px"
                                data-dismiss="modal">{{ translate('messages.No') }}</button>
                            <button type="button" id="rule-delete-confirm"
                                class="btn btn--danger min-w-120px">{{ translate('messages.Delete') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.delivery-rule.partials._status-scripts')

    <script>
        "use strict";

        // The parcel tabs. Panes are rendered server-side and swapped here rather than fetched,
        // because all three are small and already on the page.
        $(document).on('click', '.rule-tab', function () {
            const pane = $(this).data('tab');

            $('.rule-tab').removeClass('is-active');
            $(this).addClass('is-active');

            $('.rule-tab-pane').addClass('d-none');
            $('.rule-tab-pane[data-pane="' + pane + '"]').removeClass('d-none');
        });

        let pendingDeleteFormId = null;

        $(document).on('click', '.rule-delete-btn', function () {
            pendingDeleteFormId = $(this).data('id');
            $('#rule-delete-modal').modal('show');
        });

        $(document).on('click', '#rule-delete-confirm', function () {
            if (pendingDeleteFormId) { $('#' + pendingDeleteFormId).submit(); }
        });
    </script>
@endpush
