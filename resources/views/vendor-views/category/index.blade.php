@extends('layouts.vendor.app')

@section('title', translate('Main category'))

@push('css_or_js')
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/category.svg') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('Main category list') }} <span class="badge badge-soft-dark ml-2"
                        id="itemCount">{{ $categories->total() }}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('The top-level groups customers browse your store by.') }}</p>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper justify-content-end">
                            @include('partials._table-head', [
                                'subtitle' => translate('messages.Categories your items are grouped under.'),
                            ])

                            <form class="search-form">

                                <div class="input-group input--group">
                                    <input type="search" value="{{ request()?->search ?? null }}" name="search"
                                        class="form-control min-h-40px"
                                        placeholder="{{ translate('messages.Search main categories') }}"
                                        aria-label="{{ translate('Ex') }}: Categories">
                                    <button type="submit" class="btn btn--secondary py-2 min-h-40px"><i
                                            class="tio-search"></i></button>
                                </div>
                            </form>
                            <div class="hs-unfold mr-2">
                                <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px"
                                    href="javascript:"
                                    data-hs-unfold-options='{
                                        "target": "#usersExportDropdown",
                                        "type": "css-animation"
                                    }'>
                                    <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                                </a>

                                <div id="usersExportDropdown"
                                    class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                                    <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                    <a id="export-excel" class="dropdown-item"
                                        href="{{ route('vendor.category.export-categories', ['type' => 'excel', request()->getQueryString()]) }}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                            src="{{ asset('public/assets/admin/svg/components/excel.svg') }}"
                                            alt="Image Description">
                                        Excel
                                    </a>
                                    <a id="export-csv" class="dropdown-item"
                                        href="{{ route('vendor.category.export-categories', ['type' => 'csv', request()->getQueryString()]) }}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                            src="{{ asset('public/assets/admin/svg/components/placeholder-csv-format.svg') }}"
                                            alt="Image Description">
                                        CSV
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
                                        <th class="px-4 border-0">
                                            {{ translate('Main category name') }}
                                        </th>
                                        <th class="border-0 col--numeric">{{ translate('Subcategories') }}</th>

                                        @if ($categoryWiseTax)
                                            <th class="border-0 ">{{ translate('VAT/tax') }}</th>
                                        @endif
                                        <th class="border-0 text-center">{{ translate('messages.Status') }}</th>
                                        <th class="border-0 text-center">
                                            {{ translate('messages.Priority') }}
                                        </th>
                                    </tr>
                                </thead>

                                <tbody id="table-div">
                                    @foreach ($categories as $category)
                                        <tr>
                                            <td class="px-4">
                                                <div class="media-area d-flex gap-2 align-items-center">
                                                    <div class="w-40px min-w-40 h-40px rounded overflow-hidden border">
                                                        <img src="{{  $category['image_full_url'] }}" alt="{{ $category['name'] }}" class="w-100 rounded object-cover onerror-image"
                                                             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}">
                                                    </div>
                                                    <div>
                                                        <span class="fs-14 line--limit-2 text-title max-w-250 min-w-160" title="{{ $category['name'] }}">
                                                            {{ Str::limit($category['name'], 30, '...') }}
                                                        </span>
                                                        <p class="m-0">ID #{{ $category->id }}</p>
                                                    </div>
                                                </div>

                                            </td>
                                            <td class="col--numeric" data-order="{{ $category->childes_count }}">
                                                @if ($category->childes_count)
                                                    {{ $category->childes_count }}
                                                @else
                                                    <span class="text-muted font-size-sm">{{ translate('messages.N/A') }}</span>
                                                @endif
                                            </td>



                                            @if ($categoryWiseTax)
                                            <td>
                                                <span class="d-block font-size-sm text-body">
                                                    @forelse ($category?->taxVats?->pluck('tax.name', 'tax.tax_rate')->toArray() as $key => $tax)
                                                        <span class="bg-light rounded py-2 px-3">
                                                             {{ $tax }} :
                                                             <span class="font-light">
                                                                ({{ $key }}%)
                                                            </span>
                                                        </span>
                                                        <br>
                                                    @empty
                                                        <span> {{ translate('messages.No tax') }} </span>
                                                    @endforelse
                                                </span>
                                            </td>
                                            @endif
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
                        @if (count($categories) === 0)
                            <div class="empty--data">
                                <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                                <h5>
                                    {{ translate('No data found') }}
                                </h5>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>


    </div>

    <div id="offcanvas__categoryBtn" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100">
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

@endsection

