@extends('layouts.admin.app')

@section('title', translate('Vehicles category'))

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
                        <i class="tio-car"></i>
                        <span>{{ translate('Vehicles category') }}</span>
                    </h1>
                </div>
            </div>
        </div>

        {{-- The empty state takes over the whole card, but only when no category has ever been
             added — a search that matches nothing keeps the table so the search box stays
             reachable. Same rule as Dimension Setup. --}}
        @if ($vehicles->total() === 0 && !request()->has('search'))
            <div class="card">
                <div class="card-body empty-state">
                    <img class="empty-state__icon" src="{{ asset('public/assets/admin/img/price-emty.png') }}"
                        alt="{{ translate('Vehicles category') }}">
                    <h5 class="empty-state__title">
                        {{ translate('messages.Currently You Dont Have Any Vehicles Category Setup') }}</h5>
                    {{-- The mock reads "…required to calculate delivery charges based on the
                         selected vehicle category". That charge was removed in S13 — the fee
                         engine no longer adds it and the API answers 0.00 — so the copy names
                         what a category actually does now. --}}
                    <p class="empty-state__text">
                        {{ translate('messages.A vehicle category sets the delivery range, weight and package sizes a delivery man can take, and routes orders to the right riders.') }}
                    </p>
                    <a href="{{ route('admin.business-settings.zone.vehicle-category.create') }}" class="btn btn--primary">
                        <i class="tio-add-circle-outlined"></i> {{ translate('Add new') }}
                    </a>
                </div>
            </div>
        @else
            {{-- Overlap is allowed by design (QA case TC_230): with the per-category charge gone,
                 two categories covering one distance can no longer disagree about the price. --}}
            <div class="rule-note mb-20">
                <i class="tio-info-outined"></i>
                <span>{{ translate('messages.Vehicle coverage area can overlap each other.') }}</span>
            </div>

            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        <h5 class="card-title">
                            {{ translate('Vehicles category list') }}
                            <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $vehicles->total() }}</span>
                        </h5>

                        <form>
                            <div class="input--group input-group input-group-merge input-group-flush">
                                <input type="search" name="search" class="form-control"
                                    value="{{ request()?->search ?? null }}"
                                    placeholder="{{ translate('Search by title') }}"
                                    aria-label="{{ translate('Search') }}" required>
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>

                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                                href="javascript:;"
                                data-hs-unfold-options='{"target": "#vehicleCategoryExportDropdown","type": "css-animation"}'>
                                <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                            </a>
                            <div id="vehicleCategoryExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.vehicle-category.export', ['type' => 'excel', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
                                    Excel
                                </a>
                                <a class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.vehicle-category.export', ['type' => 'csv', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                        alt="">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <a href="{{ route('admin.business-settings.zone.vehicle-category.create') }}"
                            class="btn btn--primary">
                            <i class="tio-add-circle-outlined"></i> {{ translate('Add new vehicle') }}
                        </a>
                    </div>
                </div>

                <div class="table-responsive datatable-custom">
                    <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('messages.SL') }}</th>
                                <th class="border-0">{{ translate('messages.Type') }}</th>
                                <th class="border-0">{{ translate('Total deliveryman') }}</th>
                                <th class="border-0">{{ translate('messages.Minimum Coverage Area') }}({{ $distanceUnitLabel }})</th>
                                <th class="border-0">{{ translate('messages.Maximum Coverage Area') }}({{ $distanceUnitLabel }})</th>
                                <th class="border-0">{{ translate('messages.Max. Weight') }}</th>
                                {{-- The mock's header reads "Dimension Dimension (L × W × H)"; the cells
                                     under it hold size-class NAMES ("Medium, Large"), not measurements,
                                     so the duplicated word and the stale format hint are dropped. --}}
                                <th class="border-0">{{ translate('messages.Dimension') }}</th>
                                <th class="border-0">{{ translate('messages.Status') }}</th>
                                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>

                        <tbody id="set-rows">
                            @foreach ($vehicles as $key => $vehicle)
                                <tr>
                                    <td class="pl-4">{{ $key + $vehicles->firstItem() }}</td>
                                    <td>
                                        <span class="line--limit-2 max-w-220px text-title"
                                            title="{{ $vehicle->type }}">{{ $vehicle->type }}</span>
                                    </td>
                                    <td>{{ $vehicle->delivery_men_count }}</td>
                                    <td>{{ $vehicle->starting_coverage_area }}</td>
                                    <td>{{ $vehicle->maximum_coverage_area }}</td>
                                    <td>{{ $weightLabels[$vehicle->id] }}</td>
                                    <td>
                                        <span class="line--limit-2 max-w-220px"
                                            title="{{ $dimensionLabels[$vehicle->id] }}">{{ $dimensionLabels[$vehicle->id] }}</span>
                                    </td>
                                    <td>
                                        {{-- Same confirm pipeline as its siblings: .dynamic-checkbox raises
                                             #toggle-status-modal, and its Yes button submits the sibling
                                             form named "<data-id>_form". --}}
                                        <label class="toggle-switch toggle-switch-sm" for="status-{{ $vehicle->id }}">
                                            <input type="checkbox" class="toggle-switch-input dynamic-checkbox"
                                                id="status-{{ $vehicle->id }}" {{ $vehicle->status ? 'checked' : '' }}
                                                data-id="status-{{ $vehicle->id }}" data-type="status"
                                                data-image-on="{{ asset('public/assets/admin/img/status-ons.png') }}"
                                                data-image-off="{{ asset('public/assets/admin/img/off-danger.png') }}"
                                                data-title-on="{{ translate('Turn on the status?') }}"
                                                data-title-off="{{ translate('Turn off the status?') }}"
                                                data-text-on="<p>{{ translate('messages.Deliverymen in this category can receive orders.') }}</p>"
                                                data-text-off="<p>{{ translate('messages.Deliverymen in this category cannot receive orders.') }}</p>">
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <form action="{{ route('admin.business-settings.zone.vehicle-category.status', [$vehicle->id, $vehicle->status ? 0 : 1]) }}"
                                            method="get" id="status-{{ $vehicle->id }}_form"></form>
                                    </td>
                                    <td>
                                        {{-- The standard row-action pair, as every other Delivery
                                             Management list draws it. This column used to carry the
                                             theme's coloured outline pair instead, on the grounds that
                                             the mock drew it that way — but it was the only list in the
                                             section doing so, and one screen disagreeing with the other
                                             nine reads as a bug rather than as emphasis. Neutral at
                                             rest, colour on hover: admin-tables.css §row actions. --}}
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--edit"
                                                href="{{ route('admin.business-settings.zone.vehicle-category.edit', [$vehicle->id]) }}"
                                                title="{{ translate('messages.Edit vehicle category') }}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--delete vehicle-delete-btn"
                                                href="javascript:" data-id="vehicle-{{ $vehicle->id }}"
                                                title="{{ translate('messages.Delete vehicle category') }}">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{ route('admin.business-settings.zone.vehicle-category.delete', [$vehicle->id]) }}"
                                                method="post" id="vehicle-{{ $vehicle->id }}">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (count($vehicles) !== 0)
                    <div class="page-area px-4 pb-3">{!! $vehicles->withQueryString()->links() !!}</div>
                @else
                    <div class="empty--data">
                        <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                        <h5>{{ translate('No data found') }}</h5>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @include('admin-views.vehicle-category.partials._delete-modal')
@endsection

@push('script_2')
    <script>
        "use strict";

        let pendingDeleteFormId = null;

        $(document).on('click', '.vehicle-delete-btn', function () {
            pendingDeleteFormId = $(this).data('id');
            $('#vehicle-delete-modal').modal('show');
        });

        $(document).on('click', '#vehicle-delete-confirm', function () {
            if (pendingDeleteFormId) {
                $('#' + pendingDeleteFormId).submit();
            }
        });
    </script>
@endpush
