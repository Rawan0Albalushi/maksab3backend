@extends('layouts.admin.app')

@section('title', translate('Rules setup'))

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
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/condition.svg') }}" class="w--26" alt="">
                        </span>
                        <span>
                            {{ translate('Rules setup') }}
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('What each zone charges to deliver an order, and which modules that price applies to.') }}</p>
                </div>
            </div>
        </div>

        <div class="admin-alert mb-3">
            <span class="admin-alert__icon">i</span>
            <span>{{ translate('messages.Each zone can be create multiple delivery rules, but only one rule can be active at a time.') }}</span>
        </div>

        @if ($rules->total() === 0 && !request()->has('search'))
            <div class="card">
                <div class="card-body empty-state">
                    <img class="empty-state__icon" src="{{ asset('public/assets/admin/img/price-emty.png') }}"
                        alt="{{ translate('Rules setup') }}">
                    <h5 class="empty-state__title">
                        {{ translate('No delivery charge rules setup is available') }}</h5>
                    <p class="empty-state__text">
                        {{ translate("messages.Without any delivery charge rule zones are not work proper & can't get any order from the zone.") }}
                    </p>
                    <a href="{{ route('admin.business-settings.zone.delivery-rule.create') }}" class="btn btn--primary">
                        <i class="tio-add-circle-outlined"></i> {{ translate('Add new') }}
                    </a>
                </div>
            </div>
        @else
            {{-- Design rule D1 states the constraint the create form already enforces: one rule per
                 zone and module. The ETA, Free Delivery and Additional Delivery Charge lists all
                 carry the same note; this one did not, so the rule was only discoverable by
                 hitting the error. --}}
            <div class="rule-note mb-20">
                <i class="tio-info-outined"></i>
                <span>{{ translate('Duplicate configurations are not allowed. Each zone and module combination can have only one delivery rule setup.') }}</span>
            </div>

            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        <h5 class="card-title">
                            {{ translate('Delivery rule list') }}
                            <span class="badge badge-soft-dark ml-2">{{ $rules->total() }}</span>
                        </h5>

                        <form>
                            <div class="input--group input-group input-group-merge input-group-flush">
                                <input type="search" name="search" class="form-control"
                                    value="{{ request()?->search ?? null }}"
                                    placeholder="{{ translate('Search') }}" required>
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>

                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                                href="javascript:;"
                                data-hs-unfold-options='{"target": "#deliveryRuleExportDropdown","type": "css-animation"}'>
                                <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                            </a>
                            <div id="deliveryRuleExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.delivery-rule.export', ['type' => 'excel', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
                                    Excel
                                </a>
                                <a class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.delivery-rule.export', ['type' => 'csv', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                        alt="">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <a href="{{ route('admin.business-settings.zone.delivery-rule.create') }}" class="btn btn--primary">
                            <i class="tio-add-circle-outlined"></i> {{ translate('Add new delivery rule') }}
                        </a>
                    </div>
                </div>

                <div class="table-responsive datatable-custom">
                    <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('messages.SL') }}</th>
                                <th class="border-0">{{ translate('Rule name') }}</th>
                                <th class="border-0">{{ translate('messages.Zone') }}</th>
                                <th class="border-0">{{ translate('messages.Module') }}</th>
                                <th class="border-0">
                                    <div class="min-w-135px">{{ translate('Delivery method') }}</div>
                                </th>
                                <th class="border-0">{{ translate('messages.Status') }}</th>
                                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rules as $key => $rule)
                                <tr>
                                    <td class="pl-4">{{ $key + $rules->firstItem() }}</td>
                                    <td>
                                        <span class="line--limit-2 max-w-220px text-title"
                                            title="{{ $rule->name }}">{{ $rule->name }}</span>
                                    </td>
                                    <td>{{ $rule->zone?->name ?? translate('messages.N/A') }}</td>
                                    <td>
                                        {{-- The design shows a single module by name and several
                                             as "N Module" with a tooltip listing them. --}}
                                        @if ($rule->modules->count() === 1)
                                            <span class="line--limit-2 max-w-220px">{{ $rule->modules->first()->module_name }}</span>
                                        @elseif ($rule->modules->count() > 1)
                                            <span class="text-title border-bottom border-dashed" data-toggle="tooltip"
                                                title="{{ $rule->modules->pluck('module_name')->implode(', ') }}">
                                                {{ $rule->modules->count() }} {{ translate('messages.Module') }}
                                            </span>
                                        @else
                                            {{ translate('messages.N/A') }}
                                        @endif
                                    </td>
                                    <td>{{ $rule->methodLabel() }}</td>
                                    <td>
                                        {{-- Both directions need confirming, so the toggle only opens a
                                             modal; the request is sent from there. --}}
                                        <label class="toggle-switch toggle-switch-sm mb-0">
                                            {{-- A row that is the only active rule for one of its
                                                 modules carries the module names here. The script
                                                 refuses the toggle and explains why, instead of
                                                 opening the replacement picker on a hand-over that
                                                 has nowhere to go — same pattern as the ETA toggle. --}}
                                            <input type="checkbox" class="toggle-switch-input rule-status-toggle"
                                                {{ $rule->status ? 'checked' : '' }} data-id="{{ $rule->id }}"
                                                data-status="{{ $rule->status }}" data-zone="{{ $rule->zone_id }}"
                                                data-name="{{ $rule->name }}"
                                                @if (!empty($lockedModules[$rule->id]))
                                                    data-rule-locked="{{ implode(', ', $lockedModules[$rule->id]) }}"
                                                @endif>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--edit"
                                                href="{{ route('admin.business-settings.zone.delivery-rule.edit', [$rule->id]) }}"
                                                title="{{ translate('messages.Edit delivery rule') }}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--view"
                                                href="{{ route('admin.business-settings.zone.delivery-rule.show', [$rule->id]) }}"
                                                title="{{ translate('messages.View delivery rule') }}">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                            {{-- A zone must always keep a way to price delivery, so the
                                                 active rule cannot be removed. --}}
                                            @if (!$rule->status)
                                                <a class="btn action-btn action-btn--delete rule-delete-btn"
                                                    href="javascript:" data-id="rule-{{ $rule->id }}"
                                                    title="{{ translate('messages.Delete delivery rule') }}">
                                                    <i class="tio-delete-outlined"></i>
                                                </a>
                                                <form action="{{ route('admin.business-settings.zone.delivery-rule.delete', [$rule->id]) }}"
                                                    method="post" id="rule-{{ $rule->id }}">
                                                    @csrf @method('delete')
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (count($rules) !== 0)
                    <div class="page-area px-4 pb-3">{!! $rules->withQueryString()->links() !!}</div>
                @else
                    <div class="empty--data">
                        <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                        <h5>{{ translate('No data found') }}</h5>
                    </div>
                @endif
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

        let pendingDeleteFormId = null;

        $(document).on('click', '.rule-delete-btn', function () {
            pendingDeleteFormId = $(this).data('id');
            $('#rule-delete-modal').modal('show');
        });

        $(document).on('click', '#rule-delete-confirm', function () {
            if (pendingDeleteFormId) {
                $('#' + pendingDeleteFormId).submit();
            }
        });
    </script>
@endpush
