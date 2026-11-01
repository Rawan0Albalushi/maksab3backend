@extends('layouts.admin.app')

@section('title',translate('Deliveryman'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/delivery-man.svg')}}" class="w--26" alt="">
                </span>
                <span>{{translate('Deliveryman')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{ $deliveryMen->total() }}</span></span>
            </h1>
            <p class="page-header-desc">{{ translate('Everyone who delivers for you, the zone each covers and whether they are on shift.') }}</p>
        </div>
        {{-- Same filters as before (Type, Job Type, Zone), restyled to the boxed panel the
             customer list uses: its own card, a labeled row g-3/col-md-4 grid, and an
             explicit Filter submit button -- replacing the inline .min--200 dropdowns that
             auto-submitted via .set-filter/data-filter/data-url. No filter fields added or
             removed, no option values changed. --}}
        <div class="card mb-3">
            <div class="card-body">
                <form>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('messages.Type') }}</label>
                            <select name="filter" class="form-control js-select2-custom">
                                <option value="all">{{ translate('All types') }}</option>
                                <option {{ request()?->input('filter') == 'active' ? 'selected' : '' }} value="active">{{ translate('messages.online') }}</option>
                                <option {{ request()?->input('filter') == 'inactive' ? 'selected' : '' }} value="inactive">{{ translate('messages.offline') }}</option>
                                <option {{ request()?->input('filter') == 'blocked' ? 'selected' : '' }} value="blocked">{{ translate('messages.suspended') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('messages.Job Type') }}</label>
                            <select name="job_type" class="form-control js-select2-custom">
                                <option value="all">{{ translate('messages.All Job Types') }}</option>
                                <option {{ request()?->input('job_type') == 'freelancer' ? 'selected' : '' }} value="freelancer">{{ translate('Freelancer') }}</option>
                                <option {{ request()?->input('job_type') == 'salary_base' ? 'selected' : '' }} value="salary_base">{{ translate('messages.Salary Base') }}</option>
                            </select>
                        </div>
                        @if(!auth('admin')?->user()?->zone_id)
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('messages.Zone') }}</label>
                            <select name="zone_id" class="form-control js-select2-custom">
                                <option value="all">{{ translate('All zones') }}</option>
                                @foreach(\App\CentralLogics\Helpers::zones_dropdown() as $z)
                                    <option
                                        value="{{$z['id']}}" {{isset($zone) && $zone->id == $z['id']?'selected':''}}>
                                        {{$z['name']}}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="col-12">
                            <div class="btn--container justify-content-end">
                                <button type="submit" class="btn btn--primary"><i class="tio-filter-list"></i> {{ translate('messages.Filter') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper justify-content-end">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Delivery men on the platform, with their zone and current availability.'),
                    ])

                    <form class="search-form">
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" name="search" class="form-control h--45px"
                            placeholder="{{translate('Ex') . ': ' . translate('Deliveryman name, email or phone')}}" value="{{ request()->input('search') }}" aria-label="Search" required>
                            <button type="submit" class="btn btn--secondary h--45px"><i class="tio-search"></i></button>

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
                            <a id="export-excel" class="dropdown-item" href="{{route('admin.users.delivery-man.export', ['type'=>'excel',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{route('admin.users.delivery-man.export', ['type'=>'csv',request()->getQueryString()])}}">
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
                        class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                        data-hs-datatables-options='{
                            "order": [],
                            "orderCellsTop": true,
                            "paging":false,
                            "columnDefs":[{"targets":[7],"orderable":false}]
                        }'>
                    <thead class="thead-light">
                    <tr>
                        <th class="border-0 text-capitalize">{{translate('SL')}}</th>
                        <th class="border-0 text-capitalize">{{translate('Name')}}</th>
                        <th class="border-0 text-capitalize">{{translate('Contact information')}}</th>
                        <th class="border-0 text-capitalize">{{translate('messages.Zone')}}</th>
                        <th class="border-0 text-capitalize">{{translate('messages.Total Completed Orders')}}</th>
                        <th class="border-0 text-capitalize">{{translate('messages.Availability status')}}</th>
                        <th class="border-0 text-capitalize">{{translate('messages.Status')}}</th>
                        <th class="border-0 text-center text-capitalize">{{translate('messages.Action')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($deliveryMen as $key=>$dm)
                        <tr>
                            <td>{{$key+$deliveryMen->firstItem()}}</td>
                            <td>
                                <a class="table-rest-info max-w-400px min-w-220" href="{{route('admin.users.delivery-man.preview',[$dm['id']])}}">
                                    <img class="onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
                                    src="{{$dm['image_full_url'] }}"
                                    alt="{{$dm['f_name']}} {{$dm['l_name']}}">
                                    <div class="info">
                                        <h5 class="text-hover-primary line--limit-2 text-wrap mb-0">{{$dm['f_name'].' '.$dm['l_name']}}</h5>
                                        <span class="d-block text-body">
                                            <span class="rating">
                                            <i class="tio-star"></i> {{count($dm->rating)>0?number_format($dm->rating[0]->average, 1, '.', ' '):0}}
                                            </span>
                                        </span>
                                    </div>
                                </a>
                            </td>
                            <td>
                                <a class="deco-none" href="tel:{{$dm['phone']}}">{{$dm['phone']}}</a>
                            </td>
                            <td>
                                @if($dm->zone)
                                <label class="text--title font-medium mb-0">{{$dm->zone->name}}</label>
                                @else
                                <label class="text--title font-medium mb-0">{{translate('messages.Zone deleted')}}</label>
                                @endif
                            </td>
                            <td>
                                <a class="deco-none" href="{{route('admin.users.delivery-man.preview',['id'=> $dm['id'],'tab' => 'transaction' ])}}">{{count($dm['order_transaction'])}}</a>
                            </td>
                            <td>
                                <div>
                                    {{translate('Currently assigned orders')}} : {{$dm->current_orders}}
                                </div>
                                <div>
                                    {{translate('Active status')}} :
                                    @if($dm->application_status == 'approved')
                                        @if($dm->active)
                                        <strong class="text-capitalize text-primary">{{translate('messages.online')}}</strong>
                                        @else
                                        <strong class="text-capitalize text-secondary">{{translate('messages.offline')}}</strong>
                                        @endif
                                    @elseif ($dm->application_status == 'denied')
                                        <strong class="text-capitalize text-danger">{{translate('Denied')}}</strong>
                                    @else
                                        <strong class="text-capitalize text-info">{{translate('Pending')}}</strong>
                                    @endif
                                </div>
                            </td>

                            <td>
                                @if ($dm->status == 1)
                                <strong class="text-capitalize text-primary">{{translate('messages.Active')}}</strong>
                                @else
                                <strong class="text-capitalize text-danger">{{translate('messages.suspended')}}</strong>

                                @endif

                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn action-btn--view"
                                            href="{{route('admin.users.delivery-man.preview',[$dm['id']])}}"
                                            title="{{ translate('messages.View') }}"><i
                                                class="tio-visible-outlined"></i>
                                        </a>
                                    <a class="btn action-btn action-btn--edit" href="{{route('admin.users.delivery-man.edit',[$dm['id']])}}" title="{{translate('Edit')}}"><i class="tio-edit"></i>
                                        </a>
                                        <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="delivery-man-{{$dm['id']}}" data-message="{{ translate('Want to remove this deliveryman?') }}" title="{{translate('messages.Delete')}}"><i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{route('admin.users.delivery-man.delete',[$dm['id']])}}" method="post" id="delivery-man-{{$dm['id']}}">
                                        @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
                @if(count($deliveryMen) !== 0)
                <hr>
                @endif
                <div class="page-area">
                    {!! $deliveryMen->links() !!}
                </div>
                @if(count($deliveryMen) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";
        $(document).on('ready', function () {
            // INITIALIZATION OF DATATABLES
            // =======================================================
            let datatable = $.HSCore.components.HSDatatables.init($('#columnSearchDatatable'));

            $('#column1_search').on('keyup', function () {
                datatable
                    .columns(1)
                    .search(this.value)
                    .draw();
            });

            $('#column2_search').on('keyup', function () {
                datatable
                    .columns(2)
                    .search(this.value)
                    .draw();
            });

            $('#column3_search').on('keyup', function () {
                datatable
                    .columns(3)
                    .search(this.value)
                    .draw();
            });

            $('#column4_search').on('keyup', function () {
                datatable
                    .columns(4)
                    .search(this.value)
                    .draw();
            });


            // INITIALIZATION OF SELECT2
            // =======================================================
            $('.js-select2-custom').each(function () {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });
        });

        $('#search-form').on('submit', function (e) {
            e.preventDefault();
            let formData = new FormData(this);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{route('admin.users.delivery-man.search')}}',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $('#set-rows').html(data.view);
                    $('#itemCount').html(data.count);
                    $('.page-area').hide();
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        });
    </script>
@endpush
