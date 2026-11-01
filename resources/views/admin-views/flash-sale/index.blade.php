@extends('layouts.admin.app')

@section('title',translate('Flash sales'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/condition.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('messages.Flash sale setup')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('A short, sharp discount window that customers see counting down in the app.') }}</p>
        </div>
        @php($language=\App\CentralLogics\Helpers::get_business_settings('language') ?? [])

        <div class="row g-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form action="{{route('admin.flash-sale.store')}}" method="post">
                            @csrf
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
                                    <div class="row">
                                        <div class="col-12">

                                            <div class="lang_form" id="default-form">
                                                <div class="form-group">
                                                    <label class="input-label"
                                                        for="default_title">{{ translate('messages.Title') }}
                                                        ({{translate('Default')}}) <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" name="title[]" id="default_title"
                                                        class="form-control" maxlength="100" placeholder="{{ translate('messages.Ex') . ' : ' . translate('messages.new flash sale') }}"
                                                        required>
                                                </div>
                                                <input type="hidden" name="lang[]" value="default">
                                            </div>
                                        @foreach ($language as $lang)
                                            <div class="d-none lang_form"
                                                id="{{ $lang }}-form">
                                                <div class="form-group">
                                                    <label class="input-label"
                                                        for="{{ $lang }}_title">{{ translate('messages.Title') }}
                                                        ({{ strtoupper($lang) }})
                                                    </label>
                                                    <input type="text" maxlength="100" name="title[]" id="{{ $lang }}_title"
                                                        class="form-control" placeholder="{{ translate('messages.Ex') . ' : ' . translate('messages.new flash sale') }}">
                                                </div>
                                                <input type="hidden" name="lang[]" value="{{ $lang }}">
                                            </div>
                                        @endforeach
                                        </div>
                                        <div class="col-xl-6">
                                            <div class="form-group">
                                                <label class="input-label"
                                                    for="default_title">{{ translate('messages.discount Bearer') }}
                                                    <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.Define the cost amount you want to bear for this Flash Sale.') }} {{ translate('Total bear amount') }}: 100%">
                                                        <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="row g-3 __bg-F8F9FC-card">
                                                <div class="col-lg-6">
                                                    <label class="form-label">{{ translate('admin') }}(%) <span class="text-danger">*</span></label>
                                                <input type="number"  min="{{\App\CentralLogics\Helpers::getDecimalPlaces() }}" step="{{\App\CentralLogics\Helpers::getDecimalPlaces() }}" max="100" name="admin_discount_percentage"
                                                        value=""
                                                        class="form-control" id="adminDiscount"
                                                        placeholder="{{ translate('Ex') . ' : 50' }}" required>
                                                </div>
                                                <div class="col-lg-6">
                                                    <label class="form-label">{{ translate('Store owner') }}(%) <span class="text-danger">*</span></label>
                                                <input type="number"  min="{{ \App\CentralLogics\Helpers::getDecimalPlaces() }}" step="{{ \App\CentralLogics\Helpers::getDecimalPlaces() }}" max="100" name="vendor_discount_percentage"
                                                        value=""
                                                        class="form-control" id="storeDiscount"
                                                        placeholder="{{ translate('Ex') . ' : 50' }}" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-6">
                                            <div class="form-group">
                                                <label class="input-label"
                                                    for="default_title">{{ translate('messages.Validity') }}
                                                </label>
                                            </div>
                                            <div class="row g-3 __bg-F8F9FC-card">
                                                <div class="col-lg-6">
                                                    <div>
                                                        <label class="input-label" for="title">{{translate('Start date')}} <span class="text-danger">*</span></label>
                                                        <input type="datetime-local" id="from" class="form-control" required="" name="start_date">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div>
                                                        <label class="input-label" for="title">{{translate('End date')}} <span class="text-danger">*</span></label>
                                                        <input type="datetime-local" id="to" class="form-control" required="" name="end_date">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            @endif
                            <div class="btn--container justify-content-end mt-5">
                                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                                <button type="submit" class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{translate('messages.Submit')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper">
                            @include('partials._table-head', [
                                'title'    => translate('messages.Flash sale list'),
                                'subtitle' => translate('messages.Time-limited sales that highlight discounted items in the apps.'),
                                'count'    => $flash_sales->total(),
                                'count_id' => 'itemCount',
                            ])
                            <form  class="search-form">

                                <div class="input-group input--group">
                                    <input id="datatableSearch_" value="{{ request()?->search ?? null }}" type="search" name="search" class="form-control"
                                            placeholder="{{translate('Ex') . ' : ' . translate('flash sale title')}}" aria-label="Search" >
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
                               class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                               data-hs-datatables-options='{
                                 "order": [],
                                 "orderCellsTop": true,
                                 "paging":false
                               }'>
                            <thead class="thead-light">
                            <tr class="text-center">
                                <th class="border-0">{{translate('SL')}}</th>
                                <th class="border-0">{{translate('messages.Title')}}</th>
                                <th class="border-0">{{translate('messages.Duration')}}</th>
                                <th class="border-0">{{translate('messages.Active products')}}</th>
                                <th class="border-0">{{translate('messages.publish')}}</th>
                                <th class="border-0">{{translate('messages.Action')}}</th>
                            </tr>

                            </thead>

                            <tbody id="set-rows">
                            @foreach($flash_sales as $key=>$flash_sale)
                                <tr>
                                    <td class="text-center">
                                        <span  class="mr-3">
                                            {{$key+$flash_sales->firstItem()}}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span title="{{$flash_sale['title']}}" class="font-size-sm text-body mr-3">
                                            {{Str::limit($flash_sale['title'],20,'...')}}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="bg-gradient-light text-dark">{{$flash_sale->start_date?$flash_sale->start_date->format('d/M/Y'). ' - ' .$flash_sale->end_date->format('d/M/Y'): 'N/A'}}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="font-size-sm text-body mr-3">
                                            {{ $flash_sale->active_products_count }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <label class="toggle-switch toggle-switch-sm" for="is_publish-{{$flash_sale['id']}}">
                                            <input type="checkbox" class="toggle-switch-input dynamic-checkbox" {{$flash_sale->is_publish?'checked':''}}
                                                    data-id="is_publish-{{$flash_sale['id']}}"
                                                   data-type="status"
                                                   data-image-on='{{asset('/public/assets/admin/img/modal')}}/zone-status-on.png'
                                                   data-image-off="{{asset('/public/assets/admin/img/modal')}}/zone-status-off.png"
                                                   data-title-on="{{translate('Want to publish this flash sale?')}}"
                                                   data-title-off="{{translate('Want to hide this flash sale?')}}"
                                                   data-text-on="<p>{{translate('Publishing shows every store and product in this flash sale to customers.')}}</p>"
                                                   data-text-off="<p>{{translate('Hiding removes every store and product in this flash sale from customer view.')}}</p>"
                                                   id="is_publish-{{$flash_sale['id']}}">
                                            <span class="toggle-switch-label mx-auto">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <form action="{{route('admin.flash-sale.publish',[$flash_sale['id'],$flash_sale->is_publish?0:1])}}" method="get" id="is_publish-{{$flash_sale['id']}}_form">
                                        </form>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn p-2 btn--primary btn-outline-primary" href="{{route('admin.flash-sale.add-product',[$flash_sale['id']])}}" title="{{translate('messages.add-product')}}"><i class="tio-add"></i>{{ translate('messages.Add New Product') }}
                                            </a>
                                            <a class="btn action-btn action-btn--edit" href="{{route('admin.flash-sale.edit',[$flash_sale['id']])}}" title="{{translate('Edit')}}"><i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="flash_sale-{{$flash_sale['id']}}" data-message="{{ translate('Want to delete this flash sale?') }}" title="{{translate('messages.Delete')}}"><i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{route('admin.flash-sale.delete',[$flash_sale['id']])}}"
                                                    method="post" id="flash_sale-{{$flash_sale['id']}}">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(count($flash_sales) !== 0)
                    <hr>
                    @endif
                    <div class="page-area">
                        {!! $flash_sales->links() !!}
                    </div>
                    @if(count($flash_sales) === 0)
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
    <script src="{{asset('public/assets/admin')}}/js/view-pages/flash-sale-index.js"></script>
@endpush
