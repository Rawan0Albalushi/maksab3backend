@extends('layouts.admin.app')

@section('title',translate('Banner'))

@php($isServiceModule = \Illuminate\Support\Facades\Config::get('module.current_module_type') == 'service' && service_addon_active())
@php($banner_type_labels = [
    'store_wise' => $isServiceModule ? translate('Provider wise') : translate('messages.store_wise'),
    'item_wise' => $isServiceModule ? translate('Service wise') : translate('messages.item_wise'),
    'default' => translate('Default'),
])

@push('css_or_js')
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/third-party-setup.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/view-pages/banner-form.css')}}">
@endpush

@section('content')
    <div class="content container-fluid tps bnr">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{asset('public/assets/admin/img/banner.png')}}" class="w--26" alt="">
                    </span>
                    <span>
                        {{translate('Add new banner')}}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('Artwork shown at the top of the customer app, linking to a store, an item or a campaign.') }}</p>
            </div>
            <button type="button" class="tps-help" data-toggle="modal" data-target="#banner-how-it-works">
                <i class="tio-help-outlined"></i>
                <span>{{ translate('How it works') }}</span>
            </button>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-xl-8">
                <form id="banner_form">
                    @include('admin-views.banner.partials._form', [
                        'banner' => null,
                        'isServiceModule' => $isServiceModule,
                        'submitLabel' => translate('messages.Submit'),
                        'submitIcon' => 'tio-checkmark-circle-outlined',
                    ])
                </form>
            </div>

            <div class="col-xl-4">
                @include('admin-views.banner.partials._preview', ['banner' => null])
            </div>
        </div>

        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper">
                            @include('partials._table-head', [
                                'title'    => translate('Banner list'),
                                'subtitle' => translate('messages.Promotional banners shown across the customer app and website.'),
                                'count'    => $banners->total(),
                                'count_id' => 'itemCount',
                            ])
                            <form  class="search-form">
                                <div class="input-group input--group">
                                    <input id="datatableSearch" type="search" value="{{ request()->input('search')?? '' }}" name="search" class="form-control" placeholder="{{translate('messages.Search by title')}}" aria-label="{{translate('Search')}}">
                                    <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                                </div>
                            </form>
                            @if(request()->input('search'))
                            <button type="reset" class="btn btn--primary ml-2 location-reload-to-base" data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                            @endif

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
                                    <th class="border-0">{{translate('Type')}}</th>
                                    <th class="border-0">{{translate('messages.Zone')}}</th>
                                    <th class="border-0">{{translate('messages.Added')}}</th>
                                    <th class="border-0 text-center">{{translate('messages.featured')}} <span class="input-label-secondary"
                                        data-toggle="tooltip" data-placement="right" data-original-title="{{translate('Controls whether the banner shows on the module homepage in the website and app.')}}"><img src="{{asset('public/assets/admin/img/info-circle.svg')}}"
                                            alt="public/img"></span></th>
                                    <th class="border-0 text-center">{{translate('messages.Status')}}</th>
                                    <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                                </tr>
                            </thead>

                            <tbody id="set-rows">
                            @foreach($banners as $banner)
                                <tr>
                                    <td>
                                        <span class="media align-items-center">
                                            <img class="img--ratio-3 w-auto h--50px rounded mr-2 onerror-image" src="{{ $banner['image_full_url'] }}"
                                                data-onerror-image="{{asset('/public/assets/admin/img/900x400/img1.jpg')}}" alt="{{$banner->title}}">
                                            <div class="media-body max-w-200px">
                                                <h5 title="{{ $banner['title'] }}" class="text-hover-primary mb-0">{{Str::limit($banner['title'], 25, '...')}}</h5>
                                                <span class="d-block fs-12 text-muted">ID:{{$banner->id}}</span>
                                            </div>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="d-block text-title">{{ $banner_type_labels[$banner['type']] ?? $banner['type'] }}</span>
                                        @if($banner['type'] === 'store_wise' && $banner->store)
                                            <span class="d-block fs-12 text-muted" title="{{ $banner->store->name }}">{{ Str::limit($banner->store->name, 22, '...') }}</span>
                                        @elseif($banner['type'] === 'default' && $banner->default_link)
                                            <span class="d-block fs-12 text-muted" title="{{ $banner->default_link }}">{{ Str::limit($banner->default_link, 28, '...') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $banner->zone ? $banner->zone->name : translate('messages.Zone deleted') }}
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
                                        <div class="status-toggle">
                                            <label class="toggle-switch toggle-switch-sm" for="featuredCheckbox{{$banner->id}}">
                                            <input type="checkbox"
                                            data-id="featuredCheckbox{{$banner->id}}"
                                            data-type="status"
                                            data-image-on="{{ asset('/public/assets/admin/img/modal/basic_campaign_on.png') }}"
                                            data-image-off="{{ asset('/public/assets/admin/img/modal/basic_campaign_off.png') }}"
                                            data-title-on="{{ translate('By turning ON as featured!') }}"
                                            data-title-off="{{ translate('By turning OFF as featured!') }}"
                                            data-text-on="<p>{{ translate('When on, the banner shows on the module homepage in the website and app.') }}</p>"
                                            data-text-off="<p>{{ translate('If turned OFF, the banner is hidden on the module homepage in the website and user app.') }}</p>"
                                            class="toggle-switch-input  dynamic-checkbox" id="featuredCheckbox{{$banner->id}}" {{$banner->featured?'checked':''}}>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        </div>
                                        <form action="{{route('admin.banner.featured',[$banner['id'],$banner->featured?0:1])}}"
                                            method="get" id="featuredCheckbox{{$banner->id}}_form">
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <div class="status-toggle" data-status="{{$banner->status?1:0}}">
                                            <label class="toggle-switch toggle-switch-sm" for="statusCheckbox{{$banner->id}}">
                                            <input type="checkbox"
                                            data-id="statusCheckbox{{$banner->id}}"
                                            data-type="status"
                                            data-image-on="{{ asset('/public/assets/admin/img/modal/basic_campaign_on.png') }}"
                                            data-image-off="{{ asset('/public/assets/admin/img/modal/basic_campaign_off.png') }}"
                                            data-title-on="{{ translate('By turning ON banner!') }}"
                                            data-title-off="{{ translate('By turning OFF banner!') }}"
                                            data-text-on="<p>{{ translate('If you turn on this status, it will show on user website and app.') }}</p>"
                                            data-text-off="<p>{{ translate('If you turn off this status, it won\'t show on user website and app') }}</p>"
                                            class="toggle-switch-input  dynamic-checkbox" id="statusCheckbox{{$banner->id}}" {{$banner->status?'checked':''}}>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                            <span class="status-toggle__text" aria-live="polite">
                                                {{$banner->status ? translate('messages.Active') : translate('messages.Inactive')}}
                                            </span>
                                        </div>
                                        <form action="{{route('admin.banner.status',[$banner['id'],$banner->status?0:1])}}"
                                            method="get" id="statusCheckbox{{$banner->id}}_form">
                                        </form>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--edit" href="{{route('admin.banner.edit',[$banner['id']])}}" title="{{translate('Edit banner')}}"><i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="banner-{{$banner['id']}}" data-message="{{ translate('Want to delete this banner?') }}" title="{{translate('Delete banner')}}"><i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{route('admin.banner.delete',[$banner['id']])}}"
                                                        method="post" id="banner-{{$banner['id']}}">
                                                    @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                    </div>
                    @if(count($banners) !== 0)
                    <hr>
                    @endif
                    <div class="page-area">
                        {!! $banners->links() !!}
                    </div>
                    @if(count($banners) === 0)
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

    <div class="modal fade" id="banner-how-it-works" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Add new banner') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                        aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>{{ translate('A banner is artwork customers see near the top of the module home screen.') }}</li>
                        <li>{{ translate('The zone decides who sees it, and it is what the store and item pickers are filtered by.') }}</li>
                        <li>{{ translate('Store and item banners open that page when tapped. A default banner opens the link you paste, or nothing at all.') }}</li>
                        <li>{{ translate('Switch a banner off in the list to take it down without deleting it.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--info">
                        <i class="tio-info-outined"></i>
                        <div>{{ translate('Only featured banners run on the module home screen. The rest show where the app asks for that zone\'s banners.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    @php($bannerFormConfig = [
        'moduleId' => (int) Config::get('module.current_module_id'),
        'isEdit' => false,
        'itemSourceUrl' => $isServiceModule ? route('admin.service.get-services') : url('/') . '/admin/item/get-items',
        'storeSourceUrl' => route('admin.store.get-stores'),
        'submitUrl' => route('admin.banner.store'),
        'redirectUrl' => route('admin.banner.add-new'),
        'successMessage' => translate('Added successfully'),
        'ownerPlaceholder' => $isServiceModule ? translate('Select provider') : translate('Select store'),
        'lang' => [
            'notSet' => translate('messages.Not set'),
            'selectStore' => $isServiceModule ? translate('Please select a provider') : translate('Please select a store'),
            'selectItem' => $isServiceModule ? translate('Please select a service') : translate('Please select an item'),
        ],
    ])
    <script>
        "use strict";

        window.bannerFormConfig = @json($bannerFormConfig);
    </script>
    <script src="{{asset('public/assets/admin')}}/js/view-pages/banner-form.js"></script>
@endpush
