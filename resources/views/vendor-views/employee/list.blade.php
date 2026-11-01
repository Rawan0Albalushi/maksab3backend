@extends('layouts.vendor.app')
@section('title',translate('messages.Employee list'))
@push('css_or_js')

@endpush

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{asset('public/assets/admin/img/role.png')}}" class="w--26" alt="">
                    </span>
                    <span>
                        {{translate('messages.Employee list')}}
                        <span class="badge badge-soft-dark ml-2" id="itemCount">{{$em->total()}}</span>
                    </span>

                </h1>
                <p class="page-header-desc">{{ translate('Everyone on your team, the role each holds and when they joined.') }}</p>
            </div>
            <a href="{{route('vendor.employee.add-new')}}" class="btn btn--primary mb-2">
                <i class="tio-add-circle"></i>
                <span class="text">{{translate('messages.Add new employee')}}</span>
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2 border-0">
            <div class="search--button-wrapper">
                @include('partials._table-head', [
                    'subtitle' => translate('messages.Employees sign in with their own credentials and only see what their role allows.'),
                ])
                <form  class="search-form">

                    <div class="input-group input--group">
                        <input  value="{{  request()?->search ?? null }}"  type="search" name="search" class="form-control" placeholder="{{ translate('messages.Ex') }}: {{translate('Search by name or email')}}" aria-label="Search">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
                <div class="hs-unfold mr-2">
                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px" href="javascript:;"
                        data-hs-unfold-options='{
                            "target": "#usersExportDropdown",
                            "type": "css-animation"
                        }'>
                        <i class="tio-download-to mr-1"></i> {{translate('messages.Export')}}
                    </a>

                    <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                        <span
                            class="dropdown-header">{{translate('messages.Download options')}}</span>
                        <a id="export-excel" class="dropdown-item" href="{{route('vendor.employee.export-employee', ['type'=>'excel',request()->getQueryString()])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{asset('public/assets/admin/svg/components/excel.svg')}}"
                                    alt="Image Description">
                            Excel
                        </a>
                        <a id="export-csv" class="dropdown-item" href="{{route('vendor.employee.export-employee', ['type'=>'csv',request()->getQueryString()])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{asset('public/assets/admin/svg/components/placeholder-csv-format.svg')}}"
                                    alt="Image Description">
                            .csv
                        </a>

                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{ translate('messages.Employee') }}</th>
                            <th class="border-0">{{ translate('messages.Contact') }}</th>
                            <th class="border-0">{{ translate('messages.Role') }}</th>
                            <th class="border-0">{{ translate('messages.Added') }}</th>
                            <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                        </tr>
                    </thead>
                    <tbody id="set-rows">
                        @foreach($em as $e)
                            @php($is_self = $current_employee_id == $e->id)
                            <tr>
                                <td>
                                    <span class="table-rest-info">
                                        <img class="img--60 rounded-circle onerror-image" data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                             src="{{ $e->image_full_url }}" alt="{{ $e->f_name }}">
                                        <span class="info max-w-200px">
                                            <span class="d-block text--title text-capitalize line--limit-1">{{ $e->f_name }} {{ $e->l_name }}</span>
                                            <span class="d-block font-light">ID:{{ $e->id }}</span>
                                            @if($is_self)
                                                <span class="cell-chips mt-1"><span class="cell-chip">{{ translate('messages.You') }}</span></span>
                                            @endif
                                        </span>
                                    </span>
                                </td>
                                <td>
                                    <a class="d-block text-body text-break" href="mailto:{{ $e->email }}">{{ $e->email }}</a>
                                    <a class="d-block fs-12 text-muted" href="tel:{{ $e->phone }}">{{ $e->phone }}</a>
                                </td>
                                <td>
                                    @if($e->role)
                                        <span class="badge badge-soft-info">{{ $e->role->name }}</span>
                                    @else
                                        <span class="badge badge-soft-danger">{{ translate('messages.Role deleted') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="table-when">
                                        <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($e->created_at) }}</span>
                                        <span class="table-when__ago" title="{{ \App\CentralLogics\Helpers::time_date_format($e->created_at) }}">{{ \Carbon\Carbon::parse($e->created_at)->diffForHumans() }}</span>
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($is_self)
                                        <span class="text-muted" title="{{ translate('messages.You cannot edit or remove your own account.') }}">&mdash;</span>
                                    @else
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--edit" href="{{ route('vendor.employee.edit', [$e->id]) }}" title="{{ translate('Edit employee') }}"><i class="tio-edit"></i></a>
                                            <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="employee-{{ $e->id }}"
                                               data-message="{{ translate('messages.This employee will lose access to your panel straight away.') }}"
                                               title="{{ translate('Delete employee') }}"><i class="tio-delete-outlined"></i></a>
                                        </div>
                                        <form action="{{ route('vendor.employee.delete', [$e->id]) }}" method="post" id="employee-{{ $e->id }}">
                                            @csrf @method('delete')
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if(count($em) === 0)
            <div class="empty--data">
                <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                @if(request('search'))
                    <h5>{{ translate('messages.No employee matches your search.') }}</h5>
                    <p class="text-muted font-size-sm">{{ translate('Try a different name, email or phone number.') }}</p>
                @else
                    <h5>{{ translate('messages.No employee added yet.') }}</h5>
                    <p class="text-muted font-size-sm">{{ translate('Add someone to your team and give them a role so they can sign in.') }}</p>
                @endif
            </div>
        @else
            <div class="page-area px-4 pb-3">
                {!! $em->withQueryString()->links() !!}
            </div>
        @endif
    </div>
</div>
@endsection

