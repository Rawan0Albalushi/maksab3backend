@extends('layouts.vendor.app')

@section('title',translate('Main subcategory'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/categories.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('Main subcategory')}} <span class="badge badge-soft-dark ml-2" id="itemCount">{{$categories->total()}}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Subcategories that sit under a main one and narrow what customers are browsing.') }}</p>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper justify-content-end">
                            @include('partials._table-head', [
                                'subtitle' => translate('Subcategories grouped under each of your categories.'),
                            ])

                            <form  class="search-form min--280">
                                @csrf
                                <div class="input-group input--group">
                                    <input   value="{{ request()?->search ?? null }}" type="search" name="search" class="form-control" placeholder="{{translate('messages.Ex') . ' : ' . translate('Search main subcategory')}}" aria-label="{{translate('Search')}}">
                                    <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                                </div>
                            </form>
                            <div class="hs-unfold mr-2">
                                <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px" href="javascript:"
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
                                    <a id="export-excel" class="dropdown-item" href="{{route('vendor.category.export-sub-categories',['type'=>'excel',request()->getQueryString()])}}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                                src="{{asset('public/assets/admin/svg/components/excel.svg')}}"
                                                alt="Image Description">
                                        Excel
                                    </a>
                                    <a id="export-csv" class="dropdown-item" href="{{route('vendor.category.export-sub-categories', ['type'=>'csv',request()->getQueryString()])}}">
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
                                        <th class="border-0">{{translate('Main subcategory')}}</th>
                                        <th class="border-0">{{translate('Main category')}}</th>
                                        <th class="border-0 text-center">{{translate('messages.Status')}}</th>
                                        <th class="border-0 text-center">{{translate('messages.Priority')}}</th>
                                    </tr>
                                </thead>

                                <tbody id="set-rows">
                                @foreach($categories as $category)
                                    <tr>
                                        <td>
                                            <div class="media-area d-flex gap-2 align-items-center">
                                                <div class="w-40px min-w-40 h-40px rounded overflow-hidden border">
                                                    <img src="{{ $category['image_full_url'] }}" alt="{{ $category->name }}" class="w-100 rounded object-cover onerror-image"
                                                         data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}">
                                                </div>
                                                <div>
                                                    <span class="fs-14 line--limit-2 text-title max-w-250 min-w-160" title="{{ $category->name }}">
                                                        {{Str::limit($category->name,30,'...')}}
                                                    </span>
                                                    <p class="m-0">ID #{{ $category->id }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="d-block font-size-sm text-body">
                                                {{Str::limit($category->parent?$category->parent['name']:translate('messages.Category deleted'),20,'...')}}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-soft-{{ $category->status ? 'success' : 'danger' }}">
                                                {{ $category->status ? translate('messages.Active') : translate('messages.Inactive') }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="d-inline-block {{ $category->priority == 0 ? 'text-title' : '' }} {{ $category->priority == 1 ? 'text-info' : '' }} {{ $category->priority == 2 ? 'text-success' : '' }}">
                                                @if ($category->priority == 2)
                                                    {{ translate('messages.High') }}
                                                @elseif ($category->priority == 1)
                                                    {{ translate('messages.medium') }}
                                                @else
                                                    {{ translate('messages.Normal') }}
                                                @endif
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer page-area">
                        {!! $categories->links() !!}
                        @if(count($categories) === 0)
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
    </div>
@endsection


