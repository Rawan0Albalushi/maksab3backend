@extends('layouts.admin.app')

@section('title', translate('Weight setup'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/admin-shared.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/admin-shared.css')) }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/package.svg') }}" class="w--26" alt="">
                        </span>
                        <span>
                            {{ translate('Weight setup') }}
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('The weight bands a parcel is priced by, so a heavier package can cost more to deliver.') }}</p>
                </div>
            </div>
        </div>

        {{-- The empty state takes over the whole card, but only when no weight class has
             ever been added — a search that matches nothing keeps the table so the
             search box stays reachable. --}}
        @if ($weights->total() === 0 && !request()->has('search'))
            <div class="card">
                <div class="card-body empty-state">
                    <img class="empty-state__icon" src="{{ asset('public/assets/admin/img/price-emty.png') }}"
                        alt="{{ translate('Weight setup') }}">
                    <h5 class="empty-state__title">
                        {{ translate('Currently you dont have any weight setup') }}</h5>
                    <p class="empty-state__text">
                        {{ translate('messages.Set up weight ranges so parcel deliveries can be charged by how heavy the package is.') }}
                    </p>
                    <a href="javascript:void(0)" class="btn btn--primary offcanvas-trigger"
                        data-target="#offcanvas__weight" data-action="create">
                        <i class="tio-add-circle-outlined"></i> {{ translate('Add new') }}
                    </a>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        <h5 class="card-title">
                            {{ translate('messages.Weight list') }}
                            <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $weights->total() }}</span>
                        </h5>

                        <form>
                            <div class="input--group input-group input-group-merge input-group-flush">
                                <input id="datatableSearch_" type="search" name="search" class="form-control"
                                    value="{{ request()?->search ?? null }}"
                                    placeholder="{{ translate('Search') }}"
                                    aria-label="{{ translate('Search') }}" required>
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>

                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                                href="javascript:;"
                                data-hs-unfold-options='{"target": "#weightExportDropdown","type": "css-animation"}'>
                                <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                            </a>
                            <div id="weightExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a id="export-excel" class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.weight.export', ['type' => 'excel', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
                                    Excel
                                </a>
                                <a id="export-csv" class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.weight.export', ['type' => 'csv', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                        alt="">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <a href="javascript:void(0)" class="btn btn--primary offcanvas-trigger"
                            data-target="#offcanvas__weight" data-action="create">
                            <i class="tio-add-circle-outlined"></i> {{ translate('Add new weight') }}
                        </a>
                    </div>
                </div>

                <div class="table-responsive datatable-custom">
                    <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('messages.SL') }}</th>
                                <th class="border-0">{{ translate('Weight name') }}</th>
                                <th class="border-0">{{ translate('Weight range') }}</th>
                                <th class="border-0">{{ translate('messages.Status') }}</th>
                                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>

                        <tbody id="set-rows">
                            @foreach ($weights as $key => $weight)
                                <tr>
                                    <td class="pl-4">{{ $key + $weights->firstItem() }}</td>
                                    <td>
                                        <span class="line--limit-2 max-w-220px text-title"
                                            title="{{ $weight->name }}">{{ $weight->name }}</span>
                                    </td>
                                    <td>{{ $bandLabels[$weight->id] }}</td>
                                    <td>
                                        {{-- Same confirm pipeline as Area Setup: .dynamic-checkbox raises
                                             #toggle-status-modal, and its Yes button submits the sibling
                                             form named "<data-id>_form". --}}
                                        <label class="toggle-switch toggle-switch-sm" for="status-{{ $weight->id }}">
                                            <input type="checkbox" class="toggle-switch-input dynamic-checkbox"
                                                id="status-{{ $weight->id }}" {{ $weight->status ? 'checked' : '' }}
                                                data-id="status-{{ $weight->id }}" data-type="status"
                                                data-image-on="{{ asset('public/assets/admin/img/status-ons.png') }}"
                                                data-image-off="{{ asset('public/assets/admin/img/off-danger.png') }}"
                                                data-title-on="{{ translate('Turn on the status?') }}"
                                                data-title-off="{{ translate('Turn off the status?') }}"
                                                data-text-on="<p>{{ translate('Are you sure, do you want to turn on the weight status from your system.') }}</p>"
                                                data-text-off="<p>{{ translate('Are you sure, do you want to turn off the weight status from your system.') }}</p>">
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <form action="{{ route('admin.business-settings.zone.weight.status', [$weight->id, $weight->status ? 0 : 1]) }}"
                                            method="get" id="status-{{ $weight->id }}_form"></form>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--edit offcanvas-trigger"
                                                href="javascript:" data-target="#offcanvas__weight" data-action="edit"
                                                data-url="{{ route('admin.business-settings.zone.weight.edit', [$weight->id]) }}"
                                                title="{{ translate('messages.Edit weight') }}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--delete weight-delete-btn"
                                                href="javascript:" data-id="weight-{{ $weight->id }}"
                                                title="{{ translate('messages.Delete weight') }}">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{ route('admin.business-settings.zone.weight.delete', [$weight->id]) }}" method="post"
                                                id="weight-{{ $weight->id }}">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (count($weights) !== 0)
                    <div class="page-area px-4 pb-3">
                        {!! $weights->withQueryString()->links() !!}
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

    <div id="offcanvas__weight" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100"></div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

    {{-- The shared .form-alert helper renders a SweetAlert, which does not match
         the design, so deletion gets its own confirm modal — the same one Area
         Setup uses. --}}
    <div class="modal fade" id="weight-delete-modal">
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
                            <img class="mb-20" src="{{ asset('public/assets/admin/img/modal/delete-icon.png') }}"
                                alt="">
                            <h5 class="modal-title mb-3">{{ translate('Want to delete this weight?') }}</h5>
                        </div>
                        <div class="text-center">
                            <p>{{ translate('messages.Are you sure you want to delete this weight class & remove it permanently?') }}
                            </p>
                        </div>
                        <div class="btn--container justify-content-center">
                            <button type="button" class="btn btn--reset min-w-120px"
                                data-dismiss="modal">{{ translate('messages.No') }}</button>
                            <button type="button" id="weight-delete-confirm"
                                class="btn btn--danger min-w-120px">{{ translate('messages.Delete') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/offcanvas.js') }}"></script>
    @include('admin-views.weight.partials._list-scripts')
@endpush
