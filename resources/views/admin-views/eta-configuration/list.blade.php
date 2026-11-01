@extends('layouts.admin.app')

@section('title', translate('ETA configuration'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/admin-shared.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/admin-shared.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/delivery-rule.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/delivery-rule.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/eta-configuration.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/eta-configuration.css')) }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mb-0">
                <i class="tio-time"></i>
                <span>
                    {{ translate('ETA configuration') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('How long each zone tells a customer their order will take, per module.') }}</p>
        </div>

        {{-- The empty state takes over the whole card, but only when nothing has ever been
             added — a search that matches nothing keeps the table so the search box stays
             reachable. --}}
        @if ($configurations->total() === 0 && !request()->has('search'))
            <div class="card">
                <div class="card-body empty-state">
                    <img class="empty-state__icon" src="{{ asset('public/assets/admin/img/price-emty.png') }}"
                        alt="{{ translate('ETA configuration') }}">
                    <h5 class="empty-state__title">
                        {{ translate('Currently you dont have any ETA configuration setup') }}</h5>
                    <p class="empty-state__text">
                        {{ translate('messages.Configure ETA settings to accurately estimate delivery times for customers.') }}
                    </p>
                    {{-- The mock labels this button "Create Surge Price", which is a copy-paste
                         slip from the surge screen. It says what it does instead. --}}
                    <a href="{{ route('admin.business-settings.zone.eta-configuration.create') }}" class="btn btn--primary">
                        <i class="tio-add-circle-outlined"></i> {{ translate('Create ETA configuration') }}
                    </a>
                </div>
            </div>
        @else
            <div class="rule-note mb-20">
                <i class="tio-info-outined"></i>
                <span>{{ translate('Duplicate configurations are not allowed. Each zone and module combination can have only one ETA configuration setup.') }}</span>
            </div>

            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        <h5 class="card-title">
                            {{ translate('ETA configuration list') }}
                            <span class="badge badge-soft-dark ml-2">{{ $configurations->total() }}</span>
                        </h5>

                        <form>
                            <div class="input--group input-group input-group-merge input-group-flush">
                                <input id="datatableSearch_" type="search" name="search" class="form-control"
                                    value="{{ request()?->search ?? null }}"
                                    placeholder="{{ translate('Search by title') }}"
                                    aria-label="{{ translate('Search') }}" required>
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>

                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                                href="javascript:;"
                                data-hs-unfold-options='{"target": "#etaConfigurationExportDropdown","type": "css-animation"}'>
                                <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                            </a>
                            <div id="etaConfigurationExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.eta-configuration.export', ['type' => 'excel', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
                                    Excel
                                </a>
                                <a class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.eta-configuration.export', ['type' => 'csv', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <a href="{{ route('admin.business-settings.zone.eta-configuration.create') }}"
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
                                <th class="border-0">{{ translate('messages.Name') }}</th>
                                <th class="border-0">{{ translate('messages.Zone') }}</th>
                                <th class="border-0">{{ translate('messages.Module') }}</th>
                                <th class="border-0">
                                    <div class="min-w-135px">{{ translate('ETA type') }}</div>
                                </th>
                                <th class="border-0">
                                    <div class="min-w-180px">{{ translate('messages.Time duration') }}</div>
                                </th>
                                <th class="border-0">
                                    <div class="min-w-180px">{{ translate('Buffer time') }}</div>
                                </th>
                                <th class="border-0">{{ translate('messages.Status') }}</th>
                                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($configurations as $key => $configuration)
                                <tr>
                                    <td class="pl-4">{{ $key + $configurations->firstItem() }}</td>
                                    <td>
                                        <span class="line--limit-2 max-w-220px text-title"
                                            title="{{ $configuration->name }}">{{ $configuration->name ?? translate('messages.N/A') }}</span>
                                    </td>
                                    <td>
                                        <span class="line--limit-2 max-w-220px"
                                            title="{{ $configuration->zone?->name }}">{{ $configuration->zone?->name ?? translate('messages.N/A') }}</span>
                                    </td>
                                    <td>
                                        {{-- One module reads by name; several collapse to a count
                                             and carry the names as a tooltip, per the design. --}}
                                        @if ($moduleLabels[$configuration->id]['many'])
                                            <span class="text-primary border-bottom border-primary cursor-pointer"
                                                data-toggle="tooltip" data-placement="top"
                                                title="{{ $moduleLabels[$configuration->id]['tooltip'] }}">{{ $moduleLabels[$configuration->id]['text'] }}</span>
                                        @else
                                            {{ $moduleLabels[$configuration->id]['text'] }}
                                        @endif
                                    </td>
                                    <td>{{ $methodLabels[$configuration->id] }}</td>
                                    <td>
                                        <div class="eta-cell">
                                            @foreach ($timeDurations[$configuration->id] as $line)
                                                <span class="eta-cell__line">{{ $line['label'] }} :
                                                    <strong>{{ $line['value'] }}</strong></span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <div class="eta-cell">
                                            @foreach ($bufferTimes[$configuration->id] as $line)
                                                <span class="eta-cell__line">{{ $line['label'] }} :
                                                    <strong>{{ $line['value'] }}</strong></span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <label class="toggle-switch toggle-switch-sm" for="status-{{ $configuration->id }}">
                                            {{-- A row that is the only active estimate for one of its
                                                 modules carries the module names here. The script
                                                 below refuses the toggle and explains why, instead
                                                 of letting the confirm dialog through to a save the
                                                 server would only reject. --}}
                                            <input type="checkbox" class="toggle-switch-input dynamic-checkbox"
                                                id="status-{{ $configuration->id }}"
                                                {{ $configuration->status ? 'checked' : '' }}
                                                @if (!empty($lockedModules[$configuration->id]))
                                                    data-eta-locked="{{ implode(', ', $lockedModules[$configuration->id]) }}"
                                                @endif
                                                data-id="status-{{ $configuration->id }}" data-type="status"
                                                data-image-on="{{ asset('public/assets/admin/img/status-ons.png') }}"
                                                data-image-off="{{ asset('public/assets/admin/img/off-danger.png') }}"
                                                data-title-on="{{ translate('Turn on the status?') }}"
                                                data-title-off="{{ translate('Turn off the status?') }}"
                                                data-text-on="<p>{{ translate('messages.Are you sure, do you want to turn on this ETA configuration.') }}</p>"
                                                data-text-off="<p>{{ translate('messages.Are you sure, do you want to turn off this ETA configuration.') }}</p>">
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <form action="{{ route('admin.business-settings.zone.eta-configuration.status', [$configuration->id, $configuration->status ? 0 : 1]) }}"
                                            method="get" id="status-{{ $configuration->id }}_form"></form>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--edit"
                                                href="{{ route('admin.business-settings.zone.eta-configuration.edit', [$configuration->id]) }}"
                                                title="{{ translate('Edit') }}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--delete eta-configuration-delete-btn"
                                                href="javascript:" data-id="eta-configuration-{{ $configuration->id }}"
                                                title="{{ translate('Delete') }}">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{ route('admin.business-settings.zone.eta-configuration.delete', [$configuration->id]) }}"
                                                method="post" id="eta-configuration-{{ $configuration->id }}" class="d-none">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (count($configurations) !== 0)
                    <div class="page-area px-4 pb-3">
                        {!! $configurations->withQueryString()->links() !!}
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

    @include('admin-views.eta-configuration.partials._delete-modal')
    @include('admin-views.eta-configuration.partials._locked-status-modal')
@endsection

@push('script_2')
    <script>
        "use strict";

        let pendingDeleteFormId = null;

        $(document).on('click', '.eta-configuration-delete-btn', function () {
            pendingDeleteFormId = $(this).data('id');
            $('#eta-configuration-delete-modal').modal('show');
        });

        $(document).on('click', '#eta-configuration-delete-confirm', function () {
            if (pendingDeleteFormId) { $('#' + pendingDeleteFormId).submit(); }
        });

        @php($etaLockedText = translate('It is the only ETA configuration for these modules in this zone, and a zone and module pair can hold just one. Delete it instead, or disconnect them from the zone.'))
        $('input[data-eta-locked]').each(function () {
            $(this).on('click', function (event) {
                var modules = $(this).data('eta-locked');

                event.preventDefault();
                event.stopImmediatePropagation();
                $(this).prop('checked', true);

                $('#eta-status-locked-text').text(
                    @json($etaLockedText) + ' ' + @json(translate('messages.Modules')) + ': ' + modules
                );
                $('#eta-status-locked-modal').modal('show');
            });
        });
    </script>
@endpush
