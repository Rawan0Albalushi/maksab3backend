@extends('layouts.admin.app')
@section('title',translate('Employee list'))
@push('css_or_js')

@endpush


@section('employee_list')
active
@endsection

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{asset('public/assets/admin/img/role.png')}}" class="w--26" alt="">
                    </span>
                    <span>
                        {{translate('messages.Employee list')}}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('Everyone on your team, the role each holds and when they joined.') }}</p>
            </div>
            <a href="{{route('admin.users.employee.add-new')}}" class="btn btn--primary">
                <i class="tio-add-circle"></i>
                <span class="text">{{translate('Add new')}}</span>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        <h5 class="card-title">{{translate('messages.Employee table')}} <span class="badge badge-soft-dark ml-2" id="itemCount">{{$employees->total()}}</span></h5>
                        <form class="search-form min--200">
                            <div class="input-group input--group">
                                <input id="datatableSearch_" type="search" name="search"  value="{{ request()->input('search') }}" class="form-control" placeholder="{{translate('messages.Ex') . ' : ' . translate('Search by name or email')}}" aria-label="Search">
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>

                        @if(request()->input('search'))
                        <button type="reset" class="btn btn--primary ml-2 location-reload-to-base" data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                        @endif

                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--45px min-height-40" href="javascript:;"
                            data-hs-unfold-options='{
                                    "target": "#usersExportDropdown",
                                    "type": "css-animation"
                                }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{route('admin.users.employee.export', array_merge(request()->query(), ['type' => 'excel']))}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{route('admin.users.employee.export', array_merge(request()->query(), ['type' => 'csv']))}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                CSV
                            </a>
                        </div>
                    </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive datatable-custom">
                        <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100">
                            <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('messages.Employee') }}</th>
                                <th class="border-0">{{ translate('messages.Contact') }}</th>
                                <th class="border-0">{{ translate('messages.Role') }}</th>
                                <th class="border-0">{{ translate('messages.Zone') }}</th>
                                <th class="border-0">{{ translate('messages.Added') }}</th>
                                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                            </thead>
                            <tbody id="set-rows">
                            @include('admin-views.employee.partials._table')
                            </tbody>
                        </table>
                    </div>
                </div>
                @if(count($employees) !== 0)
                <hr>
                @endif
                <div class="page-area">
                    {!! $employees->withQueryString()->links() !!}
                </div>
                @if(count($employees) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('script_2')

@endpush
