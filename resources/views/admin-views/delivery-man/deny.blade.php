@extends('layouts.admin.app')

@section('title',translate('messages.Denied Delivery Man'))

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
                <span>
                    {{translate('messages.Denied Delivery Man')}}
                    <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $deliveryMen->total() }}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Applicants you turned down, kept so you can look back at why.') }}</p>

            <div class="row">
                <div class="col-md-12">
                    <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
                        <ul class="nav nav-tabs mb-3 border-0 nav--tabs nav--pills">
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.users.delivery-man.new') }}">{{translate('messages.Pending delivery man')}}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" href="{{ route('admin.users.delivery-man.deny') }}">{{translate('messages.Denied Delivery Man')}}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper justify-content-end">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Delivery man applications you have denied.'),
                    ])

                    @if(!auth('admin')?->user()?->zone_id)
                        <div class="min--200">
                            <select name="zone_id" class="form-control js-select2-custom set-filter" data-filter="zone_id"
                                    data-url="{{ url()->full() }}">
                                <option value="all">{{ translate('All zones') }}</option>
                                @foreach(\App\CentralLogics\Helpers::zones_dropdown() as $z)
                                    <option value="{{$z['id']}}" {{isset($zone) && $zone->id == $z['id']?'selected':''}}>
                                        {{$z['name']}}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <form class="search-form min--260">
                        @if(request('zone_id'))
                            <input type="hidden" name="zone_id" value="{{ request('zone_id') }}">
                        @endif
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" name="search_by" class="form-control h--45px"
                                   placeholder="{{translate('Ex') . ': ' . translate('Deliveryman name, email or phone')}}"
                                   value="{{ request('search_by') }}" aria-label="{{translate('messages.Search')}}">
                            <button type="submit" class="btn btn--secondary h--45px"><i class="tio-search"></i></button>
                        </div>
                    </form>

                    @if(request('search_by'))
                        <a class="btn btn--primary ml-2"
                           href="{{ route('admin.users.delivery-man.deny', array_filter(['zone_id' => request('zone_id')])) }}">
                            <i class="tio-refresh"></i> {{translate('messages.Reset')}}
                        </a>
                    @endif
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table id="columnSearchDatatable"
                        class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                        data-hs-datatables-options='{
                            "order": [],
                            "orderCellsTop": true,
                            "paging":false,
                            "columnDefs":[{"targets":[5],"orderable":false}]
                        }'>
                    <thead class="thead-light">
                    <tr>
                        <th class="border-0 text-capitalize">{{translate('SL')}}</th>
                        <th class="border-0 text-capitalize">{{translate('messages.Applicant')}}</th>
                        <th class="border-0 text-capitalize">{{translate('Contact information')}}</th>
                        <th class="border-0 text-capitalize">{{translate('messages.Job Type')}}</th>
                        <th class="border-0 text-capitalize">{{translate('messages.Applied')}}</th>
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
                                    src="{{ $dm['image_full_url'] }}"
                                    alt="{{$dm['f_name']}} {{$dm['l_name']}}">
                                    <div class="info">
                                        <h5 class="text-hover-primary line--limit-2 text-wrap mb-0">{{$dm['f_name'].' '.$dm['l_name']}}</h5>
                                        <small class="d-block text-muted">{{$dm->zone ? $dm->zone->name : translate('messages.Zone deleted')}}</small>
                                    </div>
                                </a>
                            </td>
                            <td>
                                @if($dm['email'])
                                    <a class="deco-none d-block" href="mailto:{{$dm['email']}}">{{$dm['email']}}</a>
                                @endif
                                <a class="deco-none d-block" href="tel:{{$dm['phone']}}">{{$dm['phone']}}</a>
                            </td>
                            <td>
                                <span class="badge {{ $dm->earning == 1 ? 'badge-soft-info' : 'badge-soft-secondary' }} text-capitalize">
                                    {{ $dm->earning == 1 ? translate('Freelancer') : translate('Salary based')}}
                                </span>
                            </td>
                            <td data-order="{{ $dm->created_at }}">
                                <span class="table-when">
                                    <span class="table-when__day">{{\App\CentralLogics\Helpers::date_format($dm->created_at)}}</span>
                                    <span class="table-when__ago" title="{{\App\CentralLogics\Helpers::time_date_format($dm->created_at)}}">
                                        {{ $dm->created_at?->diffForHumans() }}
                                    </span>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions justify-content-center">
                                    <a class="btn action-pill action-pill--approve request-alert" href="javascript:"
                                       data-url="{{route('admin.users.delivery-man.application',[$dm['id'],'approved'])}}"
                                       data-message="{{translate('messages.You want to approve this application')}}">
                                        <i class="tio-checkmark-circle-outlined"></i>
                                        <span>{{ translate('Approve') }}</span>
                                    </a>
                                    <span class="action-pill action-pill--done"
                                          title="{{ translate('messages.Already denied — approve to let them join anyway') }}">
                                        <i class="tio-clear-circle-outlined"></i>
                                        <span>{{ translate('Denied') }}</span>
                                    </span>

                                    <span class="table-actions__sep" aria-hidden="true"></span>

                                    <a class="btn action-btn action-btn--edit" href="{{route('admin.users.delivery-man.edit',[$dm['id']])}}"
                                       title="{{translate('Edit')}}" aria-label="{{translate('Edit')}}">
                                        <i class="tio-edit"></i>
                                    </a>
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
                    {!! $deliveryMen->withQueryString()->links() !!}
                </div>
                @if(count($deliveryMen) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="">
                    @if(request('search_by') || request('zone_id', 'all') !== 'all')
                        <h5>{{translate('messages.No denied application matches these filters.')}}</h5>
                        <p class="text-muted font-size-sm">{{translate('messages.Clear the search or pick another zone to see more applications.')}}</p>
                    @else
                        <h5>{{translate('messages.Nothing has been denied yet.')}}</h5>
                        <p class="text-muted font-size-sm">{{translate('messages.Applications you turn down are kept here.')}}</p>
                    @endif
                </div>
                @endif
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/deliveryman-new-denied-list.js"></script>
    <script>
        "use strict";
        function request_alert(url, message) {
            Swal.fire({
                title: '{{translate('messages.Are you sure?')}}',
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{translate('messages.No')}}',
                confirmButtonText: '{{translate('messages.Yes')}}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    location.href = url;
                }
            })
        }
    </script>
@endpush
