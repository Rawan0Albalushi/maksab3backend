@extends('layouts.admin.app')

@section('title',translate('messages.Add new condition'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/condition.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('messages.Common Condition Setup')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('The wear labels stores pick from when listing a second-hand item, such as new or used.') }}</p>
        </div>
        <div class="card">
            <div class="card-body">
                <form action="{{route('admin.common-condition.store')}}" method="post">
                    @csrf
                    <div class="mb-20">
                        <h3 class="mb-1 fs-18">{{ translate('Add Common Condition') }}</h3>
                        <p class="mb-0">{{ translate('Here you can add and manage common condition names that will be displayed to customers.') }}</p>
                    </div>
                    <div class="bg-light2 rounded p-20">
                        @if($language)
                            <ul class="nav nav-tabs mb-4">
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
                            <div class="form-group lang_form" id="default-form">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}} ({{ translate('Default') }})</label>
                                <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.New condition')}}" maxlength="191">
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                            @foreach($language as $lang)
                                <div class="form-group d-none lang_form" id="{{$lang}}-form">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}} ({{strtoupper($lang)}})</label>
                                    <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.New condition')}}" maxlength="191">
                                </div>
                                <input type="hidden" name="lang[]" value="{{$lang}}">
                            @endforeach
                        @else
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}}</label>
                                <input type="text" name="name" class="form-control" placeholder="{{translate('messages.New condition')}}" value="{{old('name')}}" maxlength="191">
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                        @endif
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                            <button type="submit" class="btn btn--primary"><i class="{{ isset($condition) ? 'tio-save' : 'tio-add-circle' }}"></i> {{isset($condition)?translate('Update'):translate('Add')}}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card mt-20">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'title'    => translate('Common conditions'),
                        'subtitle' => translate('messages.Item conditions such as new or used that vendors can choose.'),
                        'count'    => $conditions->total(),
                        'count_id' => 'itemCount',
                    ])
                    <form  class="search-form">
                        <div class="input-group input--group">
                            <input id="datatableSearch" name="search" value="{{ request()?->search ?? null }}"  type="search" class="form-control" placeholder="{{translate('messages.Search by name')}}" aria-label="{{translate('Common conditions')}}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
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
                            <a id="export-excel" class="dropdown-item" href="
                                {{ route('admin.campaign.basic_campaign_export', ['type' => 'excel', request()->getQueryString()]) }}
                                ">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="
                            {{ route('admin.campaign.basic_campaign_export', ['type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive datatable-custom">
                    <table id="columnSearchDatatable"
                        class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                        data-hs-datatables-options='{
                            "search": "#datatableSearch",
                            "entries": "#datatableEntries",
                            "isResponsive": false,
                            "isShowPaging": false,
                            "paging":false
                        }'>
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{translate('SL')}}</th>
                                <th class="border-0 w--1">{{translate('messages.Common Condition Name')}}</th>
                                <th class="border-0 text-center">{{translate('messages.Total Products')}}</th>
                                <th class="border-0 text-center">{{translate('messages.Status')}}</th>
                                <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                            </tr>
                        </thead>

                        <tbody id="table-div">
                        @foreach($conditions as $key=>$condition)
                            <tr>
                                <td class="title-clr fs-14">{{$key+$conditions->firstItem()}}</td>
                                <td>
                                    <span class="d-block fs-14 title-clr cursor-pointer offcanvas-trigger data-info-show"
                                    data-id="{{ $condition->id }}"
                                    data-url="{{route('admin.common-condition.view',$condition->id)}}"
                                    data-target="#offcanvas_common_condition">
                                        {{Str::limit($condition['name'],20,'...')}}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="d-block fs-14 title-clr cursor-pointer offcanvas-trigger data-info-show"
                                    data-id="{{ $condition->id }}"
                                    data-url="{{route('admin.common-condition.view',$condition->id)}}"
                                    data-target="#offcanvas_common_condition">
                                        {{ $condition->items_count }}
                                    </span>
                                </td>
                                <td>
                                    <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$condition->id}}">
                                    <input type="checkbox" data-url="{{route('admin.common-condition.status',[$condition['id'],$condition->status?0:1])}}" class="toggle-switch-input redirect-url" id="stocksCheckbox{{$condition->id}}" {{$condition->status?'checked':''}}>
                                        <span class="toggle-switch-label mx-auto">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <button type="#0" class="btn action-btn action-btn--view offcanvas-trigger data-info-show"
                                        data-id="{{ $condition->id }}"
                                    data-url="{{route('admin.common-condition.view',$condition->id)}}"
                                        data-target="#offcanvas_common_condition">
                                            <i class="tio-visible-outlined"></i>
                                        </button>
                                        <a class="btn action-btn action-btn--edit"
                                            href="{{route('admin.common-condition.edit',[$condition['id']])}}" title="{{translate('messages.Edit condition')}}"><i class="tio-edit"></i>
                                        </a>
                                        <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="condition-{{$condition['id']}}" data-message="{{ translate('Want to delete this condition?') }}"  title="{{translate('messages.Delete condition')}}"><i class="tio-delete-outlined"></i>
                                        </a>
                                        <form action="{{route('admin.common-condition.delete',[$condition['id']])}}" method="post" id="condition-{{$condition['id']}}">
                                            @csrf @method('delete')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if(count($conditions) !== 0)
            <hr class="border-0">
            @endif
            <div class="page-area">
                {!! $conditions->links() !!}
            </div>
            @if(count($conditions) === 0)
            <div class="empty--data">
                <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                <h5>
                    {{translate('No data found')}}
                </h5>
            </div>
            @endif
        </div>
    </div>



    <div id="offcanvas_common_condition" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100">
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>




@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/common-condition-index.js"></script>
@endpush
