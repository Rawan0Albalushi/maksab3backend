@extends('layouts.admin.app')

@section('title', translate('Additional charge'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/admin-shared.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/admin-shared.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/delivery-rule.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/delivery-rule.css')) }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mb-0">
                <i class="tio-money"></i>
                <span>
                    {{ translate('Additional charge') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('What a customer pays extra to get an order sooner, or saves by accepting it a little later.') }}</p>
        </div>

        {{-- Amber, above the card, exactly as the design places it — and it is shown with the
             empty state too, because the rule is what the admin needs to know BEFORE creating
             the first one. --}}
        <div class="rule-note mb-20">
            <i class="tio-info-outined"></i>
            <span>{{ translate('messages.Duplicate_configurations_are_not_allowed._Each_Zone_and_Module_combination_can_have_only_one_Additional_Delivery_Charge_setup.') }}</span>
        </div>

        {{-- The empty state takes over the whole card, but only when nothing has ever been
             added — a search that matches nothing keeps the table so the search box stays
             reachable. --}}
        @if ($setups->total() === 0 && !request()->has('search'))
            <div class="card">
                <div class="card-body empty-state">
                    <img class="empty-state__icon" src="{{ asset('public/assets/admin/img/price-emty.png') }}"
                        alt="{{ translate('messages.Additional_Delivery_Charge') }}">
                    <h5 class="empty-state__title">
                        {{ translate('messages.Currently_You_Dont_Have_Any_Additional_Delivery_Charge') }}</h5>
                    <p class="empty-state__text">
                        {{ translate('messages.To_enable_Additional_Delivery_Charge,_you_must_create_at_least_Additional_Delivery_Charge._In_this_page_you_see_all_the_Additional_Delivery_Charge_you_added.') }}
                    </p>
                    <a href="{{ route('admin.business-settings.zone.additional-delivery-charge.create') }}"
                        class="btn btn--primary">
                        {{ translate('Add new') }}
                    </a>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        <h5 class="card-title">
                            {{ translate('messages.Additional_Charge_List') }}
                            <span class="badge badge-soft-dark ml-2">{{ $setups->total() }}</span>
                        </h5>

                        <form>
                            <div class="input--group input-group input-group-merge input-group-flush">
                                <input id="datatableSearch_" type="search" name="search" class="form-control"
                                    value="{{ request()?->search ?? null }}"
                                    placeholder="{{ translate('messages.Search_by_Zone') }}"
                                    aria-label="{{ translate('Search') }}" required>
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>

                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                                href="javascript:;"
                                data-hs-unfold-options='{"target": "#additionalChargeExportDropdown","type": "css-animation"}'>
                                <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                            </a>
                            <div id="additionalChargeExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.additional-delivery-charge.export', ['type' => 'excel', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
                                    Excel
                                </a>
                                <a class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.additional-delivery-charge.export', ['type' => 'csv', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                        alt="">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <a href="{{ route('admin.business-settings.zone.additional-delivery-charge.create') }}"
                            class="btn btn--primary">
                            <i class="tio-add-circle-outlined"></i> {{ translate('Add new') }}
                        </a>
                    </div>
                </div>

                <div class="table-responsive datatable-custom">
                    <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('messages.SL') }}</th>
                                <th class="border-0">{{ translate('messages.Zone') }}</th>
                                <th class="border-0">{{ translate('messages.Module') }}</th>
                                <th class="border-0">{{ translate('Express delivery') }}</th>
                                <th class="border-0">{{ translate('Slightly delay delivery') }}</th>
                                <th class="border-0">{{ translate('messages.Status') }}</th>
                                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($setups as $key => $setup)
                                <tr>
                                    <td class="pl-4">{{ $key + $setups->firstItem() }}</td>
                                    <td>
                                        <span class="line--limit-2 max-w-220px"
                                            title="{{ $setup->zone?->name }}">{{ $setup->zone?->name ?? translate('messages.N/A') }}</span>
                                    </td>
                                    <td>
                                        @if ($moduleLabels[$setup->id]['many'])
                                            <span class="text-primary border-bottom border-primary cursor-pointer"
                                                data-toggle="tooltip" data-placement="top"
                                                title="{{ $moduleLabels[$setup->id]['tooltip'] }}">{{ $moduleLabels[$setup->id]['text'] }}</span>
                                        @else
                                            {{ $moduleLabels[$setup->id]['text'] }}
                                        @endif
                                    </td>
                                    <td>
                                        {{-- "+5 / −10 min" reads as the offer: pay this much more,
                                             wait this much less. --}}
                                        +{{ $currencySymbol }}{{ $setup->express_extra_charge }}
                                        / −{{ $setup->express_reduce_delivery_time }}
                                        {{ translate('ETA minute unit') }}
                                    </td>
                                    <td>
                                        −{{ $currencySymbol }}{{ $setup->delay_reduce_charge }}
                                        / +{{ $setup->delay_add_delivery_time }}
                                        {{ translate('ETA minute unit') }}
                                    </td>
                                    <td>
                                        <label class="toggle-switch toggle-switch-sm" for="status-{{ $setup->id }}">
                                            <input type="checkbox" class="toggle-switch-input dynamic-checkbox"
                                                id="status-{{ $setup->id }}" {{ $setup->status ? 'checked' : '' }}
                                                data-id="status-{{ $setup->id }}" data-type="status"
                                                data-image-on="{{ asset('public/assets/admin/img/status-ons.png') }}"
                                                data-image-off="{{ asset('public/assets/admin/img/off-danger.png') }}"
                                                data-title-on="{{ translate('Turn on the status?') }}"
                                                data-title-off="{{ translate('Turn off the status?') }}"
                                                data-text-on="<p>{{ translate('messages.Are_you_sure,_do_you_want_to_turn_on_this_additional_delivery_charge.') }}</p>"
                                                data-text-off="<p>{{ translate('messages.Are_you_sure,_do_you_want_to_turn_off_this_additional_delivery_charge.') }}</p>">
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <form
                                            action="{{ route('admin.business-settings.zone.additional-delivery-charge.status', [$setup->id, $setup->status ? 0 : 1]) }}"
                                            method="get" id="status-{{ $setup->id }}_form"></form>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--edit"
                                                href="{{ route('admin.business-settings.zone.additional-delivery-charge.edit', [$setup->id]) }}"
                                                title="{{ translate('Edit') }}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--delete additional-charge-delete-btn"
                                                href="javascript:" data-id="additional-charge-{{ $setup->id }}"
                                                title="{{ translate('Delete') }}">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                            <form
                                                action="{{ route('admin.business-settings.zone.additional-delivery-charge.delete', [$setup->id]) }}"
                                                method="post" id="additional-charge-{{ $setup->id }}" class="d-none">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (count($setups) !== 0)
                    <div class="page-area px-4 pb-3">
                        {!! $setups->withQueryString()->links() !!}
                    </div>
                @else
                    <div class="empty--data">
                        <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                        <h5>{{ translate('No data found') }}</h5>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @include('admin-views.additional-delivery-charge.partials._delete-modal')
@endsection

@push('script_2')
    <script>
        "use strict";

        let pendingDeleteFormId = null;

        $(document).on('click', '.additional-charge-delete-btn', function () {
            pendingDeleteFormId = $(this).data('id');
            $('#additional-charge-delete-modal').modal('show');
        });

        $(document).on('click', '#additional-charge-delete-confirm', function () {
            if (pendingDeleteFormId) { $('#' + pendingDeleteFormId).submit(); }
        });
    </script>
@endpush
