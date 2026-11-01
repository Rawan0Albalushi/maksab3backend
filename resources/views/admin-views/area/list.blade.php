@extends('layouts.admin.app')

@section('title', translate('Area setup'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/admin-shared.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/admin-shared.css')) }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/zone.svg') }}" class="w--26" alt="">
                </span>
                <span>{{ translate('Area setup') }}<span class="badge badge-soft-dark ml-2"
                        id="itemCount">{{ $areas->total() }}</span></span>
            </h1>
            <p class="page-header-desc">{{ translate('The named parts of a zone a customer picks from, so a delivery rule can charge each one its own rate.') }}</p>
        </div>

        @if ($areas->total() === 0 && !request()->has('search') && !request()->has('zone_id'))
            <div class="card">
                <div class="card-body empty-state">
                    <img class="empty-state__icon" src="{{ asset('public/assets/admin/img/price-emty.png') }}"
                        alt="{{ translate('Area setup') }}">
                    <h5 class="empty-state__title">
                        {{ translate('Currently you dont have any area setup') }}</h5>
                    <p class="empty-state__text">
                        {{ translate('messages.To setup area under a zone you can use area wise delivery charge when customer delivery charges calculated.') }}
                    </p>
                    <a href="javascript:void(0)" class="btn btn--primary offcanvas-trigger"
                        data-target="#offcanvas__area" data-action="create">
                        <i class="tio-add-circle-outlined"></i> {{ translate('Add new') }}
                    </a>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper justify-content-end">
                        @if (!auth('admin')?->user()?->zone_id)
                            <div class="min--200">
                                <select name="zone_id" class="form-control js-select2-custom set-filter"
                                    data-filter="zone_id" data-url="{{ url()->full() }}">
                                    <option value="all">{{ translate('All zones') }}</option>
                                    @foreach ($zones as $zone)
                                        <option value="{{ $zone->id }}"
                                            {{ request()?->input('zone_id') == $zone->id ? 'selected' : '' }}>
                                            {{ $zone->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <form class="search-form">
                            @if (request()?->input('zone_id'))
                                <input type="hidden" name="zone_id" value="{{ request()->input('zone_id') }}">
                            @endif
                            <div class="input--group input-group input-group-merge input-group-flush">
                                <input id="datatableSearch_" type="search" name="search" class="form-control"
                                    value="{{ request()?->search ?? null }}"
                                    placeholder="{{ translate('Search') }}"
                                    aria-label="{{ translate('Search') }}">
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>

                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                                href="javascript:;"
                                data-hs-unfold-options='{"target": "#areaExportDropdown","type": "css-animation"}'>
                                <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                            </a>
                            <div id="areaExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a id="export-excel" class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.area.export', ['type' => 'excel', 'search' => request()->search, 'zone_id' => request()->zone_id]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
                                    Excel
                                </a>
                                <a id="export-csv" class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.area.export', ['type' => 'csv', 'search' => request()->search, 'zone_id' => request()->zone_id]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                        alt="">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <a href="javascript:void(0)" class="btn btn--primary offcanvas-trigger"
                            data-target="#offcanvas__area" data-action="create">
                            <i class="tio-add-circle-outlined"></i> {{ translate('Add new area') }}
                        </a>
                    </div>
                </div>

                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('Area name') }}</th>
                                <th class="border-0">{{ translate('Zone name') }}</th>
                                <th class="border-0">{{ translate('messages.Created at') }}</th>
                                <th class="border-0">{{ translate('messages.Status') }}</th>
                                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>

                        <tbody id="set-rows">
                            @foreach ($areas as $area)
                                <tr>
                                    <td>
                                        <span class="line--limit-2 max-w-220px text-title font-semibold"
                                            title="{{ $area->name }}">{{ $area->name }}</span>
                                        @if ($area->display_name && $area->display_name !== $area->name)
                                            <span class="d-block fs-12 text-muted">{{ $area->display_name }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($area->zone)
                                            <span class="line--limit-2 max-w-220px"
                                                title="{{ $area->zone->name }}">{{ $area->zone->name }}</span>
                                        @else
                                            <span class="text-muted font-size-sm">{{ translate('messages.N/A') }}</span>
                                        @endif
                                    </td>
                                    <td data-order="{{ $area->created_at }}">
                                        <span class="font-size-sm"
                                            title="{{ \App\CentralLogics\Helpers::time_date_format($area->created_at) }}">{{ \App\CentralLogics\Helpers::date_format($area->created_at) }}</span>
                                    </td>
                                    <td>
                                        <label class="toggle-switch toggle-switch-sm" for="status-{{ $area->id }}">
                                            <input type="checkbox" class="toggle-switch-input dynamic-checkbox"
                                                id="status-{{ $area->id }}" {{ $area->status ? 'checked' : '' }}
                                                data-id="status-{{ $area->id }}" data-type="status"
                                                data-image-on="{{ asset('public/assets/admin/img/status-ons.png') }}"
                                                data-image-off="{{ asset('public/assets/admin/img/off-danger.png') }}"
                                                data-title-on="{{ translate('Turn on the status?') }}"
                                                data-title-off="{{ translate('Turn off the status?') }}"
                                                data-text-on="<p>{{ translate('Are you sure, do you want to turn on the area status from your system.') }}</p>"
                                                data-text-off="<p>{{ translate('Are you sure, do you want to turn off the area status from your system.') }}</p>">
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <form action="{{ route('admin.business-settings.zone.area.status', [$area->id, $area->status ? 0 : 1]) }}"
                                            method="get" id="status-{{ $area->id }}_form"></form>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--edit offcanvas-trigger"
                                                href="javascript:" data-target="#offcanvas__area" data-action="edit"
                                                data-url="{{ route('admin.business-settings.zone.area.edit', [$area->id]) }}"
                                                title="{{ translate('messages.Edit area') }}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--delete area-delete-btn"
                                                href="javascript:" data-id="area-{{ $area->id }}"
                                                title="{{ translate('messages.Delete area') }}">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{ route('admin.business-settings.zone.area.delete', [$area->id]) }}" method="post"
                                                id="area-{{ $area->id }}">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (count($areas) !== 0)
                    <hr>
                @endif
                <div class="page-area">
                    {!! $areas->withQueryString()->links() !!}
                </div>
                @if (count($areas) === 0)
                    <div class="empty--data">
                        <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                        <h5>{{ translate('No data found') }}</h5>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div id="offcanvas__area" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100"></div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

    <div class="modal fade" id="area-delete-modal">
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
                            <h5 class="modal-title mb-3">{{ translate('Want to delete this area?') }}</h5>
                        </div>
                        <div class="text-center">
                            <p>{{ translate('Are you sure you want to delete this area & remove it permanently?') }}
                            </p>
                        </div>
                        <div class="btn--container justify-content-center">
                            <button type="button" class="btn btn--reset min-w-120px"
                                data-dismiss="modal">{{ translate('messages.No') }}</button>
                            <button type="button" id="area-delete-confirm"
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
    <script>
        "use strict";

        let pendingDeleteFormId = null;

        $(document).on('click', '.area-delete-btn', function () {
            pendingDeleteFormId = $(this).data('id');
            $('#area-delete-modal').modal('show');
        });

        $(document).on('click', '#area-delete-confirm', function () {
            if (pendingDeleteFormId) {
                $('#' + pendingDeleteFormId).submit();
            }
        });

        $(document).on('click', '.offcanvas-trigger', function () {
            let action = $(this).data('action');
            let url = action === 'edit' ? $(this).data('url') : '{{ route('admin.business-settings.zone.area.create') }}';
            fetch_form(url);
        });

        $(document).on('click', '.offcanvas-close, #offcanvasOverlay', function () {
            $('.custom-offcanvas').removeClass('open');
            $('#offcanvasOverlay').removeClass('show');
        });

        function fetch_form(url) {
            $.ajax({
                url: url,
                type: 'get',
                beforeSend: function () {
                    $('#data-view').empty();
                    $('#loading').show();
                },
                success: function (data) {
                    $('#data-view').append(data.view);
                    $('#data-view .js-select2-custom').select2({
                        dropdownParent: $('#offcanvas__area'),
                        placeholder: '{{ translate('Select zone') }}',
                    });
                },
                complete: function () {
                    $('#loading').hide();
                }
            });
        }

        $(document).on('click', '#data-view .lang_link', function (e) {
            e.preventDefault();
            let lang = this.id.replace('-link', '');
            $('#data-view .lang_link').removeClass('active');
            $(this).addClass('active');
            $('#data-view .lang_form').addClass('d-none');
            $('#data-view').find('#' + lang + '-form').removeClass('d-none');
        });

        $(document).on('submit', '#area-offcanvas-form', function (e) {
            e.preventDefault();
            let form = $(this);
            let submitBtn = form.find('button[type="submit"]');
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: form.attr('action'),
                data: new FormData(this),
                cache: false,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    submitBtn.prop('disabled', true);
                },
                success: function (data) {
                    if (data.errors) {
                        submitBtn.prop('disabled', false);
                        for (let i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    } else {
                        toastr.success(data.success, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    }
                },
                error: function () {
                    submitBtn.prop('disabled', false);
                }
            });
        });
    </script>
@endpush
