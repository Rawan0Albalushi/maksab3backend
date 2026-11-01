@extends('layouts.admin.app')

@section('title',translate('Business modules'))

@push('css_or_js')
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/module-setup.css')}}">
@endpush

@section('content')
    @php
        $typeFilter = request('module_type');
        $statusFilter = request('status');
        $searchTerm = request('search');
        $hasType = $typeFilter && $typeFilter !== 'all';
        $hasStatus = $statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all';
        $isFiltered = filled($searchTerm) || $hasType || $hasStatus;
        $listUrl = route('admin.business-settings.module.index');
        $baseQuery = request()->except(['page']);
        $filterUrl = function (array $overrides) use ($baseQuery, $listUrl) {
            $query = array_filter(
                array_merge($baseQuery, $overrides),
                fn ($value) => $value !== null && $value !== '' && $value !== 'all'
            );
            return $query ? route('admin.business-settings.module.index', $query) : $listUrl;
        };
        $typeLabels = [
            'grocery' => translate('grocery'),
            'food' => translate('Food'),
            'pharmacy' => translate('pharmacy'),
            'ecommerce' => translate('ecommerce'),
            'parcel' => translate('Parcel'),
            'rental' => translate('Rental'),
            'ride-share' => translate('ride-share'),
            'service' => translate('Service'),
        ];
        $moduleTypes = array_values(array_filter(config('module.module_type'), fn ($key) =>
            ($key !== 'rental' || addon_published_status('Rental'))
            && ($key !== 'ride-share' || addon_published_status('RideShare'))
        ));
    @endphp
    <div class="content container-fluid mds">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{asset('public/assets/admin/img/module.png')}}" alt="" aria-hidden="true">
                    </span>
                    <span>
                        {{translate('Business module list')}}
                        <span class="badge badge-soft-dark ml-2" id="itemCount">{{$modules->total()}}</span>
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('Every line of business you run, and the zones each one is switched on in.') }}</p>
            </div>
            <div class="page-header-actions">
                <button type="button" class="mds-how" data-toggle="modal" data-target="#warning-status-modal">
                    <span>{{translate('How it works')}}</span>
                    <span class="blinkings"><i class="tio-info-outined"></i></span>
                </button>
            </div>
        </div>

        <div class="mds-stats">
            <a class="mds-stat{{ $hasStatus ? '' : ' is-active' }}" href="{{$filterUrl(['status' => null])}}" @if(!$hasStatus) aria-current="true" @endif>
                <span class="mds-stat__icon"><i class="tio-layers-outlined"></i></span>
                <span class="mds-stat__text">
                    <span class="mds-stat__value">{{$summary['total']}}</span>
                    <span class="mds-stat__label">{{translate('Business modules')}}</span>
                </span>
            </a>
            <a class="mds-stat mds-stat--on{{ $statusFilter === '1' ? ' is-active' : '' }}" href="{{$filterUrl(['status' => '1'])}}" @if($statusFilter === '1') aria-current="true" @endif>
                <span class="mds-stat__icon"><i class="tio-checkmark-circle-outlined"></i></span>
                <span class="mds-stat__text">
                    <span class="mds-stat__value">{{$summary['active']}}</span>
                    <span class="mds-stat__label">{{translate('Active and visible to customers')}}</span>
                </span>
            </a>
            <a class="mds-stat mds-stat--off{{ $statusFilter === '0' ? ' is-active' : '' }}" href="{{$filterUrl(['status' => '0'])}}" @if($statusFilter === '0') aria-current="true" @endif>
                <span class="mds-stat__icon"><i class="tio-pause-circle-outlined"></i></span>
                <span class="mds-stat__text">
                    <span class="mds-stat__value">{{$summary['inactive']}}</span>
                    <span class="mds-stat__label">{{translate('Turned off')}}</span>
                </span>
            </a>
            <div class="mds-stat mds-stat--static">
                <span class="mds-stat__icon"><i class="tio-category-outlined"></i></span>
                <span class="mds-stat__text">
                    <span class="mds-stat__value">{{$summary['types']}}</span>
                    <span class="mds-stat__label">{{translate('Module types in use')}}</span>
                </span>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <div class="mds-toolbar">
                    <form class="mds-toolbar__search search-form" role="search">
                        <div class="input-group input--group">
                            <input id="datatableSearch" name="search" type="search" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Search module by name')}}" aria-label="{{translate('Search')}}" value="{{$searchTerm}}">
                            <button type="submit" class="btn btn--secondary" aria-label="{{translate('messages.Search')}}"><i class="tio-search"></i></button>
                        </div>
                        @if($hasType)
                            <input type="hidden" name="module_type" value="{{$typeFilter}}">
                        @endif
                        @if($hasStatus)
                            <input type="hidden" name="status" value="{{$statusFilter}}">
                        @endif
                    </form>

                    <div class="mds-toolbar__group">
                        <select id="module_type" name="module_type" class="form-control mds-select set-filter" data-url="{{ url()->full() }}" data-filter="module_type" aria-label="{{ translate('messages.All module type') }}">
                            <option value="all" {{ $hasType ? '' : 'selected' }}>{{ translate('messages.All module type') }}</option>
                            @foreach ($moduleTypes as $key)
                                <option value="{{$key}}" {{ $typeFilter === $key ? 'selected' : '' }}>{{ $typeLabels[$key] ?? $key }}</option>
                            @endforeach
                        </select>

                        <select id="status" name="status" class="form-control mds-select set-filter" data-url="{{ url()->full() }}" data-filter="status" aria-label="{{ translate('All status') }}">
                            <option value="all" {{ $hasStatus ? '' : 'selected' }}>{{ translate('All status') }}</option>
                            <option value="1" {{ $statusFilter === '1' ? 'selected' : '' }}>{{ translate('messages.Active') }}</option>
                            <option value="0" {{ $statusFilter === '0' ? 'selected' : '' }}>{{ translate('messages.Inactive') }}</option>
                        </select>
                    </div>

                    <div class="mds-toolbar__group mds-toolbar__group--actions">
                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-white dropdown-toggle" href="javascript:;"
                                data-hs-unfold-options='{
                                        "target": "#usersExportDropdown",
                                        "type": "css-animation"
                                    }'>
                                <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                            </a>

                            <div id="usersExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a id="export-excel" class="dropdown-item" href="{{route('admin.business-settings.module.export', ['type'=>'excel',request()->getQueryString()])}}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                        alt="" aria-hidden="true">
                                    Excel
                                </a>
                                <a id="export-csv" class="dropdown-item" href="{{route('admin.business-settings.module.export', ['type'=>'csv',request()->getQueryString()])}}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                        alt="" aria-hidden="true">
                                    CSV
                                </a>
                            </div>
                        </div>
                        <a href="{{ route('admin.business-settings.module.create') }}" class="btn btn--primary"><i class="tio-add-circle"></i> {{translate('Add new module')}}</a>
                    </div>
                </div>
            </div>

            @if($isFiltered)
                <div class="mds-filters">
                    <span class="mds-filters__label">{{translate('messages.Filter by')}}</span>
                    @if(filled($searchTerm))
                        <a class="mds-chip" href="{{$filterUrl(['search' => null])}}">
                            <span class="mds-chip__key">{{translate('messages.Search')}}</span>
                            <span class="mds-chip__value">{{$searchTerm}}</span>
                            <i class="tio-clear" aria-hidden="true"></i>
                        </a>
                    @endif
                    @if($hasType)
                        <a class="mds-chip" href="{{$filterUrl(['module_type' => null])}}">
                            <span class="mds-chip__key">{{translate('Type')}}</span>
                            <span class="mds-chip__value">{{ $typeLabels[$typeFilter] ?? $typeFilter }}</span>
                            <i class="tio-clear" aria-hidden="true"></i>
                        </a>
                    @endif
                    @if($hasStatus)
                        <a class="mds-chip" href="{{$filterUrl(['status' => null])}}">
                            <span class="mds-chip__key">{{translate('messages.Status')}}</span>
                            <span class="mds-chip__value">{{ $statusFilter === '1' ? translate('messages.Active') : translate('messages.Inactive') }}</span>
                            <i class="tio-clear" aria-hidden="true"></i>
                        </a>
                    @endif
                    <a class="mds-filters__clear" href="{{$listUrl}}">{{translate('Clear filters')}}</a>
                </div>
            @endif

            <div class="table-responsive datatable-custom">
                <table id="columnSearchDatatable"
                    class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                    data-hs-datatables-options='{
                        "isResponsive": false,
                        "isShowPaging": false,
                        "paging":false
                    }'>
                    <thead class="thead-light">
                        <tr>
                            <th scope="col" class="border-0">{{translate('messages.Module')}}</th>
                            <th scope="col" class="border-0">{{translate('messages.zones')}}</th>
                            <th scope="col" class="border-0">{{translate('Stores & vendors')}}</th>
                            <th scope="col" class="border-0">{{translate('messages.Items')}}</th>
                            <th scope="col" class="border-0">{{translate('messages.Status')}}</th>
                            <th scope="col" class="border-0 text-center">{{translate('messages.Action')}}</th>
                        </tr>
                    </thead>

                    <tbody id="table-div">
                    @include('admin-views.module.partials._table', [
                        'modules' => $modules,
                        'metrics' => $metrics,
                    ])
                    </tbody>
                </table>
            </div>
            @if(count($modules) !== 0)
            <hr>
            @endif
            <div class="page-area">
                {!! $modules->links() !!}
            </div>
            @if(count($modules) === 0)
            <div class="empty--data">
                <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                @if($isFiltered)
                    <h5>{{translate('No module matches these filters')}}</h5>
                    <p class="mds-empty__text">
                        {{translate('Try a different name, module type or status — or clear the filters to see every module.')}}
                    </p>
                    <a href="{{route('admin.business-settings.module.index')}}" class="btn btn--primary">
                        <i class="tio-clear-circle-outlined"></i> {{translate('Clear filters')}}
                    </a>
                @else
                    <h5>{{translate('No business module yet')}}</h5>
                    <p class="mds-empty__text">
                        {{translate('A business module is the kind of business a store runs — food, grocery, pharmacy or parcel. Add one to start onboarding stores.')}}
                    </p>
                    <a href="{{route('admin.business-settings.module.create')}}" class="btn btn--primary">
                        <i class="tio-add-circle"></i> {{translate('Add new module')}}
                    </a>
                @endif
            </div>
            @endif
        </div>
    </div>


    <div class="modal fade" id="warning-status-modal">
        <div class="modal-dialog modal-lg warning-status-modal">
            <div class="modal-content">
                <div class="modal-header pb-0">
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true" class="tio-clear"></span>
                    </button>
                </div>
                <div class="single-item-slider owl-carousel">
                    <div class="item">
                        <div class="modal-header pt-0">
                            <h2 class="modal-title">{{translate('How does it work?')}}</h2>
                        </div>
                        <div class="modal-body">
                            <div class="how-it-works">
                                <div class="item">
                                    <img src="{{asset('/public/assets/admin/img/how/how1.png')}}" class="h-60px object-contain object-left" alt="">
                                    <h2 class="serial">1</h2>
                                    <h5>{{ translate('Create business module') }}</h5>
                                    <p>
                                        {{ translate('To create a new business module, go to:') . ' ‘Module Setup’ → ‘Add Business Module.’'}}
                                    </p>
                                </div>
                                <div class="item">
                                    <img src="{{asset('/public/assets/admin/img/how/how2.png')}}" class="h-60px object-contain object-left" alt="">
                                    <h2 class="serial">2</h2>
                                    <h5>{{ translate('Add module to zone') }}</h5>
                                    <p>
                                        {{ translate('Go to') . ' ‘Zone Setup’→ ‘Business Zone List’→ ‘Zone Settings’→ Choose Payment Method→Add Business Module into Zone with Parameters.' }}
                                    </p>
                                </div>
                                <div class="item mw-100">
                                    <img src="{{asset('/public/assets/admin/img/how/how3.png')}}" class="h-60px object-contain object-left" alt="">
                                    <h2 class="serial">3</h2>
                                    <h5>{{ translate('Create stores') }}</h5>
                                    <p>
                                        {{ translate('Select your module from the module section, click') . ' → ’Store Management’→’Add Store’→Add Store details & select Zone to integrate Module+Zone+Store.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="item">
                        <div class="modal-body py-0">
                            <div class="text-center ">
                                <h3 class="modal-title mb-3">{{translate('Please go to settings and select module for this zone')}}</h3>
                                <p class="txt">
                                    {{translate("Otherwise this zone will not function properly and will not show anything for this zone")}}
                                </p>
                            </div>
                            <img src="{{asset('/public/assets/admin/img/zone-settings-popup-arro.gif')}}" alt="admin/img" class="w-100 h-unset">
                        </div>
                    </div>
                    <div class="item px-xl-4">
                        <div class="d-flex align-items-center">
                            <div class="col-sm-4 text-14">
                                <h4>{{translate('Make sure')}}</h4>
                                <p>
                                    {{translate('Keep module details well structured — they appear on your landing page.')}}
                                </p>
                            </div>
                            <div class="col-sm-8">
                                <img src="{{asset('/public/assets/admin/img/module2.png')}}" alt="admin/img" class="w-100 h-unset">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-center pb-5">
                    <div class="slide-counter"></div>
                </div>
            </div>
        </div>
    </div>

@endsection
