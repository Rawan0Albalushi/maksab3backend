@extends('layouts.vendor.app')

@section('title',translate('Banner'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/banner-form.css') }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="tps bnr">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{asset('public/assets/admin/img/outline/stock.svg')}}" class="w--26" alt="">
                        </span>
                        <span>
                            {{translate('Banner setup')}}
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Artwork shown at the top of your store page, linking to an item or an offer.') }}</p>
                </div>
                <button type="button" class="tps-help" data-toggle="modal" data-target="#banner-how-it-works">
                    <i class="tio-help-outlined"></i>
                    <span>{{ translate('How it works') }}</span>
                </button>
            </div>

            <div class="row g-3">
                <div class="col-xl-8">
                    <form action="{{ route('vendor.banner.store') }}" method="POST" enctype="multipart/form-data"
                          class="custom-validation" id="banner_form">
                        @csrf
                        @include('vendor-views.banner.partials._form', [
                            'banner' => null,
                            'submitLabel' => translate('messages.Submit'),
                            'submitIcon' => 'tio-checkmark-circle-outlined',
                        ])
                    </form>
                </div>

                <div class="col-xl-4">
                    @include('vendor-views.banner.partials._preview', ['banner' => null])
                </div>
            </div>
        </div>

        <div class="card mt-3 bnr">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'title'    => translate('Banner list'),
                        'subtitle' => translate('messages.Banners promoting your store inside the customer app.'),
                        'count'    => $banners->total(),
                        'count_id' => 'itemCount',
                    ])
                    <form id="search-form" class="search-form">
                        <div class="input-group input--group">
                            <input id="datatableSearch" type="search" name="search" class="form-control" placeholder="{{translate('messages.Search by title')}}" aria-label="{{translate('Search')}}" value="{{ request()->search }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="table-responsive datatable-custom">
                <table id="columnSearchDatatable"
                       class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                       data-hs-datatables-options='{
                        "order": [],
                        "orderCellsTop": true,
                        "search": "#datatableSearch",
                        "entries": "#datatableEntries",
                        "isResponsive": false,
                        "isShowPaging": false,
                        "paging": false
                       }'
                       >
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{translate('messages.Title')}}</th>
                            <th class="border-0">{{translate('Redirection link')}}</th>
                            <th class="border-0">{{translate('messages.Added')}}</th>
                            <th class="border-0 text-center">{{translate('messages.Status')}}</th>
                            <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                        </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($banners as $banner)
                        <tr>
                            <td>
                                <span class="media align-items-center">
                                    <img class="img--ratio-3 w-auto h--50px rounded mr-2 onerror-image" src="{{ $banner['image_full_url']}}"
                                         data-onerror-image="{{asset('/public/assets/admin/img/900x400/img1.jpg')}}"
                                          alt="{{$banner['title']}}">
                                    <span class="media-body cell--truncate">
                                        <span class="d-block text-title font-semibold" title="{{ $banner['title'] }}">{{Str::limit($banner['title'], 25, '...')}}</span>
                                        <span class="d-block fs-12 text-muted">ID:{{$banner['id']}}</span>
                                    </span>
                                </span>
                            </td>
                            <td>
                                @if($banner->default_link)
                                    <a class="d-block bnr-link" href="{{ $banner->default_link }}" title="{{ $banner->default_link }}">{{Str::limit($banner['default_link'], 45, '...')}}</a>
                                @else
                                    <span class="text-muted font-size-sm">{{translate('messages.N/A')}}</span>
                                @endif
                            </td>
                            <td data-order="{{ $banner->created_at }}">
                                <span class="table-when">
                                    <span class="table-when__day">{{\App\CentralLogics\Helpers::date_format($banner->created_at)}}</span>
                                    <span class="table-when__ago" title="{{\App\CentralLogics\Helpers::time_date_format($banner->created_at)}}">
                                        {{ $banner->created_at?->diffForHumans() }}
                                    </span>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="status-toggle" data-status="{{$banner->status?1:0}}">
                                    <label class="toggle-switch toggle-switch-sm" for="statusCheckbox{{$banner->id}}">
                                    <input type="checkbox"
                                           data-url="{{route('vendor.banner.status_update',[$banner['id'],$banner->status?0:1])}}"
                                           class="toggle-switch-input redirect-url" id="statusCheckbox{{$banner->id}}" {{$banner->status?'checked':''}}>
                                    <span class="toggle-switch-label">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                                    <span class="status-toggle__text" aria-live="polite">
                                        {{$banner->status ? translate('messages.Active') : translate('messages.Inactive')}}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn action-btn--edit" href="{{route('vendor.banner.edit',[$banner['id']])}}" title="{{translate('Edit banner')}}"><i class="tio-edit"></i>
                                    </a>
                                    <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                       data-id="banner-{{$banner['id']}}"
                                       data-message="{{ translate('Want to delete this banner?') }}"
                                        title="{{translate('messages.Delete banner')}}"><i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{route('vendor.banner.delete',[$banner['id']])}}"
                                                method="post" id="banner-{{$banner['id']}}">
                                            @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                @if(count($banners) !== 0)
                <hr>
                @endif
                @if(count($banners) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
            </div>
            <div class="page-area">
                {!! $banners->links() !!}
            </div>
        </div>
    </div>

    <div class="modal fade tps" id="banner-how-it-works" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Banner setup') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                        aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>{{ translate('A banner is artwork customers see at the top of your store page in the website and app.') }}</li>
                        <li>{{ translate('Give it a title you will recognise in the list, then upload the artwork.') }}</li>
                        <li>{{ translate('Add a redirection link to send customers to an item or an offer when they tap it.') }}</li>
                        <li>{{ translate('Switch a banner off in the list to take it down without deleting it.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--info">
                        <i class="tio-info-outined"></i>
                        <div>{{ translate('messages.Customers will see their banners on your store details page in the website and user apps.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";

        window.vendorBannerFormConfig = {
            isEdit: false,
            notSet: @json(translate('messages.Not set'))
        };
    </script>
    <script src="{{asset('public/assets/admin')}}/js/view-pages/vendor-banner-form.js"></script>
@endpush
