@extends('layouts.admin.app')

@section('title',translate('messages.units'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/category.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('messages.Add new unit')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Measurement units stores choose from when adding an item, such as kg or litre.') }}</p>
        </div>
        <div class="row g-3">
            <div class="col-12 tps">
                <div class="tps-card">
                    {{-- custom-validation + .error-wrapper: the shared jQuery Validate
                         layer, so a missing default name fails inline instead of after
                         a round trip. UnitAddRequest still enforces it server side. --}}
                    <form action="{{route('admin.unit.store')}}" method="post" class="custom-validation"
                        id="unit-add-form"
                        data-ajax-form
                        data-ajax-refresh="[data-ajax-region]"
                        data-ajax-reset>
                        @csrf
                        <div class="tps-card__body">
                            <p class="tps-card__subtitle mb-3">
                                {{ translate('A unit says how an item is sold — per kg, per litre, per piece. Vendors pick one when they add an item.') }}
                            </p>

                            @if ($language)
                                <ul class="nav nav-tabs mb-3 border-0">
                                    <li class="nav-item">
                                        <a class="nav-link lang_link active"
                                        href="#"
                                        id="default-link">{{translate('Default')}}</a>
                                    </li>
                                    @foreach ($language as $lang)
                                        <li class="nav-item">
                                            <a class="nav-link lang_link"
                                                href="#"
                                                id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="lang_form" id="default-form">
                                    <div class="tps-field">
                                        <div class="error-wrapper">
                                            <label class="tps-field__label" for="default_title">
                                                {{ translate('Name') }} ({{translate('Default')}})
                                                <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                      data-original-title="{{ translate('messages.Required.')}}">*</span>
                                            </label>
                                            <input type="text" name="unit[]" id="default_title" class="form-control unit-input"
                                                   placeholder="{{ translate('messages.Ex') }}: kg" maxlength="191" required>
                                        </div>
                                        <small class="tps-field__hint">
                                            {{ translate('Short lowercase names read best in the vendor panel and the customer app.') }}
                                        </small>
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                                @foreach ($language as $lang)
                                    <div class="d-none lang_form" id="{{ $lang }}-form">
                                        <div class="tps-field">
                                            <label class="tps-field__label" for="{{ $lang }}_title">
                                                {{ translate('Name') }} ({{ strtoupper($lang) }})
                                                <span class="tps-opt">{{ translate('Optional') }}</span>
                                            </label>
                                            <input type="text" name="unit[]" id="{{ $lang }}_title" class="form-control unit-input"
                                                   placeholder="{{ translate('messages.Unit name') }}" maxlength="191">
                                            <small class="tps-field__hint">
                                                {{ translate('Leave it empty to fall back to the default name.') }}
                                            </small>
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                                    </div>
                                @endforeach
                            @else
                                <div id="default-form">
                                    <div class="tps-field">
                                        <div class="error-wrapper">
                                            <label class="tps-field__label" for="default_title">
                                                {{ translate('Name') }} ({{ translate('Default') }})
                                                <span class="tps-req">*</span>
                                            </label>
                                            <input type="text" name="unit[]" id="default_title" class="form-control unit-input"
                                                   placeholder="{{ translate('messages.Ex') }}: kg" maxlength="191" required>
                                        </div>
                                        <small class="tps-field__hint">
                                            {{ translate('Short lowercase names read best in the vendor panel and the customer app.') }}
                                        </small>
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                            @endif

                            <div class="d-flex flex-wrap align-items-center mt-3">
                                <small class="tps-field__hint mr-2 mt-0">{{ translate('Examples') }}:</small>
                                @foreach (['kg', 'g', 'litre', 'ml', 'piece', 'dozen', 'pack'] as $example)
                                    <button type="button" class="badge badge-soft-primary border-0 text-lowercase unit-example mr-1 mb-1">{{ $example }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="tps-card__foot">
                            <span class="tps-foot-note">{{ translate('A unit name can only be used once.') }}</span>
                            <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                            <button type="submit" class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{translate('messages.Submit')}}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-12">
                <div class="card" id="unit-list-wrapper" data-ajax-region
                    data-ajax-url="{{ url()->full() }}"
                    data-ajax-links=".page-link, .list-reset-search"
                    data-ajax-forms=".search-form">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper">
                            @include('partials._table-head', [
                                'title'    => translate('messages.Unit list'),
                                'subtitle' => translate('messages.Measurement units vendors choose from when adding an item.'),
                                'count'    => $units->total(),
                                'count_id' => 'itemCount',
                            ])
                            <form class="search-form">

                                <div class="input-group input--group">
                                    <input id="datatableSearch_" type="search" name="search" class="form-control"  value="{{request()?->search}}"
                                            placeholder="{{translate('messages.Search unit')}}" aria-label="{{translate('messages.Search unit')}}" >
                                    <button type="submit" class="btn btn--secondary">
                                        <i class="tio-search"></i>
                                    </button>
                                </div>
                            </form>

                            @if(request()->input('search'))
                            <a class="btn btn--primary ml-2 list-reset-search" href="{{ request()->fullUrlWithoutQuery(['search', 'page']) }}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</a>
                            @endif

                            <div class="hs-unfold mr-2">
                                <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                                    data-hs-unfold-options='{
                                            "target": "#usersExportDropdown",
                                            "type": "css-animation"
                                        }'>
                                    <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                                </a>

                                <div id="usersExportDropdown"
                                    class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                    <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                    <a id="export-excel" class="dropdown-item" href="{{route('admin.unit.export', ['type'=>'excel',request()->getQueryString()]) }}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                            src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                            alt="Image Description">
                                        Excel
                                    </a>
                                    <a id="export-csv" class="dropdown-item" href="{{route('admin.unit.export', ['type'=>'csv',request()->getQueryString()]) }}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                            src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                            alt="Image Description">
                                        CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive datatable-custom">
                        <table id="columnSearchDatatable"
                               class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                               data-hs-datatables-options='{
                                 "order": [],
                                 "orderCellsTop": true,
                                 "paging":false
                               }'>
                            <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{translate('Unit')}}</th>
                                <th class="border-0">{{translate('messages.Translations')}}</th>
                                <th class="border-0 col--numeric">{{translate('messages.Used in items')}}</th>
                                <th class="border-0 col--numeric">{{translate('messages.Stores')}}</th>
                                <th class="border-0">{{translate('messages.Modules')}}</th>
                                <th class="border-0">{{translate('messages.Created at')}}</th>
                                <th class="border-0">{{translate('messages.Last updated')}}</th>
                                <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                            </tr>

                            </thead>

                            <tbody id="set-rows">
                            @foreach($units as $unit)
                                @include('admin-views.unit.partials._row', ['unit' => $unit])
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(count($units) !== 0)
                    <hr>
                    @endif
                    <div class="page-area">
                        {!! $units->links() !!}
                    </div>
                    @if(count($units) === 0)
                    <div class="empty--data">
                        <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                        @if(request()->input('search'))
                            <h5>{{translate('messages.No unit matches your search.')}}</h5>
                            <p class="text-muted font-size-sm">{{translate('Try a different spelling, or clear the search to see every unit.')}}</p>
                        @else
                            <h5>{{translate('messages.No unit added yet.')}}</h5>
                            <p class="text-muted font-size-sm">{{translate('Add your first one with the form above — kg, litre or piece are good starting points.')}}</p>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')

    <script>
        "use strict";
        $(document).on('ready', function () {
            $.HSCore.components.HSDatatables.init($('#columnSearchDatatable'));
        });

        if (window.AppAjax) {
            window.AppAjax.onMount(function ($root) {
                $root.find('#columnSearchDatatable').each(function () {
                    $.HSCore.components.HSDatatables.init($(this));
                });
            });
        }

        /* Fills whichever language tab is open, so an example can seed a translation too. */
        $(document).on('click', '.unit-example', function () {
            let $input = $('.lang_form:not(.d-none) .unit-input').first();

            if (!$input.length) {
                $input = $('.unit-input').first();
            }

            $input.val($(this).text().trim()).trigger('input').trigger('focus');
        });
    </script>
@endpush
